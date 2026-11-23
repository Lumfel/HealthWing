<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$sql = "SELECT r.Rating_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, r.Rating, r.Comment, r.Service, r.Created_At
        FROM ratings r
        LEFT JOIN owner o ON r.Owner_ID = o.Owner_ID
        ORDER BY r.Created_At DESC";
$result = $conn->query($sql);
$ratings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success' => true, 'ratings' => $ratings]);
$conn->close();
