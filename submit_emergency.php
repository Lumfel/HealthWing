<?php
/**
 * HealthWing / VetSystem — Emergency Submission & Priority Triage Trigger (submit_emergency.php)
 */
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('emergency', 10, 300);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/scheduling_engine.php';
hw_start_secure_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$ownerId = null;
$ownerName = null;
$ownerEmail = null;
$ownerPhone = null;

if (isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'owner') {
    hw_require_csrf();
    $stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $ownerId = (int)$row['Owner_ID'];
        $ownerName = trim($row['First_Name'] . ' ' . $row['Last_Name']);
        $ownerEmail = $row['email'];
        $ownerPhone = $row['Contact'];
    }
    $stmt->close();
}

$details = trim((string)($_POST['details'] ?? ''));
$petId = !empty($_POST['pet_id']) ? (int)$_POST['pet_id'] : null;
$explicitSeverity = trim((string)($_POST['severity_level'] ?? ''));
if (!in_array($explicitSeverity, ['Critical', 'Emergent', 'Urgent', 'Semi-Urgent'], true)) {
    $explicitSeverity = 'Urgent';
}

if ($ownerId !== null) {
    $name = $ownerName ?: 'Registered Owner';
    $email = $ownerEmail ?: '';
    $phone = $ownerPhone ?: '';
} else {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($name === '' || $email === '' || $phone === '') {
        echo json_encode(['success' => false, 'message' => 'Name, email, and phone are required.']);
        $conn->close();
        exit();
    }
}

$ip = hw_get_client_ip();

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
    if ($dupResult->num_rows > 0) {
        $dupStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'You already have an active emergency request in the triage queue. Check My Bookings and Triage for your live queue position.',
        ]);
        $conn->close();
        exit();
    }
    $dupStmt->close();
}

$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
    $tmpPath = $_FILES['image']['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (is_string($mimeType) && isset($mimeToExt[$mimeType]) && @getimagesize($tmpPath) !== false) {
        $uploadDir = __DIR__ . '/emergency_uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $safeName = 'emergency_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $mimeToExt[$mimeType];
        if (move_uploaded_file($tmpPath, $uploadDir . $safeName)) {
            $imagePath = 'emergency_uploads/' . $safeName;
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

    $engine = new VetSchedulingEngine($conn);
    $triageResult = $engine->recalculateEmergencyTriage();

    $myAllocation = null;
    foreach ($triageResult['queue'] as $qItem) {
        if ((int)$qItem['booking_id'] === $newBookingId) {
            $myAllocation = $qItem;
            break;
        }
    }

    $chanKey = 'booking_' . $newBookingId;
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
        ? "Emergency request #{$newBookingId} triaged at Queue Position #{$myAllocation['queue_position']} ({$myAllocation['severity_level']}, Score {$myAllocation['priority_score']}/100) - Assigned: {$myAllocation['assigned_room_name']} & {$myAllocation['assigned_vet_name']}."
        : "Emergency request submitted. We will contact you shortly.";

    echo json_encode([
        'success' => true,
        'booking_id' => $newBookingId,
        'triage' => $myAllocation,
        'preempted_schedules' => $triageResult['preempted_schedules'],
        'message' => $replyMsg,
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Could not submit your request. Please try again or call the hotline.']);
}

$conn->close();
