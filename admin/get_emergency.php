<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $triageSummary = $engine->recalculateEmergencyTriage();

    $conn->query("DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

    $sql = "SELECT e.*,
                   o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName,
                   v.First_Name AS VetFirst, v.Last_Name AS VetLast,
                   r.Room_Name
            FROM emergency_booking e
            LEFT JOIN owner o ON e.Owner_ID = o.Owner_ID
            LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
            LEFT JOIN room r ON e.Assigned_Room_ID = r.Room_ID
            ORDER BY
                CASE WHEN e.Status IN ('Pending', 'In Progress') THEN 0 ELSE 1 END ASC,
                COALESCE(e.Queue_Position, 9999) ASC,
                e.Priority_Score DESC,
                e.Created_At DESC";

    $result = $conn->query($sql);
    $cases = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode([
        'success'=> true,
        'triage_summary'=>$triageSummary,
        'cases'=>$cases,
    ]);
    $conn->close();
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
}
