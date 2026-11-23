<?php
/**
 * HealthWing / VetSystem — AES-256-GCM Security, Persistent Sliding Cookie, CSRF & Cache Engine
 * (includes/security_crypto.php)
 */

if (!defined('HW_SECURITY_CRYPTO_LOADED')) {
    define('HW_SECURITY_CRYPTO_LOADED', true);
    define('HW_COOKIE_DEFAULT_TTL', 90 * 86400);
    define('HW_COOKIE_REMEMBER_TTL', 90 * 86400);

    /**
     * Resolves or generates the 256-bit (32-byte) clinic master key strictly outside the webroot.
     */
    function hw_get_master_key(): string
    {
        static $cachedKey = null;
        if ($cachedKey !== null) {
            return $cachedKey;
        }

        $envKey = getenv('HW_MASTER_KEY');
        if ($envKey !== false && strlen($envKey) >= 16) {
            $cachedKey = hash('sha256', $envKey, true);
            return $cachedKey;
        }

        $outsideDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . '.healthwing_secrets';
        if (!is_dir($outsideDir)) {
            @mkdir($outsideDir, 0700, true);
        }

        if (is_dir($outsideDir) && is_writable($outsideDir)) {
            $keyFile = $outsideDir . DIRECTORY_SEPARATOR . '.hw_master_key';
        } else {
            $keyFile = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . '.healthwing_hw_master_key';
        }

        if (file_exists($keyFile)) {
            $raw = trim((string)@file_get_contents($keyFile));
            if (strlen($raw) === 64 && ctype_xdigit($raw)) {
                $bin = hex2bin($raw);
                if ($bin !== false && strlen($bin) === 32) {
                    $cachedKey = $bin;
                    return $cachedKey;
                }
            }
        }

        $newBytes = random_bytes(32);
        if (@file_put_contents($keyFile, bin2hex($newBytes), LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist master encryption key outside webroot.');
        }
        @chmod($keyFile, 0600);
        $cachedKey = $newBytes;
        return $cachedKey;
    }

    /**
     * Encrypts plaintext using AES-256-GCM. Fails closed by throwing an exception if OpenSSL is unavailable.
     * Format: enc:gcm:<base64(iv . tag . ciphertext)>
     */
    function hw_encrypt_gcm(?string $plaintext, string $aad = 'healthwing-v2'): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        if (strpos($plaintext, 'enc:gcm:') === 0) {
            return $plaintext;
        }
        if (!function_exists('openssl_encrypt') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('AES-256-GCM encryption is unavailable.');
        }

        $key = hw_get_master_key();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            16
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('AES-256-GCM encryption failed.');
        }

        return 'enc:gcm:' . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts an `enc:gcm:...` payload and verifies its 128-bit GCM authentication tag.
     */
    function hw_decrypt_gcm(?string $stored, string $aad = 'healthwing-v2'): ?string
    {
        if ($stored === null || $stored === '') {
            return $stored;
        }
        if (strpos($stored, 'enc:gcm:') !== 0) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            return null;
        }

        $b64 = substr($stored, 8);
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $key = hw_get_master_key();

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Resolves the client IP strictly from REMOTE_ADDR unless REMOTE_ADDR matches an explicit trusted proxy list.
     */
    function hw_get_client_ip(): string
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $trustedRaw = getenv('HW_TRUSTED_PROXIES');
        if ($trustedRaw !== false && trim($trustedRaw) !== '') {
            $trustedProxies = array_filter(array_map('trim', explode(',', $trustedRaw)));
            if (in_array($remoteAddr, $trustedProxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
                foreach ($parts as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }
        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }

    /**
     * Starts a persistent 90-day sliding PHP session in a dedicated directory and automatically
     * restores + slides User, Veterinarian, and Admin logins from AES-256-GCM HttpOnly cookies.
     */
    function hw_start_secure_session(): void
    {
        $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        if (session_status() === PHP_SESSION_NONE) {
            $sessDir = dirname(__DIR__) . '/rate_limit_data/sessions';
            if (!is_dir($sessDir)) {
                @mkdir($sessDir, 0750, true);
            }
            if (is_dir($sessDir) && is_writable($sessDir)) {
                @session_save_path($sessDir);
            }
            @ini_set('session.gc_maxlifetime', (string)HW_COOKIE_REMEMBER_TTL);
            @ini_set('session.cookie_lifetime', (string)HW_COOKIE_REMEMBER_TTL);
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_httponly', '1');
            @session_set_cookie_params([
                'lifetime' => HW_COOKIE_REMEMBER_TTL,
                'path' => '/',
                'secure' => $onHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            @session_start();
        }

        if (empty($_SESSION['hw_csrf_token'])) {
            $_SESSION['hw_csrf_token'] = bin2hex(random_bytes(32));
        }

        $authCookie = hw_get_encrypted_cookie('hw_auth_gcm');
        if (empty($_SESSION['username']) && is_array($authCookie)) {
            if (!empty($authCookie['username']) && !empty($authCookie['role'])) {
                $_SESSION['username'] = $authCookie['username'];
                $_SESSION['role'] = $authCookie['role'];
                if (!empty($authCookie['owner_id'])) {
                    $_SESSION['owner_id'] = (int)$authCookie['owner_id'];
                }
                if (!empty($authCookie['vet_id'])) {
                    $_SESSION['vet_id'] = (int)$authCookie['vet_id'];
                }
            }
        }

        if (!empty($_SESSION['username']) && !empty($_SESSION['role']) && !headers_sent()) {
            $lastIssued = is_array($authCookie) ? (int)($authCookie['iat'] ?? 0) : 0;
            $desiredTtl = is_array($authCookie) ? (int)($authCookie['ttl'] ?? HW_COOKIE_REMEMBER_TTL) : HW_COOKIE_REMEMBER_TTL;
            if ($desiredTtl < HW_COOKIE_DEFAULT_TTL) {
                $desiredTtl = HW_COOKIE_DEFAULT_TTL;
            }
            if ((time() - $lastIssued) > 3600) {
                hw_set_encrypted_cookie('hw_auth_gcm', [
                    'username' => $_SESSION['username'],
                    'role' => $_SESSION['role'],
                    'owner_id' => $_SESSION['owner_id'] ?? null,
                    'vet_id' => $_SESSION['vet_id'] ?? null,
                    'ttl' => $desiredTtl,
                ], $desiredTtl, true);
            }
        }

        $adminCookie = hw_get_encrypted_cookie('hw_admin_gcm');
        if (empty($_SESSION['admin_role']) && is_array($adminCookie)) {
            if (!empty($adminCookie['admin_id']) && !empty($adminCookie['admin_username'])) {
                $_SESSION['admin_id'] = (int)$adminCookie['admin_id'];
                $_SESSION['admin_username'] = $adminCookie['admin_username'];
                $_SESSION['admin_role'] = 'Admin';
            }
        }

        if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin' && !headers_sent()) {
            $lastAdminIssued = is_array($adminCookie) ? (int)($adminCookie['iat'] ?? 0) : 0;
            $adminTtl = is_array($adminCookie) ? (int)($adminCookie['ttl'] ?? HW_COOKIE_REMEMBER_TTL) : HW_COOKIE_REMEMBER_TTL;
            if ($adminTtl < HW_COOKIE_REMEMBER_TTL) {
                $adminTtl = HW_COOKIE_REMEMBER_TTL;
            }
            if ((time() - $lastAdminIssued) > 3600 && !empty($_SESSION['admin_id'])) {
                hw_set_encrypted_cookie('hw_admin_gcm', [
                    'admin_id' => (int)$_SESSION['admin_id'],
                    'admin_username' => $_SESSION['admin_username'] ?? 'admin',
                    'admin_role' => 'Admin',
                    'ttl' => $adminTtl,
                ], $adminTtl, true);
            }
        }
    }

    /**
     * Returns the current session-bound CSRF token.
     */
    function hw_csrf_token(): string
    {
        hw_start_secure_session();
        if (empty($_SESSION['hw_csrf_token'])) {
            $_SESSION['hw_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['hw_csrf_token'];
    }

    /**
     * Verifies a session-bound CSRF token from parameter, X-CSRF-Token header, $_POST, or JSON body.
     */
    function hw_csrf_verify(?string $token = null): bool
    {
        hw_start_secure_session();
        $expected = (string)($_SESSION['hw_csrf_token'] ?? '');
        if ($expected === '') {
            return false;
        }

        $provided = $token;
        if ($provided === null || $provided === '') {
            $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        }
        if (($provided === null || $provided === '') && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
            $raw = @file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['csrf_token'])) {
                    $provided = (string)$decoded['csrf_token'];
                }
            }
        }

        if (!is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Enforces CSRF validation on state-changing HTTP requests.
     */
    function hw_require_csrf(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!hw_csrf_verify()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invalid or missing CSRF security token. Please refresh the page and try again.',
                ]);
                exit;
            }
        }
    }

    /**
     * Generates a short-lived HMAC-SHA256 signed token for WebSocket authentication.
     */
    function hw_generate_ws_token(string $role, int $userId, string $name, array $channels = [], int $ttl = 600): string
    {
        $allowedRoles = ['owner', 'vet', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('Invalid WebSocket role.');
        }

        $now = time();
        $payload = [
            'role' => $role,
            'user_id' => $userId,
            'name' => $name,
            'channels' => array_values(array_unique(array_filter($channels, 'is_string'))),
            'iat' => $now,
            'exp' => $now + max(30, $ttl),
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $b64Url = static function (string $bin): string {
            return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        };

        $payloadEncoded = $b64Url((string)json_encode($payload));
        $sig = hash_hmac('sha256', $payloadEncoded, hw_get_master_key(), true);
        return $payloadEncoded . '.' . $b64Url($sig);
    }

    /**
     * Verifies a WebSocket HMAC-SHA256 authentication token and checks expiration.
     */
    function hw_verify_ws_token(string $token): ?array
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadEncoded, $sigEncoded] = $parts;
        if ($payloadEncoded === '' || $sigEncoded === '') {
            return null;
        }

        $b64UrlDecode = static function (string $str): ?string {
            $pad = strlen($str) % 4;
            if ($pad > 0) {
                $str .= str_repeat('=', 4 - $pad);
            }
            $bin = base64_decode(strtr($str, '-_', '+/'), true);
            return $bin === false ? null : $bin;
        };

        $providedSig = $b64UrlDecode($sigEncoded);
        $expectedSig = hash_hmac('sha256', $payloadEncoded, hw_get_master_key(), true);
        if ($providedSig === null || !hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $payloadJson = $b64UrlDecode($payloadEncoded);
        if ($payloadJson === null) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $role = $payload['role'] ?? '';
        $exp = (int)($payload['exp'] ?? 0);
        if (!in_array($role, ['owner', 'vet', 'admin'], true) || $exp < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Sets an AES-256-GCM encrypted, authenticated HttpOnly cookie with `iat` and `exp`.
     */
    function hw_set_encrypted_cookie(string $name, array $payload, int $ttlSeconds = HW_COOKIE_REMEMBER_TTL, bool $httpOnly = true): bool
    {
        if (headers_sent()) {
            return false;
        }
        $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;
        $payload['ttl'] = $ttlSeconds;
        $json = json_encode($payload);
        try {
            $encrypted = hw_encrypt_gcm($json, 'cookie:' . $name);
        } catch (Throwable $e) {
            return false;
        }
        if (!$encrypted) {
            return false;
        }
        $_COOKIE[$name] = $encrypted;
        return @setcookie($name, $encrypted, [
            'expires' => $now + $ttlSeconds,
            'path' => '/',
            'secure' => $onHttps,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Reads and verifies an AES-256-GCM encrypted cookie. Returns null if tampered or expired.
     */
    function hw_get_encrypted_cookie(string $name): ?array
    {
        if (empty($_COOKIE[$name])) {
            return null;
        }
        $decrypted = hw_decrypt_gcm($_COOKIE[$name], 'cookie:' . $name);
        if (!$decrypted) {
            return null;
        }
        $data = json_decode($decrypted, true);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    /**
     * Deletes an encrypted cookie cleanly.
     */
    function hw_clear_encrypted_cookie(string $name): void
    {
        $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        unset($_COOKIE[$name]);
        if (!headers_sent()) {
            @setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $onHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Reads an AES-256-GCM encrypted cache entry from rate_limit_data/cache_<hash>.gcm.
     */
    function hw_cache_get(string $cacheKey, int $maxAgeSeconds = 300): ?array
    {
        $cacheDir = dirname(__DIR__) . '/rate_limit_data';
        $safeFile = $cacheDir . '/cache_' . hash('sha256', $cacheKey) . '.gcm';
        if (!file_exists($safeFile)) {
            return null;
        }
        if ((time() - @filemtime($safeFile)) > $maxAgeSeconds) {
            return null;
        }
        $raw = (string)@file_get_contents($safeFile);
        $dec = hw_decrypt_gcm($raw, 'cache:' . $cacheKey);
        if (!$dec) {
            return null;
        }
        $decoded = json_decode($dec, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Writes an AES-256-GCM encrypted cache entry to disk.
     */
    function hw_cache_set(string $cacheKey, array $data): void
    {
        $cacheDir = dirname(__DIR__) . '/rate_limit_data';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0750, true);
        }
        $safeFile = $cacheDir . '/cache_' . hash('sha256', $cacheKey) . '.gcm';
        try {
            $enc = hw_encrypt_gcm(json_encode($data), 'cache:' . $cacheKey);
            if ($enc) {
                @file_put_contents($safeFile, $enc, LOCK_EX);
            }
        } catch (Throwable $e) {
            // Skip cache write silently if encryption is unavailable
        }
    }

    /**
     * Invalidates a specific cache key.
     */
    function hw_cache_invalidate(string $cacheKey): void
    {
        $cacheDir = dirname(__DIR__) . '/rate_limit_data';
        $safeFile = $cacheDir . '/cache_' . hash('sha256', $cacheKey) . '.gcm';
        if (file_exists($safeFile)) {
            @unlink($safeFile);
        }
    }
}
