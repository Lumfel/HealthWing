<?php
// One-time setup: creates the first admin account. Locks itself once an admin exists.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/turnstile.php';
hw_start_secure_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit();
}

if (!hw_is_local_request() && ($_SESSION['admin_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Initial admin setup is only allowed from a local clinic host.']);
    exit();
}

hw_require_csrf();

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

$check = $conn->query("SELECT COUNT(*) AS c FROM admin");
$row = $check ? $check->fetch_assoc() : ['c' => 0];
if ((int)$row['c'] > 0) {
    echo json_encode(['status' => 'error', 'message' => 'An admin account already exists. Setup is locked.']);
    $conn->close();
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit();
}
if (strlen($password) < 8) {
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
    exit();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin (Username, Email, Password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hash);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not create admin account. Please check input constraints.']);
}

$stmt->close();
$conn->close();
