<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'read');

    if (in_array($action, ['create', 'update', 'delete'], true)) {
        hw_require_csrf();
    }

    if ($action === 'read') {
        $result = $conn->query(
            "SELECT r.Room_ID, r.Room_Name, r.Room_Type, r.Status, r.Current_Emergency_ID, r.Current_Schedule_ID, r.TimeAndDateAdded
             FROM room r
             ORDER BY r.Room_ID ASC"
        );
        if (!$result) {
            throw new Exception("Unable to load rooms.");
        }
        echo json_encode(['success' => true, 'rooms' => $result->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $name = trim((string)($data['name'] ?? ''));
        $roomType = trim((string)($data['roomType'] ?? 'General'));
        $status = trim((string)($data['status'] ?? 'idle'));
        if ($name === '') {
            throw new Exception("Room name is required");
        }
        $stmt = $conn->prepare("INSERT INTO room (Room_Name, Room_Type, Status) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $roomType, $status);
        if (!$stmt->execute()) {
            throw new Exception("Could not create room.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'update') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['id'] ?? 0);
        $status = trim((string)($data['status'] ?? ''));
        $roomType = isset($data['roomType']) ? trim((string)$data['roomType']) : null;
        if (!$id || $status === '') {
            throw new Exception("Missing id or status");
        }
        if ($roomType !== null && $roomType !== '') {
            $stmt = $conn->prepare("UPDATE room SET Status = ?, Room_Type = ? WHERE Room_ID = ?");
            $stmt->bind_param("ssi", $status, $roomType, $id);
        } else {
            $stmt = $conn->prepare("UPDATE room SET Status = ? WHERE Room_ID = ?");
            $stmt->bind_param("si", $status, $id);
        }
        if (!$stmt->execute()) {
            throw new Exception("Could not update room.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception("Missing id");
        }
        $stmt = $conn->prepare("DELETE FROM room WHERE Room_ID = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Could not delete room.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Invalid action");
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
