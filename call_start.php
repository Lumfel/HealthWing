<?php
require_once __DIR__ . '/call_common.php';
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('call_start', 20, 60);

header('Content-Type: application/json');

$role = call_current_role();
if (!$role) {
    call_fail('You must be signed in to start a call.', 401);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$bookingId = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$callType = ($data['call_type'] ?? 'video') === 'audio'? 'audio': 'video';
$offerSdp = trim($data['sdp_offer'] ?? '');

if (!$bookingId || $offerSdp === '') {
    call_fail('Missing booking id or call offer.');
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
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

if (in_array($booking['Status'], ['Completed', 'Cancelled'], true)) {
    $conn->close();
    call_fail('This emergency has already been resolved, so calling is no longer available.');
}

// The owner can only call if a vet has explicitly enabled the feature for
// this specific emergency. The vet is never blocked from calling the owner.
if ($role === 'owner'&& (int)$booking['Call_Enabled'] !== 1) {
    $conn->close();
    call_fail('The call feature has not been enabled for this emergency yet.');
}

// If a case doesn't have an Owner_ID (a guest submitted it without an
// account), there is no logged-in owner session to ring — the vet should
// use the phone number on the booking instead.
if ($role === 'vet'&& $booking['Owner_ID'] === null) {
    $conn->close();
    call_fail('This request has no linked account to call — use the phone number on file instead.');
}

// Reuse an existing ringing/active call for this booking rather than
// stacking up duplicates if someone double-clicks "Call".
$existing = $conn->prepare(
    "SELECT Call_ID FROM call_session WHERE Booking_ID = ? AND Status IN ('ringing','active') ORDER BY Call_ID DESC LIMIT 1"
);
$existing->bind_param('i', $bookingId);
$existing->execute();
$existingRow = $existing->get_result()->fetch_assoc();
$existing->close();

if ($existingRow) {
    $conn->close();
    echo json_encode(['success'=> true, 'call_id'=>(int)$existingRow['Call_ID'], 'reused'=> true]);
    exit();
}

$ownerIdForRow = $ownerId ?? (int)$booking['Owner_ID'];

$stmt = $conn->prepare(
    "INSERT INTO call_session (Booking_ID, Call_Type, Initiated_By, Veterinarian_ID, Owner_ID, Status, Offer_SDP)
     VALUES (?, ?, ?, ?, ?, 'ringing', ?)"
);
$stmt->bind_param('issiis', $bookingId, $callType, $role, $vetId, $ownerIdForRow, $offerSdp);
$ok = $stmt->execute();
$callId = $stmt->insert_id;
$stmt->close();
$conn->close();

rate_limit_log_event('call_initiated_'. $callType, 'allowed');

if ($ok) {
    echo json_encode(['success'=> true, 'call_id'=>$callId]);
} else {
    call_fail('Could not start the call. Please try again.', 500);
}
