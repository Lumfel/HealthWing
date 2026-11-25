<?php
/**
 * HealthWing / VetSystem — Cross-Table Unique Registration + PRC License Enforcement (signup.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('signup', 10, 300);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/turnstile.php';

header("Content-Type: application/json");
hw_require_csrf();

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

$firstName = trim((string)$data["first_name"]);
$lastName = trim((string)$data["last_name"]);
$email = strtolower(trim((string)$data["email"]));
$username = trim((string)$data["username"]);
$rawPassword = (string)$data["password"];
$requestedRole = strtolower(trim((string)$data["role"]));
$role = ($requestedRole === 'owner') ? 'owner' : 'vet_staff';
$age = isset($data["age"]) && $data["age"] !== '' ? (int)$data["age"] : null;
$address = trim((string)($data["address"] ?? ''));
$contact = trim((string)($data["contact"] ?? ''));
$specialties = isset($data["specialties"]) ? trim((string)$data["specialties"]) : null;
$prcLicenseNo = strtoupper(trim((string)($data["prc_license_no"] ?? '')));

if ($firstName === '' || $lastName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Please provide valid name, username, and email."]);
    exit();
}
if (strlen($rawPassword) < 8) {
    echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters."]);
    exit();
}
if ($role === 'vet_staff' && $prcLicenseNo === '') {
    echo json_encode(["status" => "error", "message" => "PRC License Number is required for veterinarian registration."]);
    exit();
}

$password = password_hash($rawPassword, PASSWORD_DEFAULT);
$timeNow = date("Y-m-d H:i:s");

$connect = null;
$lockAcquired = false;
$txStarted = false;

try {
    $connect = hw_get_db();
    $lockAcquired = hw_db_acquire_lock($connect, 'hw_identity_lock', 5);
    if (!$lockAcquired) {
        http_response_code(503);
        echo json_encode(["status" => "error", "message" => "Registration service busy. Please retry."]);
        exit();
    }

    if (!$connect->begin_transaction()) {
        throw new RuntimeException('Unable to start registration transaction.');
    }
    $txStarted = true;

    // Enforce global case-insensitive username/email uniqueness across owner and veterinarian
    $stmt = $connect->prepare("
        SELECT username FROM owner WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
        UNION
        SELECT username FROM veterinarian WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
    ");
    $stmt->bind_param("ssss", $username, $email, $username, $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        $connect->rollback();
        $txStarted = false;
        echo json_encode(["status" => "exists", "message" => "Username or email is already taken."]);
        exit();
    }

    if ($role === 'vet_staff') {
        $prcStmt = $connect->prepare("SELECT Veterinarian_ID FROM veterinarian WHERE UPPER(PRC_License_No) = UPPER(?) LIMIT 1");
        $prcStmt->bind_param("s", $prcLicenseNo);
        $prcStmt->execute();
        $prcExists = $prcStmt->get_result()->num_rows > 0;
        $prcStmt->close();
        if ($prcExists) {
            $connect->rollback();
            $txStarted = false;
            echo json_encode(["status" => "exists", "message" => "PRC License Number is already registered."]);
            exit();
        }
    }

    if ($role === 'owner') {
        $stmt = $connect->prepare("INSERT INTO owner (First_Name, Last_Name, Age, Address, Contact, username, email, password, TimeAndDateAdded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssss", $firstName, $lastName, $age, $address, $contact, $username, $email, $password, $timeNow);
    } else {
        // New veterinarian self-registrations are always vet_staff with Pending approval
        $stmt = $connect->prepare("INSERT INTO veterinarian (First_Name, Last_Name, Age, Address, Contact, Specialties, Role_Level, PRC_License_No, username, email, password, TimeAndDateAdded, Status) VALUES (?, ?, ?, ?, ?, ?, 'vet_staff', ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("ssissssssss", $firstName, $lastName, $age, $address, $contact, $specialties, $prcLicenseNo, $username, $email, $password, $timeNow);
    }

    if ($stmt->execute()) {
        $insertId = (int)$stmt->insert_id;
        $stmt->close();
        $connect->commit();
        $txStarted = false;

        if ($role === 'owner') {
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'owner';
            $_SESSION['owner_id'] = $insertId;
            hw_set_encrypted_cookie('hw_auth_gcm', [
                'username' => $username,
                'role' => 'owner',
                'owner_id' => $insertId,
                'ttl' => HW_COOKIE_REMEMBER_TTL,
            ], HW_COOKIE_REMEMBER_TTL, true);
        }
        echo json_encode(["status" => "success", "role" => $role]);
    } else {
        $stmt->close();
        $connect->rollback();
        $txStarted = false;
        echo json_encode(["status" => "error", "message" => "Could not create account."]);
    }
} catch (Throwable $e) {
    if ($txStarted && $connect instanceof mysqli) {
        @$connect->rollback();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Could not create account."]);
} finally {
    if ($lockAcquired && $connect instanceof mysqli) {
        hw_db_release_lock($connect, 'hw_identity_lock');
    }
    if ($connect instanceof mysqli) {
        $connect->close();
    }
}
