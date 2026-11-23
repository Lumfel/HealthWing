<?php
/**
 * HealthWing / VetSystem — Upgraded Admin User Management API (admin/adminphp.php)
 *
 * Fixes the bug where admin-created Owner and Veterinarian accounts lacked Last_Name,
 * username, password hash, and Specialties (preventing them from ever logging in).
 */
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    echo json_encode(["status"=>"error", "message"=>"Database connection failed"]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'read') {
        $result = $conn->query(
            "SELECT Owner_ID as id, CONCAT(First_Name, '', COALESCE(Last_Name, '')) as name,
                    First_Name, Last_Name, username, email, Contact as contact,
                    ''as specialties, 'Owner'as role, 'Approved'as status
             FROM owner
             UNION ALL
             SELECT Veterinarian_ID as id, CONCAT(First_Name, '', COALESCE(Last_Name, '')) as name,
                    First_Name, Last_Name, username, email, Contact as contact,
                    COALESCE(Specialties, 'General') as specialties, 'Veterinarian'as role, COALESCE(Status, 'Approved') as status
             FROM veterinarian"
        );
        if (!$result) {
            throw new Exception($conn->error);
        }
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? ($_POST['name'] ?? ''));
        $lastName = trim($_POST['last_name'] ?? '');
        if ($lastName === ''&& strpos($firstName, '') !== false) {
            $parts = explode('', $firstName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1];
        }
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        if ($username === ''&& $email !== '') {
            $username = explode('@', $email)[0];
        }
        $rawPass = $_POST['password'] ?? 'HealthWing123!';
        $passHash = password_hash($rawPass, PASSWORD_DEFAULT);
        $contact = trim($_POST['contact'] ?? '');
        $specialties = trim($_POST['specialties'] ?? 'General');
        $role = $_POST['role'] ?? '';

        if ($role === 'Owner') {
            $stmt = $conn->prepare("INSERT INTO owner (First_Name, Last_Name, Contact, username, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $firstName, $lastName, $contact, $username, $email, $passHash);
        } elseif ($role === 'Veterinarian') {
            $stmt = $conn->prepare("INSERT INTO veterinarian (First_Name, Last_Name, Contact, Specialties, username, email, password, Status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Approved')");
            $stmt->bind_param("sssssss", $firstName, $lastName, $contact, $specialties, $username, $email, $passHash);
        } else {
            throw new Exception("Invalid role");
        }
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        echo json_encode(["status"=>"success"]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $firstName = $name;
        $lastName = '';
        if (strpos($name, '') !== false) {
            [$firstName, $lastName] = explode('', $name, 2);
        }
        $email = trim($_POST['email'] ?? '');
        $specialties = trim($_POST['specialties'] ?? '');
        $role = $_POST['role'] ?? '';

        if ($role === 'Owner') {
            $stmt = $conn->prepare("UPDATE owner SET First_Name=?, Last_Name=?, email=? WHERE Owner_ID=?");
            $stmt->bind_param("sssi", $firstName, $lastName, $email, $id);
        } elseif ($role === 'Veterinarian') {
            $stmt = $conn->prepare("UPDATE veterinarian SET First_Name=?, Last_Name=?, email=?, Specialties=COALESCE(NULLIF(?, ''), Specialties) WHERE Veterinarian_ID=?");
            $stmt->bind_param("ssssi", $firstName, $lastName, $email, $specialties, $id);
        } else {
            throw new Exception("Invalid role");
        }
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        echo json_encode(["status"=>"success"]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if ($role === 'Owner') {
            $stmt = $conn->prepare("DELETE FROM owner WHERE Owner_ID=?");
        } elseif ($role === 'Veterinarian') {
            $stmt = $conn->prepare("DELETE FROM veterinarian WHERE Veterinarian_ID=?");
        } else {
            throw new Exception("Invalid role");
        }
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        echo json_encode(["status"=>"success"]);
    } elseif ($action === 'approve_vet'|| $action === 'reject_vet') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $action === 'approve_vet'? 'Approved': 'Rejected';
        $stmt = $conn->prepare("UPDATE veterinarian SET Status = ? WHERE Veterinarian_ID = ?");
        $stmt->bind_param("si", $newStatus, $id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        echo json_encode(["status"=>"success"]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(["status"=>"error", "message"=>$e->getMessage()]);
}

$conn->close();
