<?php
/**
 * HealthWing Mobile API - Booking & Emergency
 * POST ?action=book
 * POST ?action=emergency
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../rate_limit.php';
require_once __DIR__ . '/../../includes/scheduling_engine.php';

hw_mobile_require_method('POST');
$identity = hw_mobile_require_role(['owner']);

$action = strtolower(trim((string)($_GET['action'] ?? '')));
if (!in_array($action, ['book', 'emergency'], true)) {
    hw_mobile_json_response(400, [
        'success' => false,
        'error' => 'invalid_action',
        'message' => 'Valid action query parameter is required: book or emergency.',
    ]);
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    hw_mobile_json_response(500, [
        'success' => false,
        'error' => 'database_unavailable',
        'message' => 'Database connection failed.',
    ]);
}

$ownerId = (int)$identity['user_id'];

if ($action === 'book') {
    rate_limit_check('mobile_book', 20, 300);
    $data = hw_mobile_read_json();

    $service = trim((string)($data['service'] ?? 'Wellness Care'));
    $appointmentTime = trim((string)($data['appointment_time'] ?? ''));
    $petIdInput = isset($data['pet_id']) && $data['pet_id'] !== '' ? (int)$data['pet_id'] : null;
    $petName = trim((string)($data['pet_name'] ?? ''));
    $petSpecies = trim((string)($data['pet_species'] ?? 'Dog'));
    $preferredVetId = isset($data['preferred_vet_id']) && $data['preferred_vet_id'] !== '' ? (int)$data['preferred_vet_id'] : null;
    $details = trim((string)($data['details'] ?? ($data['notes'] ?? '')));

    if ($appointmentTime === '') {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'missing_appointment_time',
            'message' => 'appointment_time is required.',
        ]);
    }
    if ($petIdInput === null && $petName === '') {
        hw_mobile_json_response(400, [
            'success' => false,
            'error' => 'missing_pet',
            'message' => 'pet_id or pet_name is required.',
        ]);
    }

    $lockAcquired = false;
    $txStarted = false;
    try {
        $engine = new VetSchedulingEngine($conn);

        $ownerStmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name FROM owner WHERE Owner_ID = ? AND Deleted_At IS NULL LIMIT 1');
        $ownerStmt->bind_param('i', $ownerId);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();
        if (!$owner) {
            hw_mobile_json_response(404, [
                'success' => false,
                'error' => 'owner_not_found',
                'message' => 'Owner account not found.',
            ]);
        }

        $lockAcquired = hw_db_acquire_lock($conn, 'hw_schedule_lock', 5);
        if (!$lockAcquired) {
            hw_mobile_json_response(503, [
                'success' => false,
                'error' => 'scheduler_busy',
                'message' => 'The appointment scheduler is momentarily busy. Please try again.',
            ]);
        }

        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Could not initialize booking transaction.');
        }
        $txStarted = true;

        $petId = null;
        if ($petIdInput !== null) {
            $petStmt = $conn->prepare('SELECT Pet_ID, Pet_Name FROM pet WHERE Pet_ID = ? AND Owner_ID = ? AND Deleted_At IS NULL LIMIT 1');
            $petStmt->bind_param('ii', $petIdInput, $ownerId);
            $petStmt->execute();
            $petRow = $petStmt->get_result()->fetch_assoc();
            $petStmt->close();
            if (!$petRow) {
                throw new RuntimeException('Selected pet was not found for this owner.');
            }
            $petId = (int)$petRow['Pet_ID'];
            $petName = (string)$petRow['Pet_Name'];
        }

        if ($petId === null && $petName !== '') {
            $petStmt = $conn->prepare('SELECT Pet_ID FROM pet WHERE Pet_Name = ? AND Owner_ID = ? AND Deleted_At IS NULL LIMIT 1');
            $petStmt->bind_param('si', $petName, $ownerId);
            $petStmt->execute();
            $petRow = $petStmt->get_result()->fetch_assoc();
            $petStmt->close();
            if ($petRow) {
                $petId = (int)$petRow['Pet_ID'];
            } else {
                $insPet = $conn->prepare('INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Species) VALUES (?, ?, ?, ?)');
                $insPet->bind_param('isss', $ownerId, $petName, $petSpecies, $petSpecies);
                $insPet->execute();
                $petId = (int)$insPet->insert_id;
                $insPet->close();
            }
        }

        $optimal = null;
        $candidateStart = $appointmentTime;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $cand = $engine->findOptimalSlotAndResources($service, $candidateStart, $preferredVetId, null, null, true);
            if ($engine->lockAndVerifySlotAvailable($cand['vet_id'], $cand['room_id'], $cand['appointment_time'], $cand['end_time'])) {
                $optimal = $cand;
                break;
            }
            $candidateStart = date('Y-m-d H:i:s', strtotime((string)$cand['appointment_time']) + 1800);
        }

        if ($optimal === null) {
            throw new RuntimeException('Selected appointment slot was just taken. Please pick another time.');
        }

        $assignedTime = (string)$optimal['appointment_time'];
        $assignedEnd = (string)$optimal['end_time'];
        $durationMins = (int)$optimal['duration_minutes'];
        $assignedVetId = (int)$optimal['vet_id'];
        $assignedRoomId = (int)$optimal['room_id'];

        $notes = $details;
        if (!empty($optimal['was_shifted'])) {
            $notes = trim($notes . " [Auto-Scheduler: Requested {$optimal['requested_time']} had a room/vet conflict or fell outside clinic hours; assigned next open slot {$assignedTime}]");
        }

        $stmt = $conn->prepare(
            "INSERT INTO schedule (Owner_ID, Pet_ID, Veterinarian_ID, Room_ID, Services, Appointment_Time, Duration_Minutes, End_Time, Notes, Status, Auto_Assigned)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 1)"
        );
        $stmt->bind_param(
            'iiiississ',
            $ownerId,
            $petId,
            $assignedVetId,
            $assignedRoomId,
            $service,
            $assignedTime,
            $durationMins,
            $assignedEnd,
            $notes
        );
        $stmt->execute();
        $scheduleId = (int)$stmt->insert_id;
        $stmt->close();

        if ($scheduleId > 0) {
            $chanKey = 'schedule_' . $scheduleId;
            $msgText = "Automated Scheduler confirmed Appointment #{$scheduleId} for {$petName} ({$service}) on {$assignedTime} ({$durationMins} mins) in {$optimal['room_name']} with Dr. {$optimal['vet_name']}.";
            $cStmt = $conn->prepare(
                "INSERT INTO chat_messages (Channel_Key, Schedule_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
                 VALUES (?, ?, 'system', 'Automated Scheduler', ?, 'system_alert')"
            );
            $cStmt->bind_param('sis', $chanKey, $scheduleId, $msgText);
            $cStmt->execute();
            $cStmt->close();
        }

        $conn->commit();
        $txStarted = false;

        hw_mobile_json_response(201, [
            'success' => true,
            'message' => 'Appointment booked successfully.',
            'booking' => [
                'schedule_id' => $scheduleId,
                'owner_id' => $ownerId,
                'pet_id' => $petId,
                'pet_name' => $petName,
                'service' => $service,
                'appointment_time' => $assignedTime,
                'end_time' => $assignedEnd,
                'duration_minutes' => $durationMins,
                'status' => 'Pending',
                'was_shifted' => (bool)($optimal['was_shifted'] ?? false),
                'requested_time' => (string)($optimal['requested_time'] ?? $appointmentTime),
                'veterinarian_id' => $assignedVetId,
                'veterinarian_name' => (string)($optimal['vet_name'] ?? ''),
                'veterinarian_specialty' => (string)($optimal['vet_specialty'] ?? ''),
                'room_id' => $assignedRoomId,
                'room_name' => (string)($optimal['room_name'] ?? ''),
                'notes' => $notes,
            ],
        ]);
    } catch (Throwable $e) {
        if ($txStarted) {
            @$conn->rollback();
        }
        $status = ($e instanceof RuntimeException) ? 409 : 500;
        hw_mobile_json_response($status, [
            'success' => false,
            'error' => 'booking_failed',
            'message' => ($e instanceof RuntimeException) ? $e->getMessage() : 'Could not complete booking. Please try again.',
        ]);
    } finally {
        if ($lockAcquired) {
            hw_db_release_lock($conn, 'hw_schedule_lock');
        }
        $conn->close();
    }
}

if ($action === 'emergency') {
    rate_limit_check('mobile_emergency', 10, 300);
    $data = hw_mobile_read_json();

    $details = trim((string)($data['details'] ?? ($data['additional_details'] ?? '')));
    $petId = isset($data['pet_id']) && $data['pet_id'] !== '' ? (int)$data['pet_id'] : null;
    $explicitSeverity = trim((string)($data['severity_level'] ?? 'Urgent'));
    if (!in_array($explicitSeverity, ['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'], true)) {
        $explicitSeverity = 'Urgent';
    }

    $ownerStmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE Owner_ID = ? AND Deleted_At IS NULL LIMIT 1');
    $ownerStmt->bind_param('i', $ownerId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();
    if (!$owner) {
        $conn->close();
        hw_mobile_json_response(404, [
            'success' => false,
            'error' => 'owner_not_found',
            'message' => 'Owner account not found.',
        ]);
    }

    $name = trim((string)$owner['First_Name'] . ' ' . (string)$owner['Last_Name']);
    $email = (string)($owner['email'] ?? '');
    $phone = (string)($owner['Contact'] ?? '');
    $ip = hw_get_client_ip();

    $lockAcquired = false;
    $txStarted = false;
    try {
        $lockAcquired = hw_db_acquire_lock($conn, 'hw_triage_lock', 5);
        if (!$lockAcquired) {
            hw_mobile_json_response(503, [
                'success' => false,
                'error' => 'triage_busy',
                'message' => 'Emergency triage queue is busy. Please retry immediately.',
            ]);
        }

        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Could not start triage transaction.');
        }
        $txStarted = true;

        $allowConcurrent = !empty($data['allow_concurrent']);
        if (!$allowConcurrent) {
            if ($petId !== null) {
                $dupStmt = $conn->prepare("SELECT Booking_ID FROM emergency_booking WHERE Owner_ID = ? AND Pet_ID = ? AND Deleted_At IS NULL AND Status IN ('Pending', 'In Progress') LIMIT 1 FOR UPDATE");
                $dupStmt->bind_param('ii', $ownerId, $petId);
            } else {
                $dupStmt = $conn->prepare("SELECT Booking_ID FROM emergency_booking WHERE Owner_ID = ? AND Deleted_At IS NULL AND Status IN ('Pending', 'In Progress') LIMIT 1 FOR UPDATE");
                $dupStmt->bind_param('i', $ownerId);
            }
            $dupStmt->execute();
            $dupExists = $dupStmt->get_result()->num_rows > 0;
            $dupStmt->close();
            if ($dupExists) {
                $conn->rollback();
                $txStarted = false;
                hw_mobile_json_response(409, [
                    'success' => false,
                    'error' => 'active_emergency_exists',
                    'message' => 'An active emergency request already exists for this owner/pet. Check active emergency triage status.',
                ]);
            }
        }

        $imagePath = null;
        $stmt = $conn->prepare(
            "INSERT INTO emergency_booking (Owner_ID, Pet_ID, Name, Email, Phone_Number, Additional_Details, Image_Path, Status, Severity_Level, IP_Address)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)"
        );
        $stmt->bind_param('iisssssss', $ownerId, $petId, $name, $email, $phone, $details, $imagePath, $explicitSeverity, $ip);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not persist emergency booking.');
        }
        $bookingId = (int)$stmt->insert_id;
        $stmt->close();

        $engine = new VetSchedulingEngine($conn);
        $triageResult = $engine->recalculateEmergencyTriage(false);

        $myAllocation = null;
        foreach (($triageResult['queue'] ?? []) as $qItem) {
            if ((int)($qItem['booking_id'] ?? 0) === $bookingId) {
                $myAllocation = $qItem;
                break;
            }
        }

        $chanKey = 'booking_' . $bookingId;
        $triageMsg = $myAllocation
            ? "Automated Triage Engine: Case #{$bookingId} classified as {$myAllocation['severity_level']} (Priority Score: {$myAllocation['priority_score']}/100, Queue Position #{$myAllocation['queue_position']}). {$myAllocation['rationale']}"
            : "Emergency request #{$bookingId} received and entered into triage.";
        $cStmt = $conn->prepare(
            "INSERT INTO chat_messages (Channel_Key, Booking_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
             VALUES (?, ?, 'system', 'Priority Triage Engine', ?, 'triage_notice')"
        );
        $cStmt->bind_param('sis', $chanKey, $bookingId, $triageMsg);
        $cStmt->execute();
        $cStmt->close();

        $conn->commit();
        $txStarted = false;

        hw_mobile_json_response(201, [
            'success' => true,
            'message' => $myAllocation
                ? "Emergency request #{$bookingId} triaged at Queue Position #{$myAllocation['queue_position']}."
                : 'Emergency request submitted and entered into triage.',
            'emergency' => [
                'booking_id' => $bookingId,
                'owner_id' => $ownerId,
                'pet_id' => $petId,
                'status' => 'Pending',
                'severity_level' => $explicitSeverity,
                'triage' => $myAllocation,
                'preempted_schedules' => $triageResult['preempted_schedules'] ?? [],
            ],
        ]);
    } catch (Throwable $e) {
        if ($txStarted) {
            @$conn->rollback();
        }
        hw_mobile_json_response(500, [
            'success' => false,
            'error' => 'emergency_submission_failed',
            'message' => 'Could not submit emergency request. Please try again.',
        ]);
    } finally {
        if ($lockAcquired) {
            hw_db_release_lock($conn, 'hw_triage_lock');
        }
        $conn->close();
    }
}
