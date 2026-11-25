<?php
/**
 * HealthWing / VetSystem — Pet Owner Bookings, Live Triage Queue & Comm Console (owner_bookings.php)
 *
 * Displays:
 * 1. Owner's Scheduled Appointments (with assigned Veterinarian, Room, Duration, Preemption notices,
 *    1-Click Chat, Cancel button, and Star Rating link).
 * 2. Owner's Emergency Bookings (with live Queue Position #1/#2, Priority Score, Severity Level,
 *    Assigned Room & Vet, Triage Rationale, and 1-Click Video/Audio/Chat).
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
require_once __DIR__ . '/guest_notice.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';

if (!hw_is_owner()) {
    show_guest_notice("You need to sign in as a pet owner to view your bookings and live emergency triage status.");
    exit();
}

$conn = hw_get_db();
$engine = new VetSchedulingEngine($conn);
$engine->recalculateEmergencyTriage();

$stmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name FROM owner WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    $conn->close();
    show_guest_notice("We couldn't find an owner account matching your session. Please sign in again.");
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];

// Fetch emergency bookings with assigned vet & room
$emStmt = $conn->prepare(
    "SELECT e.*,
            v.First_Name AS VetFirst, v.Last_Name AS VetLast,
            rm.Room_Name
     FROM emergency_booking e
     LEFT JOIN veterinarian v ON e.Assigned_Vet_ID = v.Veterinarian_ID
     LEFT JOIN room rm ON e.Assigned_Room_ID = rm.Room_ID
     WHERE e.Owner_ID = ?
     ORDER BY e.Created_At DESC"
);
$emStmt->bind_param('i', $ownerId);
$emStmt->execute();
$emergencies = $emStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$emStmt->close();

// Fetch scheduled appointments with assigned vet, room, and ratings
$schedStmt = $conn->prepare(
    "SELECT s.*,
            p.Pet_Name,
            v.First_Name AS VetFirst, v.Last_Name AS VetLast, v.Specialties,
            rm.Room_Name,
            r.Rating_ID, r.Rating AS ExistingRating
     FROM schedule s
     LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
     LEFT JOIN veterinarian v ON s.Veterinarian_ID = v.Veterinarian_ID
     LEFT JOIN room rm ON s.Room_ID = rm.Room_ID
     LEFT JOIN ratings r ON r.Schedule_ID = s.Schedule_ID
     WHERE s.Owner_ID = ?
     ORDER BY s.Appointment_Time DESC"
);
$schedStmt->bind_param('i', $ownerId);
$schedStmt->execute();
$schedules = $schedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$schedStmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content=" width=device-width, initial-scale=1.0">
    <title>My Bookings & Live Triage | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet">
    <style>
        body { background: #F4F9FA; color: #16262A; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .owner-wrap { max-width: 1260px; margin: 28px auto 70px; padding: 0 20px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="owner-wrap">
    <!-- Top Banner -->
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:14px;padding:22px 24px;margin-bottom:22px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:14px;">
        <div>
            <h1 style="font-size:23px;font-weight:800;margin:0 0 4px 0;">My Appointments & Emergency Triage Status</h1>
            <p style="color:#62787B;font-size:13.5px;margin:0;">
                Track your assigned veterinarian, clinic room, live emergency priority queue position, and connect via direct WebSocket Video/Voice/Chat.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="appointment_schedule.php" class="btn btn-primary" style="text-decoration:none;">+ Book Appointment</a>
            <button type="button" class="btn btn-secondary" onclick="HWComm.openChat('owner_<?php echo $ownerId; ?>')">Message Clinic Care Team</button>
        </div>
    </div>

    <!-- Section 1: Emergency Triage Queue -->
    <div class="panel" style="margin-bottom:24px;">
        <h3 style="margin:0 0 12px 0;font-size:17px;display:flex;align-items:center;gap:8px;">
            <span>My Emergency Requests & Priority Triage Status</span>
        </h3>
        <?php if (!empty($emergencies)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Case ID</th>
                            <th>Queue Position & Priority Score</th>
                            <th>Assigned Room & Vet</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Direct Video / Voice / Chat</th>
                            <th>Cancel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emergencies as $erow):
                            $isResolved = in_array($erow['Status'], ['Completed', 'Cancelled'], true);
                            $minutesElapsed = (time() - strtotime($erow['Created_At'])) / 60;
                        ?>
                        <tr style="<?php echo $isResolved ? 'opacity:0.55;': ''; ?>">
                            <td>
                                <strong>#<?php echo (int)$erow['Booking_ID']; ?></strong>
                                <div style="font-size:11px;color:#62787B;"><?php echo htmlspecialchars($erow['Created_At']); ?></div>
                            </td>
                            <td>
                                <?php if (!$isResolved && !empty($erow['Queue_Position'])): ?>
                                    <div style="font-weight:900;font-size:14px;color:<?php echo (int)$erow['Queue_Position'] === 1 ? '#D8546F': '#1F3A3D'; ?>;">
                                        <?php echo (int)$erow['Queue_Position'] === 1 ? '#1 Next / In Treatment': 'Queue #'. (int)$erow['Queue_Position']; ?>
                                    </div>
                                <?php endif; ?>
                                <span class="badge badge-neutral">
                                    <?php echo htmlspecialchars($erow['Severity_Level'] ?: 'Urgent'); ?>· <?php echo number_format((float)($erow['Priority_Score'] ?? 50), 1); ?>/100
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:700;"><?php echo htmlspecialchars($erow['Room_Name'] ?: 'Awaiting Room'); ?></div>
                                <div style="font-size:12px;color:#62787B;">
                                     <?php echo !empty($erow['VetFirst']) ? ('Dr. '. htmlspecialchars($erow['VetFirst'] . ''. $erow['VetLast'])) : 'On-Call Vet'; ?>
                                </div>
                            </td>
                            <td style="max-width:340px;">
                                <div><?php echo htmlspecialchars($erow['Additional_Details']); ?></div>
                                <?php if (!empty($erow['Triage_Rationale'])): ?>
                                    <div style="font-size:11.5px;color:#62787B;margin-top:2px;">
                                        <?php echo htmlspecialchars($erow['Triage_Rationale']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $eCls = $erow['Status'] === 'Pending'? 'badge-warn'
                                        : ($erow['Status'] === 'In Progress'? 'badge-neutral'
                                        : ($erow['Status'] === 'Completed'? 'badge-good': 'badge-bad'));
                                ?>
                                <span class="badge <?php echo $eCls; ?>"><?php echo htmlspecialchars($erow['Status']); ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if (!$isResolved && $erow['Call_Enabled']): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="HWCall.startCall(<?php echo (int)$erow['Booking_ID']; ?>, 'video', 'the Veterinarian')">Video</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="HWCall.startCall(<?php echo (int)$erow['Booking_ID']; ?>, 'audio', 'the Veterinarian')">Call</button>
                                        <script>document.addEventListener('DOMContentLoaded', () => HWCall.watch(<?php echo (int)$erow['Booking_ID']; ?>, 'the Veterinarian'));</script>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="HWComm.openChat('booking_<?php echo (int)$erow['Booking_ID']; ?>')">Live Chat</button>
                                </div>
                            </td>
                            <td>
                                <?php if ($isResolved): ?>
                                    <span style="color:#62787B;">—</span>
                                <?php elseif ($erow['Cancel_Requested'] == 1): ?>
                                    <span class="badge badge-warn">Cancel Requested</span>
                                <?php elseif ($minutesElapsed < 10): ?>
                                    <button class="btn btn-danger btn-sm" onclick="cancelEmergency(<?php echo (int)$erow['Booking_ID']; ?>, 'direct')">Cancel</button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" onclick="cancelEmergency(<?php echo (int)$erow['Booking_ID']; ?>, 'request')">Request Cancel</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">No emergency requests filed.</div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Scheduled Appointments -->
    <div class="panel">
        <h3 style="margin:0 0 12px 0;font-size:17px;">My Scheduled Appointments</h3>
        <?php if (!empty($schedules)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pet</th>
                            <th>Service & Duration</th>
                            <th>Appointment Slot</th>
                            <th>Assigned Room & Vet</th>
                            <th>Status</th>
                            <th>Live Chat & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$s['Schedule_ID']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($s['Pet_Name'] ?: 'Pet'); ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($s['Services']); ?></div>
                                <div style="font-size:11.5px;color:#62787B;"><?php echo (int)($s['Duration_Minutes'] ?: 30); ?>mins</div>
                            </td>
                            <td>
                                <div style="font-weight:700;"><?php echo htmlspecialchars($s['Appointment_Time']); ?></div>
                                <?php if (!empty($s['Preempted_By_Emergency_ID'])): ?>
                                    <div style="font-size:11.5px;background:#FFF8E7;color:#7A5200;padding:4px 8px;border-radius:6px;margin-top:4px;">
                                         Auto-shifted from <?php echo htmlspecialchars($s['Previous_Appointment_Time'] ?: 'earlier slot'); ?>due to critical emergency #<?php echo (int)$s['Preempted_By_Emergency_ID']; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:700;"><?php echo htmlspecialchars($s['Room_Name'] ?: 'Main Exam Room'); ?></div>
                                <div style="font-size:12px;color:#62787B;">
                                     <?php echo !empty($s['VetFirst']) ? ('Dr. '. htmlspecialchars($s['VetFirst'] . ''. $s['VetLast'])) : 'Assigned Vet'; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $sCls = $s['Status'] === 'Pending'? 'badge-warn'
                                        : ($s['Status'] === 'In Progress'? 'badge-neutral'
                                        : ($s['Status'] === 'Completed'? 'badge-good': 'badge-bad'));
                                ?>
                                <span class="badge <?php echo $sCls; ?>"><?php echo htmlspecialchars($s['Status']); ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="HWComm.openChat('schedule_<?php echo (int)$s['Schedule_ID']; ?>')">Chat</button>
                                    <?php if ($s['Status'] === 'Pending'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="cancelScheduledAppt(<?php echo (int)$s['Schedule_ID']; ?>)">Cancel</button>
                                    <?php elseif ($s['Status'] === 'Completed'&& !empty($s['Rating_ID'])): ?>
                                        <span style="color:#E3A857;font-weight:800;"><?php echo str_repeat('*', (int)$s['ExistingRating']); ?></span>
                                    <?php elseif ($s['Status'] === 'Completed'): ?>
                                        <a href="rate_service.php?schedule_id=<?php echo (int)$s['Schedule_ID']; ?>" class="btn btn-primary btn-sm" style="text-decoration:none;">* Rate</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">No scheduled appointments yet. <a href="appointment_schedule.php" style="color:#4A9DBB;font-weight:700;">Schedule your first visit →</a></div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.HWCall) {
        HWCall.init('owner');
    }
});

function cancelEmergency(bookingId, mode) {
    const confirmMsg = mode === 'direct'
        ? 'Cancel this emergency request?'
        : 'Send a request to cancel this emergency request? A clinic veterinarian will review it.';
    if (!confirm(confirmMsg)) return;

    fetch('cancel_emergency.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json'},
        body: JSON.stringify({ bookingId, mode })
    })
        .then(res => res.json())
        .then(data =>{
            alert(data.message || (data.success ? 'Done.': 'Something went wrong.'));
            if (data.success) window.location.reload();
        });
}

function cancelScheduledAppt(scheduleId) {
    if (!confirm('Cancel this pending appointment?')) return;
    fetch('update_sched_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json'},
        body: JSON.stringify({ schedule_id: scheduleId, status: 'Cancelled'}),
    })
        .then(r => r.json())
        .then(d =>{
            if (d.success) window.location.reload();
            else alert(d.message || 'Could not cancel appointment.');
        });
}
</script>
</body>
</html>
