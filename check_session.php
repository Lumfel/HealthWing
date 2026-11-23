<?php
/**
 * HealthWing / VetSystem — Fast Encrypted Session & Cookie Status Check (check_session.php)
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

header("Content-Type: application/json");
header("Cache-Control: private, max-age=15");

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);

echo json_encode([
    "isLoggedIn"=>$isLoggedIn,
    "username"=>$isLoggedIn ? $_SESSION['username'] : null,
    "role"=>$isLoggedIn ? ($_SESSION['role'] ?? null) : null,
    "cookie_cipher"=>"AES-256-GCM (HttpOnly, SameSite=Lax)",
]);