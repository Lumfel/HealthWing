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
if (in_array($action, ['create', 'update', 'delete'], true)) {
    hw_require_csrf();
}

try {
    if ($action === 'read') {
        $sql = "SELECT p.Pet_ID, p.Owner_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName,
                       p.Pet_Name, p.Pet_Type, p.Age, p.Species, p.Description,
                       p.Past_Conditions, p.Current_Ailment, p.Current_Medications,
                       (SELECT COUNT(*) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS CheckupCount,
                       (SELECT MAX(c.Checkup_Date) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS LastCheckup
                FROM pet p
                LEFT JOIN owner o ON p.Owner_ID = o.Owner_ID
                ORDER BY p.Pet_ID DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Unable to load pet records.");
        }
        $pets = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($pets as &$p) {
            foreach (['Description', 'Past_Conditions', 'Current_Ailment', 'Current_Medications'] as $field) {
                if (!empty($p[$field])) {
                    $p[$field] = hw_decrypt_gcm($p[$field]) ?? $p[$field];
                }
            }
        }
        unset($p);

        $totalPets = count($pets);
        $ownerCountResult = $conn->query("SELECT COUNT(DISTINCT Owner_ID) AS c FROM pet WHERE Owner_ID IS NOT NULL");
        $ownersWithPets = $ownerCountResult ? (int)$ownerCountResult->fetch_assoc()['c'] : 0;

        echo json_encode([
            'success' => true,
            'pets' => $pets,
            'total_pets' => $totalPets,
            'owners_with_pets' => $ownersWithPets,
        ]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $ownerId = (int)($data['ownerId'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $type = trim((string)($data['type'] ?? 'Unknown'));
        $age = isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null;
        $species = $data['species'] ?? null;
        $description = !empty($data['description']) ? hw_encrypt_gcm((string)$data['description']) : null;
        $pastConditions = !empty($data['pastConditions']) ? hw_encrypt_gcm((string)$data['pastConditions']) : null;
        $currentAilment = !empty($data['currentAilment']) ? hw_encrypt_gcm((string)$data['currentAilment']) : null;
        $currentMedications = !empty($data['currentMedications']) ? hw_encrypt_gcm((string)$data['currentMedications']) : null;
        if (!$ownerId || $name === '') {
            throw new Exception("Owner and pet name are required");
        }

        $stmt = $conn->prepare("INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Age, Species, Description, Past_Conditions, Current_Ailment, Current_Medications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississsss", $ownerId, $name, $type, $age, $species, $description, $pastConditions, $currentAilment, $currentMedications);
        if (!$stmt->execute()) {
            throw new Exception("Could not save pet record.");
        }
        $stmt->close();
        hw_cache_invalidate('owner_pets_' . $ownerId);
        echo json_encode(['success' => true]);
    } elseif ($action === 'update') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['petId'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $type = trim((string)($data['type'] ?? 'Unknown'));
        $age = isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null;
        $species = $data['species'] ?? null;
        $description = !empty($data['description']) ? hw_encrypt_gcm((string)$data['description']) : null;
        $pastConditions = !empty($data['pastConditions']) ? hw_encrypt_gcm((string)$data['pastConditions']) : null;
        $currentAilment = !empty($data['currentAilment']) ? hw_encrypt_gcm((string)$data['currentAilment']) : null;
        $currentMedications = !empty($data['currentMedications']) ? hw_encrypt_gcm((string)$data['currentMedications']) : null;
        if (!$id) {
            throw new Exception("Missing pet id");
        }

        $stmt = $conn->prepare("UPDATE pet SET Pet_Name=?, Pet_Type=?, Age=?, Species=?, Description=?, Past_Conditions=?, Current_Ailment=?, Current_Medications=? WHERE Pet_ID=?");
        $stmt->bind_param("ssisssssi", $name, $type, $age, $species, $description, $pastConditions, $currentAilment, $currentMedications, $id);
        if (!$stmt->execute()) {
            throw new Exception("Could not update pet record.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['petId'] ?? 0);
        if (!$id) {
            throw new Exception("Missing pet id");
        }
        $stmt = $conn->prepare("DELETE FROM pet WHERE Pet_ID=?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Could not delete pet record.");
        }
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'owners') {
        $result = $conn->query("SELECT Owner_ID, First_Name, Last_Name FROM owner ORDER BY First_Name");
        echo json_encode(['success' => true, 'owners' => $result ? $result->fetch_all(MYSQLI_ASSOC) : []]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
