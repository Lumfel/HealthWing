<?php
/**
 * Simple rate limiter — no database required for the blocking logic itself.
 * Tracks requests per IP address in small local files and blocks
 * an IP temporarily if it sends too many requests too fast.
 *
 * Also logs each allowed/blocked event to the `traffic_log`table so the
 * admin Network Monitor page can show it. Logging is wrapped so that if
 * the database is ever unavailable, the rate limiter still works — it
 * just skips logging that one event instead of breaking the whole site.
 *
 * Usage: put this at the very top of any endpoint you want to protect —
 *   require_once __DIR__ . '/rate_limit.php';
 *   rate_limit_check('login', 8, 60);   // max 8 requests per 60 seconds
 */

function rate_limit_log_event(string $action, string $status): void
{
    try {
        $conn = @new mysqli('localhost', 'root', '', 'veterinarydb');
        if ($conn->connect_error) {
            return;
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $stmt = $conn->prepare("INSERT INTO traffic_log (IP_Address, Action, Status) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $ip, $action, $status);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (\Throwable $e) {
        // Never let logging failures affect the actual rate limiting or the request itself.
    }
}

function rate_limit_check(string $action, int $maxRequests, int $windowSeconds): void
{
    $dir = __DIR__ . '/rate_limit_data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // If behind a proxy/tunnel, X-Forwarded-For can contain a list — take the first IP
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $safeIp = preg_replace('/[^a-zA-Z0-9\.\:]/', '_', $ip);
    $file = $dir . '/'. $action . '_'. $safeIp . '.json';

    $now = time();
    $timestamps = [];

    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return; // fail open — never let a filesystem hiccup take the whole site down
    }
    flock($fp, LOCK_EX);

    $contents = stream_get_contents($fp);
    if ($contents) {
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            $timestamps = $decoded;
        }
    }

    // Keep only timestamps inside the current window
    $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
        return ($now - $t) < $windowSeconds;
    }));

    if (count($timestamps) >= $maxRequests) {
        flock($fp, LOCK_UN);
        fclose($fp);
        rate_limit_log_event($action, 'blocked');
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: '. $windowSeconds);
        echo json_encode([
            'success'=> false,
            'message'=>'Too many requests. Please wait a bit and try again.'
        ]);
        exit();
    }

    $timestamps[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($timestamps));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    rate_limit_log_event($action, 'allowed');
}
