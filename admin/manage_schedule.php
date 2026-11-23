<?php
/**
 * HealthWing / VetSystem — Upgraded Admin Schedule Management API (admin/manage_schedule.php)
 *
 * Integrates VetSchedulingEngine so Admin schedule creation/updates automatically calculate
 * End_Time, Duration_Minutes, and optimal Veterinarian/Room assignments when Auto-Assign is enabled.
 */
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $action = $_GET['action'] ?? '';

    if ($action === 'read') {
        $sql = "SELECT s.Schedule_ID, s.Owner_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName,
                       s.Pet_ID, p.Pet_Name, s.Veterinarian_ID, v.First_Name AS VetFirstName, v.Last_Name AS VetLastName,
                       s.Room_ID, r.Room_Name,
                       s.Services, s.Appointment_Time, s.Duration_Minutes, s.End_Time, s.Status,
                       s.Auto_Assigned, s.Preempted_By_Emergency_ID
                FROM schedule s
                LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID
                LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
                LEFT JOIN veterinarian v ON s.Veterinarian_ID = v.Veterinarian_ID
                LEFT JOIN room r ON s.Room_ID = r.Room_ID
                ORDER BY s.Appointment_Time DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception($conn->error);
        }
        echo json_encode(['success'=> true, 'schedules'=>$result->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $ownerId = (int)($data['ownerId'] ?? 0);
        $petId = !empty($data['petId']) ? (int)$data['petId'] : null;
        $vetId = !empty($data['vetId']) ? (int)$data['vetId'] : null;
        $roomId = !empty($data['roomId']) ? (int)$data['roomId'] : null;
        $services = trim($data['services'] ?? '');
        $time = trim($data['appointmentTime'] ?? '');
        $autoAssign = !empty($data['autoAssign']) || !$vetId || !$roomId;

        if (!$ownerId || !$services || !$time) {
            throw new Exception("Owner, service, and appointment time are required.");
        }

        if ($autoAssign) {
            $opt = $engine->findOptimalSlotAndResources($services, $time, $vetId, $roomId);
            $time = $opt['appointment_time'];
            $endTime = $opt['end_time'];
            $duration = $opt['duration_minutes'];
            $vetId = $opt['vet_id'];
            $roomId = $opt['room_id'];
        } else {
            $profile = $engine->getServiceProfile($services);
            $duration = (int)$profile['duration'];
            $ts = strtotime($time) ?: time();
            $time = date('Y-m-d H:i:s', $ts);
            $endTime = date('Y-m-d H:i:s', $ts + ($duration * 60));
        }

        $autoFlag = $autoAssign ? 1 : 0;
        $stmt = $conn->prepare(
            "INSERT INTO schedule (Owner_ID, Pet_ID, Veterinarian_ID, Room_ID, Services, Appointment_Time, Duration_Minutes, End_Time, Auto_Assigned)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiiissisi", $ownerId, $petId, $vetId, $roomId, $services, $time, $duration, $endTime, $autoFlag);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        echo json_encode(['success'=> true, 'assigned_time'=>$time]);
    } elseif ($action === 'update') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['scheduleId'] ?? 0);
        $ownerId = (int)($data['ownerId'] ?? 0);
        $petId = !empty($data['petId']) ? (int)$data['petId'] : null;
        $vetId = !empty($data['vetId']) ? (int)$data['vetId'] : null;
        $roomId = !empty($data['roomId']) ? (int)$data['roomId'] : null;
        $services = trim($data['services'] ?? '');
        $time = trim($data['appointmentTime'] ?? '');
        $status = trim($data['status'] ?? 'Pending');

        if (!$id) {
            throw new Exception("Missing schedule ID");
        }

        $profile = $engine->getServiceProfile($services);
        $duration = (int)$profile['duration'];
        $ts = strtotime($time) ?: time();
        $time = date('Y-m-d H:i:s', $ts);
        $endTime = date('Y-m-d H:i:s', $ts + ($duration * 60));

        $stmt = $conn->prepare(
            "UPDATE schedule
             SET Owner_ID=?, Pet_ID=?, Veterinarian_ID=?, Room_ID=?, Services=?, Appointment_Time=?, Duration_Minutes=?, End_Time=?, Status=?
             WHERE Schedule_ID=?"
        );
        $stmt->bind_param("iiiississi", $ownerId, $petId, $vetId, $roomId, $services, $time, $duration, $endTime, $status, $id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        echo json_encode(['success'=> true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['scheduleId'] ?? 0);
        if (!$id) {
            throw new Exception("Missing schedule ID");
        }
        $stmt = $conn->prepare("DELETE FROM schedule WHERE Schedule_ID=?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        echo json_encode(['success'=> true]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}

$conn->close();
