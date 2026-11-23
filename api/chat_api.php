<?php
/**
 * HealthWing / VetSystem — Unified Real-Time Chat & WebSocket Persistence API (api/chat_api.php)
 *
 * Provides persistent chat history, unread counts, active channel listing, and real-time message
 * delivery for Pet Owners, Veterinarians, and Admins. Works seamlessly alongside the WebSocket
 * server (`ws_server.php`) and acts as an automatic zero-config fallback if the WebSocket CLI
 * daemon is not running.
 */

require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

function hw_chat_current_actor(mysqli $conn): ?array
{
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
        return [
            'role'=>'admin',
            'id'=>(int)($_SESSION['admin_id'] ?? 1),
            'name'=>'Admin ('. ($_SESSION['admin_username'] ?? 'Clinic') . ')',
        ];
    }
    $role = $_SESSION['role'] ?? '';
    $username = $_SESSION['username'] ?? '';
    if ($role === 'Veterinarian'&& $username !== '') {
        $stmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name FROM veterinarian WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return [
                'role'=>'vet',
                'id'=>(int)$row['Veterinarian_ID'],
                'name'=>'Dr. '. trim($row['First_Name'] . ''. $row['Last_Name']),
            ];
        }
    }
    if ($role === 'owner'&& $username !== '') {
        $stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name FROM owner WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return [
                'role'=>'owner',
                'id'=>(int)$row['Owner_ID'],
                'name'=> trim($row['First_Name'] . ''. $row['Last_Name']),
            ];
        }
    }
    return null;
}

try {
    $conn = hw_get_db();
    $actor = hw_chat_current_actor($conn);
    if (!$actor) {
        http_response_code(401);
        echo json_encode(['success'=> false, 'error'=>'Please sign in to use clinic chat & video communication.']);
        $conn->close();
        exit;
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'history');

    // 1. List available conversation channels for the signed-in user
    if ($action === 'channels') {
        $channels = [];

        if ($actor['role'] === 'owner') {
            $ownerId = $actor['id'];
            // Owner's general clinic channel
            $channels[] = [
                'channel_key'=>'owner_'. $ownerId,
                'title'=>'Clinic Care Team (General Direct Chat)',
                'subtitle'=>'Ask questions, share updates, or request a live video consult',
                'booking_id'=> null,
                'schedule_id'=> null,
            ];

            // Active or recent emergency bookings for this owner
            $eStmt = $conn->prepare(
                "SELECT Booking_ID, Severity_Level, Status, Created_At
                 FROM emergency_booking
                 WHERE Owner_ID = ?
                 ORDER BY Booking_ID DESC LIMIT 10"
            );
            $eStmt->bind_param('i', $ownerId);
            $eStmt->execute();
            $eRes = $eStmt->get_result();
            while ($e = $eRes->fetch_assoc()) {
                $channels[] = [
                    'channel_key'=>'booking_'. $e['Booking_ID'],
                    'title'=>'Emergency Case #'. $e['Booking_ID'] . '('. $e['Severity_Level'] . ')',
                    'subtitle'=>'Status: '. $e['Status'] . '· '. $e['Created_At'],
                    'booking_id'=>(int)$e['Booking_ID'],
                    'schedule_id'=> null,
                ];
            }
            $eStmt->close();

            // Scheduled appointments for this owner
            $sStmt = $conn->prepare(
                "SELECT s.Schedule_ID, s.Services, s.Appointment_Time, s.Status, p.Pet_Name
                 FROM schedule s
                 LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
                 WHERE s.Owner_ID = ?
                 ORDER BY s.Appointment_Time DESC LIMIT 10"
            );
            $sStmt->bind_param('i', $ownerId);
            $sStmt->execute();
            $sRes = $sStmt->get_result();
            while ($s = $sRes->fetch_assoc()) {
                $channels[] = [
                    'channel_key'=>'schedule_'. $s['Schedule_ID'],
                    'title'=>'Appt #'. $s['Schedule_ID'] . ': '. ($s['Pet_Name'] ?: 'Pet') . '('. $s['Services'] . ')',
                    'subtitle'=>$s['Appointment_Time'] . '· '. $s['Status'],
                    'booking_id'=> null,
                    'schedule_id'=>(int)$s['Schedule_ID'],
                ];
            }
            $sStmt->close();
        } else {
            // Vet or Admin: list active emergencies, upcoming appointments, and active owner chats
            $eRes = $conn->query(
                "SELECT Booking_ID, Name, Severity_Level, Priority_Score, Status, Queue_Position
                 FROM emergency_booking
                 WHERE Status IN ('Pending', 'In Progress', 'Contact')
                 ORDER BY COALESCE(Queue_Position, 999) ASC, Booking_ID DESC LIMIT 25"
            );
            if ($eRes) {
                while ($e = $eRes->fetch_assoc()) {
                    $qTag = $e['Queue_Position'] ? "[Queue #{$e['Queue_Position']}]": '';
                    $channels[] = [
                        'channel_key'=>'booking_'. $e['Booking_ID'],
                        'title'=>"Case #{$e['Booking_ID']}{$qTag} — ". ($e['Name'] ?: 'Client'),
                        'subtitle'=>"{$e['Severity_Level']} (Score {$e['Priority_Score']}) · {$e['Status']}",
                        'booking_id'=>(int)$e['Booking_ID'],
                        'schedule_id'=> null,
                    ];
                }
            }

            $sRes = $conn->query(
                "SELECT s.Schedule_ID, s.Services, s.Appointment_Time, s.Status,
                        o.First_Name, o.Last_Name, p.Pet_Name
                 FROM schedule s
                 LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID
                 LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
                 WHERE s.Status IN ('Pending', 'Confirmed', 'In Progress')
                 ORDER BY s.Appointment_Time ASC LIMIT 25"
            );
            if ($sRes) {
                while ($s = $sRes->fetch_assoc()) {
                    $ownerLabel = trim(($s['First_Name'] ?? '') . ''. ($s['Last_Name'] ?? '')) ?: 'Client';
                    $channels[] = [
                        'channel_key'=>'schedule_'. $s['Schedule_ID'],
                        'title'=>"Appt #{$s['Schedule_ID']} — {$ownerLabel} ({$s['Pet_Name']})",
                        'subtitle'=>"{$s['Services']} · {$s['Appointment_Time']}",
                        'booking_id'=> null,
                        'schedule_id'=>(int)$s['Schedule_ID'],
                    ];
                }
            }

            // Also include any owner_* direct channels that have messages
            $oRes = $conn->query(
                "SELECT DISTINCT c.Channel_Key, o.Owner_ID, o.First_Name, o.Last_Name
                 FROM chat_messages c
                 JOIN owner o ON c.Channel_Key = CONCAT('owner_', o.Owner_ID)
                 ORDER BY c.Message_ID DESC LIMIT 20"
            );
            if ($oRes) {
                while ($o = $oRes->fetch_assoc()) {
                    $channels[] = [
                        'channel_key'=>$o['Channel_Key'],
                        'title'=>"Direct: {$o['First_Name']} {$o['Last_Name']}",
                        'subtitle'=>'General Owner Inquiry Channel',
                        'booking_id'=> null,
                        'schedule_id'=> null,
                    ];
                }
            }
        }

        echo json_encode([
            'success'=> true,
            'actor'=>$actor,
            'channels'=>$channels,
        ]);
        $conn->close();
        exit;
    }

    // 2. Fetch chat history for a channel
    if ($action === 'history') {
        $channelKey = trim($_GET['channel_key'] ?? '');
        $sinceId = (int)($_GET['since_id'] ?? 0);
        if ($channelKey === '') {
            echo json_encode(['success'=> false, 'error'=>'Missing channel_key']);
            $conn->close();
            exit;
        }

        // Security check: if owner, ensure they only read their own channels
        if ($actor['role'] === 'owner') {
            $allowed = false;
            if ($channelKey === 'owner_'. $actor['id']) {
                $allowed = true;
            } elseif (preg_match('/^booking_(\d+)$/', $channelKey, $m)) {
                $bId = (int)$m[1];
                $chk = $conn->prepare("SELECT 1 FROM emergency_booking WHERE Booking_ID = ? AND Owner_ID = ?");
                $chk->bind_param('ii', $bId, $actor['id']);
                $chk->execute();
                $allowed = $chk->get_result()->num_rows >0;
                $chk->close();
            } elseif (preg_match('/^schedule_(\d+)$/', $channelKey, $m)) {
                $sId = (int)$m[1];
                $chk = $conn->prepare("SELECT 1 FROM schedule WHERE Schedule_ID = ? AND Owner_ID = ?");
                $chk->bind_param('ii', $sId, $actor['id']);
                $chk->execute();
                $allowed = $chk->get_result()->num_rows >0;
                $chk->close();
            }
            if (!$allowed) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=>'Unauthorized channel']);
                $conn->close();
                exit;
            }
        }

        $stmt = $conn->prepare(
            "SELECT Message_ID, Channel_Key, Booking_ID, Schedule_ID, Sender_Role, Sender_ID,
                    Sender_Name, Message_Text, Message_Type, Created_At
             FROM chat_messages
             WHERE Channel_Key = ? AND Message_ID >?
             ORDER BY Message_ID ASC
             LIMIT 200"
        );
        $stmt->bind_param('si', $channelKey, $sinceId);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode([
            'success'=> true,
            'actor'=>$actor,
            'channel_key'=>$channelKey,
            'messages'=>$messages,
        ]);
        $conn->close();
        exit;
    }

    // 3. Send a new chat message
    if ($action === 'send') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $channelKey = trim($input['channel_key'] ?? '');
        $text = trim($input['message_text'] ?? '');
        $msgType = in_array($input['message_type'] ?? 'text', ['text', 'system_alert', 'triage_notice', 'call_event'], true)
            ? $input['message_type']
            : 'text';

        if ($channelKey === ''|| $text === '') {
            echo json_encode(['success'=> false, 'error'=>'Channel and message text are required.']);
            $conn->close();
            exit;
        }

        $bookingId = null;
        $scheduleId = null;
        if (preg_match('/^booking_(\d+)$/', $channelKey, $m)) {
            $bookingId = (int)$m[1];
        } elseif (preg_match('/^schedule_(\d+)$/', $channelKey, $m)) {
            $scheduleId = (int)$m[1];
        }

        $stmt = $conn->prepare(
            "INSERT INTO chat_messages (Channel_Key, Booking_ID, Schedule_ID, Sender_Role, Sender_ID, Sender_Name, Message_Text, Message_Type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'siisisss',
            $channelKey,
            $bookingId,
            $scheduleId,
            $actor['role'],
            $actor['id'],
            $actor['name'],
            $text,
            $msgType
        );
        $ok = $stmt->execute();
        $msgId = $stmt->insert_id;
        $stmt->close();

        echo json_encode([
            'success'=>$ok,
            'message'=>[
                'Message_ID'=>$msgId,
                'Channel_Key'=>$channelKey,
                'Booking_ID'=>$bookingId,
                'Schedule_ID'=>$scheduleId,
                'Sender_Role'=>$actor['role'],
                'Sender_ID'=>$actor['id'],
                'Sender_Name'=>$actor['name'],
                'Message_Text'=>$text,
                'Message_Type'=>$msgType,
                'Created_At'=> date('Y-m-d H:i:s'),
            ],
        ]);
        $conn->close();
        exit;
    }

    echo json_encode(['success'=> false, 'error'=>'Invalid action']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}
