<?php
require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

hw_require_csrf();

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Owner not found.']);
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = (int)($data['bookingId'] ?? 0);
$mode = $data['mode'] ?? '';

if (!$bookingId || !in_array($mode, ['direct', 'request'], true)) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$stmt = $conn->prepare('SELECT Booking_ID, Created_At, Status FROM emergency_booking WHERE Booking_ID = ? AND Owner_ID = ? LIMIT 1');
$stmt->bind_param('ii', $bookingId, $ownerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $conn->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'That booking does not belong to your account.']);
    exit();
}

if (in_array($booking['Status'], ['Completed', 'Cancelled'], true)) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'This request has already been resolved.']);
    exit();
}

$minutesElapsed = (time() - strtotime((string)$booking['Created_At'])) / 60;

if ($mode === 'direct') {
    if ($minutesElapsed >= 10) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'The 10-minute window has passed. Please use Request Cancellation instead.']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE emergency_booking SET Status = 'Cancelled', Resolved_At = NOW() WHERE Booking_ID = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Your emergency request has been cancelled.']);
} else {
    $stmt = $conn->prepare("UPDATE emergency_booking SET Cancel_Requested = 1 WHERE Booking_ID = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Your cancellation request has been sent for review.']);
}

$conn->close();
