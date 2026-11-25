<?php
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

$stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Owner not found.']);
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];
$petId = (int)($_POST['petId'] ?? 0);

if (!$petId) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Missing pet id.']);
    exit();
}

$stmt = $conn->prepare('SELECT Pet_ID FROM pet WHERE Pet_ID = ? AND Owner_ID = ? LIMIT 1');
$stmt->bind_param('ii', $petId, $ownerId);
$stmt->execute();
$ownsResult = $stmt->get_result();
if ($ownsResult->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only update photos for your own pets.']);
    exit();
}
$stmt->close();

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'No image was uploaded.']);
    exit();
}

if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Image file must be under 5MB.']);
    exit();
}

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

if (!is_string($mimeType) || !isset($mimeToExt[$mimeType]) || @getimagesize($tmpPath) === false) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Please upload a valid JPG, PNG, GIF, or WEBP image under 5MB.']);
    exit();
}

$ext = $mimeToExt[$mimeType];
$uploadDir = __DIR__ . '/pet_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$safeName = 'pet_' . $petId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

if (!move_uploaded_file($tmpPath, $uploadDir . $safeName)) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Could not save the uploaded file.']);
    exit();
}

$photoPath = 'pet_photos/' . $safeName;
$stmt = $conn->prepare('UPDATE pet SET Photo_Path = ? WHERE Pet_ID = ?');
$stmt->bind_param('si', $photoPath, $petId);
$stmt->execute();
$stmt->close();
$conn->close();

hw_cache_invalidate('owner_pets_' . $ownerId);

echo json_encode(['success' => true, 'photoPath' => $photoPath]);
