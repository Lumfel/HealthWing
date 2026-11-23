<?php
/**
 * HealthWing / VetSystem — Turnstile HTTP Entry & Compatibility Wrapper (turnstile.php)
 */
require_once __DIR__ . '/includes/turnstile.php';

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    hw_turnstile_handle_http();
}
