<?php
/**
 * HealthWing / VetSystem — Protected Emergency Bookings Viewer (view_bookings.php)
 *
 * Fixes the critical privacy vulnerability where `view_bookings.php`exposed all client
 * emergency names, emails, phone numbers, and clinical details to unauthenticated guests.
 * Guests and regular visitors are redirected to the privacy-safe `public_calendar.php`.
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';

if (!$isVet && !$isAdmin) {
    header('Location: public_calendar.php');
    exit;
}

header('Location: vetindex.php');
exit;