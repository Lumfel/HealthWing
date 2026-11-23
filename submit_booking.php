<?php
/**
 * HealthWing / VetSystem — Automated Appointment Booking Handler (submit_booking.php)
 *
 * Uses VetSchedulingEngine::findOptimalSlotAndResources() to:
 * 1. Validate clinic operating hours and service duration.
 * 2. Automatically select the best available Veterinarian (by specialty + workload balance)
 *    and compatible Clinic Room (`Room_ID`).
 * 3. Automatically resolve conflicts by shifting to the nearest open slot if the requested time is occupied.
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';

$bookingSummary = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hw_csrf_verify()) {
        $errorMsg = 'Invalid or expired security token. Please go back and submit the booking form again.';
    } else {
    try {
        $conn = hw_get_db();
        $engine = new VetSchedulingEngine($conn);

        $ownerName = trim($_POST['owner_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $petIdInput = !empty($_POST['pet_id']) ? (int)$_POST['pet_id'] : null;
        $petName = trim($_POST['pet_name'] ?? '');
        $petSpecies = trim($_POST['pet_species'] ?? 'Dog');
        $service = trim($_POST['service'] ?? 'Wellness Care');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');
        $preferredVetId = !empty($_POST['preferred_vet_id']) ? (int)$_POST['preferred_vet_id'] : null;
        $details = trim($_POST['details'] ?? '');

        $ownerId = null;
        $petId = null;

        // 1. Resolve Owner_ID from session or email
        if (isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'owner') {
            $stmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $_SESSION['username']);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $ownerId = (int)$row['Owner_ID'];
                if ($ownerName === '') {
                    $ownerName = trim($row['First_Name'] . ''. $row['Last_Name']);
                }
            }
            $stmt->close();
        }

        if (!$ownerId && $email !== '') {
            $stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $ownerId = (int)$row['Owner_ID'];
            }
            $stmt->close();
        }

        // 2. Resolve or create Pet_ID
        if ($petIdInput && $ownerId) {
            $stmt = $conn->prepare('SELECT Pet_ID, Pet_Name FROM pet WHERE Pet_ID = ? AND Owner_ID = ? LIMIT 1');
            $stmt->bind_param('ii', $petIdInput, $ownerId);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $petId = (int)$row['Pet_ID'];
                $petName = $row['Pet_Name'];
            }
            $stmt->close();
        }

        if (!$petId && $petName !== '') {
            $stmt = $conn->prepare('SELECT Pet_ID FROM pet WHERE Pet_Name = ? AND Owner_ID '. ($ownerId ? '= ?': 'IS NULL') . 'LIMIT 1');
            if ($ownerId) {
                $stmt->bind_param('si', $petName, $ownerId);
            } else {
                $stmt->bind_param('s', $petName);
            }
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $petId = (int)$row['Pet_ID'];
            } else {
                $insPet = $conn->prepare('INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Species) VALUES (?, ?, ?, ?)');
                $insPet->bind_param('isss', $ownerId, $petName, $petSpecies, $petSpecies);
                $insPet->execute();
                $petId = (int)$insPet->insert_id;
                $insPet->close();
            }
            $stmt->close();
        }

        // 3. Run Automated Scheduling Algorithm to pick optimal Slot, Veterinarian, and Room
        $optimal = $engine->findOptimalSlotAndResources($service, $appointmentTime, $preferredVetId);

        $assignedTime = $optimal['appointment_time'];
        $assignedEnd = $optimal['end_time'];
        $durationMins = $optimal['duration_minutes'];
        $assignedVetId = $optimal['vet_id'];
        $assignedRoomId = $optimal['room_id'];

        $notes = $details;
        if ($optimal['was_shifted']) {
            $notes = trim($notes . "[Auto-Scheduler: Requested {$optimal['requested_time']} had a room/vet conflict or fell outside clinic hours; automatically assigned next open slot {$assignedTime}]");
        }

        $stmt = $conn->prepare(
            "INSERT INTO schedule (Owner_ID, Pet_ID, Veterinarian_ID, Room_ID, Services, Appointment_Time, Duration_Minutes, End_Time, Notes, Status, Auto_Assigned)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 1)"
        );
        $stmt->bind_param(
            'iiiississ',
            $ownerId,
            $petId,
            $assignedVetId,
            $assignedRoomId,
            $service,
            $assignedTime,
            $durationMins,
            $assignedEnd,
            $notes
        );
        $stmt->execute();
        $scheduleId = (int)$stmt->insert_id;
        $stmt->close();

        // Seed chat channel notification
        if ($scheduleId) {
            $chanKey = 'schedule_'. $scheduleId;
            $msgText = "Automated Scheduler confirmed Appointment #{$scheduleId} for {$petName} ({$service}) on {$assignedTime} ({$durationMins} mins) in {$optimal['room_name']} with {$optimal['vet_name']}.";
            $cStmt = $conn->prepare(
                "INSERT INTO chat_messages (Channel_Key, Schedule_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
                 VALUES (?, ?, 'system', 'Automated Scheduler', ?, 'system_alert')"
            );
            $cStmt->bind_param('sis', $chanKey, $scheduleId, $msgText);
            $cStmt->execute();
            $cStmt->close();
        }

        $bookingSummary = [
            'schedule_id'=>$scheduleId,
            'pet_name'=>$petName ?: 'Pet',
            'service'=>$service,
            'appointment_time'=>$assignedTime,
            'end_time'=>$assignedEnd,
            'duration_minutes'=>$durationMins,
            'was_shifted'=>$optimal['was_shifted'],
            'requested_time'=>$optimal['requested_time'],
            'vet_name'=>$optimal['vet_name'],
            'vet_specialty'=>$optimal['vet_specialty'],
            'room_name'=>$optimal['room_name'],
        ];

        $conn->close();
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Scheduled | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet">
    <link href="api/asset_proxy.php?f=admin/dashboard.css" rel="stylesheet">
</head>
<body style="background:#F4F9FA;min-height:100vh;font-family:system-ui,-apple-system,sans-serif;">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div style="max-width:620px;margin:48px auto;padding:0 20px;">
    <?php if ($bookingSummary): ?>
        <div style="background:#fff;border:1px solid #E3EBEC;border-radius:16px;padding:30px;box-shadow:0 12px 32px rgba(15,35,42,0.08);">
            <div style="display:inline-flex;align-items:center;gap:6px;background:#E7F5EC;color:#246A46;font-size:12px;font-weight:800;padding:5px 12px;border-radius:999px;margin-bottom:12px;">
                 Automated Smart Assignment Complete
            </div>
            <h1 style="font-size:24px;font-weight:800;color:#16262A;margin:0 0 8px 0;">Appointment Scheduled!</h1>
            <p style="color:#62787B;font-size:14px;margin:0 0 20px 0;">
                Our scheduling algorithm matched your pet's service with the optimal veterinarian and clinic room.
            </p>

            <?php if ($bookingSummary['was_shifted']): ?>
                <div style="background:#FFF8E7;border:1px solid #F5D487;color:#7A5200;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;">
                    <strong>Automatic Conflict Resolution Applied:</strong>Your originally requested time (<?php echo htmlspecialchars($bookingSummary['requested_time']); ?>) conflicted with an existing booking or clinic hours, so our scheduler automatically locked in the nearest conflict-free slot below.
                </div>
            <?php endif; ?>

            <div style="background:#F6FAFA;border:1px solid #E3EBEC;border-radius:12px;padding:16px;margin-bottom:22px;display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
                <div>
                    <div style="color:#62787B;font-size:11.5px;font-weight:700;text-transform:uppercase;">Reference ID</div>
                    <div style="font-weight:800;">#<?php echo (int)$bookingSummary['schedule_id']; ?></div>
                </div>
                <div>
                    <div style="color:#62787B;font-size:11.5px;font-weight:700;text-transform:uppercase;">Pet & Service</div>
                    <div style="font-weight:800;"><?php echo htmlspecialchars($bookingSummary['pet_name'] . '— '. $bookingSummary['service']); ?></div>
                </div>
                <div>
                    <div style="color:#62787B;font-size:11.5px;font-weight:700;text-transform:uppercase;">Confirmed Slot</div>
                    <div style="font-weight:800;color:#1F5B70;"><?php echo htmlspecialchars($bookingSummary['appointment_time']); ?>(<?php echo (int)$bookingSummary['duration_minutes']; ?>m)</div>
                </div>
                <div>
                    <div style="color:#62787B;font-size:11.5px;font-weight:700;text-transform:uppercase;">Assigned Room</div>
                    <div style="font-weight:800;"><?php echo htmlspecialchars($bookingSummary['room_name']); ?></div>
                </div>
                <div style="grid-column:span 2;">
                    <div style="color:#62787B;font-size:11.5px;font-weight:700;text-transform:uppercase;">Assigned Veterinarian</div>
                    <div style="font-weight:800;">Dr. <?php echo htmlspecialchars($bookingSummary['vet_name']); ?><span style="font-weight:500;color:#62787B;">(Specialty: <?php echo htmlspecialchars($bookingSummary['vet_specialty']); ?>)</span></div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                <a href="public_calendar.php" class="btn btn-secondary" style="text-decoration:none;">View Public Calendar</a>
                <a href="owner_bookings.php" class="btn btn-primary" style="text-decoration:none;">Go to My Bookings →</a>
            </div>
        </div>
    <?php else: ?>
        <div style="background:#fff;border:1px solid #E3EBEC;border-radius:16px;padding:30px;text-align:center;">
            <h2 style="color:#D8546F;">Could Not Complete Booking</h2>
            <p style="color:#62787B;"><?php echo htmlspecialchars($errorMsg ?: 'Please submit the appointment form.'); ?></p>
            <a href="appointment_schedule.php" class="btn btn-primary" style="text-decoration:none;">Back to Booking Form</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>