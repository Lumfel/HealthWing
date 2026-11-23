<?php
/**
 * HealthWing / VetSystem — Hardened Cross-Role Registration + Persistent Cookie + Turnstile (signup.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('signup', 10, 300);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/turnstile.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["first_name"], $data["last_name"], $data["email"], $data["username"], $data["password"], $data["role"])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

$tsToken = hw_turnstile_extract_token($data);
$tsCheck = hw_turnstile_verify($tsToken, 'signup');
if (empty($tsCheck['success'])) {
    echo json_encode(["status" => "error", "message" => $tsCheck['message'] ?? "Security verification failed."]);
    exit();
}

try {
    $connect = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$firstName = trim((string)$data["first_name"]);
$lastName = trim((string)$data["last_name"]);
$email = trim((string)$data["email"]);
$username = trim((string)$data["username"]);
$password = password_hash((string)$data["password"], PASSWORD_DEFAULT);
$role = $data["role"];
$age = isset($data["age"]) ? (int)$data["age"] : null;
$address = trim((string)($data["address"] ?? ''));
$contact = trim((string)($data["contact"] ?? ''));
$specialties = isset($data["specialties"]) ? trim((string)$data["specialties"]) : null;
$timeNow = date("Y-m-d H:i:s");

// Check uniqueness across BOTH owner and veterinarian tables so roles never collide on login
$stmt = $connect->prepare("
    SELECT username FROM owner WHERE username = ? OR email = ?
    UNION
    SELECT username FROM veterinarian WHERE username = ? OR email = ?
");
$stmt->bind_param("ssss", $username, $email, $username, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "exists", "message" => "Username or email is already taken."]);
    $stmt->close();
    $connect->close();
    exit();
}
$stmt->close();

if ($role === "owner") {
    $stmt = $connect->prepare("INSERT INTO owner (First_Name, Last_Name, Age, Address, Contact, username, email, password, TimeAndDateAdded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissssss", $firstName, $lastName, $age, $address, $contact, $username, $email, $password, $timeNow);
} else {
    $stmt = $connect->prepare("INSERT INTO veterinarian (First_Name, Last_Name, Age, Address, Contact, Specialties, username, email, password, TimeAndDateAdded, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssisssssss", $firstName, $lastName, $age, $address, $contact, $specialties, $username, $email, $password, $timeNow);
}

if ($stmt->execute()) {
    if ($role === "owner") {
        $newOwnerId = (int)$stmt->insert_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'owner';
        $_SESSION['owner_id'] = $newOwnerId;
        hw_set_encrypted_cookie('hw_auth_gcm', [
            'username' => $username,
            'role' => 'owner',
            'owner_id' => $newOwnerId,
            'ttl' => HW_COOKIE_REMEMBER_TTL,
        ], HW_COOKIE_REMEMBER_TTL, true);
    }
    echo json_encode(["status" => "success", "role" => $role]);
} else {
    echo json_encode(["status" => "error", "message" => "Could not create account."]);
}

$stmt->close();
$connect->close();
