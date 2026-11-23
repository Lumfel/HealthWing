<?php
// One-time setup: creates the first admin account. Locks itself once an admin exists.
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['status'=>'error', 'message'=>'Database connection failed.']);
    exit();
}

$check = $conn->query("SELECT COUNT(*) as c FROM admin");
$row = $check->fetch_assoc();
if ((int)$row['c'] >0) {
    echo json_encode(['status'=>'error', 'message'=>'An admin account already exists. Setup is locked.']);
    $conn->close();
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if ($username === ''|| $password === '') {
    echo json_encode(['status'=>'error', 'message'=>'Username and password are required.']);
    exit();
}
if (strlen($password) < 8) {
    echo json_encode(['status'=>'error', 'message'=>'Password must be at least 8 characters.']);
    exit();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin (Username, Email, Password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hash);

if ($stmt->execute()) {
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'message'=>'Could not create account: '. $stmt->error]);
}

$stmt->close();
$conn->close();
