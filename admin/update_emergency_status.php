<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? $data['status'] : '';
$reason = trim($data['reason'] ?? '');
$allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

if (!$id || !in_array($status, $allowed, true)) {
    echo json_encode(['success'=> false, 'error'=>'Invalid id or status']);
    exit;
}

if ($status === 'Cancelled'&& $reason === '') {
    echo json_encode(['success'=> false, 'error'=>'A reason is required when cancelling a request.']);
    exit;
}

$conn->query("DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

$isResolved = in_array($status, ['Completed', 'Cancelled'], true);

if ($status === 'Cancelled') {
    $stmt = $conn->prepare(
        "UPDATE emergency_booking
         SET Status = ?, Cancel_Reason = ?, Cancel_Requested = 0, Resolved_At = NOW(), Client_Notified = 0, Queue_Position = NULL
         WHERE Booking_ID = ?"
    );
    $stmt->bind_param("ssi", $status, $reason, $id);
} elseif ($isResolved) {
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ?, Resolved_At = NOW(), Queue_Position = NULL WHERE Booking_ID = ?");
    $stmt->bind_param("si", $status, $id);
} else {
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ? WHERE Booking_ID = ?");
    $stmt->bind_param("si", $status, $id);
}

$ok = $stmt->execute();
$stmt->close();

if ($isResolved) {
    $relRoom = $conn->prepare("UPDATE room SET Status = 'idle', Current_Emergency_ID = NULL WHERE Current_Emergency_ID = ?");
    $relRoom->bind_param('i', $id);
    $relRoom->execute();
    $relRoom->close();
}

$engine = new VetSchedulingEngine($conn);
$triageSummary = $engine->recalculateEmergencyTriage();

echo json_encode(['success'=>$ok, 'triage_summary'=>$triageSummary]);
$conn->close();
