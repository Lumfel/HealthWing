<?php
/**
 * HealthWing / VetSystem — Independent Cloudflare Turnstile Security Module (includes/turnstile.php)
 */

require_once __DIR__ . '/security_crypto.php';

if (!defined('HW_TURNSTILE_LOADED')) {
    define('HW_TURNSTILE_LOADED', true);

    /**
     * Verifies whether the current HTTP request genuinely originates from localhost / 127.0.0.1.
     */
    function hw_is_local_request(): bool
    {
        $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $hostHeader = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
        $hostOnly = preg_replace('/:\d+$/', '', $hostHeader);
        $validLocalAddr = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
        $validLocalHost = in_array($hostOnly, ['localhost', '127.0.0.1', '[::1]'], true);
        $serverAddr = (string)($_SERVER['SERVER_ADDR'] ?? '');
        $validServerAddr = $serverAddr === '' || in_array($serverAddr, ['127.0.0.1', '::1'], true);
        return $validLocalAddr && $validLocalHost && $validServerAddr;
    }

    /**
     * Returns active Cloudflare Turnstile configuration.
     * Always-Pass test keys and offline fail-open are strictly confined to verified localhost requests.
     */
    function hw_turnstile_config(): array
    {
        $isLocal = hw_is_local_request();
        $siteEnv = getenv('CF_TURNSTILE_SITE_KEY');
        $secretEnv = getenv('CF_TURNSTILE_SECRET_KEY');

        $siteKey = ($siteEnv !== false && $siteEnv !== '')
            ? $siteEnv
            : ($isLocal ? '1x00000000000000000000AA' : '');
        $secretKey = ($secretEnv !== false && $secretEnv !== '')
            ? $secretEnv
            : ($isLocal ? '1x0000000000000000000000000000000AA' : '');

        $enabledEnv = getenv('CF_TURNSTILE_ENABLED');
        $enabled = ($enabledEnv === false || $enabledEnv === '')
            ? true
            : filter_var($enabledEnv, FILTER_VALIDATE_BOOLEAN);

        $mode = getenv('CF_TURNSTILE_MODE') ?: 'managed';
        $failOpenOffline = $isLocal;

        return [
            'enabled' => $enabled,
            'is_local' => $isLocal,
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
            'mode' => $mode,
            'fail_open_offline' => $failOpenOffline,
            'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'is_test_key' => ($siteKey === '1x00000000000000000000AA'),
        ];
    }

    function hw_turnstile_script_tag(): string
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled'] || $cfg['site_key'] === '') {
            return '';
        }
        return '<link rel="preconnect" href="https://challenges.cloudflare.com">' . "\n"
             . '<script src="' . htmlspecialchars($cfg['script_url'], ENT_QUOTES, 'UTF-8') . '" async defer></script>';
    }

    function hw_turnstile_widget(string $action = 'login', string $theme = 'light', string $size = 'flexible'): string
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled'] || $cfg['site_key'] === '') {
            return '';
        }
        $siteKey = htmlspecialchars($cfg['site_key'], ENT_QUOTES, 'UTF-8');
        $act = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $thm = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
        $sz = htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
        return '<div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-action="' . $act . '" data-theme="' . $thm . '" data-size="' . $sz . '"></div>';
    }

    function hw_turnstile_render_widget(string $action = 'login', string $theme = 'light', string $size = 'flexible'): string
    {
        return hw_turnstile_widget($action, $theme, $size);
    }

    function hw_turnstile_extract_token(?array $payload = null): ?string
    {
        if (is_array($payload)) {
            if (!empty($payload['cf_turnstile_response'])) {
                return trim((string)$payload['cf_turnstile_response']);
            }
            if (!empty($payload['cf-turnstile-response'])) {
                return trim((string)$payload['cf-turnstile-response']);
            }
            if (!empty($payload['turnstile_token'])) {
                return trim((string)$payload['turnstile_token']);
            }
        }
        if (!empty($_POST['cf-turnstile-response'])) {
            return trim((string)$_POST['cf-turnstile-response']);
        }
        if (!empty($_POST['cf_turnstile_response'])) {
            return trim((string)$_POST['cf_turnstile_response']);
        }
        return null;
    }

    /**
     * Checks whether the client holds a valid AES-256-GCM pre-clearance cookie bound to both IP and action.
     */
    function hw_turnstile_has_valid_clearance(?string $expectedAction, string $clientIp): ?array
    {
        $clearance = hw_get_encrypted_cookie('hw_cf_clearance_gcm');
        if (!is_array($clearance) || empty($clearance['verified'])) {
            return null;
        }
        if (($clearance['ip'] ?? '') !== $clientIp) {
            return null;
        }
        if ($expectedAction !== null && $expectedAction !== '' && ($clearance['action'] ?? '') !== $expectedAction) {
            return null;
        }
        return $clearance;
    }

    /**
     * Verifies a Cloudflare Turnstile token against Siteverify API.
     */
    function hw_turnstile_verify(?string $token = null, ?string $expectedAction = null, ?string $remoteIp = null): array
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled']) {
            return ['success' => true, 'bypassed' => true, 'reason' => 'Turnstile disabled via configuration'];
        }

        if ($cfg['secret_key'] === '') {
            return [
                'success' => false,
                'error-codes' => ['missing-input-secret'],
                'message' => 'Turnstile secret key is not configured for this environment.',
            ];
        }

        $ip = $remoteIp ?: hw_get_client_ip();

        $clearance = hw_turnstile_has_valid_clearance($expectedAction, $ip);
        if ($token === null || $token === '') {
            if ($clearance !== null) {
                return [
                    'success' => true,
                    'cached_clearance' => true,
                    'verified_at' => $clearance['verified_at'] ?? null,
                ];
            }
            if ($cfg['is_local'] && $cfg['is_test_key'] && $cfg['fail_open_offline']) {
                return [
                    'success' => true,
                    'localhost_test_pass' => true,
                    'reason' => 'Localhost development mode with Cloudflare test key',
                ];
            }
            return [
                'success' => false,
                'error-codes' => ['missing-input-response'],
                'message' => 'Please complete the Cloudflare Turnstile security check.',
            ];
        }

        $postFields = [
            'secret' => $cfg['secret_key'],
            'response' => $token,
            'remoteip' => $ip,
            'idempotency_key' => hash('sha256', $token . '|' . $ip),
        ];

        $rawResponse = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($cfg['verify_url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postFields),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $rawResponse = @curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($postFields),
                    'timeout' => 5,
                ],
            ]);
            $rawResponse = @file_get_contents($cfg['verify_url'], false, $context);
        }

        if ($rawResponse === false || $rawResponse === '') {
            if ($cfg['is_local'] && $cfg['fail_open_offline']) {
                return [
                    'success' => true,
                    'offline_fallback' => true,
                    'reason' => 'Cloudflare siteverify unreachable in local environment.',
                ];
            }
            return [
                'success' => false,
                'error-codes' => ['network-unreachable'],
                'message' => 'Could not reach Cloudflare Turnstile verification server.',
            ];
        }

        $decoded = json_decode((string)$rawResponse, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'error-codes' => ['invalid-json-response'],
                'message' => 'Invalid verification response from Cloudflare Turnstile.',
            ];
        }

        if (!empty($decoded['success'])) {
            $resolvedAction = (string)($decoded['action'] ?? ($expectedAction ?: 'general'));
            if ($expectedAction !== null && $expectedAction !== '' && !empty($decoded['action']) && $decoded['action'] !== $expectedAction) {
                return [
                    'success' => false,
                    'error-codes' => ['action-mismatch'],
                    'message' => 'Turnstile challenge action mismatch.',
                ];
            }

            $responseHost = strtolower(trim((string)($decoded['hostname'] ?? '')));
            $requestHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''))));
            if ($responseHost !== '' && $requestHost !== '' && $responseHost !== $requestHost) {
                $allowedHosts = array_filter(array_map('strtolower', array_map('trim', explode(',', (string)(getenv('CF_TURNSTILE_ALLOWED_HOSTNAMES') ?: '')))));
                $allowedHosts[] = $requestHost;
                if (!in_array($responseHost, $allowedHosts, true)) {
                    return [
                        'success' => false,
                        'error-codes' => ['hostname-mismatch'],
                        'message' => 'Turnstile challenge hostname mismatch.',
                    ];
                }
            }

            hw_set_encrypted_cookie('hw_cf_clearance_gcm', [
                'verified' => true,
                'ip' => $ip,
                'action' => $resolvedAction,
                'verified_at' => date('c'),
            ], 900, true);

            return [
                'success' => true,
                'hostname' => $decoded['hostname'] ?? $requestHost,
                'challenge_ts' => $decoded['challenge_ts'] ?? date('c'),
                'action' => $resolvedAction,
            ];
        }

        return [
            'success' => false,
            'error-codes' => $decoded['error-codes'] ?? ['verification-failed'],
            'message' => 'Cloudflare Turnstile verification failed. Please refresh and try again.',
        ];
    }

    /**
     * Handles HTTP requests made directly to turnstile.php.
     */
    function hw_turnstile_handle_http(): void
    {
        hw_start_secure_session();
        $cfg = hw_turnstile_config();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $actionParam = $_GET['action'] ?? '';

        if ($method === 'GET' && $actionParam === 'config') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok',
                'enabled' => $cfg['enabled'],
                'site_key' => $cfg['site_key'],
                'mode' => $cfg['mode'],
                'is_test_key' => $cfg['is_test_key'],
                'script_url' => $cfg['script_url'],
            ]);
            exit();
        }

        if ($method === 'POST') {
            hw_require_csrf();
            header('Content-Type: application/json');
            $rawBody = file_get_contents('php://input');
            $jsonBody = $rawBody ? json_decode($rawBody, true) : null;
            $token = hw_turnstile_extract_token(is_array($jsonBody) ? $jsonBody : $_POST);
            $expectedAction = is_array($jsonBody) ? ($jsonBody['action'] ?? null) : ($_POST['action'] ?? null);
            echo json_encode(hw_turnstile_verify($token, $expectedAction));
            exit();
        }

        if (!function_exists('hw_is_head_vet') || !hw_is_head_vet()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '403 Forbidden: Head veterinarian authentication is required to access the Turnstile diagnostic console.';
            exit();
        }

        $csrfToken = hw_csrf_token();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
            <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <title>HealthWing | Cloudflare Turnstile Security Console</title>
            <?php echo hw_turnstile_script_tag(); ?>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #F4F9FA; color: #16262A; margin: 0; padding: 24px; }
                .card { max-width: 680px; margin: 24px auto; background: #FFFFFF; border: 1px solid #D5E3E6; border-radius: 16px; padding: 24px; box-shadow: 0 8px 24px rgba(22,38,42,0.06); }
                .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #E6F4EA; color: #137333; }
                .btn { background: #1F3A3D; color: #FFFFFF; border: none; border-radius: 10px; padding: 10px 18px; font-weight: 700; cursor: pointer; font-size: 13px; margin-top: 12px; }
                .btn:hover { background: #2B5864; }
                pre { background: #16262A; color: #A7F3D0; padding: 12px; border-radius: 10px; font-size: 12px; overflow-x: auto; }
                .meta { font-size: 13px; color: #4B6366; line-height: 1.5; }
            </style>
        </head>
        <body>
            <div class="card">
                <span class="badge">Cloudflare Turnstile Module Active</span>
                <h1 style="margin: 10px 0 6px; font-size: 22px;">HealthWing Turnstile Verification Console</h1>
                <p class="meta">
                    Protects authentication and emergency endpoints and issues an AES-256-GCM IP/action-bound clearance cookie (<code>hw_cf_clearance_gcm</code>).
                </p>
                <div style="background:#F4F9FA;border:1px solid #D5E3E6;border-radius:12px;padding:16px;margin:16px 0;">
                    <div style="font-size:12px;font-weight:700;margin-bottom:8px;color:#1F3A3D;">Live Widget Test (Sitekey: <code><?php echo htmlspecialchars($cfg['site_key'], ENT_QUOTES, 'UTF-8'); ?></code>)</div>
                    <form id="tsTestForm">
                        <?php echo hw_turnstile_widget('diagnostic_test', 'light', 'normal'); ?>
                        <button type="submit" class="btn">Verify Token with Siteverify API</button>
                    </form>
                </div>
                <div id="tsResultBox" style="display:none;">
                    <div style="font-size:12px;font-weight:700;margin-bottom:4px;">Server-Side Siteverify Response:</div>
                    <pre id="tsResultPre"></pre>
                </div>
            </div>
            <script>
                document.getElementById('tsTestForm').addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const fd = new FormData(e.target);
                    const token = fd.get('cf-turnstile-response') || '';
                    const csrf = document.querySelector('meta[name="hw-csrf-token"]')?.content || '';
                    const res = await fetch('turnstile.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify({ cf_turnstile_response: token, action: 'diagnostic_test' })
                    });
                    const data = await res.json();
                    document.getElementById('tsResultBox').style.display = 'block';
                    document.getElementById('tsResultPre').textContent = JSON.stringify(data, null, 2);
                });
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}
