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
$scheduleId = (int)($data['scheduleId'] ?? 0);
$rating = (int)($data['rating'] ?? 0);
$comment = trim((string)($data['comment'] ?? ''));

if (!$scheduleId || $rating < 1 || $rating > 5) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Invalid rating.']);
    exit();
}

$stmt = $conn->prepare(
    "SELECT s.Schedule_ID, s.Services, r.Rating_ID
     FROM schedule s
     LEFT JOIN ratings r ON r.Schedule_ID = s.Schedule_ID
     WHERE s.Schedule_ID = ? AND s.Owner_ID = ? AND s.Status = 'Completed'"
);
$stmt->bind_param('ii', $scheduleId, $ownerId);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'That appointment could not be found, does not belong to you, or is not completed yet.']);
    exit();
}
if (!empty($appointment['Rating_ID'])) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'You have already rated this appointment.']);
    exit();
}

$service = $appointment['Services'];
$stmt = $conn->prepare(
    "INSERT INTO ratings (Owner_ID, Schedule_ID, Service, Rating, Comment) VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param('iisis', $ownerId, $scheduleId, $service, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not save your rating. Please try again.']);
}

$stmt->close();
$conn->close();
