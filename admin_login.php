<?php
/**
 * HealthWing / VetSystem — Persistent AES-256-GCM Admin Login & Turnstile Handler (admin_login.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('admin_login', 15, 60);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/turnstile.php';

header('Content-Type: application/json');
hw_require_csrf();

$data = json_decode(file_get_contents("php://input"), true);
$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');
$rememberMe = !isset($data['remember_me']) || !empty($data['remember_me']);
$cookieTtl = $rememberMe ? HW_COOKIE_REMEMBER_TTL : HW_COOKIE_DEFAULT_TTL;

if ($username === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit();
}

$tsToken = hw_turnstile_extract_token($data);
$tsCheck = hw_turnstile_verify($tsToken, 'admin_login');
if (empty($tsCheck['success'])) {
    echo json_encode(['status' => 'error', 'message' => $tsCheck['message'] ?? 'Security verification failed.']);
    exit();
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare("SELECT Admin_ID, Username, Password FROM admin WHERE Username = ? OR Email = ? LIMIT 1");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    if (password_verify($password, $admin['Password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['Admin_ID'];
        $_SESSION['admin_username'] = $admin['Username'];
        $_SESSION['admin_role'] = 'Admin';

        hw_set_encrypted_cookie('hw_admin_gcm', [
            'admin_id' => (int)$admin['Admin_ID'],
            'admin_username' => $admin['Username'],
            'admin_role' => 'Admin',
            'ttl' => $cookieTtl,
        ], $cookieTtl, true);

        echo json_encode([
            'status' => 'success',
            'cookie_ttl_days' => (int)($cookieTtl / 86400),
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Admin account not found. (Default: admin / Admin@1234)']);
}

$stmt->close();
$conn->close();
