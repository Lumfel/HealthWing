<?php
require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();
hw_clear_encrypted_cookie('hw_auth_gcm');
hw_clear_encrypted_cookie('hw_user_gcm');
hw_clear_encrypted_cookie('hw_admin_gcm');
$_SESSION = [];
session_destroy();
header('Location: ../login.php');
exit();
