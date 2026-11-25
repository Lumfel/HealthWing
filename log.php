<?php
/**
 * HealthWing / VetSystem — Unified 3-Role Login (owner, vet_staff, head_vet) (log.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('login', 15, 60);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/turnstile.php';

header("Content-Type: application/json");
hw_require_csrf();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Please provide both username and password."]);
    exit();
}

$tsToken = hw_turnstile_extract_token($data);
$tsCheck = hw_turnstile_verify($tsToken, 'login');
if (empty($tsCheck['success'])) {
    echo json_encode(["status" => "error", "message" => $tsCheck['message'] ?? "Security verification failed."]);
    exit();
}

$username = trim((string)$data['username']);
$password = (string)$data['password'];
$rememberMe = !isset($data['remember_me']) || !empty($data['remember_me']);
$cookieTtl = $rememberMe ? HW_COOKIE_REMEMBER_TTL : HW_COOKIE_DEFAULT_TTL;

try {
    $connect = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 1. Check in `owner` table (case-insensitive username or email, excluding soft-deleted)
$stmt = $connect->prepare("SELECT * FROM owner WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND Deleted_At IS NULL LIMIT 1");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'owner';
        $_SESSION['owner_id'] = (int)$user['Owner_ID'];
        unset($_SESSION['vet_id'], $_SESSION['prc_license_no']);

        $profilePayload = [
            'Owner_ID' => (int)$user['Owner_ID'],
            'First_Name' => hw_decrypt_gcm($user['First_Name']) ?? $user['First_Name'],
            'Last_Name' => hw_decrypt_gcm($user['Last_Name']) ?? $user['Last_Name'],
            'Email' => hw_decrypt_gcm($user['email']) ?? $user['email'],
            'Contact' => hw_decrypt_gcm($user['Contact']) ?? $user['Contact'],
        ];

        hw_set_encrypted_cookie('hw_auth_gcm', [
            'username' => $user['username'],
            'role' => 'owner',
            'owner_id' => (int)$user['Owner_ID'],
            'ttl' => $cookieTtl,
        ], $cookieTtl, true);

        hw_set_encrypted_cookie('hw_user_gcm', $profilePayload, $cookieTtl, true);
        hw_cache_set('owner_profile_' . $user['username'], $profilePayload);

        echo json_encode([
            "status" => "success",
            "role" => "owner",
            "username" => $user['username'],
            "cookie_ttl_days" => (int)($cookieTtl / 86400),
            "user_cache_token" => hw_encrypt_gcm(json_encode($profilePayload), 'client_cache'),
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid password."]);
    }
    $stmt->close();
    $connect->close();
    exit();
}
$stmt->close();

// 2. Check in `veterinarian` table (handles both `vet_staff` and `head_vet`)
$stmt = $connect->prepare("SELECT * FROM veterinarian WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND Deleted_At IS NULL LIMIT 1");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        $vetStatus = $user['Status'] ?? 'Approved';
        if ($vetStatus === 'Pending') {
            echo json_encode(["status" => "error", "message" => "Your veterinarian account is awaiting Head Veterinarian approval."]);
            $stmt->close();
            $connect->close();
            exit();
        }
        if ($vetStatus === 'Rejected') {
            echo json_encode(["status" => "error", "message" => "Your signup request was declined. Please contact the clinic."]);
            $stmt->close();
            $connect->close();
            exit();
        }

        $roleLevel = hw_normalize_role((string)($user['Role_Level'] ?? 'vet_staff'));
        if (!in_array($roleLevel, ['vet_staff', 'head_vet'], true)) {
            $roleLevel = 'vet_staff';
        }

        session_regenerate_id(true);
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $roleLevel;
        $_SESSION['vet_id'] = (int)$user['Veterinarian_ID'];
        $_SESSION['prc_license_no'] = (string)($user['PRC_License_No'] ?? '');
        unset($_SESSION['owner_id']);

        $vetPayload = [
            'Veterinarian_ID' => (int)$user['Veterinarian_ID'],
            'First_Name' => $user['First_Name'],
            'Last_Name' => $user['Last_Name'],
            'Role_Level' => $roleLevel,
            'PRC_License_No' => (string)($user['PRC_License_No'] ?? ''),
            'Specialties' => $user['Specialties'] ?? 'General',
        ];

        hw_set_encrypted_cookie('hw_auth_gcm', [
            'username' => $user['username'],
            'role' => $roleLevel,
            'vet_id' => (int)$user['Veterinarian_ID'],
            'prc_license_no' => (string)($user['PRC_License_No'] ?? ''),
            'ttl' => $cookieTtl,
        ], $cookieTtl, true);

        hw_set_encrypted_cookie('hw_user_gcm', $vetPayload, $cookieTtl, true);

        echo json_encode([
            "status" => "success",
            "role" => $roleLevel,
            "username" => $user['username'],
            "cookie_ttl_days" => (int)($cookieTtl / 86400),
            "user_cache_token" => hw_encrypt_gcm(json_encode($vetPayload), 'client_cache'),
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid password."]);
    }
    $stmt->close();
    $connect->close();
    exit();
}
$stmt->close();

echo json_encode(["status" => "error", "message" => "Account not found."]);
$connect->close();
