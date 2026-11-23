<?php
/**
 * HealthWing / VetSystem — Automated Scheduling & Emergency Priority Triage Engine
 *
 * Implements two core algorithmic subsystems:
 * 1. Automated Appointment Scheduling Algorithm (`autoScheduleAppointment`):
 *    - Maps clinical services to required room types, veterinarian specialties, and standard slot durations.
 *    - Evaluates clinic operating hours (Mon-Fri 08:00-18:00, Sat 09:00-15:00).
 *    - Scores and assigns the optimal available (Veterinarian, Room) pair using specialty matching and daily workload balancing.
 *    - Automatically resolves time conflicts by stepping forward in 30-minute intervals to find the nearest conflict-free slot.
 *
 * 2. Multi-Pet Emergency Priority & Conflict Preemption Algorithm (`recalculateEmergencyTriage`):
 *    - Computes a deterministic Composite Priority Score P in [0, 100] for every active emergency:
 *      P = Clinical Acuity (12..50) + Pet Vulnerability (0..15) + Waiting Aging Factor (0..20) + Continuity Bonus (0..15)
 *    - Ranks concurrent emergencies (tie-breaking by Acuity Rank DESC, Arrival Timestamp ASC, Booking_ID ASC).
 *    - Allocates idle rooms and vets to the highest-priority emergency first.
 *    - Preempts conflicting regular scheduled appointments if all compatible rooms or vets are occupied by routine bookings,
 *      automatically rescheduling the displaced regular appointment to the next available conflict-free slot and notifying the owner.
 */

require_once __DIR__ . '/db.php';

class VetSchedulingEngine
{
    private mysqli $conn;

    /** Service profile definitions: duration (mins), preferred room types, preferred vet specialties, priority weight */
    public const SERVICE_PROFILES = [
        'Surgery'=>[
            'duration'=>90,
            'rooms'=>['Surgery', 'Emergency', 'General'],
            'specialties'=>['Surgery', 'General'],
            'preempt_rank'=>4, // hardest regular service to preempt
        ],
        'Dental'=>[
            'duration'=>60,
            'rooms'=>['Dental', 'General'],
            'specialties'=>['Dental', 'General'],
            'preempt_rank'=>3,
        ],
        'Vaccination'=>[
            'duration'=>30,
            'rooms'=>['General'],
            'specialties'=>['Vaccination', 'Wellness', 'General'],
            'preempt_rank'=>2,
        ],
        'Parasite Control'=>[
            'duration'=>30,
            'rooms'=>['General'],
            'specialties'=>['Parasite', 'Wellness', 'General'],
            'preempt_rank'=>2,
        ],
        'Wellness Care'=>[
            'duration'=>30,
            'rooms'=>['General'],
            'specialties'=>['Wellness', 'General'],
            'preempt_rank'=>1,
        ],
        'Grooming'=>[
            'duration'=>60,
            'rooms'=>['Grooming', 'General'],
            'specialties'=>['Grooming', 'Wellness', 'General'],
            'preempt_rank'=>1, // easiest regular service to reschedule during an emergency
        ],
    ];

    /** Clinical triage acuity tiers & NLP keyword weights */
    public const TRIAGE_TIERS = [
        'Critical'=>[
            'base_score'=>50.0,
            'rank'=>4,
            'keywords'=>[
                'unconscious', 'not breathing', 'choking', 'apnea', 'cardiac', 'arrest',
                'hit by car', 'car accident', 'accident', 'seizure', 'convulsion', 'poison',
                'toxin', 'chocolate', 'rat bait', 'bloat', 'gdv', 'collapse', 'collapsed',
                'hemorrhage', 'arterial', 'blue gums', 'cyanosis', 'drowning', 'electrocution',
                'dystocia', 'stuck puppy', 'stuck kitten', 'paralyzed', 'severe trauma'
            ],
        ],
        'Emergent'=>[
            'base_score'=>38.0,
            'rank'=>3,
            'keywords'=>[
                'bleeding', 'blood', 'fracture', 'broken bone', 'broken leg', 'snake',
                'venom', 'heatstroke', 'burn', 'eye injury', 'proptosis', 'unable to stand',
                'deep wound', 'laceration', 'swollen abdomen', 'straining to urinate',
                'blocked cat', 'uret', 'difficulty breathing', 'wheezing', 'gasping', 'foreign body'
            ],
        ],
        'Urgent'=>[
            'base_score'=>25.0,
            'rank'=>2,
            'keywords'=>[
                'vomiting', 'diarrhea', 'fever', 'lethargic', 'lethargy', 'pain', 'crying',
                'wound', 'bite', 'abscess', 'allergic', 'hives', 'swelling', 'not eating',
                'anorexia', 'trembling', 'shaking', 'infection', 'pus', 'ulcer'
            ],
        ],
        'Semi-Urgent'=>[
            'base_score'=>12.0,
            'rank'=>1,
            'keywords'=>[
                'limping', 'limp', 'scratch', 'ear infection', 'itching', 'cough',
                'sneezing', 'rash', 'tick', 'flea', 'nail', 'minor', 'constipation'
            ],
        ],
    ];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Returns metadata for a given service name.
     */
    public function getServiceProfile(string $service): array
    {
        foreach (self::SERVICE_PROFILES as $name =>$profile) {
            if (strcasecmp(trim($service), $name) === 0 || stripos($service, $name) !== false) {
                return $profile;
            }
        }
        return [
            'duration'=>30,
            'rooms'=>['General'],
            'specialties'=>['General', 'Wellness'],
            'preempt_rank'=>2,
        ];
    }

    /**
     * Normalizes a requested timestamp into valid clinic operating hours:
     * Mon-Fri: 08:00 - 18:00, Sat: 09:00 - 15:00, Sun: Closed (rolls to Monday 08:00).
     */
    public function normalizeToClinicHours(int $timestamp, int $durationMinutes = 30): int
    {
        // Round up to nearest 15-minute boundary if not aligned
        $rem = $timestamp % 900;
        if ($rem !== 0) {
            $timestamp += (900 - $rem);
        }

        for ($guard = 0; $guard < 14; $guard++) {
            $dow = (int)date('w', $timestamp); // 0 = Sun, 6 = Sat
            $dateStr = date('Y-m-d', $timestamp);

            if ($dow === 0) {
                // Sunday ->advance to Monday 08:00
                $timestamp = strtotime($dateStr . '+1 day 08:00:00');
                continue;
            }

            $openHour = ($dow === 6) ? '09:00:00': '08:00:00';
            $closeHour = ($dow === 6) ? '15:00:00': '18:00:00';

            $openTs = strtotime("$dateStr $openHour");
            $closeTs = strtotime("$dateStr $closeHour");

            if ($timestamp < $openTs) {
                $timestamp = $openTs;
            }

            if (($timestamp + ($durationMinutes * 60)) >$closeTs) {
                // Roll over to next day 08:00
                $timestamp = strtotime($dateStr . '+1 day 08:00:00');
                continue;
            }

            return $timestamp;
        }

        return $timestamp;
    }

    /**
     * Checks whether a specific Veterinarian is free during [startDt, endDt].
     */
    public function isVetAvailable(int $vetId, string $startDt, string $endDt, ?int $ignoreScheduleId = null): bool
    {
        // 1. Check overlapping scheduled appointments
        $sql = "SELECT COUNT(*) AS cnt FROM schedule
                WHERE Veterinarian_ID = ?
                  AND Status IN ('Pending', 'In Progress', 'Confirmed')
                  AND Appointment_Time < ?
                  AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) >?";
        if ($ignoreScheduleId) {
            $sql .= "AND Schedule_ID != ". (int)$ignoreScheduleId;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iss', $vetId, $endDt, $startDt);
        $stmt->execute();
        $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt >0) {
            return false;
        }

        // 2. If window overlaps "now"(within next 60 mins), ensure vet is not tied up in an In Progress emergency
        $now = time();
        $startTs = strtotime($startDt);
        if (abs($startTs - $now) <= 3600) {
            $emStmt = $this->conn->prepare(
                "SELECT COUNT(*) AS cnt FROM emergency_booking
                 WHERE Assigned_Vet_ID = ? AND Status = 'In Progress'"
            );
            $emStmt->bind_param('i', $vetId);
            $emStmt->execute();
            $emCnt = (int)($emStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $emStmt->close();
            if ($emCnt >0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether a specific Room is free during [startDt, endDt].
     */
    public function isRoomAvailable(int $roomId, string $startDt, string $endDt, ?int $ignoreScheduleId = null): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM schedule
                WHERE Room_ID = ?
                  AND Status IN ('Pending', 'In Progress', 'Confirmed')
                  AND Appointment_Time < ?
                  AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) >?";
        if ($ignoreScheduleId) {
            $sql .= "AND Schedule_ID != ". (int)$ignoreScheduleId;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iss', $roomId, $endDt, $startDt);
        $stmt->execute();
        $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt >0) {
            return false;
        }

        // Also check if room is currently occupied by an active emergency if booking is within 60 mins of now
        $now = time();
        $startTs = strtotime($startDt);
        if (abs($startTs - $now) <= 3600) {
            $emStmt = $this->conn->prepare(
                "SELECT COUNT(*) AS cnt FROM emergency_booking
                 WHERE Assigned_Room_ID = ? AND Status = 'In Progress'"
            );
            $emStmt->bind_param('i', $roomId);
            $emStmt->execute();
            $emCnt = (int)($emStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $emStmt->close();
            if ($emCnt >0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds the optimal available (Veterinarian, Room, StartTime, EndTime) for a service.
     * If the requested slot has a conflict, automatically scans forward in 30-minute increments
     * until an open conflict-free slot is found.
     */
    public function findOptimalSlotAndResources(
        string $service,
        string $requestedDateTime,
        ?int $preferredVetId = null,
        ?int $preferredRoomId = null,
        ?int $ignoreScheduleId = null
    ): array {
        $profile = $this->getServiceProfile($service);
        $duration = (int)$profile['duration'];
        $reqTs = strtotime($requestedDateTime) ?: (time() + 3600);
        $baseTs = $this->normalizeToClinicHours($reqTs, $duration);

        // Fetch all approved veterinarians
        $vets = [];
        $vRes = $this->conn->query(
            "SELECT Veterinarian_ID, First_Name, Last_Name, Specialties
             FROM veterinarian
             WHERE COALESCE(Status, 'Approved') = 'Approved'
             ORDER BY Veterinarian_ID ASC"
        );
        if ($vRes) {
            $vets = $vRes->fetch_all(MYSQLI_ASSOC);
        }

        // Fetch all clinic rooms
        $rooms = [];
        $rRes = $this->conn->query(
            "SELECT Room_ID, Room_Name, Room_Type, Status
             FROM room
             ORDER BY Room_ID ASC"
        );
        if ($rRes) {
            $rooms = $rRes->fetch_all(MYSQLI_ASSOC);
        }

        // Scan up to 96 thirty-minute slots (48 hours of clinic time)
        $candTs = $baseTs;
        for ($step = 0; $step < 96; $step++) {
            $candTs = $this->normalizeToClinicHours($candTs, $duration);
            $startDt = date('Y-m-d H:i:s', $candTs);
            $endDt = date('Y-m-d H:i:s', $candTs + ($duration * 60));
            $dayStr = date('Y-m-d', $candTs);

            // Score available vets for this slot
            $bestVet = null;
            $bestVetScore = -9999;

            foreach ($vets as $v) {
                $vId = (int)$v['Veterinarian_ID'];
                if (!$this->isVetAvailable($vId, $startDt, $endDt, $ignoreScheduleId)) {
                    continue;
                }

                $score = 50;
                if ($preferredVetId && $vId === $preferredVetId) {
                    $score += 100;
                }
                $spec = strtolower($v['Specialties'] ?? '');
                foreach ($profile['specialties'] as $idx =>$prefSpec) {
                    if (stripos($spec, strtolower($prefSpec)) !== false) {
                        $score += (35 - ($idx * 10));
                        break;
                    }
                }

                // Workload balancing: count appointments on the same day
                $wlStmt = $this->conn->prepare(
                    "SELECT COUNT(*) AS c FROM schedule
                     WHERE Veterinarian_ID = ? AND DATE(Appointment_Time) = ? AND Status != 'Cancelled'"
                );
                $wlStmt->bind_param('is', $vId, $dayStr);
                $wlStmt->execute();
                $dayCount = (int)($wlStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $wlStmt->close();

                $score -= ($dayCount * 6);

                if ($score >$bestVetScore) {
                    $bestVetScore = $score;
                    $bestVet = $v;
                }
            }

            // Score available rooms for this slot
            $bestRoom = null;
            $bestRoomScore = -9999;

            foreach ($rooms as $r) {
                $rId = (int)$r['Room_ID'];
                $rType = $r['Room_Type'] ?? 'General';
                // Keep Emergency Trauma Bay reserved for emergencies unless service is Surgery and no other room exists
                if ($rType === 'Emergency'&& $service !== 'Surgery') {
                    continue;
                }
                if (!$this->isRoomAvailable($rId, $startDt, $endDt, $ignoreScheduleId)) {
                    continue;
                }

                $rScore = 40;
                if ($preferredRoomId && $rId === $preferredRoomId) {
                    $rScore += 100;
                }
                foreach ($profile['rooms'] as $idx =>$prefRoomType) {
                    if (strcasecmp($rType, $prefRoomType) === 0 || stripos($r['Room_Name'] ?? '', $prefRoomType) !== false) {
                        $rScore += (40 - ($idx * 12));
                        break;
                    }
                }

                if ($rScore >$bestRoomScore) {
                    $bestRoomScore = $rScore;
                    $bestRoom = $r;
                }
            }

            // If both a vet (or no vets exist in DB yet) and a room (or no rooms exist yet) are available
            $vetOk = ($bestVet !== null) || empty($vets);
            $roomOk = ($bestRoom !== null) || empty($rooms);

            if ($vetOk && $roomOk) {
                $wasShifted = ($candTs !== $reqTs);
                return [
                    'success'=> true,
                    'appointment_time'=>$startDt,
                    'end_time'=>$endDt,
                    'duration_minutes'=>$duration,
                    'was_shifted'=>$wasShifted,
                    'requested_time'=> date('Y-m-d H:i:s', $reqTs),
                    'vet_id'=>$bestVet ? (int)$bestVet['Veterinarian_ID'] : null,
                    'vet_name'=>$bestVet ? trim($bestVet['First_Name'] . ''. $bestVet['Last_Name']) : 'On-Call Veterinarian',
                    'vet_specialty'=>$bestVet['Specialties'] ?? 'General Practice',
                    'room_id'=>$bestRoom ? (int)$bestRoom['Room_ID'] : null,
                    'room_name'=>$bestRoom ? ($bestRoom['Room_Name'] ?: 'Room #'. $bestRoom['Room_ID']) : 'Main Exam Room',
                    'room_type'=>$bestRoom['Room_Type'] ?? 'General',
                ];
            }

            // Advance 30 minutes and try again
            $candTs += 1800;
        }

        // Fallback if clinic is 100% saturated for 48 hours
        $fallbackStart = date('Y-m-d H:i:s', $baseTs);
        $fallbackEnd = date('Y-m-d H:i:s', $baseTs + ($duration * 60));
        return [
            'success'=> true,
            'appointment_time'=>$fallbackStart,
            'end_time'=>$fallbackEnd,
            'duration_minutes'=>$duration,
            'was_shifted'=> true,
            'requested_time'=> date('Y-m-d H:i:s', $reqTs),
            'vet_id'=>!empty($vets) ? (int)$vets[0]['Veterinarian_ID'] : null,
            'vet_name'=>!empty($vets) ? trim($vets[0]['First_Name'] . ''. $vets[0]['Last_Name']) : 'On-Call Veterinarian',
            'vet_specialty'=>!empty($vets) ? ($vets[0]['Specialties'] ?? 'General') : 'General',
            'room_id'=>!empty($rooms) ? (int)$rooms[0]['Room_ID'] : null,
            'room_name'=>!empty($rooms) ? ($rooms[0]['Room_Name'] ?: 'Main Room') : 'Main Room',
            'room_type'=>'General',
        ];
    }

    /**
     * Computes the clinical triage level and Composite Priority Score P in [0, 100]
     * for an emergency case based on clinical details, explicit severity, pet vulnerability, and wait time.
     */
    public function computeEmergencyPriority(array $booking): array
    {
        $details = strtolower(trim($booking['Additional_Details'] ?? ''));
        $explicitSeverity = trim($booking['Severity_Level'] ?? '');
        $createdAtTs = !empty($booking['Created_At']) ? strtotime($booking['Created_At']) : time();
        $waitMinutes = max(0.0, (time() - $createdAtTs) / 60.0);

        // 1. Determine Acuity Tier from NLP keywords + explicit severity
        $detectedTier = 'Urgent';
        $matchedKeywords = [];

        foreach (self::TRIAGE_TIERS as $tierName =>$meta) {
            foreach ($meta['keywords'] as $kw) {
                if ($details !== ''&& strpos($details, $kw) !== false) {
                    $matchedKeywords[] = $kw;
                    if (self::TRIAGE_TIERS[$tierName]['rank'] >self::TRIAGE_TIERS[$detectedTier]['rank']) {
                        $detectedTier = $tierName;
                    }
                }
            }
        }

        // Honor explicit severity if higher or if no keywords matched
        if (isset(self::TRIAGE_TIERS[$explicitSeverity])) {
            if (empty($matchedKeywords) || self::TRIAGE_TIERS[$explicitSeverity]['rank'] >self::TRIAGE_TIERS[$detectedTier]['rank']) {
                $detectedTier = $explicitSeverity;
            }
        }

        $acuityScore = self::TRIAGE_TIERS[$detectedTier]['base_score'];
        // Extra multi-symptom boost (up to +6 pts within acuity band)
        $symptomBonus = min(6.0, count(array_unique($matchedKeywords)) * 2.0);
        $acuityScore = min(50.0, $acuityScore + $symptomBonus);

        // 2. Pet Vulnerability Score (0..15 pts)
        $vulnerabilityScore = 0.0;
        $petReasons = [];
        $petId = !empty($booking['Pet_ID']) ? (int)$booking['Pet_ID'] : null;
        $ownerId = !empty($booking['Owner_ID']) ? (int)$booking['Owner_ID'] : null;

        $petRow = null;
        if ($petId) {
            $pStmt = $this->conn->prepare("SELECT * FROM pet WHERE Pet_ID = ? LIMIT 1");
            $pStmt->bind_param('i', $petId);
            $pStmt->execute();
            $petRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();
        } elseif ($ownerId) {
            $pStmt = $this->conn->prepare("SELECT * FROM pet WHERE Owner_ID = ? ORDER BY Pet_ID DESC LIMIT 1");
            $pStmt->bind_param('i', $ownerId);
            $pStmt->execute();
            $petRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();
        }

        if ($petRow) {
            $age = isset($petRow['Age']) && $petRow['Age'] !== null ? (int)$petRow['Age'] : null;
            if ($age !== null && ($age <= 1 || $age >= 9)) {
                $vulnerabilityScore += 6.0;
                $petReasons[] = "high-risk age ({$age}y)";
            }
            if (!empty(trim($petRow['Past_Conditions'] ?? '')) && strtolower(trim($petRow['Past_Conditions'])) !== 'none') {
                $vulnerabilityScore += 5.0;
                $petReasons[] = "prior medical history";
            }
            if (!empty(trim($petRow['Current_Medications'] ?? '')) && strtolower(trim($petRow['Current_Medications'])) !== 'none') {
                $vulnerabilityScore += 4.0;
                $petReasons[] = "active medications";
            }
        }

        // 3. Waiting Time Escalation / Aging Factor (0..20 pts, +1.25 pts/min waited)
        $agingScore = min(20.0, round($waitMinutes * 1.25, 2));

        // 4. Active Treatment Continuity Bonus (0..15 pts)
        $continuityBonus = (($booking['Status'] ?? '') === 'In Progress') ? 15.0 : 0.0;

        $totalScore = min(100.0, round($acuityScore + $vulnerabilityScore + $agingScore + $continuityBonus, 2));

        return [
            'severity_level'=>$detectedTier,
            'severity_rank'=> self::TRIAGE_TIERS[$detectedTier]['rank'],
            'priority_score'=>$totalScore,
            'acuity_score'=>$acuityScore,
            'vulnerability_score'=>$vulnerabilityScore,
            'aging_score'=>$agingScore,
            'wait_minutes'=> round($waitMinutes, 1),
            'matched_keywords'=> array_values(array_unique($matchedKeywords)),
            'pet_factors'=>$petReasons,
        ];
    }

    /**
     * Master Triage & Resource Preemption Algorithm:
     * Evaluates all active emergencies ('Pending', 'In Progress'), dictates which pet goes first
     * when two or more emergencies occur simultaneously, allocates compatible clinic rooms & vets,
     * and automatically preempts/reschedules conflicting regular appointments if needed.
     */
    public function recalculateEmergencyTriage(): array
    {
        // 1. Fetch all active emergencies (excluding 'Contact'direct-call placeholders)
        $res = $this->conn->query(
            "SELECT * FROM emergency_booking
             WHERE Status IN ('Pending', 'In Progress')
             ORDER BY Created_At ASC, Booking_ID ASC"
        );
        $activeCases = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        // If no active emergencies exist, release any rooms tied to finished emergencies
        if (empty($activeCases)) {
            $this->conn->query("UPDATE room SET Status = 'idle', Current_Emergency_ID = NULL WHERE Current_Emergency_ID IS NOT NULL");
            return [
                'active_count'=>0,
                'queue'=>[],
                'preempted_schedules'=>[],
            ];
        }

        // 2. Score every active emergency
        foreach ($activeCases as &$case) {
            $metrics = $this->computeEmergencyPriority($case);
            $case['_metrics'] = $metrics;
            $case['Severity_Level'] = $metrics['severity_level'];
            $case['Priority_Score'] = $metrics['priority_score'];
        }
        unset($case);

        // 3. Sort deterministically:
        //    1) Priority_Score DESC
        //    2) Severity_Rank DESC
        //    3) Created_At ASC (FIFO tie-breaker)
        //    4) Booking_ID ASC
        usort($activeCases, function (array $a, array $b): int {
            if (abs($b['_metrics']['priority_score'] - $a['_metrics']['priority_score']) >0.001) {
                return $b['_metrics']['priority_score'] <=>$a['_metrics']['priority_score'];
            }
            if ($b['_metrics']['severity_rank'] !== $a['_metrics']['severity_rank']) {
                return $b['_metrics']['severity_rank'] <=>$a['_metrics']['severity_rank'];
            }
            $tA = strtotime($a['Created_At'] ?? '') ?: 0;
            $tB = strtotime($b['Created_At'] ?? '') ?: 0;
            if ($tA !== $tB) {
                return $tA <=>$tB;
            }
            return ((int)$a['Booking_ID']) <=>((int)$b['Booking_ID']);
        });

        // 4. Load available clinical rooms (prefer Emergency Trauma Bay ->Surgery Suite ->Main/General ->Dental)
        $roomsRes = $this->conn->query(
            "SELECT * FROM room
             WHERE COALESCE(Room_Type, 'General') != 'Grooming'
             ORDER BY CASE
                WHEN Room_Type = 'Emergency'OR Room_Name LIKE '%Emergency%'OR Room_Name LIKE '%Trauma%'THEN 1
                WHEN Room_Type = 'Surgery'OR Room_Name LIKE '%Surgery%'THEN 2
                WHEN Room_Type = 'General'OR Room_Name LIKE '%Main%'THEN 3
                ELSE 4
             END ASC, Room_ID ASC"
        );
        $candidateRooms = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];

        // 5. Load approved veterinarians (prefer Surgery/Emergency specialties)
        $vetsRes = $this->conn->query(
            "SELECT * FROM veterinarian
             WHERE COALESCE(Status, 'Approved') = 'Approved'
             ORDER BY CASE
                WHEN Specialties LIKE '%Emergency%'OR Specialties LIKE '%Surgery%'THEN 1
                ELSE 2
             END ASC, Veterinarian_ID ASC"
        );
        $candidateVets = $vetsRes ? $vetsRes->fetch_all(MYSQLI_ASSOC) : [];

        // 6. Identify regular scheduled appointments overlapping the current emergency window [NOW - 30m, NOW + 90m]
        $winStart = date('Y-m-d H:i:s', time() - 1800);
        $winEnd = date('Y-m-d H:i:s', time() + 5400);
        $confStmt = $this->conn->prepare(
            "SELECT * FROM schedule
             WHERE Status IN ('Pending', 'Confirmed', 'In Progress')
               AND Appointment_Time < ?
               AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) >?
             ORDER BY Appointment_Time ASC"
        );
        $confStmt->bind_param('ss', $winEnd, $winStart);
        $confStmt->execute();
        $overlappingSchedules = $confStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $confStmt->close();

        // Track rooms and vets claimed by higher-priority emergencies during this pass
        $usedRoomIds = [];
        $usedVetIds = [];
        $preemptedSchedules = [];
        $queueOutput = [];

        // Reset emergency room pointers before re-applying current active assignments
        $this->conn->query("UPDATE room SET Current_Emergency_ID = NULL, Status = 'idle'WHERE Current_Emergency_ID IS NOT NULL");

        foreach ($activeCases as $idx =>$case) {
            $queuePos = $idx + 1;
            $bookingId = (int)$case['Booking_ID'];
            $m = $case['_metrics'];

            // Find best unassigned room
            $chosenRoom = null;
            foreach ($candidateRooms as $r) {
                $rId = (int)$r['Room_ID'];
                if (!in_array($rId, $usedRoomIds, true)) {
                    $chosenRoom = $r;
                    break;
                }
            }

            // Find best unassigned vet
            $chosenVet = null;
            foreach ($candidateVets as $v) {
                $vId = (int)$v['Veterinarian_ID'];
                if (!in_array($vId, $usedVetIds, true)) {
                    $chosenVet = $v;
                    break;
                }
            }

            $preemptedSchedId = $case['Preempted_Schedule_ID'] ? (int)$case['Preempted_Schedule_ID'] : null;
            $preemptNote = '';

            if ($chosenRoom && $chosenVet) {
                $rId = (int)$chosenRoom['Room_ID'];
                $vId = (int)$chosenVet['Veterinarian_ID'];
                $usedRoomIds[] = $rId;
                $usedVetIds[] = $vId;

                // Check if this room or vet has a conflicting regular appointment right now; if so, preempt & auto-reschedule it!
                foreach ($overlappingSchedules as &$sched) {
                    if (!empty($sched['_already_preempted'])) {
                        continue;
                    }
                    $sId = (int)$sched['Schedule_ID'];
                    $sRoom = (int)($sched['Room_ID'] ?? 0);
                    $sVet = (int)($sched['Veterinarian_ID'] ?? 0);

                    if ($sRoom === $rId || $sVet === $vId) {
                        $sched['_already_preempted'] = true;
                        $oldTime = $sched['Appointment_Time'];
                        // Find next available slot after the emergency window (+90 mins)
                        $nextStartReq = date('Y-m-d H:i:s', max(strtotime($oldTime), time()) + 5400);
                        $reassigned = $this->findOptimalSlotAndResources(
                            $sched['Services'] ?? 'Wellness Care',
                            $nextStartReq,
                            $sVet ?: null,
                            $sRoom ?: null,
                            $sId
                        );

                        $newTime = $reassigned['appointment_time'];
                        $newEnd = $reassigned['end_time'];
                        $newVet = $reassigned['vet_id'];
                        $newRoom = $reassigned['room_id'];
                        $noteAppend = sprintf(
                            "[Auto-Rescheduled by Emergency Case #%d (%s, Score %.1f): moved from %s to %s]",
                            $bookingId,
                            $m['severity_level'],
                            $m['priority_score'],
                            $oldTime,
                            $newTime
                        );

                        $upSched = $this->conn->prepare(
                            "UPDATE schedule
                             SET Appointment_Time = ?,
                                 End_Time = ?,
                                 Veterinarian_ID = ?,
                                 Room_ID = ?,
                                 Preempted_By_Emergency_ID = ?,
                                 Previous_Appointment_Time = COALESCE(Previous_Appointment_Time, ?),
                                 Notes = CONCAT(COALESCE(Notes, ''), '', ?)
                             WHERE Schedule_ID = ?"
                        );
                        $upSched->bind_param('ssiiissi', $newTime, $newEnd, $newVet, $newRoom, $bookingId, $oldTime, $noteAppend, $sId);
                        $upSched->execute();
                        $upSched->close();

                        // Also log a system chat notification for the preempted schedule's owner
                        $chanKey = 'schedule_'. $sId;
                        $sysMsg = "Emergency Preemption Notice: Your {$sched['Services']} appointment (#{$sId}) originally at {$oldTime} was automatically shifted to {$newTime} so the clinic could treat a {$m['severity_level']} emergency (Case #{$bookingId}).";
                        $chatStmt = $this->conn->prepare(
                            "INSERT INTO chat_messages (Channel_Key, Schedule_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
                             VALUES (?, ?, 'system', 'Automated Triage Scheduler', ?, 'triage_notice')"
                        );
                        $chatStmt->bind_param('sis', $chanKey, $sId, $sysMsg);
                        $chatStmt->execute();
                        $chatStmt->close();

                        $preemptedSchedId = $sId;
                        $preemptNote = "Preempted & auto-rescheduled conflicting Appointment #{$sId} ({$sched['Services']}) from {$oldTime} to {$newTime}.";
                        $preemptedSchedules[] = [
                            'schedule_id'=>$sId,
                            'old_time'=>$oldTime,
                            'new_time'=>$newTime,
                            'preempted_by_booking_id'=>$bookingId,
                        ];
                    }
                }
                unset($sched);

                // Mark room as ongoing with this emergency
                $rUp = $this->conn->prepare("UPDATE room SET Status = 'ongoing', Current_Emergency_ID = ? WHERE Room_ID = ?");
                $rUp->bind_param('ii', $bookingId, $rId);
                $rUp->execute();
                $rUp->close();

                $kwText = !empty($m['matched_keywords']) ? 'keywords: '. implode(', ', $m['matched_keywords']) : 'clinical assessment';
                $vulnText = !empty($m['pet_factors']) ? '; pet factors: '. implode(', ', $m['pet_factors']) : '';
                $rationale = $preemptedSchedId ? "Priority #{$queuePos} ({$m['severity_level']}) · Auto-shifted conflicting Appt #{$preemptedSchedId}": "Priority #{$queuePos} ({$m['severity_level']})";

                // Promote top priority case to 'In Progress'& auto-enable call if it was Pending
                $newStatus = ($queuePos === 1 && $case['Status'] === 'Pending') ? 'In Progress': $case['Status'];
                $callEnabled = ($queuePos === 1) ? 1 : (int)($case['Call_Enabled'] ?? 0);

                $uStmt = $this->conn->prepare(
                    "UPDATE emergency_booking
                     SET Severity_Level = ?,
                         Priority_Score = ?,
                         Queue_Position = ?,
                         Assigned_Vet_ID = ?,
                         Assigned_Room_ID = ?,
                         Preempted_Schedule_ID = ?,
                         Triage_Rationale = ?,
                         Status = ?,
                         Call_Enabled = ?
                     WHERE Booking_ID = ?"
                );
                $uStmt->bind_param(
                    'sdiiiissii',
                    $m['severity_level'],
                    $m['priority_score'],
                    $queuePos,
                    $vId,
                    $rId,
                    $preemptedSchedId,
                    $rationale,
                    $newStatus,
                    $callEnabled,
                    $bookingId
                );
                $uStmt->execute();
                $uStmt->close();

                $queueOutput[] = [
                    'booking_id'=>$bookingId,
                    'queue_position'=>$queuePos,
                    'severity_level'=>$m['severity_level'],
                    'priority_score'=>$m['priority_score'],
                    'status'=>$newStatus,
                    'assigned_room_id'=>$rId,
                    'assigned_room_name'=>$chosenRoom['Room_Name'] ?: ('Room #'. $rId),
                    'assigned_vet_id'=>$vId,
                    'assigned_vet_name'=>'Dr. '. trim($chosenVet['First_Name'] . ''. $chosenVet['Last_Name']),
                    'preempted_schedule_id'=>$preemptedSchedId,
                    'rationale'=>$rationale,
                ];
            } else {
                // Standby in Triage Queue: all compatible rooms or vets are occupied by higher-priority emergencies
                $topCase = $activeCases[0];
                $rationale = "Queue #{$queuePos} (Standby) · Waiting for Case #". (int)$topCase['Booking_ID'];

                $rId = $chosenRoom ? (int)$chosenRoom['Room_ID'] : null;
                $vId = $chosenVet ? (int)$chosenVet['Veterinarian_ID'] : null;

                $uStmt = $this->conn->prepare(
                    "UPDATE emergency_booking
                     SET Severity_Level = ?,
                         Priority_Score = ?,
                         Queue_Position = ?,
                         Assigned_Vet_ID = ?,
                         Assigned_Room_ID = ?,
                         Triage_Rationale = ?
                     WHERE Booking_ID = ?"
                );
                $uStmt->bind_param(
                    'sdiiisi',
                    $m['severity_level'],
                    $m['priority_score'],
                    $queuePos,
                    $vId,
                    $rId,
                    $rationale,
                    $bookingId
                );
                $uStmt->execute();
                $uStmt->close();

                $queueOutput[] = [
                    'booking_id'=>$bookingId,
                    'queue_position'=>$queuePos,
                    'severity_level'=>$m['severity_level'],
                    'priority_score'=>$m['priority_score'],
                    'status'=>$case['Status'],
                    'assigned_room_id'=>$rId,
                    'assigned_room_name'=>$chosenRoom ? ($chosenRoom['Room_Name'] ?: 'Standby') : 'Awaiting Free Trauma/Exam Room',
                    'assigned_vet_id'=>$vId,
                    'assigned_vet_name'=>$chosenVet ? ('Dr. '. trim($chosenVet['First_Name'] . ''. $chosenVet['Last_Name'])) : 'Awaiting Next Free Vet',
                    'preempted_schedule_id'=> null,
                    'rationale'=>$rationale,
                ];
            }
        }

        return [
            'active_count'=> count($queueOutput),
            'queue'=>$queueOutput,
            'preempted_schedules'=>$preemptedSchedules,
        ];
    }
}
