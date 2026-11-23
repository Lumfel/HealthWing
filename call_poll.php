<?php
require_once __DIR__ . '/call_common.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$role = call_current_role();
if (!$role) {
    call_fail('You must be signed in.', 401);
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$callId = isset($_GET['call_id']) ? (int)$_GET['call_id'] : 0;
$sinceCandidateId = isset($_GET['since_candidate_id']) ? (int)$_GET['since_candidate_id'] : 0;

if (!$bookingId) {
    call_fail('Missing booking id.');
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

$booking = call_load_authorized_booking($conn, $bookingId, $role, $ownerId);
if (!$booking) {
    $conn->close();
    call_fail('Emergency booking not found.', 404);
}

$call = null;

if ($callId) {
    $call = call_load_authorized_session($conn, $callId, $role, $ownerId, $vetId);
} else {
    $otherRole = $role === 'owner' ? 'vet' : 'owner';
    $stmt = $conn->prepare(
        "SELECT * FROM call_session WHERE Booking_ID = ? AND Initiated_By = ? AND Status = 'ringing' ORDER BY Call_ID DESC LIMIT 1"
    );
    $stmt->bind_param('is', $bookingId, $otherRole);
    $stmt->execute();
    $call = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$response = ['success' => true, 'call' => null, 'new_candidates' => []];

if ($call) {
    $otherRole = $role === 'owner' ? 'vet' : 'owner';
    $candStmt = $conn->prepare(
        "SELECT Candidate_ID, Candidate FROM call_ice_candidate WHERE Call_ID = ? AND Sender = ? AND Candidate_ID > ? ORDER BY Candidate_ID ASC"
    );
    $candStmt->bind_param('isi', $call['Call_ID'], $otherRole, $sinceCandidateId);
    $candStmt->execute();
    $candidates = $candStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $candStmt->close();

    $response['call'] = [
        'Call_ID' => (int)$call['Call_ID'],
        'Call_Type' => $call['Call_Type'],
        'Initiated_By' => $call['Initiated_By'],
        'Status' => $call['Status'],
        'Offer_SDP' => $call['Offer_SDP'],
        'Answer_SDP' => $call['Answer_SDP'],
        'call_id' => (int)$call['Call_ID'],
        'call_type' => $call['Call_Type'],
        'initiated_by' => $call['Initiated_By'],
        'status' => $call['Status'],
        'offer_sdp' => $call['Offer_SDP'],
        'answer_sdp' => $call['Answer_SDP'],
    ];
    $response['new_candidates'] = $candidates;
}

$conn->close();
echo json_encode($response);
