<?php
require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'Veterinarian') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in as a veterinarian.']);
    exit();
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$sinceId = (int)($_GET['sinceId'] ?? 0);

$stmt = $conn->prepare(
    "SELECT Booking_ID, Name, Additional_Details, Created_At
     FROM emergency_booking
     WHERE Booking_ID > ?
     ORDER BY Booking_ID DESC"
);
$stmt->bind_param('i', $sinceId);
$stmt->execute();
$newBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'new_bookings' => $newBookings]);
