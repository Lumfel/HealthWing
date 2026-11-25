<?php
/**
 * HealthWing / VetSystem — Privacy-Safe Public Calendar API (api/public_calendar_api.php)
 *
 * Exposes all booked schedules and active clinic room blocks for the public calendar
 * WITHOUT ANY SENSITIVE DATA (no owner names, no emails, no phone numbers, no pet names,
 * no medical notes or clinical descriptions).
 *
 * Returned fields per slot:
 * - slot_id: anonymized slot identifier (e.g., "SLOT-104")
 * - date: YYYY-MM-DD
 * - start_time: YYYY-MM-DD HH:MM:SS
 * - end_time: YYYY-MM-DD HH:MM:SS
 * - time_label: e.g. "09:30 AM – 10:30 AM"
 * - duration_minutes: integer
 * - category: "Booked Appointment"or "Emergency Block"
 * - room_label: e.g. "Surgery Suite A"or "Main Room"
 * - status_label: "Booked"| "In Session"| "Emergency Priority Block"
 */

require_once dirname(__DIR__) . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scheduling_engine.php';

try {
    $conn = hw_get_db();
    $engine = new VetSchedulingEngine($conn);
    $currentVetId = null;
    $currentVetName = null;
    if (hw_is_vet_role()) {
        $vStmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name FROM veterinarian WHERE username = ? AND Deleted_At IS NULL LIMIT 1");
        $vStmt->bind_param('s', $_SESSION['username']);
        $vStmt->execute();
        if ($vRow = $vStmt->get_result()->fetch_assoc()) {
            $currentVetId = (int)$vRow['Veterinarian_ID'];
            $currentVetName = 'Dr. '. trim($vRow['First_Name'] . ''. $vRow['Last_Name']);
        }
        $vStmt->close();
    }

    // Optional date range filter (defaults to 30 days back through 60 days ahead)
    $startParam = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
    $endParam = $_GET['end'] ?? date('Y-m-d', strtotime('+60 days'));

    $startDt = date('Y-m-d 00:00:00', strtotime($startParam) ?: strtotime('-30 days'));
    $endDt = date('Y-m-d 23:59:59', strtotime($endParam) ?: strtotime('+60 days'));

    // 1. Fetch non-cancelled booked schedules (STRICTLY NO OWNER/PET PII SELECTED)
    $stmt = $conn->prepare(
        "SELECT s.Schedule_ID,
                s.Appointment_Time,
                s.Duration_Minutes,
                s.End_Time,
                s.Status,
                s.Veterinarian_ID, s.Services, s.Preempted_By_Emergency_ID,
                r.Room_Name,
                r.Room_Type
         FROM schedule s
         LEFT JOIN room r ON s.Room_ID = r.Room_ID
         WHERE s.Status IN ('Pending', 'Confirmed', 'In Progress', 'Completed')
           AND s.Appointment_Time BETWEEN ? AND ?
         ORDER BY s.Appointment_Time ASC"
    );
    $stmt->bind_param('ss', $startDt, $endDt);
    $stmt->execute();
    $schedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $publicSlots = [];

    foreach ($schedRows as $row) {
        $startTs = strtotime($row['Appointment_Time']);
        if (!$startTs) {
            continue;
        }
        $dur = (int)($row['Duration_Minutes'] ?: 30);
        $endTs = !empty($row['End_Time']) ? strtotime($row['End_Time']) : ($startTs + ($dur * 60));

        $statusLabel = 'Booked';
        if ($row['Status'] === 'In Progress') {
            $statusLabel = 'In Session';
        } elseif ($row['Status'] === 'Completed') {
            $statusLabel = 'Completed';
        }

        $publicSlots[] = [
            'slot_id'=>'SCH-'. (int)$row['Schedule_ID'],
            'date'=> date('Y-m-d', $startTs),
            'start_time'=> date('Y-m-d H:i:s', $startTs),
            'end_time'=> date('Y-m-d H:i:s', $endTs),
            'start_hm'=> date('H:i', $startTs),
            'end_hm'=> date('H:i', $endTs),
            'time_label'=> date('g:i A', $startTs) . '– '. date('g:i A', $endTs),
            'duration_minutes'=>$dur,
            'category'=>'Scheduled Slot',
            'room_label'=>$row['Room_Name'] ?: 'Clinic Exam Room',
            'status_label'=>$statusLabel,
            'was_rescheduled_by_emergency'=>!empty($row['Preempted_By_Emergency_ID']),
            'vet_id'=>!empty($row['Veterinarian_ID']) ? (int)$row['Veterinarian_ID'] : null,
            'is_my_schedule'=>($currentVetId !== null && (int)($row['Veterinarian_ID'] ?? 0) === $currentVetId),
        ];
    }

    // 2. Include active emergency room blocks (STRICTLY NO PII — only time window & room status)
    $emRes = $conn->query(
        "SELECT e.Booking_ID,
                e.Created_At,
                e.Status,
                e.Queue_Position, e.Assigned_Vet_ID,
                r.Room_Name
         FROM emergency_booking e
         LEFT JOIN room r ON e.Assigned_Room_ID = r.Room_ID
         WHERE e.Status IN ('Pending', 'In Progress')
         ORDER BY COALESCE(e.Queue_Position, e.Assigned_Vet_ID, 999) ASC"
    );
    if ($emRes) {
        while ($em = $emRes->fetch_assoc()) {
            $emStart = strtotime($em['Created_At']) ?: time();
            // Emergency block occupies 60 minutes from start or current time if In Progress
            $emEnd = max($emStart + 3600, time() + 1800);
            $publicSlots[] = [
                'slot_id'=>'EMG-'. (int)$em['Booking_ID'],
                'date'=> date('Y-m-d', $emStart),
                'start_time'=> date('Y-m-d H:i:s', $emStart),
                'end_time'=> date('Y-m-d H:i:s', $emEnd),
                'start_hm'=> date('H:i', $emStart),
                'end_hm'=> date('H:i', $emEnd),
                'time_label'=> date('g:i A', $emStart) . '– '. date('g:i A', $emEnd),
                'duration_minutes'=> round(($emEnd - $emStart) / 60),
                'category'=>'Emergency Priority Block',
                'room_label'=>$em['Room_Name'] ?: 'Emergency Trauma Bay',
                'status_label'=>'Emergency Priority Block',
                'was_rescheduled_by_emergency'=> false,
                'vet_id'=>!empty($em['Assigned_Vet_ID']) ? (int)$em['Assigned_Vet_ID'] : null,
                'is_my_schedule'=>($currentVetId !== null && (int)($em['Assigned_Vet_ID'] ?? 0) === $currentVetId),
            ];
        }
    }

    // 3. Also return non-sensitive clinic room status summary (Room_Name, Room_Type, Status)
    $roomSummary = [];
    $rRes = $conn->query("SELECT Room_ID, Room_Name, Room_Type, Status FROM room ORDER BY Room_ID ASC");
    if ($rRes) {
        while ($r = $rRes->fetch_assoc()) {
            $roomSummary[] = [
                'room_name'=>$r['Room_Name'] ?: ('Room #'. $r['Room_ID']),
                'room_type'=>$r['Room_Type'] ?: 'General',
                'status'=>$r['Status'] ?: 'idle',
            ];
        }
    }

    echo json_encode([
        'success'=> true,
        'privacy_notice'=>'All personal, pet, and clinical details are omitted from the public schedule.',
        'operating_hours'=>[
            'weekdays'=>'08:00 AM – 06:00 PM (Mon–Fri)',
            'saturday'=>'09:00 AM – 03:00 PM (Sat)',
            'sunday'=>'Closed (Emergency Triage Only)',
        ],
        'current_vet_id'=>$currentVetId,
        'current_vet_name'=>$currentVetName,
        'rooms'=>$roomSummary,
        'booked_slots'=>$publicSlots,
    ]);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=> false, 'error'=>'Could not load public calendar.']);
}
