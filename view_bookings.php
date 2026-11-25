<?php
/**
 * HealthWing / VetSystem — Protected Emergency Bookings Redirector (view_bookings.php)
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

if (!hw_is_vet_role()) {
    header('Location: public_calendar.php');
    exit;
}

header('Location: vetindex.php');
exit;
