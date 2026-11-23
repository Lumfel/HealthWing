<?php
/**
 * HealthWing / VetSystem — Veterinarian Clinical Workstation API with AES-256-GCM Field Encryption (api/vet_api.php)
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';

if (!$isVet && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success'=> false, 'error'=>'Veterinarian or Admin authentication required.']);
    exit;
}

try {
    $conn = hw_get_db();
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'clinical_overview');

    $currentVetId = null;
    $currentVetName = 'Veterinarian';
    if ($isVet) {
        $vStmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name, Specialties FROM veterinarian WHERE username = ? LIMIT 1");
        $vStmt->bind_param('s', $_SESSION['username']);
        $vStmt->execute();
        if ($vRow = $vStmt->get_result()->fetch_assoc()) {
            $currentVetId = (int)$vRow['Veterinarian_ID'];
            $currentVetName = 'Dr. '. trim($vRow['First_Name'] . ''. $vRow['Last_Name']);
        }
        $vStmt->close();
    }

    if ($action === 'clinical_overview') {
        $petsSql = "SELECT p.*, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, o.Contact AS OwnerPhone,
                           (SELECT COUNT(*) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS CheckupCount,
                           (SELECT MAX(c.Checkup_Date) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS LastCheckup
                    FROM pet p
                    LEFT JOIN owner o ON p.Owner_ID = o.Owner_ID
                    ORDER BY p.Pet_ID DESC";
        $petsRes = $conn->query($petsSql);
        $pets = $petsRes ? $petsRes->fetch_all(MYSQLI_ASSOC) : [];

        // Transparently decrypt AES-256-GCM clinical fields
        foreach ($pets as &$p) {
            foreach (['Description', 'Past_Conditions', 'Current_Ailment', 'Current_Medications'] as $field) {
                if (!empty($p[$field])) {
                    $p[$field] = hw_decrypt_gcm($p[$field]) ?? $p[$field];
                }
            }
        }
        unset($p);

        $roomsRes = $conn->query("SELECT * FROM room ORDER BY Room_ID ASC");
        $rooms = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];

        $checkupsRes = $conn->query(
            "SELECT c.*, p.Pet_Name, v.First_Name AS VetFirst, v.Last_Name AS VetLast
             FROM pet_checkup c
             JOIN pet p ON c.Pet_ID = p.Pet_ID
             LEFT JOIN veterinarian v ON c.Veterinarian_ID = v.Veterinarian_ID
             ORDER BY c.Checkup_Date DESC, c.Checkup_ID DESC
             LIMIT 100"
        );
        $checkups = $checkupsRes ? $checkupsRes->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($checkups as &$c) {
            foreach (['Diagnosis', 'Prescription', 'Notes'] as $f) {
                if (!empty($c[$f])) {
                    $c[$f] = hw_decrypt_gcm($c[$f]) ?? $c[$f];
                }
            }
        }
        unset($c);

        echo json_encode([
            'success'=> true,
            'cipher'=>'AES-256-GCM',
            'current_vet_id'=>$currentVetId,
            'current_vet_name'=>$currentVetName,
            'pets'=>$pets,
            'rooms'=>$rooms,
            'recent_checkups'=>$checkups,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'add_checkup') {
        hw_require_csrf();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $petId = (int)($input['pet_id'] ?? 0);
        $date = trim($input['checkup_date'] ?? date('Y-m-d'));
        $diagnosis = trim($input['diagnosis'] ?? '');
        $prescription = trim($input['prescription'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $updatePetMed = !empty($input['update_pet_record']);

        if (!$petId) {
            echo json_encode(['success'=> false, 'error'=>'Pet selection is required.']);
            $conn->close();
            exit;
        }

        $combinedNotes = $notes;
        if ($diagnosis !== '') {
            $combinedNotes = "[Diagnosis: {$diagnosis}] ". $combinedNotes;
        }
        if ($prescription !== '') {
            $combinedNotes .= "[Rx: {$prescription}]";
        }

        $encDiag = hw_encrypt_gcm($diagnosis);
        $encRx = hw_encrypt_gcm($prescription);
        $encNotes = hw_encrypt_gcm($combinedNotes);

        $stmt = $conn->prepare(
            "INSERT INTO pet_checkup (Pet_ID, Veterinarian_ID, Checkup_Date, Diagnosis, Prescription, Notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iissss', $petId, $currentVetId, $date, $encDiag, $encRx, $encNotes);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $updatePetMed) {
            $uPet = $conn->prepare(
                "UPDATE pet
                 SET Current_Ailment = COALESCE(NULLIF(?, ''), Current_Ailment),
                     Current_Medications = COALESCE(NULLIF(?, ''), Current_Medications)
                 WHERE Pet_ID = ?"
            );
            $uPet->bind_param('ssi', $encDiag, $encRx, $petId);
            $uPet->execute();
            $uPet->close();
        }

        // Invalidate owner's pet cache
        $oLook = $conn->prepare("SELECT Owner_ID FROM pet WHERE Pet_ID = ? LIMIT 1");
        $oLook->bind_param('i', $petId);
        $oLook->execute();
        if ($oRow = $oLook->get_result()->fetch_assoc()) {
            hw_cache_invalidate('owner_pets_'. (int)$oRow['Owner_ID']);
        }
        $oLook->close();

        echo json_encode(['success'=>$ok, 'cipher'=>'AES-256-GCM']);
        $conn->close();
        exit;
    }

    if ($action === 'update_pet_medical') {
        hw_require_csrf();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $petId = (int)($input['pet_id'] ?? 0);
        $species = trim($input['species'] ?? '');
        $age = isset($input['age']) && $input['age'] !== ''? (int)$input['age'] : null;
        $ailment = hw_encrypt_gcm(trim($input['current_ailment'] ?? ''));
        $meds = hw_encrypt_gcm(trim($input['current_medications'] ?? ''));
        $past = hw_encrypt_gcm(trim($input['past_conditions'] ?? ''));

        if (!$petId) {
            echo json_encode(['success'=> false, 'error'=>'Missing pet_id']);
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare(
            "UPDATE pet
             SET Species = COALESCE(NULLIF(?, ''), Species),
                 Age = COALESCE(?, Age),
                 Current_Ailment = ?,
                 Current_Medications = ?,
                 Past_Conditions = ?
             WHERE Pet_ID = ?"
        );
        $stmt->bind_param('sisssi', $species, $age, $ailment, $meds, $past, $petId);
        $ok = $stmt->execute();
        $stmt->close();

        $oLook = $conn->prepare("SELECT Owner_ID FROM pet WHERE Pet_ID = ? LIMIT 1");
        $oLook->bind_param('i', $petId);
        $oLook->execute();
        if ($oRow = $oLook->get_result()->fetch_assoc()) {
            hw_cache_invalidate('owner_pets_'. (int)$oRow['Owner_ID']);
        }
        $oLook->close();

        echo json_encode(['success'=>$ok, 'cipher'=>'AES-256-GCM']);
        $conn->close();
        exit;
    }

    if ($action === 'update_room') {
        hw_require_csrf();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $roomId = (int)($input['room_id'] ?? 0);
        $status = trim($input['status'] ?? 'idle');
        if (!$roomId || !in_array($status, ['idle', 'ongoing', 'booked'], true)) {
            echo json_encode(['success'=> false, 'error'=>'Invalid room or status']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("UPDATE room SET Status = ? WHERE Room_ID = ?");
        $stmt->bind_param('si', $status, $roomId);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>$ok]);
        $conn->close();
        exit;
    }

    echo json_encode(['success'=> false, 'error'=>'Unknown action']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=> false, 'error'=>$e->getMessage()]);
}
