<?php
/**
 * HealthWing / VetSystem — Atomic Emergency Status Update & Triage Re-Evaluation (update_status.php)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';
require_once __DIR__ . '/includes/receipt_blockchain.php';

hw_start_secure_session();
header('Content-Type: application/json');

hw_require_role(['vet_staff', 'head_vet'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

hw_require_csrf();

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['booking_id'], $data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit();
}

$bookingId = (int)$data['booking_id'];
$status = (string)$data['status'];
$reason = trim((string)($data['reason'] ?? ''));
$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit();
}

if ($status === 'Cancelled' && $reason === '') {
    echo json_encode(['success' => false, 'message' => 'A reason is required when cancelling a request.']);
    exit();
}

$conn = null;
$lockAcquired = false;
$txStarted = false;

try {
    $conn = hw_get_db();
    $lockAcquired = hw_db_acquire_lock($conn, 'hw_triage_lock', 5);
    if (!$lockAcquired) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Emergency queue is locked by another operation. Please retry.']);
        exit();
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start status update transaction.');
    }
    $txStarted = true;

    // Lock target emergency row
    $lockStmt = $conn->prepare(
        "SELECT e.Booking_ID, e.Owner_ID, e.Name, e.Severity_Level, e.Assigned_Vet_ID, p.Pet_Name
         FROM emergency_booking e
         LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
         WHERE e.Booking_ID = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bookingId);
    $lockStmt->execute();
    $bookingRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bookingRow) {
        $conn->rollback();
        $txStarted = false;
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Emergency booking not found.']);
        exit();
    }

    $isResolved = in_array($status, ['Completed', 'Cancelled'], true);

    if ($status === 'Cancelled') {
        $stmt = $conn->prepare(
            "UPDATE emergency_booking
             SET Status = ?, Cancel_Reason = ?, Cancel_Requested = 0, Resolved_At = NOW(), Client_Notified = 0, Queue_Position = NULL
             WHERE Booking_ID = ?"
        );
        $stmt->bind_param("ssi", $status, $reason, $bookingId);
    } elseif ($isResolved) {
        $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ?, Resolved_At = NOW(), Queue_Position = NULL WHERE Booking_ID = ?");
        $stmt->bind_param("si", $status, $bookingId);
    } else {
        $stmt = $conn->prepare("UPDATE emergency_booking SET Status = ? WHERE Booking_ID = ?");
        $stmt->bind_param("si", $status, $bookingId);
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
        $relRoom->bind_param('i', $bookingId);
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
            $attendingVetId = (int)($_SESSION['vet_id'] ?? ($bookingRow['Assigned_Vet_ID'] ?? 0));
            if ($attendingVetId > 0) {
                $receiptInfo = hw_receipt_issue($conn, [
                    'reference_type' => 'emergency',
                    'reference_id' => $bookingId,
                    'owner_id' => !empty($bookingRow['Owner_ID']) ? (int)$bookingRow['Owner_ID'] : null,
                    'owner_name' => $bookingRow['Name'] ?: 'Clinic Client',
                    'pet_name' => $bookingRow['Pet_Name'] ?: 'Emergency Patient',
                    'service_summary' => 'Emergency Triage & Care (' . ($bookingRow['Severity_Level'] ?: 'Urgent') . ')',
                    'attending_vet_id' => $attendingVetId,
                    'issued_by' => $_SESSION['username'] ?? 'Veterinarian',
                ]);
            }
        } catch (Throwable $e) {
            // Receipt issuance failure does not revert completed triage status
        }
    }

    echo json_encode([
        'success' => $ok,
        'triage_summary' => $triageSummary,
        'receipt' => $receiptInfo,
    ]);
} catch (Throwable $e) {
    if ($txStarted && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update emergency status.']);
} finally {
    if ($lockAcquired && $conn instanceof mysqli) {
        hw_db_release_lock($conn, 'hw_triage_lock');
    }
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
