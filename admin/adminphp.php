<?php
/**
 * HealthWing / VetSystem — Admin User Management API (admin/adminphp.php)
 */
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$action = $_GET['action'] ?? '';
if (in_array($action, ['create', 'update', 'delete', 'approve_vet', 'reject_vet'], true)) {
    hw_require_csrf();
}

try {
    if ($action === 'read') {
        $result = $conn->query(
            "SELECT Owner_ID AS id, CONCAT(First_Name, ' ', COALESCE(Last_Name, '')) AS name,
                    First_Name, Last_Name, username, email, Contact AS contact,
                    '' AS specialties, 'Owner' AS role, 'Approved' AS status
             FROM owner
             UNION ALL
             SELECT Veterinarian_ID AS id, CONCAT(First_Name, ' ', COALESCE(Last_Name, '')) AS name,
                    First_Name, Last_Name, username, email, Contact AS contact,
                    COALESCE(Specialties, 'General') AS specialties, 'Veterinarian' AS role, COALESCE(Status, 'Approved') AS status
             FROM veterinarian"
        );
        if (!$result) {
            throw new Exception("Unable to load user directory.");
        }
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($action === 'create') {
        $firstName = trim((string)($_POST['first_name'] ?? ($_POST['name'] ?? '')));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        if ($lastName === '' && strpos($firstName, ' ') !== false) {
            $parts = explode(' ', $firstName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1];
        }
        $email = trim((string)($_POST['email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        if ($username === '' && $email !== '') {
            $username = explode('@', $email)[0];
        }
        $rawPass = (string)($_POST['password'] ?? 'HealthWing123!');
        $passHash = password_hash($rawPass, PASSWORD_DEFAULT);
        $contact = trim((string)($_POST['contact'] ?? ''));
        $specialties = trim((string)($_POST['specialties'] ?? 'General'));
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
            throw new Exception("Could not create account.");
        }
        $stmt->close();
        echo json_encode(["status" => "success"]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $firstName = $name;
        $lastName = '';
        if (strpos($name, ' ') !== false) {
            [$firstName, $lastName] = explode(' ', $name, 2);
        }
        $email = trim((string)($_POST['email'] ?? ''));
        $specialties = trim((string)($_POST['specialties'] ?? ''));
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
            throw new Exception("Could not update account.");
        }
        $stmt->close();
        echo json_encode(["status" => "success"]);
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
            throw new Exception("Could not remove account.");
        }
        $stmt->close();
        echo json_encode(["status" => "success"]);
    } elseif ($action === 'approve_vet' || $action === 'reject_vet') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $action === 'approve_vet' ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE veterinarian SET Status = ? WHERE Veterinarian_ID = ?");
        $stmt->bind_param("si", $newStatus, $id);
        if (!$stmt->execute()) {
            throw new Exception("Could not update veterinarian status.");
        }
        $stmt->close();
        echo json_encode(["status" => "success"]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
