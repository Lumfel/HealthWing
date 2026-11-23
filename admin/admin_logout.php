<?php
require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();
hw_clear_encrypted_cookie('hw_admin_gcm');
unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
header('Location: ../admin_login.html');
exit();
