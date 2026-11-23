<?php
/**
 * HealthWing / VetSystem — Veterinarian Emergency Status Update + Queue Re-Evaluation (update_status.php)
 *
 * Updates an emergency booking's status and automatically triggers
 * VetSchedulingEngine::recalculateEmergencyTriage() so freed rooms and vets are
 * immediately reassigned to the next highest-priority waiting emergency.
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';

if (!$isVet && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'Not logged in as a veterinarian or admin.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=> false, 'message'=>'Invalid request method.']);
    exit();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['booking_id'], $data['status'])) {
    echo json_encode(['success'=> false, 'message'=>'Invalid input.']);
    exit();
}

$bookingId = (int)$data['booking_id'];
$status = $data['status'];
$reason = trim($data['reason'] ?? '');
$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success'=> false, 'message'=>'Invalid status.']);
    exit();
}

if ($status === 'Cancelled'&& $reason === '') {
    echo json_encode(['success'=> false, 'message'=>'A reason is required when cancelling a request.']);
    exit();
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

$conn->query("DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

$isResolved = in_array($status, ['Completed', 'Cancelled'], true);

if ($status === 'Cancelled') {
    $stmt = $conn->prepare(
        "UPDATE emergency_booking
         SET Status = ?, Cancel_Reason = ?, Cancel_Requested = 0, Resolved_At = NOW(), Client_Notified = 0, Queue_Position = NULL
         WHERE Booking_ID = ?"
    );
    $stmt->bind_param("ssi", $status, $reason, $bookingId);
} elseif ($isResolved) {
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ?, Resolved_At = NOW(), Queue_Position = NULL WHERE Booking_ID = ?");
    $stmt->bind_param("si", $status, $bookingId);
} else {
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ? WHERE Booking_ID = ?");
    $stmt->bind_param("si", $status, $bookingId);
}

$ok = $stmt->execute();
$stmt->close();

// Release any room tied to this emergency if resolved, then re-run triage queue so next pet moves up
if ($isResolved) {
    $relRoom = $conn->prepare("UPDATE room SET Status = 'idle', Current_Emergency_ID = NULL WHERE Current_Emergency_ID = ?");
    $relRoom->bind_param('i', $bookingId);
    $relRoom->execute();
    $relRoom->close();
}

$engine = new VetSchedulingEngine($conn);
$triageSummary = $engine->recalculateEmergencyTriage();

echo json_encode([
    'success'=>$ok,
    'triage_summary'=>$triageSummary,
]);

$conn->close();
