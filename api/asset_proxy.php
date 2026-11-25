<?php
/**
 * HealthWing / VetSystem — Controlled Asset Delivery Proxy (api/asset_proxy.php)
 *
 * Serves allowlisted embedded CSS and JavaScript files to <link> and <script> tags
 * while blocking direct top-level browser navigation and path traversal.
 */

$fetchMode = strtolower((string)($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
$fetchDest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
if ($fetchMode === 'navigate' || $fetchDest === 'document') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden: Direct navigation to static asset sources is disabled.');
}

$rel = trim((string)($_GET['f'] ?? ''));
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');

if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, "\0") !== false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden');
}

$allowedPrefix = preg_match('#^(assets/css/|assets/js/|vendor/)#', $rel) === 1;

$ext = strtolower((string)pathinfo($rel, PATHINFO_EXTENSION));
if (!$allowedPrefix || !in_array($ext, ['css', 'js'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden');
}

$rootReal = realpath(dirname(__DIR__));
$targetReal = $rootReal !== false ? realpath($rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)) : false;

if (
    $rootReal === false ||
    $targetReal === false ||
    !is_file($targetReal) ||
    strpos($targetReal, $rootReal . DIRECTORY_SEPARATOR) !== 0
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('404 Not Found');
}

$mime = $ext === 'css' ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($targetReal);
exit;
