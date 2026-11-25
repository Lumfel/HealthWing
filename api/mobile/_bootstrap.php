<?php
/**
 * HealthWing Mobile API Bootstrap
 * Stateless JSON helpers and HMAC-SHA256 Bearer token authentication.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

if (!defined('HW_MOBILE_BOOTSTRAP_LOADED')) {
    define('HW_MOBILE_BOOTSTRAP_LOADED', true);
    define('HW_MOBILE_TOKEN_TTL', 86400);

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    function hw_mobile_json_response(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    function hw_mobile_read_json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            hw_mobile_json_response(400, [
                'success' => false,
                'error' => 'invalid_json',
                'message' => 'Request body must be valid JSON.',
            ]);
        }
        return $decoded;
    }

    function hw_mobile_require_method(string $method): void
    {
        $current = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $expected = strtoupper($method);
        if ($current !== $expected) {
            header('Allow: ' . $expected);
            hw_mobile_json_response(405, [
                'success' => false,
                'error' => 'method_not_allowed',
                'message' => 'Method not allowed. Use ' . $expected . '.',
            ]);
        }
    }

    function hw_mobile_b64url_encode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    function hw_mobile_b64url_decode(string $str): ?string
    {
        $pad = strlen($str) % 4;
        if ($pad > 0) {
            $str .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($str, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    function hw_mobile_issue_token(int $userId, string $username, string $role, ?string $prcLicenseNo = null, int $ttl = HW_MOBILE_TOKEN_TTL): string
    {
        $role = hw_normalize_role($role);
        $allowed = ['owner', 'vet_staff', 'head_vet'];
        if (!in_array($role, $allowed, true)) {
            throw new InvalidArgumentException('Invalid mobile token role.');
        }

        $now = time();
        $payload = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'prc_license_no' => ($prcLicenseNo !== null && $prcLicenseNo !== '') ? $prcLicenseNo : null,
            'iat' => $now,
            'exp' => $now + max(60, $ttl),
        ];

        $payloadEncoded = hw_mobile_b64url_encode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $payloadEncoded, hw_get_master_key(), true);
        return $payloadEncoded . '.' . hw_mobile_b64url_encode($signature);
    }

    function hw_mobile_verify_token(string $token): ?array
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadEncoded, $sigEncoded] = $parts;
        if ($payloadEncoded === '' || $sigEncoded === '') {
            return null;
        }

        $providedSig = hw_mobile_b64url_decode($sigEncoded);
        $payloadJson = hw_mobile_b64url_decode($payloadEncoded);
        if ($providedSig === null || $payloadJson === null) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $payloadEncoded, hw_get_master_key(), true);
        if (!hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = isset($payload['exp']) ? (int)$payload['exp'] : 0;
        if ($exp <= time()) {
            return null;
        }

        $role = hw_normalize_role((string)($payload['role'] ?? ''));
        $allowed = ['owner', 'vet_staff', 'head_vet'];
        if (!in_array($role, $allowed, true)) {
            return null;
        }

        $userId = (int)($payload['user_id'] ?? 0);
        $username = trim((string)($payload['username'] ?? ''));
        if ($userId <= 0 || $username === '') {
            return null;
        }

        $prc = $payload['prc_license_no'] ?? null;
        if ($prc !== null) {
            $prc = trim((string)$prc);
            if ($prc === '') {
                $prc = null;
            }
        }

        return [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'prc_license_no' => $prc,
            'iat' => (int)($payload['iat'] ?? 0),
            'exp' => $exp,
        ];
    }

    function hw_mobile_get_bearer_token(): ?string
    {
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strtolower((string)$name) === 'authorization') {
                        $header = (string)$value;
                        break;
                    }
                }
            }
        }

        if ($header !== '' && preg_match('/^\s*Bearer\s+(.+)$/i', $header, $m)) {
            $token = trim($m[1]);
            return $token !== '' ? $token : null;
        }

        return null;
    }

    function hw_mobile_require_role(array $allowedRoles): array
    {
        $token = hw_mobile_get_bearer_token();
        if ($token === null) {
            hw_mobile_json_response(401, [
                'success' => false,
                'error' => 'authentication_required',
                'message' => 'Missing or invalid Authorization Bearer token.',
            ]);
        }

        $identity = hw_mobile_verify_token($token);
        if ($identity === null) {
            hw_mobile_json_response(401, [
                'success' => false,
                'error' => 'invalid_or_expired_token',
                'message' => 'Authentication token is invalid or expired.',
            ]);
        }

        $normalizedAllowed = array_values(array_unique(array_map(static function ($r): string {
            return hw_normalize_role((string)$r);
        }, $allowedRoles)));
        if (!in_array($identity['role'], $normalizedAllowed, true)) {
            hw_mobile_json_response(403, [
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Forbidden: insufficient role permissions.',
            ]);
        }

        return $identity;
    }
}
