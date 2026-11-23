<?php
/**
 * HealthWing / VetSystem — Multi-Pet Emergency Priority & Conflict Preemption API (api/triage_engine.php)
 *
 * Actions:
 * - GET ?action=queue : Runs recalculateEmergencyTriage() and returns the live prioritized emergency queue,
 *   room/vet allocations, and any preempted regular schedules.
 * - POST ?action=set_severity : Allows a Vet or Admin to override an emergency's clinical Severity_Level
 *   ('Critical', 'Emergent', 'Urgent', 'Semi-Urgent') and immediately re-runs the priority & preemption engine.
 * - POST ?action=simulate_conflict : Optional simulation helper for testing concurrent emergencies in the UI.
 */

require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'queue');

    if ($action === 'queue') {
        $triageResult = $engine->recalculateEmergencyTriage();

        // Fetch full enriched emergency records ordered by Queue_Position ASC (active first), then Created_At DESC
        $sql = "SELECT e.*,
                       p.Pet_Name, p.Species, p.Pet_Type, p.Age AS Pet_Age,
                       v.First_Name AS Vet_First, v.Last_Name AS Vet_Last, v.Specialties AS Vet_Specialties,
                       r.Room_Name, r.Room_Type
                FROM emergency_booking e
                LEFT JOIN pet p ON e.Pet_ID = p.Pet_ID
                LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
                LEFT JOIN room r ON e.Assigned_Room_ID = r.Room_ID
                ORDER BY
                    CASE WHEN e.Status IN ('Pending', 'In Progress') THEN 0 ELSE 1 END ASC,
                    COALESCE(e.Queue_Position, 9999) ASC,
                    e.Priority_Score DESC,
                    e.Created_At DESC";
        $res = $conn->query($sql);
        $cases = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        echo json_encode([
            'success'=> true,
            'triage_summary'=>$triageResult,
            'cases'=>$cases,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'set_severity') {
        $isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
        $isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';
        if (!$isVet && !$isAdmin) {
            http_response_code(401);
            echo json_encode(['success'=> false, 'error'=>'Only a veterinarian or admin can update triage severity.']);
            $conn->close();
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $bookingId = (int)($input['booking_id'] ?? 0);
        $severity = trim($input['severity_level'] ?? 'Urgent');
        $allowed = ['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'];
        if (!$bookingId || !in_array($severity, $allowed, true)) {
            echo json_encode(['success'=> false, 'error'=>'Invalid booking ID or severity level.']);
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE emergency_booking SET Severity_Level = ? WHERE Booking_ID = ?");
        $stmt->bind_param('si', $severity, $bookingId);
        $stmt->execute();
        $stmt->close();

        $triageResult = $engine->recalculateEmergencyTriage();
        echo json_encode([
            'success'=> true,
            'triage_summary'=>$triageResult,
        ]);
        $conn->close();
        exit;
    }

    echo json_encode(['success'=> false, 'error'=>'Unknown action']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}
