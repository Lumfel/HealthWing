<?php
/**
 * HealthWing / VetSystem — Emergency Submission & Priority Triage Trigger (submit_emergency.php)
 *
 * Preserves existing emergency submission behavior while immediately invoking
 * VetSchedulingEngine::recalculateEmergencyTriage() to:
 * 1. Compute the pet's Composite Priority Score (0-100) and Triage Acuity Level.
 * 2. Dictate queue ordering when two or more pets have simultaneous emergencies.
 * 3. Allocate the best available Trauma/Exam Room & Veterinarian, and automatically preempt/reschedule
 *    any conflicting routine appointment if rooms/vets are currently booked.
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('emergency', 10, 300);
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

$ownerId = null;
$ownerName = null;
$ownerEmail = null;
$ownerPhone = null;

if (isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'owner') {
    $stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $ownerId = (int)$row['Owner_ID'];
        $ownerName = trim($row['First_Name'] . ''. $row['Last_Name']);
        $ownerEmail = $row['email'];
        $ownerPhone = $row['Contact'];
    }
    $stmt->close();
}

$details = trim($_POST['details'] ?? '');
$petId = !empty($_POST['pet_id']) ? (int)$_POST['pet_id'] : null;
$explicitSeverity = trim($_POST['severity_level'] ?? '');
if (!in_array($explicitSeverity, ['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'], true)) {
    $explicitSeverity = 'Urgent';
}

if ($ownerId !== null) {
    $name = $ownerName ?: 'Registered Owner';
    $email = $ownerEmail ?: '';
    $phone = $ownerPhone ?: '';
} else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === ''|| $email === ''|| $phone === '') {
        echo json_encode(['success'=> false, 'message'=>'Name, email, and phone are required.']);
        $conn->close();
        exit();
    }
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// Allow multiple emergencies if they are for different pets or allow testing concurrent emergencies if `allow_concurrent`is passed;
// otherwise keep the per-owner/IP duplicate guard unless it's a different pet.
$allowConcurrent = !empty($_POST['allow_concurrent']);
if (!$allowConcurrent) {
    if ($ownerId !== null && $petId !== null) {
        $dupStmt = $conn->prepare("SELECT Booking_ID FROM emergency_booking WHERE Owner_ID = ? AND Pet_ID = ? AND Status IN ('Pending', 'In Progress') LIMIT 1");
        $dupStmt->bind_param("ii", $ownerId, $petId);
    } elseif ($ownerId !== null) {
        $dupStmt = $conn->prepare("SELECT Booking_ID FROM emergency_booking WHERE Owner_ID = ? AND Status IN ('Pending', 'In Progress') LIMIT 1");
        $dupStmt->bind_param("i", $ownerId);
    } else {
        $dupStmt = $conn->prepare("SELECT Booking_ID FROM emergency_booking WHERE Owner_ID IS NULL AND IP_Address = ? AND Status IN ('Pending', 'In Progress') LIMIT 1");
        $dupStmt->bind_param("s", $ip);
    }
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();
    if ($dupResult->num_rows >0) {
        $dupStmt->close();
        echo json_encode([
            'success'=> false,
            'message'=>'You already have an active emergency request in the triage queue. Check "My Bookings & Triage"for your live queue position and direct video/chat link.'
        ]);
        $conn->close();
        exit();
    }
    $dupStmt->close();
}

$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/emergency_uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed, true) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
        $safeName = 'emergency_'. time() . '_'. bin2hex(random_bytes(4)) . '.'. $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $safeName)) {
            $imagePath = 'emergency_uploads/'. $safeName;
        }
    }
}

$stmt = $conn->prepare(
    "INSERT INTO emergency_booking (Owner_ID, Pet_ID, Name, Email, Phone_Number, Additional_Details, Image_Path, Status, Severity_Level, IP_Address)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)"
);
$stmt->bind_param("iisssssss", $ownerId, $petId, $name, $email, $phone, $details, $imagePath, $explicitSeverity, $ip);

if ($stmt->execute()) {
    $newBookingId = (int)$stmt->insert_id;
    $stmt->close();

    // Run Priority & Room/Vet Preemption Algorithm across all active emergencies
    $engine = new VetSchedulingEngine($conn);
    $triageResult = $engine->recalculateEmergencyTriage();

    // Find this booking's assigned position & rationale
    $myAllocation = null;
    foreach ($triageResult['queue'] as $qItem) {
        if ((int)$qItem['booking_id'] === $newBookingId) {
            $myAllocation = $qItem;
            break;
        }
    }

    // Seed an automated triage status message in the case chat channel
    $chanKey = 'booking_'. $newBookingId;
    $triageMsg = $myAllocation
        ? "Automated Triage Engine: Case #{$newBookingId} classified as {$myAllocation['severity_level']} (Priority Score: {$myAllocation['priority_score']}/100, Queue Position #{$myAllocation['queue_position']}). {$myAllocation['rationale']}"
        : "Emergency request #{$newBookingId} received and entered into triage.";
    $cStmt = $conn->prepare(
        "INSERT INTO chat_messages (Channel_Key, Booking_ID, Sender_Role, Sender_Name, Message_Text, Message_Type)
         VALUES (?, ?, 'system', 'Priority Triage Engine', ?, 'triage_notice')"
    );
    $cStmt->bind_param('sis', $chanKey, $newBookingId, $triageMsg);
    $cStmt->execute();
    $cStmt->close();

    $replyMsg = $myAllocation
        ? "Emergency request #{$newBookingId} triaged at Queue Position #{$myAllocation['queue_position']} ({$myAllocation['severity_level']}, Score {$myAllocation['priority_score']}/100) — Assigned: {$myAllocation['assigned_room_name']} & {$myAllocation['assigned_vet_name']}."
        : "Emergency request submitted. We will contact you shortly.";

    echo json_encode([
        'success'=> true,
        'booking_id'=>$newBookingId,
        'triage'=>$myAllocation,
        'preempted_schedules'=>$triageResult['preempted_schedules'],
        'message'=>$replyMsg,
    ]);
} else {
    $stmt->close();
    echo json_encode(['success'=> false, 'message'=>'Could not submit your request. Please try again or call the hotline.']);
}

$conn->close();
