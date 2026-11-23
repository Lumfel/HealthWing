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

$sql = "SELECT s.Schedule_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, p.Pet_Name, s.Services, s.Appointment_Time, s.Status, s.Last_Updated_At
        FROM schedule s
        LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID
        LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
        ORDER BY s.Appointment_Time DESC";
$result = $conn->query($sql);
$appointments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success' => true, 'appointments' => $appointments]);
$conn->close();
