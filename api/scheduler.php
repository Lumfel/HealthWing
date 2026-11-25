<?php
/**
 * HealthWing / VetSystem — Automated Scheduling API (api/scheduler.php)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

hw_start_secure_session();
header('Content-Type: application/json');

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'preview_slot');

    if ($action === 'preview_slot') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;
        $service = trim((string)($input['service'] ?? 'Wellness Care'));
        $requestedTime = trim((string)($input['appointment_time'] ?? date('Y-m-d H:i:s', time() + 3600)));
        $preferredVetId = !empty($input['vet_id']) ? (int)$input['vet_id'] : null;
        $preferredRoomId = !empty($input['room_id']) ? (int)$input['room_id'] : null;

        $slot = $engine->findOptimalSlotAndResources($service, $requestedTime, $preferredVetId, $preferredRoomId);
        echo json_encode($slot);
        $conn->close();
        exit;
    }

    if ($action === 'resources') {
        hw_require_role(['owner', 'vet_staff', 'head_vet'], true);

        $vetsRes = $conn->query(
            "SELECT Veterinarian_ID, First_Name, Last_Name, Specialties, Role_Level, PRC_License_No, Status
             FROM veterinarian
             WHERE Deleted_At IS NULL AND COALESCE(Status, 'Approved') = 'Approved'
             ORDER BY First_Name ASC"
        );
        $roomsRes = $conn->query(
            "SELECT Room_ID, Room_Name, Room_Type, Status, Current_Emergency_ID, Current_Schedule_ID
             FROM room
             ORDER BY Room_ID ASC"
        );

        $owners = [];
        $pets = [];
        if (hw_is_vet_role()) {
            $ownersRes = $conn->query(
                "SELECT Owner_ID, First_Name, Last_Name, email, Contact
                 FROM owner
                 WHERE Deleted_At IS NULL
                 ORDER BY First_Name ASC"
            );
            $petsRes = $conn->query(
                "SELECT Pet_ID, Owner_ID, Pet_Name, Pet_Type, Species, Age
                 FROM pet
                 WHERE Deleted_At IS NULL
                 ORDER BY Pet_Name ASC"
            );
            $owners = $ownersRes ? $ownersRes->fetch_all(MYSQLI_ASSOC) : [];
            $pets = $petsRes ? $petsRes->fetch_all(MYSQLI_ASSOC) : [];
        }

        echo json_encode([
            'success' => true,
            'vets' => $vetsRes ? $vetsRes->fetch_all(MYSQLI_ASSOC) : [],
            'rooms' => $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [],
            'owners' => $owners,
            'pets' => $pets,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'auto_assign_all') {
        hw_require_role(['vet_staff', 'head_vet'], true);
        hw_require_csrf();

        $lockAcquired = false;
        $txStarted = false;
        try {
            $lockAcquired = hw_db_acquire_lock($conn, 'hw_schedule_lock', 5);
            if (!$lockAcquired) {
                http_response_code(503);
                echo json_encode(['success' => false, 'error' => 'Scheduler busy. Please retry.']);
                exit;
            }
            $conn->begin_transaction();
            $txStarted = true;

            $res = $conn->query(
                "SELECT * FROM schedule
                 WHERE Deleted_At IS NULL
                   AND Status IN ('Pending', 'Confirmed')
                   AND (Veterinarian_ID IS NULL OR Room_ID IS NULL OR End_Time IS NULL)
                 ORDER BY Appointment_Time ASC
                 FOR UPDATE"
            );
            $unassigned = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $updatedCount = 0;
            $assignments = [];

            foreach ($unassigned as $row) {
                $sId = (int)$row['Schedule_ID'];
                $opt = $engine->findOptimalSlotAndResources(
                    $row['Services'] ?: 'Wellness Care',
                    $row['Appointment_Time'],
                    !empty($row['Veterinarian_ID']) ? (int)$row['Veterinarian_ID'] : null,
                    !empty($row['Room_ID']) ? (int)$row['Room_ID'] : null,
                    $sId,
                    true
                );

                $stmt = $conn->prepare(
                    "UPDATE schedule
                     SET Appointment_Time = ?,
                         End_Time = ?,
                         Duration_Minutes = ?,
                         Veterinarian_ID = ?,
                         Room_ID = ?,
                         Auto_Assigned = 1
                     WHERE Schedule_ID = ?"
                );
                $stmt->bind_param(
                    'ssiiii',
                    $opt['appointment_time'],
                    $opt['end_time'],
                    $opt['duration_minutes'],
                    $opt['vet_id'],
                    $opt['room_id'],
                    $sId
                );
                if ($stmt->execute()) {
                    $updatedCount++;
                    $assignments[] = [
                        'schedule_id' => $sId,
                        'service' => $row['Services'],
                        'appointment_time' => $opt['appointment_time'],
                        'vet_name' => $opt['vet_name'],
                        'room_name' => $opt['room_name'],
                        'was_shifted' => $opt['was_shifted'],
                    ];
                }
                $stmt->close();
            }

            $conn->commit();
            $txStarted = false;

            echo json_encode([
                'success' => true,
                'updated_count' => $updatedCount,
                'assignments' => $assignments,
            ]);
        } catch (Throwable $txErr) {
            if ($txStarted) {
                @$conn->rollback();
            }
            throw $txErr;
        } finally {
            if ($lockAcquired) {
                hw_db_release_lock($conn, 'hw_schedule_lock');
            }
        }
        $conn->close();
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Scheduling service error.']);
}
