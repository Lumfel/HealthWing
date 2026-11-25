<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';
require_once __DIR__ . '/../includes/receipt_blockchain.php';

header('Content-Type: application/json');
hw_require_csrf();

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? (string)$data['status'] : '';
$reason = trim((string)($data['reason'] ?? ''));
$allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

if (!$id || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid id or status']);
    exit;
}

if ($status === 'Cancelled' && $reason === '') {
    echo json_encode(['success' => false, 'error' => 'A reason is required when cancelling a request.']);
    exit;
}

$conn = null;
$lockAcquired = false;
$txStarted = false;

try {
    $conn = hw_get_db();
    $lockAcquired = hw_db_acquire_lock($conn, 'hw_triage_lock', 5);
    if (!$lockAcquired) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Triage queue is busy. Please retry.']);
        exit;
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start triage update transaction.');
    }
    $txStarted = true;

    $lockStmt = $conn->prepare(
        "SELECT e.Owner_ID, e.Name, e.Severity_Level, e.Assigned_Vet_ID, p.Pet_Name
         FROM emergency_booking e
         LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
         WHERE e.Booking_ID = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $id);
    $lockStmt->execute();
    $row = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$row) {
        $conn->rollback();
        $txStarted = false;
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Emergency booking not found.']);
        exit;
    }

    $isResolved = in_array($status, ['Completed', 'Cancelled'], true);

    if ($status === 'Cancelled') {
        $stmt = $conn->prepare(
            "UPDATE emergency_booking
             SET Status = ?, Cancel_Reason = ?, Cancel_Requested = 0, Resolved_At = NOW(), Client_Notified = 0, Queue_Position = NULL
             WHERE Booking_ID = ?"
        );
        $stmt->bind_param("ssi", $status, $reason, $id);
    } elseif ($isResolved) {
        $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ?, Resolved_At = NOW(), Queue_Position = NULL WHERE Booking_ID = ?");
        $stmt->bind_param("si", $status, $id);
    } else {
        $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ? WHERE Booking_ID = ?");
        $stmt->bind_param("si", $status, $id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if ($isResolved) {
        $relRoom = $conn->prepare(
            "UPDATE room
             SET Status = CASE WHEN Current_Schedule_ID IS NOT NULL THEN 'ongoing' ELSE 'idle' END,
                 Current_Emergency_ID = NULL
             WHERE Current_Emergency_ID = ?"
        );
        $relRoom->bind_param('i', $id);
        $relRoom->execute();
        $relRoom->close();
    }

    $engine = new VetSchedulingEngine($conn);
    $triageSummary = $engine->recalculateEmergencyTriage(false);

    $conn->commit();
    $txStarted = false;
    hw_db_release_lock($conn, 'hw_triage_lock');
    $lockAcquired = false;

    $receiptInfo = null;
    if ($ok && $status === 'Completed') {
        try {
            $attendingVetId = (int)($_SESSION['vet_id'] ?? ($row['Assigned_Vet_ID'] ?? 0));
            if ($attendingVetId > 0) {
                $receiptInfo = hw_receipt_issue($conn, [
                    'reference_type' => 'emergency',
                    'reference_id' => $id,
                    'owner_id' => !empty($row['Owner_ID']) ? (int)$row['Owner_ID'] : null,
                    'owner_name' => $row['Name'] ?: 'Clinic Client',
                    'pet_name' => $row['Pet_Name'] ?: 'Emergency Patient',
                    'service_summary' => 'Emergency Triage & Care (' . ($row['Severity_Level'] ?: 'Urgent') . ')',
                    'attending_vet_id' => $attendingVetId,
                    'issued_by' => $_SESSION['username'] ?? 'Head Veterinarian',
                ]);
            }
        } catch (Throwable $e) {
        }
    }

    echo json_encode(['success' => $ok, 'triage_summary' => $triageSummary, 'receipt' => $receiptInfo]);
} catch (Throwable $e) {
    if ($txStarted && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update emergency status.']);
} finally {
    if ($lockAcquired && $conn instanceof mysqli) {
        hw_db_release_lock($conn, 'hw_triage_lock');
    }
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
