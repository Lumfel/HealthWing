<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}

// Most active owners by number of appointments booked
$sql = "SELECT o.Owner_ID, o.First_Name, o.Last_Name, o.email,
               COUNT(s.Schedule_ID) as total_bookings,
               MAX(s.Appointment_Time) as last_booking
        FROM owner o
        LEFT JOIN schedule s ON o.Owner_ID = s.Owner_ID
        GROUP BY o.Owner_ID
        HAVING total_bookings >0
        ORDER BY total_bookings DESC, last_booking DESC
        LIMIT 25";
$result = $conn->query($sql);
$activeOwners = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Newest registered owners
$sql2 = "SELECT Owner_ID, First_Name, Last_Name, email, TimeAndDateAdded
         FROM owner ORDER BY TimeAndDateAdded DESC LIMIT 10";
$result2 = $conn->query($sql2);
$newOwners = $result2 ? $result2->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode([
    'success'=> true,
    'active_owners'=>$activeOwners,
    'new_owners'=>$newOwners
]);
$conn->close();
