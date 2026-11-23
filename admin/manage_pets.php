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
        $sql = "SELECT p.Pet_ID, p.Owner_ID, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName,
                       p.Pet_Name, p.Pet_Type, p.Age, p.Species, p.Description,
                       p.Past_Conditions, p.Current_Ailment, p.Current_Medications,
                       (SELECT COUNT(*) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS CheckupCount,
                       (SELECT MAX(c.Checkup_Date) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS LastCheckup
                FROM pet p
                LEFT JOIN owner o ON p.Owner_ID = o.Owner_ID
                ORDER BY p.Pet_ID DESC";
        $result = $conn->query($sql);
        if (!$result) throw new Exception($conn->error);
        $pets = $result->fetch_all(MYSQLI_ASSOC);

        // Summary stats: total pets, and how many distinct owners actually have a pet on file
        $totalPets = count($pets);
        $ownerCountResult = $conn->query("SELECT COUNT(DISTINCT Owner_ID) AS c FROM pet WHERE Owner_ID IS NOT NULL");
        $ownersWithPets = $ownerCountResult ? (int)$ownerCountResult->fetch_assoc()['c'] : 0;

        echo json_encode([
            'success'=> true,
            'pets'=>$pets,
            'total_pets'=>$totalPets,
            'owners_with_pets'=>$ownersWithPets
        ]);
    } elseif ($action === 'create') {
        $data = json_decode(file_get_contents("php://input"), true);
        $ownerId = (int)($data['ownerId'] ?? 0);
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'Unknown';
        $age = $data['age'] !== ''&& isset($data['age']) ? (int)$data['age'] : null;
        $species = $data['species'] ?? null;
        $description = $data['description'] ?? null;
        $pastConditions = $data['pastConditions'] ?? null;
        $currentAilment = $data['currentAilment'] ?? null;
        $currentMedications = $data['currentMedications'] ?? null;
        if (!$ownerId || $name === '') throw new Exception("Owner and pet name are required");

        $stmt = $conn->prepare("INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Age, Species, Description, Past_Conditions, Current_Ailment, Current_Medications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississsss", $ownerId, $name, $type, $age, $species, $description, $pastConditions, $currentAilment, $currentMedications);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        echo json_encode(['success'=> true]);
    } elseif ($action === 'update') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['petId'] ?? 0);
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'Unknown';
        $age = $data['age'] !== ''&& isset($data['age']) ? (int)$data['age'] : null;
        $species = $data['species'] ?? null;
        $description = $data['description'] ?? null;
        $pastConditions = $data['pastConditions'] ?? null;
        $currentAilment = $data['currentAilment'] ?? null;
        $currentMedications = $data['currentMedications'] ?? null;
        if (!$id) throw new Exception("Missing pet id");

        $stmt = $conn->prepare("UPDATE pet SET Pet_Name=?, Pet_Type=?, Age=?, Species=?, Description=?, Past_Conditions=?, Current_Ailment=?, Current_Medications=? WHERE Pet_ID=?");
        $stmt->bind_param("ssisssssi", $name, $type, $age, $species, $description, $pastConditions, $currentAilment, $currentMedications, $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        echo json_encode(['success'=> true]);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['petId'] ?? 0);
        if (!$id) throw new Exception("Missing pet id");
        $stmt = $conn->prepare("DELETE FROM pet WHERE Pet_ID=?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        echo json_encode(['success'=> true]);
    } elseif ($action === 'owners') {
        // Small helper list for the "Owner"dropdown when adding a pet
        $result = $conn->query("SELECT Owner_ID, First_Name, Last_Name FROM owner ORDER BY First_Name");
        echo json_encode(['success'=> true, 'owners'=>$result ? $result->fetch_all(MYSQLI_ASSOC) : []]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}

$conn->close();
