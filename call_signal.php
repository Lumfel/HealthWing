<?php
require_once __DIR__ . '/call_common.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/includes/db.php';
rate_limit_check('call_signal', 120, 60);

header('Content-Type: application/json');

$role = call_current_role();
if (!$role) {
    call_fail('You must be signed in.', 401);
}

hw_require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';
$callId = isset($data['call_id']) ? (int)$data['call_id'] : 0;

if (!$callId || !in_array($action, ['answer', 'ice'], true)) {
    call_fail('Missing or invalid parameters.');
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

if ($action === 'answer') {
    $answerSdp = trim((string)($data['sdp_answer'] ?? ''));
    if ($answerSdp === '') {
        $conn->close();
        call_fail('Missing SDP answer.');
    }
    if ($session['Status'] !== 'ringing') {
        $conn->close();
        call_fail('This call is no longer ringing.');
    }

    if ($role === 'vet' && $session['Veterinarian_ID'] === null) {
        $stmt = $conn->prepare(
            "UPDATE call_session SET Answer_SDP = ?, Status = 'active', Answered_At = NOW(), Veterinarian_ID = ? WHERE Call_ID = ?"
        );
        $stmt->bind_param('sii', $answerSdp, $vetId, $callId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE call_session SET Answer_SDP = ?, Status = 'active', Answered_At = NOW() WHERE Call_ID = ?"
        );
        $stmt->bind_param('si', $answerSdp, $callId);
    }
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    rate_limit_log_event('call_answered', 'allowed');
    echo json_encode(['success' => $ok]);
    exit();
}

if ($action === 'ice') {
    $candidate = trim((string)($data['candidate'] ?? ''));
    if ($candidate === '') {
        $conn->close();
        call_fail('Missing candidate.');
    }
    $stmt = $conn->prepare("INSERT INTO call_ice_candidate (Call_ID, Sender, Candidate) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $callId, $role, $candidate);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => $ok]);
    exit();
}
