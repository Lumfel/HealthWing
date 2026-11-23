<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');

if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}

// Get service utilization counts
$sql = "SELECT 
    Services,
    COUNT(*) as total_appointments,
    COUNT(CASE WHEN Status = 'Completed'THEN 1 END) as completed_appointments,
    COUNT(CASE WHEN Status = 'Pending'THEN 1 END) as pending_appointments,
    COUNT(CASE WHEN Status = 'Cancelled'THEN 1 END) as cancelled_appointments,
    DATE_FORMAT(Appointment_Time, '%Y-%m') as month
FROM schedule 
GROUP BY Services, DATE_FORMAT(Appointment_Time, '%Y-%m')
ORDER BY month DESC, Services";

$result = $conn->query($sql);
$services = [];

if ($result && $result->num_rows >0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Get average rating per service
$sql_ratings = "SELECT 
    s.Services,
    ROUND(AVG(r.Rating), 2) as average_rating,
    COUNT(r.Rating) as total_ratings
FROM schedule s
LEFT JOIN ratings r ON s.Schedule_ID = r.Schedule_ID
GROUP BY s.Services";

$result_ratings = $conn->query($sql_ratings);
$ratings = [];

if ($result_ratings && $result_ratings->num_rows >0) {
    while ($row = $result_ratings->fetch_assoc()) {
        $ratings[$row['Services']] = [
            'average_rating'=>$row['average_rating'],
            'total_ratings'=>$row['total_ratings']
        ];
    }
}

echo json_encode([
    'success'=> true, 
    'services'=>$services,
    'ratings'=>$ratings
]);

$conn->close();
