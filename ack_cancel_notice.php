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

$stmt = $conn->prepare('UPDATE emergency_booking SET Client_Notified = 1 WHERE Booking_ID = ? AND Owner_ID = ?');
$stmt->bind_param('ii', $bookingId, $ownerId);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => true]);
