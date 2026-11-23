<?php
/**
 * Shared Admin Auth Guard with Automatic AES-256-GCM Cookie Session Restoration
 */
require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    if (!empty($ADMIN_GUARD_IS_API)) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated as admin.']);
    } else {
        header('Location: ../admin_login_page.php');
    }
    exit();
}
