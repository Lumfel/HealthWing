<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'Not logged in.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ?');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerResult = $stmt->get_result();
$ownerRow = $ownerResult->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    echo json_encode(['success'=> false, 'message'=>'Owner not found.']);
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = (int)($data['bookingId'] ?? 0);
$mode = $data['mode'] ?? ''; // 'direct'or 'request'

if (!$bookingId || !in_array($mode, ['direct', 'request'], true)) {
    echo json_encode(['success'=> false, 'message'=>'Invalid request.']);
    exit();
}

// Verify this booking actually belongs to the logged-in owner before doing
// anything to it — otherwise anyone could cancel someone else's emergency
// request just by guessing a booking ID.
$stmt = $conn->prepare('SELECT Booking_ID, Created_At, Status FROM emergency_booking WHERE Booking_ID = ? AND Owner_ID = ?');
$stmt->bind_param('ii', $bookingId, $ownerId);
$stmt->execute();
$bookingResult = $stmt->get_result();
$booking = $bookingResult->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(403);
    echo json_encode(['success'=> false, 'message'=>'That booking does not belong to your account.']);
    exit();
}

if (in_array($booking['Status'], ['Completed', 'Cancelled'], true)) {
    echo json_encode(['success'=> false, 'message'=>'This request has already been resolved.']);
    exit();
}

$minutesElapsed = (time() - strtotime($booking['Created_At'])) / 60;

if ($mode === 'direct') {
    // Only allowed within the 10-minute grace period — re-checked here
    // server-side, since the button shown client-side isn't a security
    // boundary on its own.
    if ($minutesElapsed >= 10) {
        echo json_encode(['success'=> false, 'message'=>'The 10-minute window has passed. Please use "Request Cancellation"instead.']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = 'Cancelled', Resolved_At = NOW() WHERE Booking_ID = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success'=> true, 'message'=>'Your emergency request has been cancelled.']);
} else {
    // After the grace period, cancelling requires vet/admin review instead
    // of happening immediately — they may already be responding.
    $stmt = $conn->prepare("UPDATE emergency_booking SET Cancel_Requested = 1 WHERE Booking_ID = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success'=> true, 'message'=>'Your cancellation request has been sent for review.']);
}

$conn->close();
