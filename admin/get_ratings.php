<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}
// Example: adjust table/column names as needed
$sql = "SELECT r.Rating_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, r.Rating, r.Comment, r.Service, r.Created_At FROM ratings r LEFT JOIN owner o ON r.Owner_ID = o.Owner_ID ORDER BY r.Created_At DESC";
$result = $conn->query($sql);
$ratings = [];
if ($result && $result->num_rows >0) {
    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }
}
echo json_encode(['success'=> true, 'ratings'=>$ratings]);
$conn->close();
