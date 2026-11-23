<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$cacheFile = __DIR__ . '/cache_dashboard_data.json';
$cacheMaxAge = 20;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheMaxAge) {
    readfile($cacheFile);
    exit();
}

ob_start();

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

try {
    $monthly_appointments = [];
    $sql = "SELECT
        DATE_FORMAT(Appointment_Time, '%Y-%m') AS month,
        COUNT(*) AS total_appointments,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) AS completed,
        COUNT(CASE WHEN Status = 'Pending' THEN 1 END) AS pending,
        COUNT(CASE WHEN Status = 'Cancelled' THEN 1 END) AS cancelled
    FROM schedule
    WHERE Appointment_Time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(Appointment_Time, '%Y-%m')
    ORDER BY month ASC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching monthly appointments.");
    }

    while ($row = $result->fetch_assoc()) {
        $row['total_appointments'] = (int)$row['total_appointments'];
        $row['completed'] = (int)$row['completed'];
        $row['pending'] = (int)$row['pending'];
        $row['cancelled'] = (int)$row['cancelled'];
        $monthly_appointments[] = $row;
    }

    $service_distribution = [];
    $sql = "SELECT
        COALESCE(Services, 'Other') AS Services,
        COUNT(*) AS count
    FROM schedule
    WHERE Appointment_Time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY Services
    ORDER BY count DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching service distribution.");
    }

    while ($row = $result->fetch_assoc()) {
        $row['count'] = (int)$row['count'];
        $service_distribution[] = $row;
    }

    $sql = "SELECT
        COUNT(*) AS total_today,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) AS completed_today,
        COUNT(CASE WHEN Status = 'Pending' THEN 1 END) AS pending_today,
        COUNT(CASE WHEN Status = 'Cancelled' THEN 1 END) AS cancelled_today
    FROM schedule
    WHERE DATE(Appointment_Time) = CURDATE()";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching today statistics.");
    }
    $today_stats = $result->fetch_assoc();
    foreach ($today_stats as &$value) {
        $value = (int)$value;
    }

    $sql = "SELECT
        COUNT(*) AS total_emergency,
        COUNT(CASE WHEN Status = 'Pending' THEN 1 END) AS pending_emergency,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) AS completed_emergency
    FROM emergency_booking
    WHERE Created_At >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching emergency statistics.");
    }
    $emergency_stats = $result->fetch_assoc();
    foreach ($emergency_stats as &$value) {
        $value = (int)$value;
    }

    if (count($monthly_appointments) < 6) {
        $months_needed = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months_needed[$month] = true;
        }
        foreach ($monthly_appointments as $data) {
            unset($months_needed[$data['month']]);
        }
        foreach ($months_needed as $month => $needed) {
            $monthly_appointments[] = [
                'month' => $month,
                'total_appointments' => 0,
                'completed' => 0,
                'pending' => 0,
                'cancelled' => 0,
            ];
        }
        usort($monthly_appointments, function ($a, $b) {
            return strcmp($a['month'], $b['month']);
        });
    }

    echo json_encode([
        'success' => true,
        'monthly_appointments' => $monthly_appointments,
        'service_distribution' => $service_distribution,
        'today_stats' => $today_stats,
        'emergency_stats' => $emergency_stats,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

$conn->close();

$output = ob_get_clean();
@file_put_contents($cacheFile, $output);
echo $output;
