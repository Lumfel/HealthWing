<?php
/**
 * HealthWing / VetSystem — AES-256-GCM Security, Persistent Sliding Cookie & Cache Engine
 * (includes/security_crypto.php)
 *
 * Capabilities:
 * 1. Authenticated AES-256-GCM Encryption & Decryption (`hw_encrypt_gcm`, `hw_decrypt_gcm`)
 *    with 256-bit key derivation, 96-bit CSPRNG IV (`random_bytes(12)`), and 128-bit GCM authentication tag.
 * 2. Persistent Sliding-Window Session & Cookie Management (`hw_start_secure_session`):
 *    - Dedicated project session storage (`rate_limit_data/sessions`) so Windows/Laragon's
 *      default 24-minute global `/tmp` garbage collector never logs active users out.
 *    - 30-day default persistent cookie lifetime (`2,592,000` seconds) and 90-day lifetime
 *      when "Remember Me" is enabled (`7,776,000` seconds).
 *    - Automatic restoration of both User/Veterinarian (`hw_auth_gcm`, `hw_user_gcm`) and
 *      Admin (`hw_admin_gcm`) sessions whenever a browser is reopened.
 *    - Automatic sliding-window renewal: refreshes cookie & PHP session expiry when >1 hour
 *      has elapsed so you never have to constantly log in again.
 * 3. AES-256-GCM Encrypted Server-Side & User-Side Fast Cache (`hw_cache_get`, `hw_cache_set`,
 *    `hw_cache_invalidate`).
 */

if (!defined('HW_SECURITY_CRYPTO_LOADED')) {
    define('HW_SECURITY_CRYPTO_LOADED', true);
    define('HW_COOKIE_DEFAULT_TTL', 30 * 86400);  // 30 days (2,592,000s) standard persistent login
    define('HW_COOKIE_REMEMBER_TTL', 90 * 86400); // 90 days (7,776,000s) when Remember Me is checked

    /**
     * Resolves or generates the 256-bit (32-byte) clinic master key.
     */
    function hw_get_master_key(): string
    {
        static $cachedKey = null;
        if ($cachedKey !== null) {
            return $cachedKey;
        }

        $envKey = getenv('HW_MASTER_KEY');
        if ($envKey && strlen($envKey) >= 16) {
            $cachedKey = hash('sha256', $envKey, true);
            return $cachedKey;
        }

        $keyDir = dirname(__DIR__) . '/rate_limit_data';
        if (!is_dir($keyDir)) {
            @mkdir($keyDir, 0750, true);
        }
        $keyFile = $keyDir . '/.hw_master_key';
        if (file_exists($keyFile)) {
            $raw = trim((string)@file_get_contents($keyFile));
            if (strlen($raw) === 64 && ctype_xdigit($raw)) {
                $cachedKey = hex2bin($raw);
                return $cachedKey;
            }
        }

        $newBytes = random_bytes(32);
        @file_put_contents($keyFile, bin2hex($newBytes));
        $cachedKey = $newBytes;
        return $cachedKey;
    }

    /**
     * Encrypts any string using AES-256-GCM with a 12-byte random IV and 16-byte authentication tag.
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
        if (!function_exists('openssl_encrypt')) {
            return $plaintext;
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
            return $plaintext;
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
     * Starts a persistent 30-day/90-day PHP session in a dedicated directory and automatically
     * restores + slides User, Veterinarian, and Admin logins from AES-256-GCM cookies.
     */
    function hw_start_secure_session(): void
    {
        $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

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

        // 1. Auto-restore User / Veterinarian session from `hw_auth_gcm` if PHP session expired
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

        // Sliding window renewal for User / Veterinarian cookie (renews if >1 hour since last issue)
        if (!empty($_SESSION['username']) && !empty($_SESSION['role']) && !headers_sent()) {
            $lastIssued = is_array($authCookie) ? (int)($authCookie['iat'] ?? 0) : 0;
            $desiredTtl = is_array($authCookie) ? (int)($authCookie['ttl'] ?? HW_COOKIE_DEFAULT_TTL) : HW_COOKIE_DEFAULT_TTL;
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

        // 2. Auto-restore Admin session from `hw_admin_gcm` if PHP session expired
        $adminCookie = hw_get_encrypted_cookie('hw_admin_gcm');
        if (empty($_SESSION['admin_role']) && is_array($adminCookie)) {
            if (!empty($adminCookie['admin_id']) && !empty($adminCookie['admin_username'])) {
                $_SESSION['admin_id'] = (int)$adminCookie['admin_id'];
                $_SESSION['admin_username'] = $adminCookie['admin_username'];
                $_SESSION['admin_role'] = 'Admin';
            }
        }

        // Sliding window renewal for Admin cookie (renews if >1 hour since last issue)
        if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin' && !headers_sent()) {
            $lastAdminIssued = is_array($adminCookie) ? (int)($adminCookie['iat'] ?? 0) : 0;
            $adminTtl = is_array($adminCookie) ? (int)($adminCookie['ttl'] ?? HW_COOKIE_REMEMBER_TTL) : HW_COOKIE_REMEMBER_TTL;
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
     * Sets an AES-256-GCM encrypted, authenticated HttpOnly cookie with `iat` and `exp`.
     */
    function hw_set_encrypted_cookie(string $name, array $payload, int $ttlSeconds = HW_COOKIE_DEFAULT_TTL, bool $httpOnly = true): bool
    {
        if (headers_sent()) {
            return false;
        }
        $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;
        $payload['ttl'] = $ttlSeconds;
        $json = json_encode($payload);
        $encrypted = hw_encrypt_gcm($json, 'cookie:' . $name);
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
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
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
        $enc = hw_encrypt_gcm(json_encode($data), 'cache:' . $cacheKey);
        if ($enc) {
            @file_put_contents($safeFile, $enc, LOCK_EX);
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
