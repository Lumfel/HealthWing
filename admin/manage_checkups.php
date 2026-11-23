<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'veterinarydb');
if ($conn->connect_error) {
    echo json_encode(['success'=> false, 'error'=>'DB connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'read';

try {
    if ($action === 'read') {
        $petId = (int)($_GET['petId'] ?? 0);
        if (!$petId) throw new Exception("Missing pet id");
        $stmt = $conn->prepare(
            "SELECT c.Checkup_ID, c.Checkup_Date, c.Notes, c.Veterinarian_ID,
                    v.First_Name AS VetFirstName, v.Last_Name AS VetLastName
             FROM pet_checkup c
             LEFT JOIN veterinarian v ON c.Veterinarian_ID = v.Veterinarian_ID
             WHERE c.Pet_ID = ?
             ORDER BY c.Checkup_Date DESC"
        );
        $stmt->bind_param("i", $petId);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode(['success'=> true, 'checkups'=>$result->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $petId = (int)($data['petId'] ?? 0);
        $date = $data['date'] ?? '';
        $notes = $data['notes'] ?? '';
        $vetId = !empty($data['vetId']) ? (int)$data['vetId'] : null;
        if (!$petId || $date === '') throw new Exception("Pet and date are required");

        $stmt = $conn->prepare("INSERT INTO pet_checkup (Pet_ID, Veterinarian_ID, Checkup_Date, Notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $petId, $vetId, $date, $notes);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        echo json_encode(['success'=> true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['checkupId'] ?? 0);
        if (!$id) throw new Exception("Missing checkup id");
        $stmt = $conn->prepare("DELETE FROM pet_checkup WHERE Checkup_ID=?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        echo json_encode(['success'=> true]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}

$conn->close();
