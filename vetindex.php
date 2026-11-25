<?php
/**
 * HealthWing / VetSystem — Veterinarian Clinical Workstation (vetindex.php)
 *
 * Eliminates the old vet-side limitations and role confusion by providing a full clinical console:
 * 1. Live Priority-Ranked Emergency Triage Queue (with Composite Priority Score, Severity Override,
 *    Assigned Room/Vet, Conflict Preemption Rationale, and 1-Click Video/Audio/Chat).
 * 2. Smart Assigned & Clinic-Wide Appointments (showing assigned Vet, Room, Duration, and Auto-Scheduler controls).
 * 3. Direct Clinical Pet Records & Checkup/Prescription Logger.
 * 4. Real-Time Clinic Room Status Board.
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
require_once __DIR__ . '/guest_notice.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';

if (!hw_is_vet_role()) {
    show_guest_notice("You need to sign in as a veterinarian to access the Veterinarian Clinical Workstation.");
    exit();
}

$username = $_SESSION['username'];
$csrfToken = hw_csrf_token();
$conn = hw_get_db();
$engine = new VetSchedulingEngine($conn);

// Run triage calculation on load so every emergency has an up-to-date Priority_Score, Queue_Position, and Room/Vet allocation
$triageSummary = $engine->recalculateEmergencyTriage();

// Look up logged-in vet details
$currentVet = null;
$vStmt = $conn->prepare("SELECT * FROM veterinarian WHERE username = ? LIMIT 1");
$vStmt->bind_param('s', $username);
$vStmt->execute();
$currentVet = $vStmt->get_result()->fetch_assoc();
$vStmt->close();
$currentVetId = $currentVet ? (int)$currentVet['Veterinarian_ID'] : 0;

// Soft-archive resolved emergencies older than 24h instead of hard-deleting clinical history
$conn->query("UPDATE emergency_booking SET Deleted_At = NOW() WHERE Resolved_At IS NOT NULL AND Deleted_At IS NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)");

// Fetch prioritized emergencies
$sqlEm = "SELECT e.*,
                 v.First_Name AS VetFirst, v.Last_Name AS VetLast,
                 r.Room_Name
          FROM emergency_booking e
          LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
          LEFT JOIN room r ON e.Assigned_Room_ID = r.Room_ID
          WHERE e.Deleted_At IS NULL
          ORDER BY
              CASE WHEN e.Status IN ('Pending', 'In Progress') THEN 0 ELSE 1 END ASC,
              COALESCE(e.Queue_Position, 9999) ASC,
              e.Priority_Score DESC,
              e.Created_At DESC";
$resEm = $conn->query($sqlEm);
$emergencies = $resEm ? $resEm->fetch_all(MYSQLI_ASSOC) : [];

$maxIdRes = $conn->query("SELECT COALESCE(MAX(Booking_ID), 0) AS maxId FROM emergency_booking");
$initialMaxBookingId = $maxIdRes ? (int)$maxIdRes->fetch_assoc()['maxId'] : 0;

// Fetch scheduled appointments with Vet & Room joins
$sqlSched = "SELECT s.*,
                    o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, o.Contact AS OwnerContact,
                    p.Pet_Name, p.Species, p.Current_Ailment,
                    v.First_Name AS VetFirstName, v.Last_Name AS VetLastName,
                    r.Room_Name
             FROM schedule s
             LEFT JOIN owner o ON s.Owner_ID = o.Owner_ID
             LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
             LEFT JOIN veterinarian v ON s.Veterinarian_ID = v.Veterinarian_ID
             LEFT JOIN room r ON s.Room_ID = r.Room_ID
             ORDER BY s.Appointment_Time ASC";
$resSched = $conn->query($sqlSched);
$schedules = $resSched ? $resSched->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"/>
    <title>Veterinarian Clinical Workstation | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet"/>
    <style>
        body { background: #F4F9FA; color: #16262A; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .vet-wrap { max-width: 1340px; margin: 24px auto 70px; padding: 0 20px; }
        .panel { background: #FFFFFF; border: 1px solid #E3EBEC; border-radius: 14px; padding: 18px 20px; box-shadow: 0 4px 16px rgba(15,35,42,0.04); }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #E5EEF0; vertical-align: top; }
        .data-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em; color: #546E73; background: #F8FCFD; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid transparent; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.15s; }
        .btn-primary { background: #1F3A3D; color: #FFFFFF; }
        .btn-primary:hover { background: #2B5054; }
        .btn-secondary { background: #EEF5F7; color: #1F3A3D; border-color: #D5E3E6; }
        .btn-secondary:hover { background: #E1EFF2; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
        .badge-good { background: #E6F4EA; color: #137333; }
        .badge-warn { background: #FFF8E7; color: #8A5A00; }
        .badge-bad { background: #FBE7EC; color: #9E1C38; }
        .badge-neutral { background: #E7F3F7; color: #1F5B70; }
        .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-top: 12px; }
        .room-card { background: #F9FCFD; border: 1px solid #D5E3E6; border-radius: 12px; padding: 14px; }
        .room-name { font-weight: 800; font-size: 14px; color: #1F3A3D; margin-bottom: 4px; }
        .empty { text-align: center; padding: 24px 12px; color: #62787B; font-size: 13.5px; }
        .tab-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 2px solid #E3EBEC; padding-bottom: 10px; }
        .tab-btn {
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid #D5E3E6;
            background: #FFFFFF;
            color: #16262A;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: 0.15s;
        }
        .tab-btn.active { background: #1F3A3D; color: #FFFFFF; border-color: #1F3A3D; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .priority-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 12px;
        }
        .sev-Critical { background: #FBE7EC; color: #9E1C38; border: 1px solid #F3B7C5; }
        .sev-Emergent { background: #FFF1E6; color: #B44D12; border: 1px solid #F8CBA6; }
        .sev-Urgent { background: #FFF8E7; color: #8A5A00; border: 1px solid #F5D487; }
        .sev-Semi-Urgent { background: #E7F3F7; color: #1F5B70; border: 1px solid #B5DCE8; }
        .modal-backdrop {
            display: none; position: fixed; inset: 0; z-index: 9000;
            background: rgba(15,35,42,0.55); align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-card {
            background: #fff; border-radius: 14px; padding: 22px; width: min(94vw, 520px);
            max-height: 88vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="vet-wrap">
    <!-- Top Clinical Header -->
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:14px;">
        <div>
            <div style="font-size:12px;font-weight:800;text-transform:uppercase;color:#4A9DBB;letter-spacing:0.04em;">Veterinarian Clinical Workstation</div>
            <h1 style="font-size:23px;font-weight:800;margin:2px 0 4px 0;">
                Dr. <?php echo htmlspecialchars(($currentVet['First_Name'] ?? $username) . ''. ($currentVet['Last_Name'] ?? '')); ?>
                <?php if (!empty($currentVet['Specialties'])): ?>
                    <span style="font-size:13px;font-weight:600;background:#E7F3F7;color:#1F5B70;padding:4px 10px;border-radius:999px;vertical-align:middle;">
                        Specialty: <?php echo htmlspecialchars($currentVet['Specialties']); ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p style="color:#62787B;font-size:13px;margin:0;">
                Real-time emergency priority triage, automated room & schedule conflict resolution, pet medical records, and direct WebSocket video/chat.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" onclick="rerunTriageEngine()">
                 Re-Run Priority Triage & Conflict Engine
            </button>
            <button type="button" class="btn btn-secondary" onclick="HWComm.openChat()">
                 Open Comm Hub
            </button>
        </div>
    </div>

    <!-- Workstation Navigation Tabs -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchVetTab('triage', this)">
             Priority Emergency Triage (<?php echo count(array_filter($emergencies, fn($e) => in_array($e['Status'], ['Pending', 'In Progress'], true))); ?>Active)
        </button>
        <button class="tab-btn" onclick="switchVetTab('schedule', this)">
             Smart Appointments & Schedule (<?php echo count($schedules); ?>)
        </button>
        <button class="tab-btn" onclick="switchVetTab('pets', this)">
             Clinical Pet Records & Checkups
        </button>
        <button class="tab-btn" onclick="switchVetTab('rooms', this)">
             Live Clinic Rooms
        </button>
        <button class="tab-btn" onclick="switchVetTab('history', this)">
             Unified Client History
        </button>
    </div>

    <!-- TAB 1: PRIORITY EMERGENCY TRIAGE QUEUE -->
    <div id="vet-tab-triage" class="tab-pane active">
        <div class="panel" style="margin-bottom:16px;background:#FFFDF9;border-color:#F5D487;">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
                <div>
                    <strong style="font-size:14px;color:#7A5200;">Multi-Pet Emergency Priority & Room Preemption Algorithm Active</strong>
                    <div style="font-size:12.5px;color:#62787B;margin-top:2px;">
                        Simultaneous emergencies are automatically ranked by <strong>Composite Priority Score (0–100)</strong>factoring Clinical Acuity, Pet Vulnerability, and Waiting Time Aging. Conflicting routine appointments in required rooms are automatically preempted and rescheduled.
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="search-emergencies" placeholder="Search client, phone, details..." style="padding:8px 12px;border:1px solid #D5E3E6;border-radius:8px;font-size:13px;">
                    <select id="filter-emergency-status" style="padding:8px 12px;border:1px solid #D5E3E6;border-radius:8px;font-size:13px;">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="panel">
            <?php if (!empty($emergencies)): ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Queue / Priority</th>
                                <th>Case & Client</th>
                                <th>Details</th>
                                <th>Allocated Room & Vet</th>
                                <th>Status</th>
                                <th>Direct Comm (WS + WebRTC)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="booking-table-body">
                            <?php foreach ($emergencies as $row):
                                $isResolved = in_array($row['Status'], ['Completed', 'Cancelled'], true);
                                $sev = $row['Severity_Level'] ?: 'Urgent';
                                $score = number_format((float)($row['Priority_Score'] ?? 50), 1);
                                $qPos = $row['Queue_Position'] ? (int)$row['Queue_Position'] : null;
                            ?>
                            <tr id="booking-<?php echo (int)$row['Booking_ID']; ?>" style="<?php echo $isResolved ? 'opacity:0.55;': ''; ?>">
                                <td>
                                    <?php if (!$isResolved && $qPos): ?>
                                        <div style="font-size:15px;font-weight:900;color:<?php echo $qPos === 1 ? '#D8546F': '#1F3A3D'; ?>;margin-bottom:4px;">
                                            <?php echo $qPos === 1 ? '#1 GO FIRST': 'Queue #'. $qPos; ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="priority-pill sev-<?php echo htmlspecialchars($sev); ?>">
                                        <?php echo htmlspecialchars($sev); ?>· <?php echo $score; ?>pts
                                    </span>
                                    <?php if (!$isResolved): ?>
                                        <div style="margin-top:6px;">
                                            <select onchange="changeEmergencySeverity(<?php echo (int)$row['Booking_ID']; ?>, this.value)"
                                                    style="font-size:11.5px;padding:3px 6px;border:1px solid #C8D8DC;border-radius:6px;background:#fff;">
                                                <?php foreach (['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'] as $tier): ?>
                                                    <option value="<?php echo $tier; ?>"<?php echo $sev === $tier ? 'selected': ''; ?>>Set: <?php echo $tier; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:800;font-size:14px;">Case #<?php echo (int)$row['Booking_ID']; ?>— <?php echo htmlspecialchars($row['Name'] ?: 'Guest'); ?></div>
                                    <div style="font-size:12px;color:#62787B;"><?php echo htmlspecialchars($row['Phone_Number'] ?: 'No phone'); ?>· <?php echo htmlspecialchars($row['Email'] ?: ''); ?></div>
                                    <div style="font-size:11px;color:#62787B;margin-top:2px;">Arrived: <?php echo htmlspecialchars($row['Created_At']); ?></div>
                                    <?php if (!empty($row['Image_Path'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['Image_Path']); ?>" target="_blank" style="font-size:12px;color:#4A9DBB;font-weight:700;">View Injury Photo</a>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:340px;">
                                    <div style="font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($row['Additional_Details'] ?: 'No details provided.'); ?></div>
                                    <?php if (!empty($row['Triage_Rationale'])): ?>
                                        <div style="font-size:11.5px;color:#62787B;margin-top:2px;">
                                            <?php echo htmlspecialchars($row['Triage_Rationale']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($row['Cancel_Requested'] == 1 && !$isResolved): ?>
                                        <div style="color:#D8546F;font-weight:700;font-size:12px;margin-top:4px;">[Notice] Client requested cancellation</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700;font-size:13px;color:#1F3A3D;">
                                         <?php echo htmlspecialchars($row['Room_Name'] ?: 'Standby / Unassigned'); ?>
                                    </div>
                                    <div style="font-size:12px;color:#62787B;">
                                         <?php echo !empty($row['VetFirst']) ? ('Dr. '. htmlspecialchars($row['VetFirst'] . ''. $row['VetLast'])) : 'Any Available Vet'; ?>
                                    </div>
                                    <?php if (!empty($row['Preempted_Schedule_ID'])): ?>
                                        <span class="badge badge-warn" style="margin-top:4px;display:inline-block;">Preempted Appt #<?php echo (int)$row['Preempted_Schedule_ID']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $bCls = $row['Status'] === 'Pending'? 'badge-warn'
                                            : ($row['Status'] === 'In Progress'? 'badge-neutral'
                                            : ($row['Status'] === 'Completed'? 'badge-good': 'badge-bad'));
                                    ?>
                                    <span class="badge <?php echo $bCls; ?>" id="status-<?php echo (int)$row['Booking_ID']; ?>">
                                        <?php echo htmlspecialchars($row['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$isResolved): ?>
                                        <label style="display:flex;align-items:center;gap:6px;font-size:11.5px;margin-bottom:6px;cursor:pointer;">
                                            <input type="checkbox" class="call-toggle" data-booking-id="<?php echo (int)$row['Booking_ID']; ?>"
                                                   <?php echo $row['Call_Enabled'] ? 'checked': ''; ?>
                                                   onchange="toggleCallFeature(this)">
                                            Allow client call
                                        </label>
                                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                            <?php if ($row['Owner_ID']): ?>
                                                <?php $callerJs = htmlspecialchars(json_encode($row['Name'] ?: 'Client'), ENT_QUOTES, 'UTF-8'); ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="HWCall.startCall(<?php echo (int)$row['Booking_ID']; ?>, 'video', <?php echo $callerJs; ?>)">Video</button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="HWCall.startCall(<?php echo (int)$row['Booking_ID']; ?>, 'audio', <?php echo $callerJs; ?>)">Audio</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="HWComm.openChat('booking_<?php echo (int)$row['Booking_ID']; ?>')">Chat</button>
                                        </div>
                                        <?php if ($row['Owner_ID']): ?>
                                            <script>document.addEventListener('DOMContentLoaded', () => HWCall.watch(<?php echo (int)$row['Booking_ID']; ?>, <?php echo json_encode($row['Name'] ?: 'Client'); ?>));</script>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="HWComm.openChat('booking_<?php echo (int)$row['Booking_ID']; ?>')">History</button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form onsubmit="updateStatus(event, <?php echo (int)$row['Booking_ID']; ?>)" style="display:flex;flex-direction:column;gap:6px;">
                                        <select id="status-select-<?php echo (int)$row['Booking_ID']; ?>" style="padding:6px;border:1px solid #C8D8DC;border-radius:6px;font-size:12.5px;">
                                            <option value="Pending"<?php echo $row['Status'] === 'Pending'? 'selected': ''; ?>>Pending</option>
                                            <option value="In Progress"<?php echo $row['Status'] === 'In Progress'? 'selected': ''; ?>>In Progress</option>
                                            <option value="Completed"<?php echo $row['Status'] === 'Completed'? 'selected': ''; ?>>Completed</option>
                                            <option value="Cancelled"<?php echo $row['Status'] === 'Cancelled'? 'selected': ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Save Status</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">No emergency bookings on file right now.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: SMART APPOINTMENTS & SCHEDULE -->
    <div id="vet-tab-schedule" class="tab-pane">
        <div class="panel" style="margin-bottom:16px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="search-appointments" placeholder="Search owner, pet, service..." style="padding:8px 12px;border:1px solid #D5E3E6;border-radius:8px;font-size:13px;">
                <select id="filter-status" style="padding:8px 12px;border:1px solid #D5E3E6;border-radius:8px;font-size:13px;">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="filter-my-vet-only" onchange="filterAppointments()">
                    Show Only My Assigned Cases
                </label>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="autoAssignAllUnassigned()">
                 Auto-Assign Any Unassigned Appointments
            </button>
        </div>

        <div class="panel">
            <?php if (!empty($schedules)): ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client & Pet</th>
                                <th>Service & Duration</th>
                                <th>Scheduled Slot</th>
                                <th>Assigned Room & Vet</th>
                                <th>Status</th>
                                <th>Comm</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody id="schedule-table-body">
                            <?php foreach ($schedules as $s): ?>
                            <tr data-vet-id="<?php echo (int)($s['Veterinarian_ID'] ?? 0); ?>">
                                <td><strong>#<?php echo (int)$s['Schedule_ID']; ?></strong></td>
                                <td>
                                    <div style="font-weight:700;"><?php echo htmlspecialchars(trim(($s['OwnerFirstName'] ?? '') . ''. ($s['OwnerLastName'] ?? '')) ?: 'Client'); ?></div>
                                    <div style="font-size:12px;color:#62787B;"><?php echo htmlspecialchars($s['Pet_Name'] ?: 'Pet'); ?><?php echo !empty($s['Species']) ? '('. htmlspecialchars($s['Species']) . ')': ''; ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?php echo htmlspecialchars($s['Services']); ?></div>
                                    <div style="font-size:11.5px;color:#62787B;"><?php echo (int)($s['Duration_Minutes'] ?: 30); ?>mins</div>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?php echo htmlspecialchars($s['Appointment_Time']); ?></div>
                                    <?php if (!empty($s['Preempted_By_Emergency_ID'])): ?>
                                        <span class="badge badge-warn" style="font-size:10.5px;">Shifted by Emergency #<?php echo (int)$s['Preempted_By_Emergency_ID']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?php echo htmlspecialchars($s['Room_Name'] ?: 'Unassigned Room'); ?></div>
                                    <div style="font-size:12px;color:#62787B;"><?php echo !empty($s['VetFirstName']) ? ('Dr. '. htmlspecialchars($s['VetFirstName'] . ''. $s['VetLastName'])) : 'Unassigned Vet'; ?></div>
                                </td>
                                <td>
                                    <?php
                                        $sCls = $s['Status'] === 'Pending'? 'badge-warn'
                                            : ($s['Status'] === 'In Progress'? 'badge-neutral'
                                            : ($s['Status'] === 'Completed'? 'badge-good': 'badge-bad'));
                                    ?>
                                    <span class="badge <?php echo $sCls; ?>" id="sched-status-<?php echo (int)$s['Schedule_ID']; ?>">
                                        <?php echo htmlspecialchars($s['Status'] ?: 'Pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="HWComm.openChat('schedule_<?php echo (int)$s['Schedule_ID']; ?>')">Chat</button>
                                </td>
                                <td>
                                    <form onsubmit="updateSchedStatus(event, <?php echo (int)$s['Schedule_ID']; ?>)" style="display:flex;gap:6px;">
                                        <select id="sched-status-select-<?php echo (int)$s['Schedule_ID']; ?>" style="padding:5px;border:1px solid #C8D8DC;border-radius:6px;font-size:12px;">
                                            <option value="Pending"<?php echo $s['Status'] === 'Pending'? 'selected': ''; ?>>Pending</option>
                                            <option value="In Progress"<?php echo $s['Status'] === 'In Progress'? 'selected': ''; ?>>In Progress</option>
                                            <option value="Completed"<?php echo $s['Status'] === 'Completed'? 'selected': ''; ?>>Completed</option>
                                            <option value="Cancelled"<?php echo $s['Status'] === 'Cancelled'? 'selected': ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">No scheduled appointments found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: CLINICAL PET RECORDS & CHECKUP LOGGER -->
    <div id="vet-tab-pets" class="tab-pane">
        <div class="panel" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0;font-size:16px;">Clinical Pet Records & Checkup / Prescription Logger</h3>
                <p style="margin:2px 0 0 0;font-size:12.5px;color:#62787B;">View and update pet medical charts, current ailments, active medications, and log clinical checkups directly as a Veterinarian.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openVetCheckupModal()">+ Log Clinical Checkup / Rx</button>
        </div>

        <div class="panel">
            <div style="overflow-x:auto;">
                <table class="data-table" id="vetPetsTable">
                    <thead>
                        <tr>
                            <th>Pet ID & Name</th>
                            <th>Owner</th>
                            <th>Species / Age</th>
                            <th>Current Ailment</th>
                            <th>Active Medications</th>
                            <th>Past Conditions</th>
                            <th>Checkups</th>
                            <th>Clinical Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: LIVE CLINIC ROOMS -->
    <div id="vet-tab-rooms" class="tab-pane">
        <div class="panel">
            <h3 style="margin-top:0;font-size:16px;">Live Clinic Room Occupancy & Control</h3>
            <p style="font-size:12.5px;color:#62787B;margin-top:-4px;">Rooms automatically transition to Ongoing when assigned an active emergency or in-progress procedure, or you can toggle them manually below.</p>
            <div class="room-grid" id="vetRoomGrid"></div>
        </div>
    </div>

    <!-- TAB 5: UNIFIED CLIENT & PET HISTORY -->
    <div id="vet-tab-history" class="tab-pane">
        <div class="panel">
            <h3 style="margin-top:0;font-size:16px;">Unified Client & Pet Clinical Timeline</h3>
            <div style="overflow-x:auto;max-height:500px;overflow-y:auto;">
                <table class="data-table" id="clientHistoryTable">
                    <thead>
                        <tr><th>Type</th><th>Client</th><th>Pet</th><th>Vet</th><th>Date</th><th>Details</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Log Clinical Checkup / Rx -->
<div class="modal-backdrop" id="vetCheckupModal">
    <div class="modal-card">
        <h3 style="margin:0 0 12px 0;font-size:18px;">Log Clinical Checkup & Prescription</h3>
        <form id="vetCheckupForm">
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Select Pet</label>
                <select id="chkPetId"required style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></select>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Checkup Date</label>
                <input type="date" id="chkDate"required value="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Diagnosis / Current Ailment</label>
                <input type="text" id="chkDiagnosis" placeholder="e.g. Acute Gastroenteritis, Dental Tartar Stage II" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Prescription / Active Medications</label>
                <input type="text" id="chkPrescription" placeholder="e.g. Amoxicillin 250mg BID x 7 days" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Clinical Examination Notes</label>
                <textarea id="chkNotes" rows="3" placeholder="Vitals, weight, physical exam findings, follow-up instructions..." style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;margin-bottom:16px;cursor:pointer;">
                <input type="checkbox" id="chkUpdateChart"checked>
                <span>Also update pet's active medical chart (Current Ailment & Medications)</span>
            </label>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeVetCheckupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Clinical Checkup</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Pet Clinical Chart -->
<div class="modal-backdrop" id="vetEditPetModal">
    <div class="modal-card">
        <h3 style="margin:0 0 12px 0;font-size:18px;">Update Pet Medical Chart</h3>
        <form id="vetEditPetForm">
            <input type="hidden" id="editMedPetId">
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Pet Name</label>
                <input type="text" id="editMedPetName"readonly style="width:100%;padding:9px;border:1px solid #E3EBEC;background:#F6FAFA;border-radius:8px;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Species</label>
                    <input type="text" id="editMedSpecies" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Age (Years)</label>
                    <input type="number" id="editMedAge" min="0" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Current Ailment</label>
                <textarea id="editMedAilment" rows="2" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></textarea>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Active Medications</label>
                <textarea id="editMedMeds" rows="2" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></textarea>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Past Conditions / Surgical History</label>
                <textarea id="editMedPast" rows="2" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('vetEditPetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Medical Record</button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENT_VET_ID = <?php echo (int)$currentVetId; ?>;
const CURRENT_VET_ROLE = <?php echo json_encode(hw_session_role()); ?>;
let vetPetsCache = [];
const vetPetsMap = new Map();

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')
        || document.querySelector('meta[name="hw-csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
}

function buildCsrfHeaders(extra = {}) {
    return Object.assign({ 'X-CSRF-Token': getCsrfToken() }, extra);
}

document.addEventListener('DOMContentLoaded', function () {
    if (window.HWCall) {
        HWCall.init('vet');
    }

    const petTbody = document.querySelector('#vetPetsTable tbody');
    if (petTbody) {
        petTbody.addEventListener('click', function (event) {
            const btn = event.target.closest('button[data-action][data-pet-id]');
            if (!btn) return;
            const petId = Number(btn.dataset.petId);
            if (!Number.isFinite(petId) || petId <= 0) return;
            if (btn.dataset.action === 'checkup') {
                openVetCheckupModal(petId);
            } else if (btn.dataset.action === 'edit-pet') {
                const petRecord = vetPetsMap.get(petId);
                if (petRecord) {
                    openVetEditPetModal(petRecord);
                }
            }
        });
    }

    const roomGrid = document.getElementById('vetRoomGrid');
    if (roomGrid) {
        roomGrid.addEventListener('change', function (event) {
            const select = event.target.closest('select[data-room-id]');
            if (!select) return;
            const roomId = Number(select.dataset.roomId);
            if (Number.isFinite(roomId) && roomId > 0) {
                updateVetRoomStatus(roomId, select.value);
            }
        });
    }

    loadVetClinicalData();
    loadClientHistory();
});

function switchVetTab(tabName, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('vet-tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}

async function rerunTriageEngine() {
    if (window.HWBone) HWBone.showOverlay('Loading...', 'Evaluating emergency priority queue and room allocation');
    const res = await fetch('api/triage_engine.php?action=queue');
    const data = await res.json();
    if (data.success) {
        if (window.HWComm) {
            HWComm.broadcastTriageUpdate(data.triage_summary);
        }
        window.location.reload();
    }
}

async function changeEmergencySeverity(bookingId, severityLevel) {
    const res = await fetch('api/triage_engine.php?action=set_severity', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ booking_id: bookingId, severity_level: severityLevel, csrf_token: getCsrfToken() }),
    });
    const data = await res.json();
    if (data.success) {
        if (window.HWComm) {
            HWComm.broadcastTriageUpdate(data.triage_summary);
        }
        window.location.reload();
    } else {
        alert(data.error || data.message || 'Could not change severity.');
    }
}

async function autoAssignAllUnassigned() {
    if (window.HWBone) HWBone.showOverlay('Loading...', 'Matching unassigned appointments to available veterinarians and rooms');
    const res = await fetch('api/scheduler.php?action=auto_assign_all', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ csrf_token: getCsrfToken() }),
    });
    const data = await res.json();
    if (data.success) {
        alert(`Auto-assigned ${data.updated_count} appointment(s) to available Veterinarians and Rooms.`);
        window.location.reload();
    } else {
        alert(data.error || data.message || 'Could not auto-assign appointments.');
    }
}

async function updateStatus(event, bookingId) {
    event.preventDefault();
    const newStatus = document.getElementById(`status-select-${bookingId}`).value;
    let reason = '';
    if (newStatus === 'Cancelled') {
        reason = prompt('Please provide a clinical reason for cancelling this emergency request:');
        if (reason === null) return;
        if (reason.trim() === '') {
            alert('A cancellation reason is required.');
            return;
        }
    }
    const res = await fetch('update_status.php', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ booking_id: bookingId, status: newStatus, reason, csrf_token: getCsrfToken() }),
    });
    const result = await res.json();
    if (result.success) {
        window.location.reload();
    } else {
        alert(result.message || result.error || 'Failed to save status.');
    }
}

async function toggleCallFeature(checkbox) {
    const bookingId = checkbox.dataset.bookingId;
    const enabled = checkbox.checked;
    checkbox.disabled = true;
    try {
        const res = await fetch('toggle_call_feature.php', {
            method: 'POST',
            headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ booking_id: bookingId, enabled, csrf_token: getCsrfToken() }),
        });
        const data = await res.json();
        if (!data.success) {
            checkbox.checked = !enabled;
            alert(data.message || data.error || 'Could not toggle call feature.');
        }
    } catch (e) {
        checkbox.checked = !enabled;
    } finally {
        checkbox.disabled = false;
    }
}

async function updateSchedStatus(event, scheduleId) {
    event.preventDefault();
    const newStatus = document.getElementById(`sched-status-select-${scheduleId}`).value;
    const res = await fetch('update_sched_status.php', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ schedule_id: scheduleId, status: newStatus, csrf_token: getCsrfToken() }),
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || data.error || 'Failed to save appointment status.');
    }
}

// Search & Filter Emergencies
const searchEmergencyInput = document.getElementById('search-emergencies');
const filterEmergencyStatus = document.getElementById('filter-emergency-status');
if (searchEmergencyInput && filterEmergencyStatus) {
    searchEmergencyInput.addEventListener('input', filterEmergencies);
    filterEmergencyStatus.addEventListener('change', filterEmergencies);
}
function filterEmergencies() {
    const q = (searchEmergencyInput.value || '').toLowerCase();
    const st = filterEmergencyStatus.value;
    document.querySelectorAll('#booking-table-body tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowStatus = (row.querySelector('.badge')?.textContent || '').trim();
        const matchQ = !q || text.includes(q);
        const matchSt = !st || rowStatus === st;
        row.style.display = (matchQ && matchSt) ? '' : 'none';
    });
}

// Search & Filter Appointments
const searchApptInput = document.getElementById('search-appointments');
const filterApptStatus = document.getElementById('filter-status');
if (searchApptInput && filterApptStatus) {
    searchApptInput.addEventListener('input', filterAppointments);
    filterApptStatus.addEventListener('change', filterAppointments);
}
function filterAppointments() {
    const q = (searchApptInput.value || '').toLowerCase();
    const st = filterApptStatus.value;
    const myOnly = document.getElementById('filter-my-vet-only')?.checked;
    document.querySelectorAll('#schedule-table-body tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowStatus = (row.querySelector('[id^="sched-status-"]')?.textContent || '').trim();
        const rowVetId = Number(row.getAttribute('data-vet-id') || 0);
        const matchQ = !q || text.includes(q);
        const matchSt = !st || rowStatus === st;
        const matchVet = !myOnly || (CURRENT_VET_ID > 0 && rowVetId === CURRENT_VET_ID);
        row.style.display = (matchQ && matchSt && matchVet) ? '' : 'none';
    });
}

function createEmptyRow(colSpan, text) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = colSpan;
    td.className = 'empty';
    td.textContent = text;
    tr.appendChild(td);
    return tr;
}

// Load Clinical Pets & Rooms for Vet (safe DOM construction; no raw DB HTML interpolation)
async function loadVetClinicalData() {
    if (window.HWBone) {
        const pt = document.querySelector('#vetPetsTable tbody');
        if (pt) pt.innerHTML = HWBone.tableRows(8, 4);
        const rg = document.getElementById('vetRoomGrid');
        if (rg && !rg.children.length) rg.innerHTML = HWBone.roomCards(5);
    }
    const res = await fetch('api/vet_api.php?action=clinical_overview');
    const data = await res.json();
    if (!data.success) return;

    vetPetsCache = Array.isArray(data.pets) ? data.pets : [];
    vetPetsMap.clear();
    vetPetsCache.forEach(p => {
        const numericId = Number(p.Pet_ID);
        if (Number.isFinite(numericId) && numericId > 0) {
            vetPetsMap.set(numericId, p);
        }
    });

    const petTbody = document.querySelector('#vetPetsTable tbody');
    if (petTbody) {
        petTbody.replaceChildren();
        if (vetPetsCache.length === 0) {
            petTbody.appendChild(createEmptyRow(8, 'No pets registered yet.'));
        } else {
            const frag = document.createDocumentFragment();
            vetPetsCache.forEach(p => {
                const petId = Number(p.Pet_ID);
                const tr = document.createElement('tr');

                // Col 1: Pet ID & Name
                const tdPet = document.createElement('td');
                const petStrong = document.createElement('strong');
                petStrong.textContent = `#${petId} ${p.Pet_Name || 'Unnamed Pet'}`;
                tdPet.appendChild(petStrong);
                tr.appendChild(tdPet);

                // Col 2: Owner & Phone
                const tdOwner = document.createElement('td');
                const ownerFull = [p.OwnerFirstName || '', p.OwnerLastName || ''].join(' ').trim();
                tdOwner.appendChild(document.createTextNode(ownerFull || '—'));
                tdOwner.appendChild(document.createElement('br'));
                const phoneSpan = document.createElement('span');
                phoneSpan.style.fontSize = '11px';
                phoneSpan.style.color = '#62787B';
                phoneSpan.textContent = p.OwnerPhone || '';
                tdOwner.appendChild(phoneSpan);
                tr.appendChild(tdOwner);

                // Col 3: Species / Age
                const tdSpecies = document.createElement('td');
                const speciesLabel = p.Species || p.Pet_Type || 'Unknown';
                const ageLabel = (p.Age !== null && p.Age !== undefined && p.Age !== '') ? `${p.Age}y` : '—';
                tdSpecies.textContent = `${speciesLabel} · ${ageLabel}`;
                tr.appendChild(tdSpecies);

                // Col 4: Current Ailment
                const tdAilment = document.createElement('td');
                const ailmentSpan = document.createElement('span');
                ailmentSpan.style.color = p.Current_Ailment ? '#D8546F' : '#4CAF7D';
                ailmentSpan.style.fontWeight = '600';
                ailmentSpan.textContent = p.Current_Ailment || 'Healthy / None';
                tdAilment.appendChild(ailmentSpan);
                tr.appendChild(tdAilment);

                // Col 5: Active Medications
                const tdMeds = document.createElement('td');
                tdMeds.textContent = p.Current_Medications || 'None';
                tr.appendChild(tdMeds);

                // Col 6: Past Conditions
                const tdPast = document.createElement('td');
                tdPast.textContent = p.Past_Conditions || 'None';
                tr.appendChild(tdPast);

                // Col 7: Checkups
                const tdCheckups = document.createElement('td');
                tdCheckups.appendChild(document.createTextNode(`${Number(p.CheckupCount || 0)} `));
                const lastSpan = document.createElement('span');
                lastSpan.style.fontSize = '11px';
                lastSpan.style.color = '#62787B';
                lastSpan.textContent = `(Last: ${p.LastCheckup || 'Never'})`;
                tdCheckups.appendChild(lastSpan);
                tr.appendChild(tdCheckups);

                // Col 8: Clinical Actions (delegated data-pet-id buttons)
                const tdActions = document.createElement('td');
                const btnWrap = document.createElement('div');
                btnWrap.style.display = 'flex';
                btnWrap.style.gap = '6px';
                btnWrap.style.flexWrap = 'wrap';

                const chkBtn = document.createElement('button');
                chkBtn.type = 'button';
                chkBtn.className = 'btn btn-primary btn-sm';
                chkBtn.dataset.action = 'checkup';
                chkBtn.dataset.petId = String(petId);
                chkBtn.textContent = '+ Checkup / Rx';
                btnWrap.appendChild(chkBtn);

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-secondary btn-sm';
                editBtn.dataset.action = 'edit-pet';
                editBtn.dataset.petId = String(petId);
                editBtn.textContent = 'Edit Chart';
                btnWrap.appendChild(editBtn);

                tdActions.appendChild(btnWrap);
                tr.appendChild(tdActions);
                frag.appendChild(tr);
            });
            petTbody.appendChild(frag);
        }
    }

    // Populate checkup pet selector safely via DOM
    const sel = document.getElementById('chkPetId');
    if (sel) {
        sel.replaceChildren();
        const optFrag = document.createDocumentFragment();
        vetPetsCache.forEach(p => {
            const opt = document.createElement('option');
            opt.value = String(Number(p.Pet_ID));
            const ownerName = p.OwnerFirstName || 'Guest';
            const species = p.Species || p.Pet_Type || 'Pet';
            opt.textContent = `${p.Pet_Name || 'Unnamed Pet'} (${species} — Owner: ${ownerName})`;
            optFrag.appendChild(opt);
        });
        sel.appendChild(optFrag);
    }

    // Render clinic rooms safely via DOM
    const rGrid = document.getElementById('vetRoomGrid');
    if (rGrid) {
        rGrid.replaceChildren();
        const rooms = Array.isArray(data.rooms) ? data.rooms : [];
        if (rooms.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'empty';
            emptyDiv.textContent = 'No clinic rooms configured.';
            rGrid.appendChild(emptyDiv);
        } else {
            const roomFrag = document.createDocumentFragment();
            const allowedStatuses = [
                { value: 'idle', label: 'Idle (Available)' },
                { value: 'ongoing', label: 'Ongoing (In Session)' },
                { value: 'booked', label: 'Booked (Reserved)' },
            ];
            rooms.forEach(r => {
                const roomId = Number(r.Room_ID);
                const status = String(r.Status || 'idle');
                const cls = status === 'idle' ? 'badge-good' : (status === 'ongoing' ? 'badge-bad' : 'badge-warn');

                const card = document.createElement('div');
                card.className = 'room-card';

                const nameDiv = document.createElement('div');
                nameDiv.className = 'room-name';
                nameDiv.textContent = r.Room_Name || `Room #${roomId}`;
                card.appendChild(nameDiv);

                const typeDiv = document.createElement('div');
                typeDiv.style.fontSize = '12px';
                typeDiv.style.color = '#62787B';
                typeDiv.style.marginBottom = '8px';
                typeDiv.textContent = `Type: ${r.Room_Type || 'General'}`;
                card.appendChild(typeDiv);

                const badgeWrap = document.createElement('div');
                badgeWrap.style.marginBottom = '10px';
                const badge = document.createElement('span');
                badge.className = `badge ${cls}`;
                badge.textContent = status;
                badgeWrap.appendChild(badge);
                card.appendChild(badgeWrap);

                const select = document.createElement('select');
                select.dataset.roomId = String(roomId);
                select.style.width = '100%';
                select.style.padding = '7px';
                select.style.border = '1px solid #C8D8DC';
                select.style.borderRadius = '6px';
                select.style.fontSize = '12.5px';

                allowedStatuses.forEach(optDef => {
                    const opt = document.createElement('option');
                    opt.value = optDef.value;
                    opt.textContent = optDef.label;
                    opt.selected = (status === optDef.value);
                    select.appendChild(opt);
                });

                card.appendChild(select);
                roomFrag.appendChild(card);
            });
            rGrid.appendChild(roomFrag);
        }
    }
}

window.openVetCheckupModal = function(petId) {
    if (petId) {
        document.getElementById('chkPetId').value = String(Number(petId));
    }
    document.getElementById('vetCheckupModal').classList.add('open');
};

window.closeVetCheckupModal = function() {
    document.getElementById('vetCheckupModal').classList.remove('open');
};

document.getElementById('vetCheckupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const payload = {
        pet_id: document.getElementById('chkPetId').value,
        checkup_date: document.getElementById('chkDate').value,
        diagnosis: document.getElementById('chkDiagnosis').value,
        prescription: document.getElementById('chkPrescription').value,
        notes: document.getElementById('chkNotes').value,
        update_pet_record: document.getElementById('chkUpdateChart').checked,
        csrf_token: getCsrfToken(),
    };
    const res = await fetch('api/vet_api.php?action=save_clinical_note', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
        closeVetCheckupModal();
        e.target.reset();
        loadVetClinicalData();
        loadClientHistory();
    } else {
        alert(data.error || data.message || 'Could not save clinical checkup.');
    }
});

window.openVetEditPetModal = function(p) {
    document.getElementById('editMedPetId').value = p.Pet_ID;
    document.getElementById('editMedPetName').value = p.Pet_Name;
    document.getElementById('editMedSpecies').value = p.Species || p.Pet_Type || '';
    document.getElementById('editMedAge').value = p.Age ?? '';
    document.getElementById('editMedAilment').value = p.Current_Ailment || '';
    document.getElementById('editMedMeds').value = p.Current_Medications || '';
    document.getElementById('editMedPast').value = p.Past_Conditions || '';
    document.getElementById('vetEditPetModal').classList.add('open');
};

document.getElementById('vetEditPetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const payload = {
        pet_id: document.getElementById('editMedPetId').value,
        species: document.getElementById('editMedSpecies').value,
        age: document.getElementById('editMedAge').value,
        current_ailment: document.getElementById('editMedAilment').value,
        current_medications: document.getElementById('editMedMeds').value,
        past_conditions: document.getElementById('editMedPast').value,
        csrf_token: getCsrfToken(),
    };
    const res = await fetch('api/vet_api.php?action=update_pet_medical', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('vetEditPetModal').classList.remove('open');
        loadVetClinicalData();
    } else {
        alert(data.error || data.message || 'Could not save medical record.');
    }
});

window.updateVetRoomStatus = async function(roomId, status) {
    const res = await fetch('api/vet_api.php?action=update_room', {
        method: 'POST',
        headers: buildCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ room_id: roomId, status, csrf_token: getCsrfToken() }),
    });
    const data = await res.json();
    if (!data.success) {
        alert(data.error || data.message || 'You do not have permission to change this room status.');
    }
    loadVetClinicalData();
};

function loadClientHistory() {
    fetch('vet_client_history.php')
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#clientHistoryTable tbody');
            if (!tbody) return;
            tbody.replaceChildren();
            const history = (data.success && Array.isArray(data.history)) ? data.history : [];
            if (history.length === 0) {
                tbody.appendChild(createEmptyRow(6, 'No history recorded yet.'));
                return;
            }
            const frag = document.createDocumentFragment();
            history.forEach(h => {
                const tr = document.createElement('tr');

                const tdType = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge ${h.Type === 'Emergency' ? 'badge-bad' : 'badge-good'}`;
                badge.textContent = h.Type || 'Record';
                tdType.appendChild(badge);
                tr.appendChild(tdType);

                const tdClient = document.createElement('td');
                tdClient.textContent = [h.OwnerFirst || '', h.OwnerLast || ''].join(' ').trim() || '—';
                tr.appendChild(tdClient);

                const tdPet = document.createElement('td');
                tdPet.textContent = h.PetName || '—';
                tr.appendChild(tdPet);

                const tdVet = document.createElement('td');
                tdVet.textContent = h.VetName || '—';
                tr.appendChild(tdVet);

                const tdDate = document.createElement('td');
                tdDate.textContent = h.EventDate || '—';
                tr.appendChild(tdDate);

                const tdDetails = document.createElement('td');
                tdDetails.textContent = h.Details || '—';
                tr.appendChild(tdDetails);

                frag.appendChild(tr);
            });
            tbody.appendChild(frag);
        });
}
</script>
</body>
</html>