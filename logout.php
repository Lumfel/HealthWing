<?php
/**
 * HealthWing / VetSystem — Secure Logout & Encrypted Cookie/Cache Cleanup (logout.php)
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

if (!empty($_SESSION['username'])) {
    hw_cache_invalidate('owner_profile_'. $_SESSION['username']);
}

hw_clear_encrypted_cookie('hw_auth_gcm');
hw_clear_encrypted_cookie('hw_user_gcm');

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    @setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: index.php");
exit();