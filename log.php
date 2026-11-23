<?php
/**
 * HealthWing / VetSystem — Persistent AES-256-GCM Login & Cloudflare Turnstile Handler (log.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('login', 15, 60);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/turnstile.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Please provide both username and password."]);
    exit();
}

// Verify Cloudflare Turnstile token (passes automatically on localhost test key or pre-clearance cookie)
$tsToken = hw_turnstile_extract_token($data);
$tsCheck = hw_turnstile_verify($tsToken, 'login');
if (empty($tsCheck['success'])) {
    echo json_encode(["status" => "error", "message" => $tsCheck['message'] ?? "Security verification failed."]);
    exit();
}

$username = trim((string)$data['username']);
$password = (string)$data['password'];
$rememberMe = !isset($data['remember_me']) || !empty($data['remember_me']);
// 90 days when Remember Me is ticked (default), 30 days otherwise so user never has to constantly log in
$cookieTtl = $rememberMe ? HW_COOKIE_REMEMBER_TTL : HW_COOKIE_DEFAULT_TTL;

try {
    $connect = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 1. Check in the `owner` table (matches username OR email)
$stmt = $connect->prepare("SELECT * FROM owner WHERE username = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = "owner";
        $_SESSION['owner_id'] = (int)$user['Owner_ID'];

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

// 2. Check in the `veterinarian` table (matches username OR email)
$stmt = $connect->prepare("SELECT * FROM veterinarian WHERE username = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        $vetStatus = $user['Status'] ?? 'Approved';
        if ($vetStatus === 'Pending') {
            echo json_encode(["status" => "error", "message" => "Your veterinarian account is awaiting admin approval."]);
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

        session_regenerate_id(true);
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = "Veterinarian";
        $_SESSION['vet_id'] = (int)$user['Veterinarian_ID'];

        $vetPayload = [
            'Veterinarian_ID' => (int)$user['Veterinarian_ID'],
            'First_Name' => $user['First_Name'],
            'Last_Name' => $user['Last_Name'],
            'Specialties' => $user['Specialties'] ?? 'General',
        ];

        hw_set_encrypted_cookie('hw_auth_gcm', [
            'username' => $user['username'],
            'role' => 'Veterinarian',
            'vet_id' => (int)$user['Veterinarian_ID'],
            'ttl' => $cookieTtl,
        ], $cookieTtl, true);

        hw_set_encrypted_cookie('hw_user_gcm', $vetPayload, $cookieTtl, true);

        echo json_encode([
            "status" => "success",
            "role" => "Veterinarian",
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

echo json_encode(["status" => "error", "message" => "Account not found. Default accounts: user / vet / admin."]);
$connect->close();
