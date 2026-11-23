<?php
/**
 * HealthWing / VetSystem — Independent Cloudflare Turnstile Security Module (turnstile.php)
 *
 * Works in TWO ways:
 *   1. Included in any PHP file (`require_once __DIR__ . '/turnstile.php';`):
 *      - `hw_turnstile_script_tag()`    -> Outputs the official Cloudflare Turnstile <script> tag
 *      - `hw_turnstile_widget($action)` -> Outputs the <div class="cf-turnstile" ...> widget
 *      - `hw_turnstile_verify($token)`  -> Validates `cf-turnstile-response` against Cloudflare Siteverify
 *   2. Called directly in the browser or via AJAX (`/turnstile.php`):
 *      - `GET /turnstile.php?action=config` -> Returns JSON config (`site_key`, `enabled`, `mode`)
 *      - `POST /turnstile.php`              -> Verifies a token and issues an AES-256-GCM clearance cookie
 *      - `GET /turnstile.php` (in browser)  -> Renders an interactive Turnstile Diagnostic & Test Console
 *
 * Official Cloudflare Turnstile Endpoints & Local Test Keys:
 *   - Client Script: https://challenges.cloudflare.com/turnstile/v0/api.js
 *   - Siteverify API: https://challenges.cloudflare.com/turnstile/v0/siteverify
 *   - Localhost Always-Pass Site Key:   1x00000000000000000000AA
 *   - Localhost Always-Pass Secret Key: 1x0000000000000000000000000000000AA
 */

require_once __DIR__ . '/includes/security_crypto.php';

if (!defined('HW_TURNSTILE_LOADED')) {
    define('HW_TURNSTILE_LOADED', true);

    /**
     * Returns the active Cloudflare Turnstile configuration.
     * Uses environment variables `CF_TURNSTILE_SITE_KEY` and `CF_TURNSTILE_SECRET_KEY` when set,
     * and defaults to Cloudflare's official Always-Pass keys on localhost / 127.0.0.1.
     */
    function hw_turnstile_config(): array
    {
        $siteKey = getenv('CF_TURNSTILE_SITE_KEY') ?: '1x00000000000000000000AA';
        $secretKey = getenv('CF_TURNSTILE_SECRET_KEY') ?: '1x0000000000000000000000000000000AA';
        $enabledEnv = getenv('CF_TURNSTILE_ENABLED');
        $enabled = ($enabledEnv === false || $enabledEnv === '') ? true : filter_var($enabledEnv, FILTER_VALIDATE_BOOLEAN);
        $mode = getenv('CF_TURNSTILE_MODE') ?: 'managed'; // 'managed' | 'non-interactive' | 'invisible'
        $failOpenOffline = true; // Prevents local Laragon lockouts if PC has no internet connection

        return [
            'enabled' => $enabled,
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
            'mode' => $mode,
            'fail_open_offline' => $failOpenOffline,
            'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'is_test_key' => ($siteKey === '1x00000000000000000000AA'),
        ];
    }

    /**
     * Outputs the Cloudflare Turnstile preconnect hint and async/defer script tag.
     */
    function hw_turnstile_script_tag(): string
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled']) {
            return '';
        }
        return '<link rel="preconnect" href="https://challenges.cloudflare.com">' . "\n"
             . '<script src="' . htmlspecialchars($cfg['script_url'], ENT_QUOTES, 'UTF-8') . '" async defer></script>';
    }

    /**
     * Renders the Cloudflare Turnstile widget container (<div class="cf-turnstile" ...>).
     */
    function hw_turnstile_widget(string $action = 'login', string $theme = 'light', string $size = 'flexible'): string
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled']) {
            return '';
        }
        $siteKey = htmlspecialchars($cfg['site_key'], ENT_QUOTES, 'UTF-8');
        $act = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $thm = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
        $sz = htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
        return '<div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-action="' . $act . '" data-theme="' . $thm . '" data-size="' . $sz . '"></div>';
    }

    /**
     * Extracts the Turnstile response token from an array payload, $_POST, or JSON body.
     */
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
     * Verifies a Cloudflare Turnstile token against https://challenges.cloudflare.com/turnstile/v0/siteverify.
     * Issues a 15-minute AES-256-GCM pre-clearance cookie (`hw_cf_clearance_gcm`) upon verification.
     */
    function hw_turnstile_verify(?string $token = null, ?string $expectedAction = null, ?string $remoteIp = null): array
    {
        $cfg = hw_turnstile_config();
        if (!$cfg['enabled']) {
            return ['success' => true, 'bypassed' => true, 'reason' => 'Turnstile disabled via configuration'];
        }

        // Check if client already holds a valid AES-256-GCM Turnstile pre-clearance cookie (15 min TTL)
        $clearance = hw_get_encrypted_cookie('hw_cf_clearance_gcm');
        if ($token === null || $token === '') {
            if (is_array($clearance) && !empty($clearance['verified'])) {
                return [
                    'success' => true,
                    'cached_clearance' => true,
                    'verified_at' => $clearance['verified_at'] ?? null,
                ];
            }
            // If running on localhost with test key and widget was not yet solved/loaded (e.g. offline Laragon)
            if ($cfg['is_test_key'] && $cfg['fail_open_offline']) {
                return [
                    'success' => true,
                    'localhost_test_pass' => true,
                    'reason' => 'Localhost development mode with official Cloudflare test key',
                ];
            }
            return [
                'success' => false,
                'error-codes' => ['missing-input-response'],
                'message' => 'Please complete the Cloudflare Turnstile security check.',
            ];
        }

        $ip = $remoteIp ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
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

        // Graceful fail-open if Laragon has no outbound internet connectivity
        if ($rawResponse === false || $rawResponse === '') {
            if ($cfg['fail_open_offline']) {
                return [
                    'success' => true,
                    'offline_fallback' => true,
                    'reason' => 'Cloudflare siteverify unreachable (offline local environment); fail-open applied.',
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
            if ($expectedAction !== null && !empty($decoded['action']) && $decoded['action'] !== $expectedAction) {
                return [
                    'success' => false,
                    'error-codes' => ['action-mismatch'],
                    'message' => 'Turnstile challenge action mismatch.',
                ];
            }
            // Issue 15-minute AES-256-GCM pre-clearance cookie so rapid retries do not lock out the user
            hw_set_encrypted_cookie('hw_cf_clearance_gcm', [
                'verified' => true,
                'ip' => $ip,
                'action' => $decoded['action'] ?? ($expectedAction ?: 'general'),
                'verified_at' => date('c'),
            ], 900, true);

            return [
                'success' => true,
                'hostname' => $decoded['hostname'] ?? 'localhost',
                'challenge_ts' => $decoded['challenge_ts'] ?? date('c'),
                'action' => $decoded['action'] ?? ($expectedAction ?: 'general'),
            ];
        }

        return [
            'success' => false,
            'error-codes' => $decoded['error-codes'] ?? ['verification-failed'],
            'message' => 'Cloudflare Turnstile verification failed. Please refresh and try again.',
        ];
    }
}

// ---------------------------------------------------------------------------
// Direct Execution Mode: When `turnstile.php` is requested directly via HTTP
// ---------------------------------------------------------------------------
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
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
        header('Content-Type: application/json');
        $rawBody = file_get_contents('php://input');
        $jsonBody = $rawBody ? json_decode($rawBody, true) : null;
        $token = hw_turnstile_extract_token(is_array($jsonBody) ? $jsonBody : $_POST);
        $expectedAction = is_array($jsonBody) ? ($jsonBody['action'] ?? null) : ($_POST['action'] ?? null);
        $result = hw_turnstile_verify($token, $expectedAction);
        echo json_encode($result);
        exit();
    }

    // Interactive Browser Diagnostic & Integration Console (GET /turnstile.php)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>HealthWing | Cloudflare Turnstile Independent Security Module</title>
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
                This independent module (<code>turnstile.php</code>) protects login, registration, and emergency booking endpoints against automated bots and credential stuffing while issuing an AES-256-GCM pre-clearance cookie (<code>hw_cf_clearance_gcm</code>).
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
                const res = await fetch('turnstile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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
