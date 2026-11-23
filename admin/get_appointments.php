<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}
$sql = "SELECT s.Schedule_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, p.Pet_Name, s.Services, s.Appointment_Time, s.Status, s.Last_Updated_At FROM schedule s LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID ORDER BY s.Appointment_Time DESC";
$result = $conn->query($sql);
$appointments = [];
if ($result && $result->num_rows >0) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
echo json_encode(['success'=> true, 'appointments'=>$appointments]);
$conn->close();
