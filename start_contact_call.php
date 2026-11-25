<?php
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('contact_call_create', 10, 300);

require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();
header('Content-Type: application/json');

hw_require_role(['owner'], true);

hw_require_csrf();

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Owner account not found.']);
    exit();
}

$ip = hw_get_client_ip();

$name = trim($owner['First_Name'] . ' ' . $owner['Last_Name']) ?: 'Registered Owner';
$email = $owner['email'] ?: '';
$phone = $owner['Contact'] ?: '';
$details = 'Direct contact call requested from the Contact page.';

$stmt = $conn->prepare(
    "INSERT INTO emergency_booking (Owner_ID, Name, Email, Phone_Number, Additional_Details, Status, IP_Address, Call_Enabled)
     VALUES (?, ?, ?, ?, ?, 'Contact', ?, 1)"
);
$stmt->bind_param("isssss", $owner['Owner_ID'], $name, $email, $phone, $details, $ip);

if ($stmt->execute()) {
    $bookingId = $conn->insert_id;
    rate_limit_log_event('contact_call_create', 'allowed');
    echo json_encode(['success' => true, 'bookingId' => $bookingId, 'callerName' => $name]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not start a call request. Please try again.']);
}

$stmt->close();
$conn->close();
