<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(['success'=> false, 'message'=>'Not logged in.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ?');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerResult = $stmt->get_result();
$ownerRow = $ownerResult->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    echo json_encode(['success'=> false, 'message'=>'Owner not found.']);
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];
$petId = (int)($_POST['petId'] ?? 0);

if (!$petId) {
    echo json_encode(['success'=> false, 'message'=>'Missing pet id.']);
    exit();
}

// Security check: make sure this pet actually belongs to the logged-in owner
// before allowing a photo to be attached to it — otherwise anyone logged in
// as any owner could overwrite another family's pet photo just by knowing its ID.
$stmt = $conn->prepare('SELECT Pet_ID FROM pet WHERE Pet_ID = ? AND Owner_ID = ?');
$stmt->bind_param('ii', $petId, $ownerId);
$stmt->execute();
$ownsResult = $stmt->get_result();
if ($ownsResult->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['success'=> false, 'message'=>'You can only update photos for your own pets.']);
    exit();
}
$stmt->close();

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=> false, 'message'=>'No image was uploaded.']);
    exit();
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true) || $_FILES['image']['size'] >5 * 1024 * 1024) {
    echo json_encode(['success'=> false, 'message'=>'Please upload a JPG, PNG, GIF, or WEBP image under 5MB.']);
    exit();
}

$uploadDir = __DIR__ . '/pet_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$safeName = 'pet_'. $petId . '_'. time() . '.'. $ext;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $safeName)) {
    echo json_encode(['success'=> false, 'message'=>'Could not save the uploaded file.']);
    exit();
}

$photoPath = 'pet_photos/'. $safeName;
$stmt = $conn->prepare('UPDATE pet SET Photo_Path = ? WHERE Pet_ID = ?');
$stmt->bind_param('si', $photoPath, $petId);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success'=> true, 'photoPath'=>$photoPath]);
