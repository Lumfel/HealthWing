<?php
/**
 * HealthWing / VetSystem — Authenticated RFC 6455 WebSocket Server (ws_server.php)
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'service' => 'HealthWing WebSocket Hub',
        'usage' => 'Run `php ws_server.php` in terminal or launch start_websocket.bat behind a TLS reverse proxy (wss://)',
        'default_host' => '127.0.0.1',
        'default_port' => 8080,
    ]);
    exit;
}

require_once __DIR__ . '/includes/security_crypto.php';
// Ensure the shared master key can be loaded/created cleanly
hw_get_master_key();

$port = isset($argv[1]) ? (int)$argv[1] : (int)(getenv('HW_WS_PORT') ?: 8080);
$envHost = getenv('HW_WS_BIND_HOST');
if ($envHost === false || trim($envHost) === '') {
    $envHost = getenv('WS_BIND_HOST');
}
$address = ($envHost !== false && trim($envHost) !== '') ? trim($envHost) : '127.0.0.1';

$serverSocket = stream_socket_server("tcp://{$address}:{$port}", $errno, $errstr);
if (!$serverSocket) {
    fwrite(STDERR, "Failed to bind WebSocket server on {$address}:{$port}: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($serverSocket, false);

echo "============================================================\n";
echo "HealthWing VetSystem WebSocket Hub running on ws://{$address}:{$port}\n";
echo "Supporting: HMAC-Authenticated Real-Time Video/Audio, Chat & Triage\n";
echo "============================================================\n";

/** @var array<int, resource> $clients */
$clients = [];
/** @var array<int, array> $clientMeta */
$clientMeta = [];

function ws_encode_frame(string $payload, int $opcode = 0x1): string
{
    $length = strlen($payload);
    $head = chr(0x80 | ($opcode & 0x0F));
    if ($length <= 125) {
        $head .= chr($length);
    } elseif ($length <= 65535) {
        $head .= chr(126) . pack('n', $length);
    } else {
        $head .= chr(127) . pack('J', $length);
    }
    return $head . $payload;
}

function ws_decode_frames(string &$buffer): array
{
    $messages = [];
    $maxPayload = 65536;
    while (strlen($buffer) >= 2) {
        $firstByte = ord($buffer[0]);
        $secondByte = ord($buffer[1]);
        $opcode = $firstByte & 0x0F;
        $isMasked = ($secondByte & 0x80) !== 0;
        $payloadLen = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLen === 126) {
            if (strlen($buffer) < 4) {
                break;
            }
            $payloadLen = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLen === 127) {
            if (strlen($buffer) < 10) {
                break;
            }
            $payloadLen = unpack('J', substr($buffer, 2, 8))[1];
            $offset = 10;
        }

        if ($payloadLen > $maxPayload) {
            return [['opcode' => 0x8, 'payload' => '', 'error' => 'frame_too_large']];
        }

        $maskKey = '';
        if ($isMasked) {
            if (strlen($buffer) < $offset + 4) {
                break;
            }
            $maskKey = substr($buffer, $offset, 4);
            $offset += 4;
        } else {
            return [['opcode' => 0x8, 'payload' => '', 'error' => 'unmasked_frame']];
        }

        if (strlen($buffer) < $offset + $payloadLen) {
            break;
        }

        $data = substr($buffer, $offset, $payloadLen);
        $buffer = substr($buffer, $offset + $payloadLen);

        $unmasked = '';
        for ($i = 0; $i < $payloadLen; $i++) {
            $unmasked .= $data[$i] ^ $maskKey[$i % 4];
        }
        $data = $unmasked;

        $messages[] = ['opcode' => $opcode, 'payload' => $data];
    }
    return $messages;
}

function ws_origin_allowed(string $headers): bool
{
    if (!preg_match('/Origin:\s*(.+)\r\n/i', $headers, $originMatch)) {
        return false;
    }
    $origin = trim($originMatch[1]);
    $allowed = array_filter(array_map('trim', explode(',', (string)(getenv('HW_WS_ALLOWED_ORIGINS') ?: ''))));
    if (!empty($allowed)) {
        return in_array($origin, $allowed, true);
    }
    $host = '';
    if (preg_match('/Host:\s*(.+)\r\n/i', $headers, $hostMatch)) {
        $host = trim($hostMatch[1]);
    }
    $originParts = parse_url($origin);
    $originHost = strtolower((string)($originParts['host'] ?? ''));
    if ($originHost === '') {
        return false;
    }
    $originPort = isset($originParts['port']) ? ':' . $originParts['port'] : '';
    $originAuthority = $originHost . $originPort;
    $hostNorm = strtolower($host);
    return $hostNorm !== '' && ($originAuthority === $hostNorm || $originHost === preg_replace('/:\d+$/', '', $hostNorm));
}

function ws_perform_handshake($client, string $headers): bool
{
    if (!ws_origin_allowed($headers)) {
        return false;
    }
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $headers, $matches)) {
        return false;
    }
    $key = trim($matches[1]);
    $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n"
             . "Upgrade: websocket\r\n"
             . "Connection: Upgrade\r\n"
             . "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
    @fwrite($client, $upgrade);
    return true;
}

function ws_channel_authorized(array $meta, string $channel): bool
{
    if (empty($meta['authenticated']) || time() > (int)($meta['token_exp'] ?? 0)) {
        return false;
    }
    $role = hw_normalize_role((string)($meta['role'] ?? 'guest'));
    if (in_array($role, ['vet_staff', 'head_vet'], true)) {
        return true;
    }
    if ($role === 'owner') {
        $allowed = $meta['allowed_channels'] ?? [];
        return in_array($channel, $allowed, true);
    }
    return false;
}

function ws_broadcast(array $clients, array $clientMeta, array $packet, ?string $channelFilter = null, ?int $excludeId = null): void
{
    $frame = ws_encode_frame((string)json_encode($packet));
    foreach ($clients as $id => $sock) {
        if ($excludeId !== null && $id === $excludeId) {
            continue;
        }
        if (empty($clientMeta[$id]['handshaked']) || empty($clientMeta[$id]['authenticated'])) {
            continue;
        }
        if (time() > (int)($clientMeta[$id]['token_exp'] ?? 0)) {
            continue;
        }
        if ($channelFilter !== null) {
            $subs = $clientMeta[$id]['channels'] ?? [];
            $role = hw_normalize_role((string)($clientMeta[$id]['role'] ?? 'guest'));
            if (!in_array($role, ['vet_staff', 'head_vet'], true) && !in_array($channelFilter, $subs, true)) {
                continue;
            }
        }
        @fwrite($sock, $frame);
    }
}

while (true) {
    $read = array_values($clients);
    $read[] = $serverSocket;
    $write = null;
    $except = null;

    if (@stream_select($read, $write, $except, 1) === false) {
        continue;
    }

    if (in_array($serverSocket, $read, true)) {
        $newSock = @stream_socket_accept($serverSocket, 0);
        if ($newSock) {
            stream_set_blocking($newSock, false);
            $id = (int)$newSock;
            $clients[$id] = $newSock;
            $clientMeta[$id] = [
                'handshaked' => false,
                'authenticated' => false,
                'buffer' => '',
                'role' => 'guest',
                'user_id' => null,
                'name' => 'Visitor',
                'channels' => [],
                'allowed_channels' => [],
                'token_exp' => 0,
            ];
        }
        unset($read[array_search($serverSocket, $read, true)]);
    }

    foreach ($read as $sock) {
        $id = (int)$sock;
        $chunk = @fread($sock, 65535);
        if ($chunk === '' || $chunk === false) {
            if (feof($sock)) {
                @fclose($sock);
                unset($clients[$id], $clientMeta[$id]);
            }
            continue;
        }

        if (!$clientMeta[$id]['handshaked']) {
            $clientMeta[$id]['buffer'] .= $chunk;
            if (strlen($clientMeta[$id]['buffer']) > 65536) {
                @fclose($sock);
                unset($clients[$id], $clientMeta[$id]);
                continue;
            }
            if (strpos($clientMeta[$id]['buffer'], "\r\n\r\n") !== false) {
                if (ws_perform_handshake($sock, $clientMeta[$id]['buffer'])) {
                    $clientMeta[$id]['handshaked'] = true;
                    $clientMeta[$id]['buffer'] = '';
                    @fwrite($sock, ws_encode_frame((string)json_encode([
                        'type' => 'connected',
                        'auth_required' => true,
                        'timestamp' => date('c'),
                    ])));
                } else {
                    @fclose($sock);
                    unset($clients[$id], $clientMeta[$id]);
                }
            }
            continue;
        }

        $clientMeta[$id]['buffer'] .= $chunk;
        if (strlen($clientMeta[$id]['buffer']) > 65536) {
            @fclose($sock);
            unset($clients[$id], $clientMeta[$id]);
            continue;
        }
        $frames = ws_decode_frames($clientMeta[$id]['buffer']);

        foreach ($frames as $frame) {
            $op = $frame['opcode'];
            if ($op === 0x8) {
                @fclose($sock);
                unset($clients[$id], $clientMeta[$id]);
                break;
            }
            if ($op === 0x9) {
                @fwrite($sock, ws_encode_frame($frame['payload'], 0xA));
                continue;
            }
            if ($op !== 0x1) {
                continue;
            }

            $msg = json_decode($frame['payload'], true);
            if (!is_array($msg) || empty($msg['type'])) {
                continue;
            }

            if ($msg['type'] === 'auth') {
                $token = trim((string)($msg['token'] ?? ''));
                $claims = $token !== '' ? hw_verify_ws_token($token) : null;
                if ($claims === null) {
                    $clientMeta[$id]['authenticated'] = false;
                    $clientMeta[$id]['role'] = 'guest';
                    @fwrite($sock, ws_encode_frame((string)json_encode([
                        'type' => 'auth_error',
                        'message' => 'Invalid or expired WebSocket authentication token.',
                    ])));
                    continue;
                }

                $clientMeta[$id]['authenticated'] = true;
                $clientMeta[$id]['role'] = (string)$claims['role'];
                $clientMeta[$id]['user_id'] = (int)($claims['user_id'] ?? 0);
                $clientMeta[$id]['name'] = (string)($claims['name'] ?? 'User');
                $allowedCh = is_array($claims['channels'] ?? null) ? array_values(array_unique($claims['channels'])) : [];
                $clientMeta[$id]['allowed_channels'] = $allowedCh;
                $clientMeta[$id]['channels'] = $allowedCh;
                $clientMeta[$id]['token_exp'] = (int)$claims['exp'];

                @fwrite($sock, ws_encode_frame((string)json_encode([
                    'type' => 'auth_ok',
                    'role' => $clientMeta[$id]['role'],
                    'user_id' => $clientMeta[$id]['user_id'],
                    'expires_at' => $clientMeta[$id]['token_exp'],
                ])));
                continue;
            }

            if (empty($clientMeta[$id]['authenticated']) || time() > (int)$clientMeta[$id]['token_exp']) {
                @fwrite($sock, ws_encode_frame((string)json_encode([
                    'type' => 'auth_error',
                    'message' => 'Authentication required before sending WebSocket events.',
                ])));
                continue;
            }

            switch ($msg['type']) {
                case 'subscribe':
                    $ch = trim((string)($msg['channel_key'] ?? ''));
                    if ($ch !== '' && ws_channel_authorized($clientMeta[$id], $ch)) {
                        if (!in_array($ch, $clientMeta[$id]['channels'], true)) {
                            $clientMeta[$id]['channels'][] = $ch;
                        }
                    }
                    break;

                case 'chat_message':
                    $ch = trim((string)($msg['channel_key'] ?? ''));
                    $text = trim((string)($msg['message_text'] ?? ''));
                    $msgType = in_array($msg['message_type'] ?? 'text', ['text', 'system_alert', 'triage_notice', 'call_event'], true) ? $msg['message_type'] : 'text';
                    $senderRole = hw_normalize_role((string)$clientMeta[$id]['role']);
                    if ($ch === '' || $text === '' || !ws_channel_authorized($clientMeta[$id], $ch)) {
                        break;
                    }
                    if (in_array($msgType, ['system_alert', 'triage_notice'], true) && !in_array($senderRole, ['vet_staff', 'head_vet'], true)) {
                        break;
                    }
                    ws_broadcast($clients, $clientMeta, [
                        'type' => 'chat_message',
                        'channel_key' => $ch,
                        'message' => [
                            'Channel_Key' => $ch,
                            'Sender_Role' => $senderRole,
                            'Sender_ID' => $clientMeta[$id]['user_id'],
                            'Sender_Name' => $clientMeta[$id]['name'],
                            'Message_Text' => $text,
                            'Message_Type' => $msgType,
                            'Created_At' => date('Y-m-d H:i:s'),
                        ],
                    ], $ch);
                    break;

                case 'typing':
                    $ch = trim((string)($msg['channel_key'] ?? ''));
                    if ($ch !== '' && ws_channel_authorized($clientMeta[$id], $ch)) {
                        ws_broadcast($clients, $clientMeta, [
                            'type' => 'typing',
                            'channel_key' => $ch,
                            'sender_name' => $clientMeta[$id]['name'],
                            'sender_role' => $clientMeta[$id]['role'],
                        ], $ch, $id);
                    }
                    break;

                case 'webrtc_signal':
                    $bookingId = (int)($msg['booking_id'] ?? 0);
                    $ch = $bookingId > 0 ? ('booking_' . $bookingId) : '';
                    $signalType = trim((string)($msg['signal_type'] ?? 'ice'));
                    $allowedSignals = ['offer', 'answer', 'ice', 'hangup', 'decline', 'busy'];
                    if ($bookingId <= 0 || $ch === '' || !in_array($signalType, $allowedSignals, true) || !ws_channel_authorized($clientMeta[$id], $ch)) {
                        break;
                    }
                    ws_broadcast($clients, $clientMeta, [
                        'type' => 'webrtc_signal',
                        'signal_type' => $signalType,
                        'booking_id' => $bookingId,
                        'call_id' => $msg['call_id'] ?? null,
                        'call_type' => in_array(($msg['call_type'] ?? 'video'), ['video', 'audio'], true) ? $msg['call_type'] : 'video',
                        'sender_role' => hw_normalize_role((string)$clientMeta[$id]['role']),
                        'sender_name' => $clientMeta[$id]['name'],
                        'payload' => $msg['payload'] ?? null,
                    ], $ch, $id);
                    break;

                case 'triage_broadcast':
                    if (!in_array(hw_normalize_role((string)$clientMeta[$id]['role']), ['vet_staff', 'head_vet'], true)) {
                        break;
                    }
                    ws_broadcast($clients, $clientMeta, [
                        'type' => 'triage_broadcast',
                        'timestamp' => date('c'),
                        'summary' => $msg['summary'] ?? null,
                    ]);
                    break;
            }
        }
    }
}
