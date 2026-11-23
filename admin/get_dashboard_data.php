<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');

// Simple file-based cache: this endpoint runs several summary queries at once,
// and the numbers don't need to be perfectly to-the-second fresh. Reusing a
// 20-second-old result means repeated dashboard loads/refreshes hit the
// database far less often, without the admin ever noticing a difference.
$cacheFile = __DIR__ . '/cache_dashboard_data.json';
$cacheMaxAge = 20; // seconds
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheMaxAge) {
    readfile($cacheFile);
    exit();
}

ob_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');

if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'Database connection failed: '. $conn->connect_error]);
    exit;
}

try {
    // Get monthly appointment counts for the last 6 months
    $monthly_appointments = [];
    $sql = "SELECT 
        DATE_FORMAT(Appointment_Time, '%Y-%m') as month,
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN Status = 'Completed'THEN 1 END) as completed,
        COUNT(CASE WHEN Status = 'Pending'THEN 1 END) as pending,
        COUNT(CASE WHEN Status = 'Cancelled'THEN 1 END) as cancelled
    FROM schedule 
    WHERE Appointment_Time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(Appointment_Time, '%Y-%m')
    ORDER BY month ASC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching monthly appointments: ". $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        // Ensure numeric values
        $row['total_appointments'] = (int)$row['total_appointments'];
        $row['completed'] = (int)$row['completed'];
        $row['pending'] = (int)$row['pending'];
        $row['cancelled'] = (int)$row['cancelled'];
        $monthly_appointments[] = $row;
    }

    // Get service distribution with proper NULL handling
    $service_distribution = [];
    $sql = "SELECT 
        COALESCE(Services, 'Other') as Services,
        COUNT(*) as count
    FROM schedule 
    WHERE Appointment_Time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY Services
    ORDER BY count DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching service distribution: ". $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $row['count'] = (int)$row['count'];
        $service_distribution[] = $row;
    }

    // Get today's statistics
    $sql = "SELECT 
        COUNT(*) as total_today,
        COUNT(CASE WHEN Status = 'Completed'THEN 1 END) as completed_today,
        COUNT(CASE WHEN Status = 'Pending'THEN 1 END) as pending_today,
        COUNT(CASE WHEN Status = 'Cancelled'THEN 1 END) as cancelled_today
    FROM schedule 
    WHERE DATE(Appointment_Time) = CURDATE()";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching today's statistics: ". $conn->error);
    }
    $today_stats = $result->fetch_assoc();
    
    // Convert to integers
    foreach ($today_stats as &$value) {
        $value = (int)$value;
    }

    // Get emergency bookings stats
    $sql = "SELECT 
        COUNT(*) as total_emergency,
        COUNT(CASE WHEN Status = 'Pending'THEN 1 END) as pending_emergency,
        COUNT(CASE WHEN Status = 'Completed'THEN 1 END) as completed_emergency
    FROM emergency_booking 
    WHERE Created_At >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error fetching emergency statistics: ". $conn->error);
    }
    $emergency_stats = $result->fetch_assoc();
    
    // Convert to integers
    foreach ($emergency_stats as &$value) {
        $value = (int)$value;
    }

    // Ensure we have at least 6 months of data
    if (count($monthly_appointments) < 6) {
        $current_month = date('Y-m');
        $months_needed = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months_needed[$month] = true;
        }
        
        foreach ($monthly_appointments as $data) {
            unset($months_needed[$data['month']]);
        }
        
        // Fill in missing months with zero values
        foreach ($months_needed as $month =>$needed) {
            $monthly_appointments[] = [
                'month'=>$month,
                'total_appointments'=>0,
                'completed'=>0,
                'pending'=>0,
                'cancelled'=>0
            ];
        }
        
        // Sort by month
        usort($monthly_appointments, function($a, $b) {
            return strcmp($a['month'], $b['month']);
        });
    }

    echo json_encode([
        'success'=> true,
        'monthly_appointments'=>$monthly_appointments,
        'service_distribution'=>$service_distribution,
        'today_stats'=>$today_stats,
        'emergency_stats'=>$emergency_stats
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success'=> false,
        'error'=>$e->getMessage()
    ]);
}

$conn->close();

$output = ob_get_clean();
@file_put_contents($cacheFile, $output);
echo $output;
