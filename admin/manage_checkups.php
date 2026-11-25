<?php
$ADMIN_GUARD_IS_API = true;
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'read';
if (in_array($action, ['create', 'delete'], true)) {
    hw_require_csrf();
}

try {
    if ($action === 'read') {
        $petId = (int)($_GET['petId'] ?? 0);
        if (!$petId) {
            throw new Exception("Missing pet id");
        }
        $stmt = $conn->prepare(
            "SELECT c.Checkup_ID, c.Checkup_Date, c.Notes, c.Diagnosis, c.Prescription, c.Veterinarian_ID,
                    v.First_Name AS VetFirstName, v.Last_Name AS VetLastName
             FROM pet_checkup c
             LEFT JOIN veterinarian v ON c.Veterinarian_ID = v.Veterinarian_ID
             WHERE c.Pet_ID = ?
             ORDER BY c.Checkup_Date DESC"
        );
        $stmt->bind_param("i", $petId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$r) {
            foreach (['Notes', 'Diagnosis', 'Prescription'] as $f) {
                if (!empty($r[$f])) {
                    $r[$f] = hw_decrypt_gcm($r[$f]) ?? $r[$f];
                }
            }
        }
        unset($r);
        echo json_encode(['success' => true, 'checkups' => $rows]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $petId = (int)($data['petId'] ?? 0);
        $date = trim((string)($data['date'] ?? ''));
        $notes = hw_encrypt_gcm(trim((string)($data['notes'] ?? '')));
        $vetId = !empty($data['vetId']) ? (int)$data['vetId'] : (int)($_SESSION['vet_id'] ?? 0);
        if (!$petId || $date === '' || $vetId <= 0) {
            throw new Exception("Pet, date, and attending veterinarian are required");
        }

        $stmt = $conn->prepare("INSERT INTO pet_checkup (Pet_ID, Veterinarian_ID, Checkup_Date, Notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $petId, $vetId, $date, $notes);
        if (!$stmt->execute()) {
            throw new Exception("Could not record checkup.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['checkupId'] ?? 0);
        if (!$id) {
            throw new Exception("Missing checkup id");
        }
        $stmt = $conn->prepare("DELETE FROM pet_checkup WHERE Checkup_ID=?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Could not delete checkup.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
