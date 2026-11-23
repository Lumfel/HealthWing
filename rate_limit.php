<?php
/**
 * HealthWing / VetSystem — Rate Limiter & Traffic Logger (rate_limit.php)
 */

require_once __DIR__ . '/includes/db.php';

function rate_limit_log_event(string $action, string $status): void
{
    try {
        $conn = hw_get_db();
        $ip = hw_get_client_ip();
        $stmt = $conn->prepare("INSERT INTO traffic_log (IP_Address, Action, Status) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $ip, $action, $status);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (\Throwable $e) {
        // Logging failures do not interrupt rate limiting.
    }
}

function rate_limit_check(string $action, int $maxRequests, int $windowSeconds): void
{
    $dir = __DIR__ . '/rate_limit_data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $ip = hw_get_client_ip();
    $safeIp = preg_replace('/[^a-zA-Z0-9\.\:]/', '_', $ip);
    $file = $dir . '/' . $action . '_' . $safeIp . '.json';

    $now = time();
    $timestamps = [];

    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        return;
    }
    flock($fp, LOCK_EX);

    $contents = stream_get_contents($fp);
    if ($contents) {
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            $timestamps = $decoded;
        }
    }

    $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
        return ($now - $t) < $windowSeconds;
    }));

    if (count($timestamps) >= $maxRequests) {
        flock($fp, LOCK_UN);
        fclose($fp);
        rate_limit_log_event($action, 'blocked');
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $windowSeconds);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait a bit and try again.',
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
