<?php
require_once __DIR__ . '/call_common.php';
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('toggle_call_feature', 30, 60);

header('Content-Type: application/json');

$role = call_current_role();
if ($role !== 'vet') {
    call_fail('Only a veterinarian can enable the call feature.', 401);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$bookingId = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$enabled = !empty($data['enabled']) ? 1 : 0;

if (!$bookingId) {
    call_fail('Missing booking id.');
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    call_fail('Database connection failed.', 500);
}

$vetId = call_lookup_vet_id($conn, $_SESSION['username']);

$booking = call_load_authorized_booking($conn, $bookingId, $role, null);
if (!$booking) {
    $conn->close();
    call_fail('Emergency booking not found.', 404);
}

if (in_array($booking['Status'], ['Completed', 'Cancelled'], true)) {
    $conn->close();
    call_fail('This emergency has already been resolved.');
}

$stmt = $conn->prepare(
    "UPDATE emergency_booking SET Call_Enabled = ?, Call_Enabled_By = ?, Call_Enabled_At = NOW() WHERE Booking_ID = ?"
);
$stmt->bind_param('iii', $enabled, $vetId, $bookingId);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

// Log this to the Network Monitor's traffic_log, same table used for
// login/signup/emergency-submission events.
rate_limit_log_event($enabled ? 'call_feature_enabled': 'call_feature_disabled', 'allowed');

echo json_encode(['success'=>$ok, 'enabled'=>(bool)$enabled]);
