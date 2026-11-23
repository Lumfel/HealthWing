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

$stmt = $conn->prepare(
    "SELECT Booking_ID, Cancel_Reason
     FROM emergency_booking
     WHERE Owner_ID = ? AND Status = 'Cancelled'AND Cancel_Reason IS NOT NULL AND Client_Notified = 0
     ORDER BY Resolved_At DESC LIMIT 1"
);
$stmt->bind_param('i', $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$notice = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode(['success'=> true, 'notice'=>$notice ?: null]);
