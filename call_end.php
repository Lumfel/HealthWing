<?php
require_once __DIR__ . '/call_common.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$role = call_current_role();
if (!$role) {
    call_fail('You must be signed in.', 401);
}

hw_require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$callId = isset($data['call_id']) ? (int)$data['call_id'] : 0;
$reason = in_array($data['reason'] ?? '', ['ended', 'declined', 'missed'], true) ? $data['reason'] : 'ended';

if (!$callId) {
    call_fail('Missing call id.');
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    call_fail('Database connection failed.', 500);
}

$ownerId = null;
$vetId = null;
if ($role === 'owner') {
    $ownerId = call_lookup_owner_id($conn, $_SESSION['username']);
} else {
    $vetId = call_lookup_vet_id($conn, $_SESSION['username']);
}

$session = call_load_authorized_session($conn, $callId, $role, $ownerId, $vetId);
if (!$session) {
    $conn->close();
    call_fail('Call not found.', 404);
}

if (in_array($session['Status'], ['ended', 'declined', 'missed'], true)) {
    $conn->close();
    echo json_encode(['success' => true, 'already_closed' => true]);
    exit();
}

$status = $reason === 'declined' ? 'declined' : ($reason === 'missed' ? 'missed' : 'ended');

$stmt = $conn->prepare("UPDATE call_session SET Status = ?, Ended_At = NOW() WHERE Call_ID = ?");
$stmt->bind_param('si', $status, $callId);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

rate_limit_log_event('call_' . $status, 'allowed');

echo json_encode(['success' => $ok]);
