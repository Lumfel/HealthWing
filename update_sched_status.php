<?php
/**
 * HealthWing / VetSystem — Authenticated Schedule Status Update & Room Sync (update_sched_status.php)
 *
 * Fixes the security flaw where `update_sched_status.php`had no session/role authentication,
 * and synchronizes room status ('ongoing'when In Progress, 'idle'when Completed/Cancelled).
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';
$isOwner = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'owner';

if (!$isVet && !$isAdmin && !$isOwner) {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=> false, 'message'=>'POST required']);
    exit;
}

require_once __DIR__ . '/includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['schedule_id'], $input['status'])) {
    echo json_encode(['success'=> false, 'message'=>'Missing schedule_id or status']);
    exit;
}

$scheduleId = (int)$input['schedule_id'];
$status = trim($input['status']);
$allowed = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success'=> false, 'message'=>'Invalid status']);
    exit;
}

try {
    $conn = hw_get_db();

    // Owners may only cancel their own Pending appointments
    if ($isOwner && !$isVet && !$isAdmin) {
        if ($status !== 'Cancelled') {
            http_response_code(403);
            echo json_encode(['success'=> false, 'message'=>'Owners may only cancel their own appointments.']);
            $conn->close();
            exit;
        }
        $oStmt = $conn->prepare("SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1");
        $oStmt->bind_param('s', $_SESSION['username']);
        $oStmt->execute();
        $oRow = $oStmt->get_result()->fetch_assoc();
        $oStmt->close();
        if (!$oRow) {
            echo json_encode(['success'=> false, 'message'=>'Owner account not found.']);
            $conn->close();
            exit;
        }
        $ownerId = (int)$oRow['Owner_ID'];
        $stmt = $conn->prepare("UPDATE schedule SET Status = 'Cancelled'WHERE Schedule_ID = ? AND Owner_ID = ?");
        $stmt->bind_param('ii', $scheduleId, $ownerId);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>$ok]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("UPDATE schedule SET Status = ? WHERE Schedule_ID = ?");
    $stmt->bind_param("si", $status, $scheduleId);
    $ok = $stmt->execute();
    $stmt->close();

    // Sync assigned Room status if applicable
    $rLook = $conn->prepare("SELECT Room_ID FROM schedule WHERE Schedule_ID = ? LIMIT 1");
    $rLook->bind_param('i', $scheduleId);
    $rLook->execute();
    if ($rRow = $rLook->get_result()->fetch_assoc()) {
        $roomId = (int)($rRow['Room_ID'] ?? 0);
        if ($roomId >0) {
            if ($status === 'In Progress') {
                $rUp = $conn->prepare("UPDATE room SET Status = 'ongoing', Current_Schedule_ID = ? WHERE Room_ID = ?");
                $rUp->bind_param('ii', $scheduleId, $roomId);
                $rUp->execute();
                $rUp->close();
            } elseif (in_array($status, ['Completed', 'Cancelled'], true)) {
                $rUp = $conn->prepare("UPDATE room SET Status = 'idle', Current_Schedule_ID = NULL WHERE Room_ID = ? AND Current_Emergency_ID IS NULL");
                $rUp->bind_param('i', $roomId);
                $rUp->execute();
                $rUp->close();
            }
        }
    }
    $rLook->close();

    echo json_encode(['success'=>$ok]);
    $conn->close();
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'message'=>'Database error.']);
}
