<?php
/**
 * HealthWing / VetSystem — Veterinarian Emergency Status Update + Queue Re-Evaluation (update_status.php)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';
require_once __DIR__ . '/includes/receipt_blockchain.php';
hw_start_secure_session();
header('Content-Type: application/json');

$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';

if (!$isVet && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in as a veterinarian or admin.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$conn->query("DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

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
    $relRoom = $conn->prepare("UPDATE room SET Status = 'idle', Current_Emergency_ID = NULL WHERE Current_Emergency_ID = ?");
    $relRoom->bind_param('i', $bookingId);
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
        $q->bind_param('i', $bookingId);
        $q->execute();
        if ($row = $q->get_result()->fetch_assoc()) {
            $issuer = $isAdmin ? ('Admin (' . ($_SESSION['admin_username'] ?? 'Clinic') . ')') : ($_SESSION['username'] ?? 'Veterinarian');
            $receiptInfo = hw_receipt_issue($conn, [
                'reference_type' => 'emergency',
                'reference_id' => $bookingId,
                'owner_id' => $row['Owner_ID'] ? (int)$row['Owner_ID'] : null,
                'owner_name' => $row['Name'] ?: 'Clinic Client',
                'pet_name' => $row['Pet_Name'] ?: 'Emergency Patient',
                'service_summary' => 'Emergency Triage & Care (' . ($row['Severity_Level'] ?: 'Urgent') . ')',
                'issued_by' => $issuer,
            ]);
        }
        $q->close();
    } catch (Throwable $e) {
    }
}

$engine = new VetSchedulingEngine($conn);
$triageSummary = $engine->recalculateEmergencyTriage();

echo json_encode([
    'success' => $ok,
    'triage_summary' => $triageSummary,
    'receipt' => $receiptInfo,
]);

$conn->close();
