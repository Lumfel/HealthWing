<?php
/**
 * HealthWing / VetSystem — AES-256-GCM Cached Owner Profile Endpoint (get_owner_info.php)
 *
 * Serves the logged-in owner's profile from the AES-256-GCM encrypted cookie (`hw_user_gcm`)
 * or encrypted disk cache (`owner_profile_<username>`) in <1ms without hitting MySQL on repeat loads.
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: private, max-age=30');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    echo json_encode(['status'=>'error', 'message'=>'Not logged in as owner']);
    exit();
}

$username = $_SESSION['username'];

// 1. Fast path: Check AES-256-GCM encrypted cookie first (0 DB queries)
$cookieProfile = hw_get_encrypted_cookie('hw_user_gcm');
if (is_array($cookieProfile) && !empty($cookieProfile['First_Name'])) {
    echo json_encode([
        'status'=>'success',
        'source'=>'aes-256-gcm-cookie-cache',
        'data'=>$cookieProfile,
    ]);
    exit();
}

// 2. Second fast path: Check AES-256-GCM encrypted disk cache
$cachedProfile = hw_cache_get('owner_profile_'. $username, 300);
if (is_array($cachedProfile) && !empty($cachedProfile['First_Name'])) {
    hw_set_encrypted_cookie('hw_user_gcm', $cachedProfile, 86400, true);
    echo json_encode([
        'status'=>'success',
        'source'=>'aes-256-gcm-server-cache',
        'data'=>$cachedProfile,
    ]);
    exit();
}

// 3. Database fallback + populate AES-256-GCM cache & cookie
try {
    $connect = hw_get_db();
    $stmt = $connect->prepare('SELECT Owner_ID, First_Name, Last_Name, email AS Email, Contact FROM owner WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $row['First_Name'] = hw_decrypt_gcm($row['First_Name']) ?? $row['First_Name'];
        $row['Last_Name'] = hw_decrypt_gcm($row['Last_Name']) ?? $row['Last_Name'];
        $row['Email'] = hw_decrypt_gcm($row['Email']) ?? $row['Email'];
        $row['Contact'] = hw_decrypt_gcm($row['Contact']) ?? $row['Contact'];

        hw_cache_set('owner_profile_'. $username, $row);
        hw_set_encrypted_cookie('hw_user_gcm', $row, 86400, true);

        echo json_encode([
            'status'=>'success',
            'source'=>'database-hydrated-gcm',
            'data'=>$row,
        ]);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Owner not found']);
    }
    $stmt->close();
    $connect->close();
} catch (Throwable $e) {
    echo json_encode(['status'=>'error', 'message'=>'Database connection failed']);
}
