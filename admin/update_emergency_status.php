<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';
require_once __DIR__ . '/../includes/receipt_blockchain.php';

header('Content-Type: application/json');
hw_require_csrf();

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? (string)$data['status'] : '';
$reason = trim((string)($data['reason'] ?? ''));
$allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

if (!$id || !in_array($status, $allowed, true)) {
    $conn->close();
    echo json_encode(['success' => false, 'error' => 'Invalid id or status']);
    exit;
}

if ($status === 'Cancelled' && $reason === '') {
    $conn->close();
    echo json_encode(['success' => false, 'error' => 'A reason is required when cancelling a request.']);
    exit;
}

$conn->query("DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

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
    $relRoom = $conn->prepare("UPDATE room SET Status = 'idle', Current_Emergency_ID = NULL WHERE Current_Emergency_ID = ?");
    $relRoom->bind_param('i', $id);
    $relRoom->execute();
    $relRoom->close();
}

$receiptInfo = null;
if ($ok && $status === 'Completed') {
    try {
        $q = $conn->prepare(
            "SELECT e.Owner_ID, e.Name, e.Severity_Level, p.Pet_Name
             FROM emergency_booking e
             LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
             WHERE e.Booking_ID = ? LIMIT 1"
        );
        $q->bind_param('i', $id);
        $q->execute();
        if ($row = $q->get_result()->fetch_assoc()) {
            $receiptInfo = hw_receipt_issue($conn, [
                'reference_type' => 'emergency',
                'reference_id' => $id,
                'owner_id' => $row['Owner_ID'] ? (int)$row['Owner_ID'] : null,
                'owner_name' => $row['Name'] ?: 'Clinic Client',
                'pet_name' => $row['Pet_Name'] ?: 'Emergency Patient',
                'service_summary' => 'Emergency Triage & Care (' . ($row['Severity_Level'] ?: 'Urgent') . ')',
                'issued_by' => 'Admin (' . ($_SESSION['admin_username'] ?? 'Clinic') . ')',
            ]);
        }
        $q->close();
    } catch (Throwable $e) {
    }
}

$engine = new VetSchedulingEngine($conn);
$triageSummary = $engine->recalculateEmergencyTriage();

echo json_encode(['success' => $ok, 'triage_summary' => $triageSummary, 'receipt' => $receiptInfo]);
$conn->close();
