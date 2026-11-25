<?php
/**
 * Shared Head Veterinarian (Super Admin) Auth Guard via unified hw_require_role()
 */
require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();

hw_require_role(['head_vet'], !empty($ADMIN_GUARD_IS_API), '../login.php');
