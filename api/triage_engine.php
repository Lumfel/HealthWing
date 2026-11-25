<?php
/**
 * HealthWing / VetSystem — Multi-Pet Emergency Priority & Conflict Preemption API (api/triage_engine.php)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

hw_start_secure_session();
header('Content-Type: application/json');

hw_require_role(['vet_staff', 'head_vet'], true);

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'queue');

    if ($action === 'queue') {
        $triageResult = $engine->recalculateEmergencyTriage(true);

        $sql = "SELECT e.*,
                       p.Pet_Name, p.Species, p.Pet_Type, p.Age AS Pet_Age,
                       v.First_Name AS Vet_First, v.Last_Name AS Vet_Last, v.Specialties AS Vet_Specialties,
                       r.Room_Name, r.Room_Type
                FROM emergency_booking e
                LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
                LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
                LEFT JOIN room r ON e.Assigned_Room_ID = r.Room_ID
                WHERE e.Deleted_At IS NULL
                ORDER BY
                    CASE WHEN e.Status IN ('Pending', 'In Progress') THEN 0 ELSE 1 END ASC,
                    COALESCE(e.Queue_Position, 9999) ASC,
                    e.Priority_Score DESC,
                    e.Created_At DESC";
        $res = $conn->query($sql);
        $cases = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        echo json_encode([
            'success' => true,
            'triage_summary' => $triageResult,
            'cases' => $cases,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'set_severity') {
        hw_require_csrf();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $bookingId = (int)($input['booking_id'] ?? 0);
        $severity = trim((string)($input['severity_level'] ?? 'Urgent'));
        $allowed = ['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'];
        if (!$bookingId || !in_array($severity, $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid booking ID or severity level.']);
            $conn->close();
            exit;
        }

        $lockAcquired = false;
        $txStarted = false;
        try {
            $lockAcquired = hw_db_acquire_lock($conn, 'hw_triage_lock', 5);
            if (!$lockAcquired) {
                http_response_code(503);
                echo json_encode(['success' => false, 'error' => 'Triage queue busy. Please retry.']);
                exit;
            }
            $conn->begin_transaction();
            $txStarted = true;

            $stmt = $conn->prepare("UPDATE emergency_booking SET Severity_Level = ? WHERE Booking_ID = ?");
            $stmt->bind_param('si', $severity, $bookingId);
            $stmt->execute();
            $stmt->close();

            $triageResult = $engine->recalculateEmergencyTriage(false);
            $conn->commit();
            $txStarted = false;

            echo json_encode([
                'success' => true,
                'triage_summary' => $triageResult,
            ]);
        } catch (Throwable $txErr) {
            if ($txStarted) {
                @$conn->rollback();
            }
            throw $txErr;
        } finally {
            if ($lockAcquired) {
                hw_db_release_lock($conn, 'hw_triage_lock');
            }
        }
        $conn->close();
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Triage engine error.']);
}
