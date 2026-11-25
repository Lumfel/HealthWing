<?php
/**
 * HealthWing / VetSystem — Authenticated Schedule Status Update & Room Sync (update_sched_status.php)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/receipt_blockchain.php';

hw_start_secure_session();
header('Content-Type: application/json');

hw_require_role(['owner', 'vet_staff', 'head_vet'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

hw_require_csrf();

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['schedule_id'], $input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing schedule_id or status']);
    exit;
}

$scheduleId = (int)$input['schedule_id'];
$status = trim((string)$input['status']);
$allowed = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$conn = null;
$lockAcquired = false;
$txStarted = false;

try {
    $conn = hw_get_db();
    $lockAcquired = hw_db_acquire_lock($conn, 'hw_schedule_lock', 5);
    if (!$lockAcquired) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Scheduler is busy. Please retry.']);
        exit;
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start schedule update transaction.');
    }
    $txStarted = true;

    if (hw_is_owner()) {
        if ($status !== 'Cancelled') {
            $conn->rollback();
            $txStarted = false;
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Owners may only cancel their own appointments.']);
            exit;
        }
        $oStmt = $conn->prepare("SELECT Owner_ID FROM owner WHERE username = ? AND Deleted_At IS NULL LIMIT 1");
        $oStmt->bind_param('s', $_SESSION['username']);
        $oStmt->execute();
        $oRow = $oStmt->get_result()->fetch_assoc();
        $oStmt->close();
        if (!$oRow) {
            $conn->rollback();
            $txStarted = false;
            echo json_encode(['success' => false, 'message' => 'Owner account not found.']);
            exit;
        }
        $ownerId = (int)$oRow['Owner_ID'];
        $stmt = $conn->prepare("UPDATE schedule SET Status = 'Cancelled' WHERE Schedule_ID = ? AND Owner_ID = ?");
        $stmt->bind_param('ii', $scheduleId, $ownerId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            $relRoom = $conn->prepare(
                "UPDATE room
                 SET Status = CASE WHEN Current_Emergency_ID IS NOT NULL THEN 'ongoing' ELSE 'idle' END,
                     Current_Schedule_ID = NULL
                 WHERE Current_Schedule_ID = ?"
            );
            $relRoom->bind_param('i', $scheduleId);
            $relRoom->execute();
            $relRoom->close();
        }

        $conn->commit();
        $txStarted = false;
        echo json_encode(['success' => $ok && $affected > 0]);
        exit;
    }

    $rLook = $conn->prepare(
        "SELECT s.Room_ID, s.Owner_ID, s.Veterinarian_ID, s.Services, o.First_Name, o.Last_Name, p.Pet_Name
         FROM schedule s
         LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID
         LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
         WHERE s.Schedule_ID = ? LIMIT 1 FOR UPDATE"
    );
    $rLook->bind_param('i', $scheduleId);
    $rLook->execute();
    $sRow = $rLook->get_result()->fetch_assoc();
    $rLook->close();

    if (!$sRow) {
        $conn->rollback();
        $txStarted = false;
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE schedule SET Status = ? WHERE Schedule_ID = ?");
    $stmt->bind_param("si", $status, $scheduleId);
    $ok = $stmt->execute();
    $stmt->close();

    $roomId = (int)($sRow['Room_ID'] ?? 0);
    if ($roomId > 0) {
        if ($status === 'In Progress') {
            $rUp = $conn->prepare("UPDATE room SET Status = 'ongoing', Current_Schedule_ID = ? WHERE Room_ID = ? AND Current_Emergency_ID IS NULL");
            $rUp->bind_param('ii', $scheduleId, $roomId);
            $rUp->execute();
            $rUp->close();
        } elseif (in_array($status, ['Completed', 'Cancelled'], true)) {
            $rUp = $conn->prepare(
                "UPDATE room
                 SET Status = CASE WHEN Current_Emergency_ID IS NOT NULL THEN 'ongoing' ELSE 'idle' END,
                     Current_Schedule_ID = NULL
                 WHERE Current_Schedule_ID = ?"
            );
            $rUp->bind_param('i', $scheduleId);
            $rUp->execute();
            $rUp->close();
        }
    }

    $conn->commit();
    $txStarted = false;
    hw_db_release_lock($conn, 'hw_schedule_lock');
    $lockAcquired = false;

    $receiptInfo = null;
    if ($ok && $status === 'Completed') {
        try {
            $ownerFullName = trim(($sRow['First_Name'] ?? '') . ' ' . ($sRow['Last_Name'] ?? '')) ?: 'Clinic Client';
            $attendingVetId = (int)($_SESSION['vet_id'] ?? ($sRow['Veterinarian_ID'] ?? 0));
            if ($attendingVetId > 0) {
                $receiptInfo = hw_receipt_issue($conn, [
                    'reference_type' => 'schedule',
                    'reference_id' => $scheduleId,
                    'owner_id' => !empty($sRow['Owner_ID']) ? (int)$sRow['Owner_ID'] : null,
                    'owner_name' => $ownerFullName,
                    'pet_name' => $sRow['Pet_Name'] ?: 'Companion Pet',
                    'service_summary' => $sRow['Services'] ?: 'Veterinary Appointment',
                    'attending_vet_id' => $attendingVetId,
                    'issued_by' => $_SESSION['username'] ?? 'Veterinarian',
                ]);
            }
        } catch (Throwable $e) {
        }
    }

    echo json_encode(['success' => $ok, 'receipt' => $receiptInfo]);
} catch (Throwable $e) {
    if ($txStarted && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
} finally {
    if ($lockAcquired && $conn instanceof mysqli) {
        hw_db_release_lock($conn, 'hw_schedule_lock');
    }
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
