<?php
/**
 * HealthWing / VetSystem — Dedicated RFC 6455 WebSocket Server (ws_server.php)
 *
 * Zero-dependency native PHP CLI WebSocket Server for:
 * 1. Direct real-time bidirectional Chat (`chat_message`, `typing`, `read_receipt`)
 * 2. Instant WebRTC Video & Voice Call Signaling (`webrtc_offer`, `webrtc_answer`, `webrtc_ice`, `call_ring`, `call_end`)
 * 3. Live Emergency Triage & Schedule Preemption Broadcasts (`emergency_triage_update`, `schedule_update`)
 *
 * Usage:
 *   php ws_server.php [port]
 *   Default port: 8080 (ws://localhost:8080)
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'service'=>'HealthWing WebSocket Hub',
        'usage'=>'Run `php ws_server.php`in terminal or double-click start_websocket.bat',
        'default_port'=>8080,
    ]);
    exit;
}

require_once __DIR__ . '/includes/db.php';

$port = isset($argv[1]) ? (int)$argv[1] : 8080;
$address = '0.0.0.0';

$serverSocket = stream_socket_server("tcp://{$address}:{$port}", $errno, $errstr);
if (!$serverSocket) {
    fwrite(STDERR, "Failed to bind WebSocket server on port {$port}: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($serverSocket, false);

echo "============================================================\n";
echo "HealthWing VetSystem WebSocket Hub running on ws://{$address}:{$port}\n";
echo "Supporting: Real-Time Video/Audio Signaling, Chat & Triage\n";
echo "============================================================\n";

/** @var array<int, resource>$clients */
$clients = [];
/** @var array<int, array>$clientMeta */
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

        $maskKey = '';
        if ($isMasked) {
            if (strlen($buffer) < $offset + 4) {
                break;
            }
            $maskKey = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if (strlen($buffer) < $offset + $payloadLen) {
            break;
        }

        $data = substr($buffer, $offset, $payloadLen);
        $buffer = substr($buffer, $offset + $payloadLen);

        if ($isMasked) {
            $unmasked = '';
            for ($i = 0; $i < $payloadLen; $i++) {
                $unmasked .= $data[$i] ^ $maskKey[$i % 4];
            }
            $data = $unmasked;
        }

        $messages[] = ['opcode'=>$opcode, 'payload'=>$data];
    }
    return $messages;
}

function ws_perform_handshake($client, string $headers): bool
{
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

function ws_broadcast(array $clients, array $clientMeta, array $packet, ?string $channelFilter = null, ?int $excludeId = null): void
{
    $frame = ws_encode_frame(json_encode($packet));
    foreach ($clients as $id =>$sock) {
        if ($excludeId !== null && $id === $excludeId) {
            continue;
        }
        if (empty($clientMeta[$id]['handshaked'])) {
            continue;
        }
        if ($channelFilter !== null) {
            $subs = $clientMeta[$id]['channels'] ?? [];
            $role = $clientMeta[$id]['role'] ?? '';
            // Vets and admins receive all clinic channel events; owners receive subscribed channels
            if ($role !== 'vet'&& $role !== 'admin'&& !in_array($channelFilter, $subs, true)) {
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
                'handshaked'=> false,
                'buffer'=>'',
                'role'=>'guest',
                'user_id'=> null,
                'name'=>'Visitor',
                'channels'=>[],
            ];
        }
        unset($read[array_search($serverSocket, $read, true)]);
    }

    foreach ($read as $sock) {
        $id = (int)$sock;
        $chunk = @fread($sock, 65535);
        if ($chunk === ''|| $chunk === false) {
            if (feof($sock)) {
                @fclose($sock);
                unset($clients[$id], $clientMeta[$id]);
            }
            continue;
        }

        if (!$clientMeta[$id]['handshaked']) {
            $clientMeta[$id]['buffer'] .= $chunk;
            if (strpos($clientMeta[$id]['buffer'], "\r\n\r\n") !== false) {
                if (ws_perform_handshake($sock, $clientMeta[$id]['buffer'])) {
                    $clientMeta[$id]['handshaked'] = true;
                    $clientMeta[$id]['buffer'] = '';
                    @fwrite($sock, ws_encode_frame(json_encode([
                        'type'=>'connected',
                        'timestamp'=> date('c'),
                    ])));
                } else {
                    @fclose($sock);
                    unset($clients[$id], $clientMeta[$id]);
                }
            }
            continue;
        }

        $clientMeta[$id]['buffer'] .= $chunk;
        $frames = ws_decode_frames($clientMeta[$id]['buffer']);

        foreach ($frames as $frame) {
            $op = $frame['opcode'];
            if ($op === 0x8) {
                // Close frame
                @fclose($sock);
                unset($clients[$id], $clientMeta[$id]);
                break;
            }
            if ($op === 0x9) {
                // Ping ->Pong
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

            switch ($msg['type']) {
                case 'auth':
                    $clientMeta[$id]['role'] = $msg['role'] ?? 'guest';
                    $clientMeta[$id]['user_id'] = $msg['user_id'] ?? null;
                    $clientMeta[$id]['name'] = $msg['name'] ?? 'User';
                    if (!empty($msg['channels']) && is_array($msg['channels'])) {
                        $clientMeta[$id]['channels'] = array_values(array_unique($msg['channels']));
                    }
                    break;

                case 'subscribe':
                    $ch = trim($msg['channel_key'] ?? '');
                    if ($ch !== ''&& !in_array($ch, $clientMeta[$id]['channels'], true)) {
                        $clientMeta[$id]['channels'][] = $ch;
                    }
                    break;

                case 'chat_message':
                    $ch = trim($msg['channel_key'] ?? '');
                    $text = trim($msg['message_text'] ?? '');
                    if ($ch === ''|| $text === '') {
                        break;
                    }
                    // Broadcast immediately to all channel participants
                    ws_broadcast($clients, $clientMeta, [
                        'type'=>'chat_message',
                        'channel_key'=>$ch,
                        'message'=>$msg['message'] ?? [
                            'Channel_Key'=>$ch,
                            'Sender_Role'=>$clientMeta[$id]['role'],
                            'Sender_ID'=>$clientMeta[$id]['user_id'],
                            'Sender_Name'=>$clientMeta[$id]['name'],
                            'Message_Text'=>$text,
                            'Message_Type'=>$msg['message_type'] ?? 'text',
                            'Created_At'=> date('Y-m-d H:i:s'),
                        ],
                    ], $ch);
                    break;

                case 'typing':
                    $ch = trim($msg['channel_key'] ?? '');
                    if ($ch !== '') {
                        ws_broadcast($clients, $clientMeta, [
                            'type'=>'typing',
                            'channel_key'=>$ch,
                            'sender_name'=>$clientMeta[$id]['name'],
                            'sender_role'=>$clientMeta[$id]['role'],
                        ], $ch, $id);
                    }
                    break;

                case 'webrtc_signal':
                    // Instant low-latency relay for WebRTC offer, answer, ICE candidates, and call ring/end
                    $ch = !empty($msg['booking_id']) ? ('booking_'. (int)$msg['booking_id']) : null;
                    ws_broadcast($clients, $clientMeta, [
                        'type'=>'webrtc_signal',
                        'signal_type'=>$msg['signal_type'] ?? 'ice',
                        'booking_id'=>$msg['booking_id'] ?? null,
                        'call_id'=>$msg['call_id'] ?? null,
                        'call_type'=>$msg['call_type'] ?? 'video',
                        'sender_role'=>$clientMeta[$id]['role'],
                        'sender_name'=>$clientMeta[$id]['name'],
                        'payload'=>$msg['payload'] ?? null,
                    ], $ch, $id);
                    break;

                case 'triage_broadcast':
                    // Broadcast emergency queue reordering or schedule preemption to all online vets, admins, and owners
                    ws_broadcast($clients, $clientMeta, [
                        'type'=>'triage_broadcast',
                        'timestamp'=> date('c'),
                        'summary'=>$msg['summary'] ?? null,
                    ]);
                    break;
            }
        }
    }
}
