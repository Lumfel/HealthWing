<?php
/**
 * HealthWing / VetSystem — Automated Scheduling & Emergency Priority Triage Engine
 * (includes/scheduling_engine.php)
 */

require_once __DIR__ . '/db.php';

class VetSchedulingEngine
{
    private mysqli $conn;

    public const SERVICE_PROFILES = [
        'Surgery' => [
            'duration' => 90,
            'rooms' => ['Surgery', 'Emergency', 'General'],
            'specialties' => ['Surgery', 'General'],
            'preempt_rank' => 4,
        ],
        'Dental' => [
            'duration' => 60,
            'rooms' => ['Dental', 'General'],
            'specialties' => ['Dental', 'General'],
            'preempt_rank' => 3,
        ],
        'Vaccination' => [
            'duration' => 30,
            'rooms' => ['General'],
            'specialties' => ['Vaccination', 'Wellness', 'General'],
            'preempt_rank' => 2,
        ],
        'Parasite Control' => [
            'duration' => 30,
            'rooms' => ['General'],
            'specialties' => ['Parasite', 'Wellness', 'General'],
            'preempt_rank' => 2,
        ],
        'Wellness Care' => [
            'duration' => 30,
            'rooms' => ['General'],
            'specialties' => ['Wellness', 'General'],
            'preempt_rank' => 1,
        ],
        'Grooming' => [
            'duration' => 60,
            'rooms' => ['Grooming', 'General'],
            'specialties' => ['Grooming', 'Wellness', 'General'],
            'preempt_rank' => 1,
        ],
    ];

    public const TRIAGE_TIERS = [
        'Critical' => [
            'base_score' => 50.0,
            'rank' => 4,
            'keywords' => [
                'unconscious', 'not breathing', 'choking', 'apnea', 'cardiac', 'arrest',
                'hit by car', 'car accident', 'accident', 'seizure', 'convulsion', 'poison',
                'toxin', 'chocolate', 'rat bait', 'bloat', 'gdv', 'collapse', 'collapsed',
                'hemorrhage', 'arterial', 'blue gums', 'cyanosis', 'drowning', 'electrocution',
                'dystocia', 'stuck puppy', 'stuck kitten', 'paralyzed', 'severe trauma',
            ],
        ],
        'Emergent' => [
            'base_score' => 38.0,
            'rank' => 3,
            'keywords' => [
                'bleeding', 'blood', 'fracture', 'broken bone', 'broken leg', 'snake',
                'venom', 'heatstroke', 'burn', 'eye injury', 'proptosis', 'unable to stand',
                'deep wound', 'laceration', 'swollen abdomen', 'straining to urinate',
                'blocked cat', 'uret', 'difficulty breathing', 'wheezing', 'gasping', 'foreign body',
            ],
        ],
        'Urgent' => [
            'base_score' => 25.0,
            'rank' => 2,
            'keywords' => [
                'vomiting', 'diarrhea', 'fever', 'lethargic', 'lethargy', 'pain', 'crying',
                'wound', 'bite', 'abscess', 'allergic', 'hives', 'swelling', 'not eating',
                'anorexia', 'trembling', 'shaking', 'infection', 'pus', 'ulcer',
            ],
        ],
        'Semi-Urgent' => [
            'base_score' => 12.0,
            'rank' => 1,
            'keywords' => [
                'limping', 'limp', 'scratch', 'ear infection', 'itching', 'cough',
                'sneezing', 'rash', 'tick', 'flea', 'nail', 'minor', 'constipation',
            ],
        ],
    ];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getServiceProfile(string $service): array
    {
        foreach (self::SERVICE_PROFILES as $name => $profile) {
            if (strcasecmp(trim($service), $name) === 0 || stripos($service, $name) !== false) {
                return $profile;
            }
        }
        return [
            'duration' => 30,
            'rooms' => ['General'],
            'specialties' => ['General', 'Wellness'],
            'preempt_rank' => 2,
        ];
    }

    public function normalizeToClinicHours(int $timestamp, int $durationMinutes = 30): int
    {
        $rem = $timestamp % 900;
        if ($rem !== 0) {
            $timestamp += (900 - $rem);
        }

        for ($guard = 0; $guard < 14; $guard++) {
            $dow = (int)date('w', $timestamp);
            $dateStr = date('Y-m-d', $timestamp);

            if ($dow === 0) {
                $timestamp = strtotime($dateStr . ' +1 day 08:00:00');
                continue;
            }

            $openHour = ($dow === 6) ? '09:00:00' : '08:00:00';
            $closeHour = ($dow === 6) ? '15:00:00' : '18:00:00';

            $openTs = strtotime("$dateStr $openHour");
            $closeTs = strtotime("$dateStr $closeHour");

            if ($timestamp < $openTs) {
                $timestamp = $openTs;
            }

            if (($timestamp + ($durationMinutes * 60)) > $closeTs) {
                $timestamp = strtotime($dateStr . ' +1 day 08:00:00');
                continue;
            }

            return $timestamp;
        }

        return $timestamp;
    }

    /**
     * Checks whether a specific Veterinarian is free during [startDt, endDt].
     * Uses fully parameterized SQL and optional InnoDB FOR UPDATE row locks.
     */
    public function isVetAvailable(int $vetId, string $startDt, string $endDt, ?int $ignoreScheduleId = null, bool $forUpdate = false): bool
    {
        $lockSuffix = $forUpdate ? ' FOR UPDATE' : '';
        if ($ignoreScheduleId !== null) {
            $sql = "SELECT Schedule_ID FROM schedule
                    WHERE Veterinarian_ID = ?
                      AND Deleted_At IS NULL
                      AND Status IN ('Pending', 'In Progress', 'Confirmed')
                      AND Appointment_Time < ?
                      AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) > ?
                      AND Schedule_ID != ?" . $lockSuffix;
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('issi', $vetId, $endDt, $startDt, $ignoreScheduleId);
        } else {
            $sql = "SELECT Schedule_ID FROM schedule
                    WHERE Veterinarian_ID = ?
                      AND Deleted_At IS NULL
                      AND Status IN ('Pending', 'In Progress', 'Confirmed')
                      AND Appointment_Time < ?
                      AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) > ?" . $lockSuffix;
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('iss', $vetId, $endDt, $startDt);
        }
        $stmt->execute();
        $hasConflict = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($hasConflict) {
            return false;
        }

        $now = time();
        $startTs = strtotime($startDt);
        if (abs($startTs - $now) <= 3600) {
            $emSql = "SELECT Booking_ID FROM emergency_booking
                      WHERE Assigned_Vet_ID = ? AND Deleted_At IS NULL AND Status = 'In Progress'" . $lockSuffix;
            $emStmt = $this->conn->prepare($emSql);
            $emStmt->bind_param('i', $vetId);
            $emStmt->execute();
            $emConflict = $emStmt->get_result()->num_rows > 0;
            $emStmt->close();
            if ($emConflict) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether a specific Room is free during [startDt, endDt].
     * Uses fully parameterized SQL and optional InnoDB FOR UPDATE row locks.
     */
    public function isRoomAvailable(int $roomId, string $startDt, string $endDt, ?int $ignoreScheduleId = null, bool $forUpdate = false): bool
    {
        $lockSuffix = $forUpdate ? ' FOR UPDATE' : '';
        if ($ignoreScheduleId !== null) {
            $sql = "SELECT Schedule_ID FROM schedule
                    WHERE Room_ID = ?
                      AND Deleted_At IS NULL
                      AND Status IN ('Pending', 'In Progress', 'Confirmed')
                      AND Appointment_Time < ?
                      AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) > ?
                      AND Schedule_ID != ?" . $lockSuffix;
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('issi', $roomId, $endDt, $startDt, $ignoreScheduleId);
        } else {
            $sql = "SELECT Schedule_ID FROM schedule
                    WHERE Room_ID = ?
                      AND Deleted_At IS NULL
                      AND Status IN ('Pending', 'In Progress', 'Confirmed')
                      AND Appointment_Time < ?
                      AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) > ?" . $lockSuffix;
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('iss', $roomId, $endDt, $startDt);
        }
        $stmt->execute();
        $hasConflict = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($hasConflict) {
            return false;
        }

        $now = time();
        $startTs = strtotime($startDt);
        if (abs($startTs - $now) <= 3600) {
            $emSql = "SELECT Booking_ID FROM emergency_booking
                      WHERE Assigned_Room_ID = ? AND Deleted_At IS NULL AND Status = 'In Progress'" . $lockSuffix;
            $emStmt = $this->conn->prepare($emSql);
            $emStmt->bind_param('i', $roomId);
            $emStmt->execute();
            $emConflict = $emStmt->get_result()->num_rows > 0;
            $emStmt->close();
            if ($emConflict) {
                return false;
            }
        }

        return true;
    }

    /**
     * Locks conflicting schedule rows for the target Veterinarian_ID and Room_ID via SELECT ... FOR UPDATE
     * inside an active transaction to guarantee zero double-booking races.
     */
    public function lockAndVerifySlotAvailable(
        ?int $vetId,
        ?int $roomId,
        string $startDt,
        string $endDt,
        ?int $ignoreScheduleId = null
    ): bool {
        if ($vetId !== null) {
            $vLock = $this->conn->prepare("SELECT Veterinarian_ID FROM veterinarian WHERE Veterinarian_ID = ? FOR UPDATE");
            $vLock->bind_param('i', $vetId);
            $vLock->execute();
            $vLock->close();
        }
        if ($roomId !== null) {
            $rLock = $this->conn->prepare("SELECT Room_ID FROM room WHERE Room_ID = ? FOR UPDATE");
            $rLock->bind_param('i', $roomId);
            $rLock->execute();
            $rLock->close();
        }

        if ($vetId !== null && !$this->isVetAvailable($vetId, $startDt, $endDt, $ignoreScheduleId, true)) {
            return false;
        }
        if ($roomId !== null && !$this->isRoomAvailable($roomId, $startDt, $endDt, $ignoreScheduleId, true)) {
            return false;
        }
        return true;
    }

    /**
     * Finds the optimal available (Veterinarian, Room, StartTime, EndTime) for a service.
     */
    public function findOptimalSlotAndResources(
        string $service,
        string $requestedDateTime,
        ?int $preferredVetId = null,
        ?int $preferredRoomId = null,
        ?int $ignoreScheduleId = null,
        bool $forUpdate = false
    ): array {
        $profile = $this->getServiceProfile($service);
        $duration = (int)$profile['duration'];
        $reqTs = strtotime($requestedDateTime) ?: (time() + 3600);
        $baseTs = $this->normalizeToClinicHours($reqTs, $duration);

        $vets = [];
        $vRes = $this->conn->query(
            "SELECT Veterinarian_ID, First_Name, Last_Name, Specialties
             FROM veterinarian
             WHERE Deleted_At IS NULL AND COALESCE(Status, 'Approved') = 'Approved'
             ORDER BY Veterinarian_ID ASC"
        );
        if ($vRes) {
            $vets = $vRes->fetch_all(MYSQLI_ASSOC);
        }

        $rooms = [];
        $rRes = $this->conn->query(
            "SELECT Room_ID, Room_Name, Room_Type, Status
             FROM room
             ORDER BY Room_ID ASC"
        );
        if ($rRes) {
            $rooms = $rRes->fetch_all(MYSQLI_ASSOC);
        }

        $candTs = $baseTs;
        for ($step = 0; $step < 96; $step++) {
            $candTs = $this->normalizeToClinicHours($candTs, $duration);
            $startDt = date('Y-m-d H:i:s', $candTs);
            $endDt = date('Y-m-d H:i:s', $candTs + ($duration * 60));
            $dayStr = date('Y-m-d', $candTs);

            $bestVet = null;
            $bestVetScore = -9999;

            foreach ($vets as $v) {
                $vId = (int)$v['Veterinarian_ID'];
                if (!$this->isVetAvailable($vId, $startDt, $endDt, $ignoreScheduleId, $forUpdate)) {
                    continue;
                }

                $score = 50;
                if ($preferredVetId && $vId === $preferredVetId) {
                    $score += 100;
                }
                $spec = strtolower($v['Specialties'] ?? '');
                foreach ($profile['specialties'] as $idx => $prefSpec) {
                    if (stripos($spec, strtolower($prefSpec)) !== false) {
                        $score += (35 - ($idx * 10));
                        break;
                    }
                }

                $wlStmt = $this->conn->prepare(
                    "SELECT COUNT(*) AS c FROM schedule
                     WHERE Veterinarian_ID = ? AND DATE(Appointment_Time) = ? AND Deleted_At IS NULL AND Status != 'Cancelled'"
                );
                $wlStmt->bind_param('is', $vId, $dayStr);
                $wlStmt->execute();
                $dayCount = (int)($wlStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $wlStmt->close();

                $score -= ($dayCount * 6);

                if ($score > $bestVetScore) {
                    $bestVetScore = $score;
                    $bestVet = $v;
                }
            }

            $bestRoom = null;
            $bestRoomScore = -9999;

            foreach ($rooms as $r) {
                $rId = (int)$r['Room_ID'];
                $rType = $r['Room_Type'] ?? 'General';
                if ($rType === 'Emergency' && $service !== 'Surgery') {
                    continue;
                }
                if (!$this->isRoomAvailable($rId, $startDt, $endDt, $ignoreScheduleId, $forUpdate)) {
                    continue;
                }

                $rScore = 40;
                if ($preferredRoomId && $rId === $preferredRoomId) {
                    $rScore += 100;
                }
                foreach ($profile['rooms'] as $idx => $prefRoomType) {
                    if (strcasecmp($rType, $prefRoomType) === 0 || stripos($r['Room_Name'] ?? '', $prefRoomType) !== false) {
                        $rScore += (40 - ($idx * 12));
                        break;
                    }
                }

                if ($rScore > $bestRoomScore) {
                    $bestRoomScore = $rScore;
                    $bestRoom = $r;
                }
            }

            $vetOk = ($bestVet !== null) || empty($vets);
            $roomOk = ($bestRoom !== null) || empty($rooms);

            if ($vetOk && $roomOk) {
                $wasShifted = ($candTs !== $reqTs);
                return [
                    'success' => true,
                    'appointment_time' => $startDt,
                    'end_time' => $endDt,
                    'duration_minutes' => $duration,
                    'was_shifted' => $wasShifted,
                    'requested_time' => date('Y-m-d H:i:s', $reqTs),
                    'vet_id' => $bestVet ? (int)$bestVet['Veterinarian_ID'] : null,
                    'vet_name' => $bestVet ? trim($bestVet['First_Name'] . ' ' . $bestVet['Last_Name']) : 'On-Call Veterinarian',
                    'vet_specialty' => $bestVet['Specialties'] ?? 'General Practice',
                    'room_id' => $bestRoom ? (int)$bestRoom['Room_ID'] : null,
                    'room_name' => $bestRoom ? ($bestRoom['Room_Name'] ?: 'Room #' . $bestRoom['Room_ID']) : 'Main Exam Room',
                    'room_type' => $bestRoom['Room_Type'] ?? 'General',
                ];
            }

            $candTs += 1800;
        }

        $fallbackStart = date('Y-m-d H:i:s', $baseTs);
        $fallbackEnd = date('Y-m-d H:i:s', $baseTs + ($duration * 60));
        return [
            'success' => true,
            'appointment_time' => $fallbackStart,
            'end_time' => $fallbackEnd,
            'duration_minutes' => $duration,
            'was_shifted' => true,
            'requested_time' => date('Y-m-d H:i:s', $reqTs),
            'vet_id' => !empty($vets) ? (int)$vets[0]['Veterinarian_ID'] : null,
            'vet_name' => !empty($vets) ? trim($vets[0]['First_Name'] . ' ' . $vets[0]['Last_Name']) : 'On-Call Veterinarian',
            'vet_specialty' => !empty($vets) ? ($vets[0]['Specialties'] ?? 'General') : 'General',
            'room_id' => !empty($rooms) ? (int)$rooms[0]['Room_ID'] : null,
            'room_name' => !empty($rooms) ? ($rooms[0]['Room_Name'] ?: 'Main Room') : 'Main Room',
            'room_type' => 'General',
        ];
    }

    public function computeEmergencyPriority(array $booking): array
    {
        $details = strtolower(trim((string)($booking['Additional_Details'] ?? '')));
        $explicitSeverity = trim((string)($booking['Severity_Level'] ?? ''));
        $createdAtTs = !empty($booking['Created_At']) ? strtotime((string)$booking['Created_At']) : time();
        $waitMinutes = max(0.0, (time() - $createdAtTs) / 60.0);

        $detectedTier = 'Urgent';
        $matchedKeywords = [];

        foreach (self::TRIAGE_TIERS as $tierName => $meta) {
            foreach ($meta['keywords'] as $kw) {
                if ($details !== '' && strpos($details, $kw) !== false) {
                    $matchedKeywords[] = $kw;
                    if (self::TRIAGE_TIERS[$tierName]['rank'] > self::TRIAGE_TIERS[$detectedTier]['rank']) {
                        $detectedTier = $tierName;
                    }
                }
            }
        }

        if (isset(self::TRIAGE_TIERS[$explicitSeverity])) {
            if (empty($matchedKeywords) || self::TRIAGE_TIERS[$explicitSeverity]['rank'] > self::TRIAGE_TIERS[$detectedTier]['rank']) {
                $detectedTier = $explicitSeverity;
            }
        }

        $acuityScore = self::TRIAGE_TIERS[$detectedTier]['base_score'];
        $symptomBonus = min(6.0, count(array_unique($matchedKeywords)) * 2.0);
        $acuityScore = min(50.0, $acuityScore + $symptomBonus);

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
            $pStmt = $this->conn->prepare("SELECT * FROM pet WHERE Owner_ID = ? AND Deleted_At IS NULL ORDER BY Pet_ID DESC LIMIT 1");
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
            $past = hw_decrypt_gcm($petRow['Past_Conditions'] ?? null) ?? ($petRow['Past_Conditions'] ?? '');
            if (!empty(trim((string)$past)) && strtolower(trim((string)$past)) !== 'none') {
                $vulnerabilityScore += 5.0;
                $petReasons[] = "prior medical history";
            }
            $meds = hw_decrypt_gcm($petRow['Current_Medications'] ?? null) ?? ($petRow['Current_Medications'] ?? '');
            if (!empty(trim((string)$meds)) && strtolower(trim((string)$meds)) !== 'none') {
                $vulnerabilityScore += 4.0;
                $petReasons[] = "active medications";
            }
        }

        $agingScore = min(20.0, round($waitMinutes * 1.25, 2));
        $continuityBonus = (($booking['Status'] ?? '') === 'In Progress') ? 15.0 : 0.0;
        $totalScore = min(100.0, round($acuityScore + $vulnerabilityScore + $agingScore + $continuityBonus, 2));

        return [
            'severity_level' => $detectedTier,
            'severity_rank' => self::TRIAGE_TIERS[$detectedTier]['rank'],
            'priority_score' => $totalScore,
            'acuity_score' => $acuityScore,
            'vulnerability_score' => $vulnerabilityScore,
            'aging_score' => $agingScore,
            'wait_minutes' => round($waitMinutes, 1),
            'matched_keywords' => array_values(array_unique($matchedKeywords)),
            'pet_factors' => $petReasons,
        ];
    }

    /**
     * Master Triage & Resource Preemption Algorithm.
     * Guarded by advisory lock `hw_triage_lock` + InnoDB transaction + SELECT ... FOR UPDATE row locks.
     * Pass $manageLockAndTx = false when caller already holds `hw_triage_lock` and an open transaction.
     */
    public function recalculateEmergencyTriage(bool $manageLockAndTx = true): array
    {
        $lockAcquired = false;
        $startedTx = false;

        try {
            if ($manageLockAndTx) {
                $lockAcquired = hw_db_acquire_lock($this->conn, 'hw_triage_lock', 5);
                if (!$lockAcquired) {
                    throw new RuntimeException('Emergency triage queue is busy. Please retry.');
                }
                if (!$this->conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start triage transaction.');
                }
                $startedTx = true;
            }

            // Lock all active emergency_booking rows with FOR UPDATE
            $res = $this->conn->query(
                "SELECT * FROM emergency_booking
                 WHERE Deleted_At IS NULL AND Status IN ('Pending', 'In Progress')
                 ORDER BY Created_At ASC, Booking_ID ASC
                 FOR UPDATE"
            );
            $activeCases = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

            // Lock candidate room rows with FOR UPDATE
            $roomsRes = $this->conn->query(
                "SELECT * FROM room
                 WHERE COALESCE(Room_Type, 'General') != 'Grooming'
                 ORDER BY CASE
                    WHEN Room_Type = 'Emergency' OR Room_Name LIKE '%Emergency%' OR Room_Name LIKE '%Trauma%' THEN 1
                    WHEN Room_Type = 'Surgery' OR Room_Name LIKE '%Surgery%' THEN 2
                    WHEN Room_Type = 'General' OR Room_Name LIKE '%Main%' THEN 3
                    ELSE 4
                 END ASC, Room_ID ASC
                 FOR UPDATE"
            );
            $candidateRooms = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];

            if (empty($activeCases)) {
                $this->conn->query(
                    "UPDATE room
                     SET Status = CASE WHEN Current_Schedule_ID IS NOT NULL THEN 'ongoing' ELSE 'idle' END,
                         Current_Emergency_ID = NULL
                     WHERE Current_Emergency_ID IS NOT NULL"
                );
                if ($startedTx) {
                    $this->conn->commit();
                }
                return [
                    'active_count' => 0,
                    'queue' => [],
                    'preempted_schedules' => [],
                ];
            }

            foreach ($activeCases as &$case) {
                $metrics = $this->computeEmergencyPriority($case);
                $case['_metrics'] = $metrics;
                $case['Severity_Level'] = $metrics['severity_level'];
                $case['Priority_Score'] = $metrics['priority_score'];
            }
            unset($case);

            usort($activeCases, function (array $a, array $b): int {
                if (abs($b['_metrics']['priority_score'] - $a['_metrics']['priority_score']) > 0.001) {
                    return $b['_metrics']['priority_score'] <=> $a['_metrics']['priority_score'];
                }
                if ($b['_metrics']['severity_rank'] !== $a['_metrics']['severity_rank']) {
                    return $b['_metrics']['severity_rank'] <=> $a['_metrics']['severity_rank'];
                }
                $tA = strtotime($a['Created_At'] ?? '') ?: 0;
                $tB = strtotime($b['Created_At'] ?? '') ?: 0;
                if ($tA !== $tB) {
                    return $tA <=> $tB;
                }
                return ((int)$a['Booking_ID']) <=> ((int)$b['Booking_ID']);
            });

            $vetsRes = $this->conn->query(
                "SELECT * FROM veterinarian
                 WHERE Deleted_At IS NULL AND COALESCE(Status, 'Approved') = 'Approved'
                 ORDER BY CASE
                    WHEN Specialties LIKE '%Emergency%' OR Specialties LIKE '%Surgery%' THEN 1
                    ELSE 2
                 END ASC, Veterinarian_ID ASC
                 FOR UPDATE"
            );
            $candidateVets = $vetsRes ? $vetsRes->fetch_all(MYSQLI_ASSOC) : [];

            $winStart = date('Y-m-d H:i:s', time() - 1800);
            $winEnd = date('Y-m-d H:i:s', time() + 5400);
            $confStmt = $this->conn->prepare(
                "SELECT * FROM schedule
                 WHERE Deleted_At IS NULL
                   AND Status IN ('Pending', 'Confirmed', 'In Progress')
                   AND Appointment_Time < ?
                   AND COALESCE(End_Time, DATE_ADD(Appointment_Time, INTERVAL COALESCE(Duration_Minutes, 30) MINUTE)) > ?
                 ORDER BY Appointment_Time ASC
                 FOR UPDATE"
            );
            $confStmt->bind_param('ss', $winEnd, $winStart);
            $confStmt->execute();
            $overlappingSchedules = $confStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $confStmt->close();

            $usedRoomIds = [];
            $usedVetIds = [];
            $preemptedSchedules = [];
            $queueOutput = [];

            // Reset emergency room pointers safely inside transaction
            $this->conn->query(
                "UPDATE room
                 SET Current_Emergency_ID = NULL,
                     Status = CASE WHEN Current_Schedule_ID IS NOT NULL THEN 'ongoing' ELSE 'idle' END
                 WHERE Current_Emergency_ID IS NOT NULL"
            );

            foreach ($activeCases as $idx => $case) {
                $queuePos = $idx + 1;
                $bookingId = (int)$case['Booking_ID'];
                $m = $case['_metrics'];

                $chosenRoom = null;
                foreach ($candidateRooms as $r) {
                    $rId = (int)$r['Room_ID'];
                    if (!in_array($rId, $usedRoomIds, true)) {
                        $chosenRoom = $r;
                        break;
                    }
                }

                $chosenVet = null;
                foreach ($candidateVets as $v) {
                    $vId = (int)$v['Veterinarian_ID'];
                    if (!in_array($vId, $usedVetIds, true)) {
                        $chosenVet = $v;
                        break;
                    }
                }

                $preemptedSchedId = !empty($case['Preempted_Schedule_ID']) ? (int)$case['Preempted_Schedule_ID'] : null;

                if ($chosenRoom && $chosenVet) {
                    $rId = (int)$chosenRoom['Room_ID'];
                    $vId = (int)$chosenVet['Veterinarian_ID'];
                    $usedRoomIds[] = $rId;
                    $usedVetIds[] = $vId;

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
                            $nextStartReq = date('Y-m-d H:i:s', max(strtotime($oldTime), time()) + 5400);
                            $reassigned = $this->findOptimalSlotAndResources(
                                $sched['Services'] ?? 'Wellness Care',
                                $nextStartReq,
                                $sVet ?: null,
                                $sRoom ?: null,
                                $sId,
                                true
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
                                     Notes = TRIM(CONCAT(COALESCE(Notes, ''), ' ', ?))
                                 WHERE Schedule_ID = ?"
                            );
                            $upSched->bind_param('ssiiissi', $newTime, $newEnd, $newVet, $newRoom, $bookingId, $oldTime, $noteAppend, $sId);
                            $upSched->execute();
                            $upSched->close();

                            // Clear old schedule room pointer if it was bound to this displaced schedule
                            $clrRoom = $this->conn->prepare("UPDATE room SET Current_Schedule_ID = NULL WHERE Current_Schedule_ID = ?");
                            $clrRoom->bind_param('i', $sId);
                            $clrRoom->execute();
                            $clrRoom->close();

                            $chanKey = 'schedule_' . $sId;
                            $sysMsg = "Emergency Preemption Notice: Your {$sched['Services']} appointment (#{$sId}) originally at {$oldTime} was automatically shifted to {$newTime} so the clinic could treat a {$m['severity_level']} emergency (Case #{$bookingId}).";
                            $chatStmt = $this->conn->prepare(
                                "INSERT INTO chat_messages (Channel_Key, Schedule_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
                                 VALUES (?, ?, 'system', 'Automated Triage Scheduler', ?, 'triage_notice')"
                            );
                            $chatStmt->bind_param('sis', $chanKey, $sId, $sysMsg);
                            $chatStmt->execute();
                            $chatStmt->close();

                            $preemptedSchedId = $sId;
                            $preemptedSchedules[] = [
                                'schedule_id' => $sId,
                                'old_time' => $oldTime,
                                'new_time' => $newTime,
                                'preempted_by_booking_id' => $bookingId,
                            ];
                        }
                    }
                    unset($sched);

                    $rUp = $this->conn->prepare("UPDATE room SET Status = 'ongoing', Current_Emergency_ID = ? WHERE Room_ID = ?");
                    $rUp->bind_param('ii', $bookingId, $rId);
                    $rUp->execute();
                    $rUp->close();

                    $rationale = $preemptedSchedId
                        ? "Priority #{$queuePos} ({$m['severity_level']}) · Auto-shifted conflicting Appt #{$preemptedSchedId}"
                        : "Priority #{$queuePos} ({$m['severity_level']})";

                    $newStatus = ($queuePos === 1 && $case['Status'] === 'Pending') ? 'In Progress' : $case['Status'];
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
                        'booking_id' => $bookingId,
                        'queue_position' => $queuePos,
                        'severity_level' => $m['severity_level'],
                        'priority_score' => $m['priority_score'],
                        'status' => $newStatus,
                        'assigned_room_id' => $rId,
                        'assigned_room_name' => $chosenRoom['Room_Name'] ?: ('Room #' . $rId),
                        'assigned_vet_id' => $vId,
                        'assigned_vet_name' => 'Dr. ' . trim($chosenVet['First_Name'] . ' ' . $chosenVet['Last_Name']),
                        'preempted_schedule_id' => $preemptedSchedId,
                        'rationale' => $rationale,
                    ];
                } else {
                    $topCase = $activeCases[0];
                    $rationale = "Queue #{$queuePos} (Standby) · Waiting for Case #" . (int)$topCase['Booking_ID'];

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
                        'booking_id' => $bookingId,
                        'queue_position' => $queuePos,
                        'severity_level' => $m['severity_level'],
                        'priority_score' => $m['priority_score'],
                        'status' => $case['Status'],
                        'assigned_room_id' => $rId,
                        'assigned_room_name' => $chosenRoom ? ($chosenRoom['Room_Name'] ?: 'Standby') : 'Awaiting Free Trauma/Exam Room',
                        'assigned_vet_id' => $vId,
                        'assigned_vet_name' => $chosenVet ? ('Dr. ' . trim($chosenVet['First_Name'] . ' ' . $chosenVet['Last_Name'])) : 'Awaiting Next Free Vet',
                        'preempted_schedule_id' => null,
                        'rationale' => $rationale,
                    ];
                }
            }

            if ($startedTx) {
                $this->conn->commit();
            }

            return [
                'active_count' => count($queueOutput),
                'queue' => $queueOutput,
                'preempted_schedules' => $preemptedSchedules,
            ];
        } catch (Throwable $e) {
            if ($startedTx) {
                @$this->conn->rollback();
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                hw_db_release_lock($this->conn, 'hw_triage_lock');
            }
        }
    }
}
