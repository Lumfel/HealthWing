<?php
require_once __DIR__ . '/rate_limit.php';
rate_limit_check('contact_call_create', 10, 300);

require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'You need to sign in as a pet owner to use this.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare("SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    echo json_encode(['success'=> false, 'message'=>'Owner account not found.']);
    exit();
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

$name = trim($owner['First_Name'] . ''. $owner['Last_Name']) ?: 'Registered Owner';
$email = $owner['email'] ?: '';
$phone = $owner['Contact'] ?: '';
$details = 'Direct contact call requested from the Contact page.';

// Status = 'Contact'keeps this out of the real emergency Pending/urgent
// counts on the vet dashboard, and Call_Enabled is turned on immediately
// since the owner initiated this themselves — a vet doesn't need to
// separately approve calling in this case, unlike an emergency booking
// where the vet controls when calling becomes available.
$stmt = $conn->prepare(
    "INSERT INTO emergency_booking (Owner_ID, Name, Email, Phone_Number, Additional_Details, Status, IP_Address, Call_Enabled)
     VALUES (?, ?, ?, ?, ?, 'Contact', ?, 1)"
);
$stmt->bind_param("issssss", $owner['Owner_ID'], $name, $email, $phone, $details, $ip);

if ($stmt->execute()) {
    $bookingId = $conn->insert_id;
    rate_limit_log_event('contact_call_create', 'allowed');
    echo json_encode(['success'=> true, 'bookingId'=>$bookingId, 'callerName'=>$name]);
} else {
    echo json_encode(['success'=> false, 'message'=>'Could not start a call request. Please try again.']);
}

$stmt->close();
$conn->close();
