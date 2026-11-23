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

$sql = "SELECT
    Services,
    COUNT(*) AS total_appointments,
    COUNT(CASE WHEN Status = 'Completed' THEN 1 END) AS completed_appointments,
    COUNT(CASE WHEN Status = 'Pending' THEN 1 END) AS pending_appointments,
    COUNT(CASE WHEN Status = 'Cancelled' THEN 1 END) AS cancelled_appointments,
    DATE_FORMAT(Appointment_Time, '%Y-%m') AS month
FROM schedule
GROUP BY Services, DATE_FORMAT(Appointment_Time, '%Y-%m')
ORDER BY month DESC, Services";

$result = $conn->query($sql);
$services = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$sql_ratings = "SELECT
    s.Services,
    ROUND(AVG(r.Rating), 2) AS average_rating,
    COUNT(r.Rating) AS total_ratings
FROM schedule s
LEFT JOIN ratings r ON s.Schedule_ID = r.Schedule_ID
GROUP BY s.Services";

$result_ratings = $conn->query($sql_ratings);
$ratings = [];
if ($result_ratings) {
    while ($row = $result_ratings->fetch_assoc()) {
        $ratings[$row['Services']] = [
            'average_rating' => $row['average_rating'],
            'total_ratings' => $row['total_ratings'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'services' => $services,
    'ratings' => $ratings,
]);

$conn->close();
