<?php
/**
 * HealthWing Mobile API - Veterinarian Clinical Workstation
 * GET  ?action=overview
 * POST ?action=save_clinical_note
 * POST ?action=update_room
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = strtolower(trim((string)($_GET['action'] ?? ($_POST['action'] ?? ''))));
if ($action === '') {
    hw_mobile_json_response(400, [
        'success' => false,
        'error' => 'missing_action',
        'message' => 'Action is required: overview, save_clinical_note, update_room.',
    ]);
}

if ($action === 'overview') {
    hw_mobile_require_method('GET');
} elseif (in_array($action, ['save_clinical_note', 'update_room'], true)) {
    hw_mobile_require_method('POST');
} else {
    hw_mobile_json_response(400, [
        'success' => false,
        'error' => 'invalid_action',
        'message' => 'Supported actions: overview, save_clinical_note, update_room.',
    ]);
}

$identity = hw_mobile_require_role(['vet_staff', 'head_vet']);

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    hw_mobile_json_response(500, [
        'success' => false,
        'error' => 'database_unavailable',
        'message' => 'Database connection failed.',
    ]);
}

try {
    $currentVetId = (int)$identity['user_id'];
    $currentVetRole = hw_normalize_role((string)$identity['role']);
    $currentVetName = 'Veterinarian';
    $currentVetPrc = (string)($identity['prc_license_no'] ?? '');

    $vStmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name, Specialties, Role_Level, PRC_License_No, Status FROM veterinarian WHERE Veterinarian_ID = ? AND Deleted_At IS NULL LIMIT 1");
    $vStmt->bind_param('i', $currentVetId);
    $vStmt->execute();
    $vRow = $vStmt->get_result()->fetch_assoc();
    $vStmt->close();

    if (!$vRow) {
        hw_mobile_json_response(404, [
            'success' => false,
            'error' => 'veterinarian_not_found',
            'message' => 'Veterinarian account not found.',
        ]);
    }

    $vetStatus = (string)($vRow['Status'] ?? 'Approved');
    if ($vetStatus !== 'Approved') {
        hw_mobile_json_response(403, [
            'success' => false,
            'error' => 'account_not_approved',
            'message' => 'Veterinarian account is not approved.',
        ]);
    }

    $currentVetName = 'Dr. ' . trim((string)$vRow['First_Name'] . ' ' . (string)$vRow['Last_Name']);
    $currentVetRole = hw_normalize_role((string)($vRow['Role_Level'] ?? $currentVetRole));
    $currentVetPrc = (string)($vRow['PRC_License_No'] ?? $currentVetPrc);

    if ($action === 'overview') {
        $petsRes = $conn->query(
            "SELECT p.*, o.First_Name AS OwnerFirstName, o.Last_Name AS OwnerLastName, o.Contact AS OwnerPhone,
                    (SELECT COUNT(*) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS CheckupCount,
                    (SELECT MAX(c.Checkup_Date) FROM pet_checkup c WHERE c.Pet_ID = p.Pet_ID) AS LastCheckup
             FROM pet p
             LEFT JOIN owner o ON p.Owner_ID = o.Owner_ID
             WHERE p.Deleted_At IS NULL
             ORDER BY p.Pet_ID DESC"
        );
        $pets = $petsRes ? $petsRes->fetch_all(MYSQLI_ASSOC) : [];

        foreach ($pets as &$p) {
            foreach (['Description', 'Past_Conditions', 'Current_Ailment', 'Current_Medications'] as $field) {
                if (!empty($p[$field])) {
                    $p[$field] = hw_decrypt_gcm((string)$p[$field]) ?? $p[$field];
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
                    $c[$f] = hw_decrypt_gcm((string)$c[$f]) ?? $c[$f];
                }
            }
        }
        unset($c);

        hw_mobile_json_response(200, [
            'success' => true,
            'action' => 'overview',
            'cipher' => 'AES-256-GCM',
            'current_vet' => [
                'veterinarian_id' => $currentVetId,
                'name' => $currentVetName,
                'role' => $currentVetRole,
                'prc_license_no' => $currentVetPrc,
            ],
            'pets' => $pets,
            'rooms' => $rooms,
            'recent_checkups' => $checkups,
        ]);
    }

    $input = hw_mobile_read_json();

    if ($action === 'save_clinical_note') {
        $petId = (int)($input['pet_id'] ?? 0);
        $date = trim((string)($input['checkup_date'] ?? date('Y-m-d')));
        $diagnosis = trim((string)($input['diagnosis'] ?? ''));
        $prescription = trim((string)($input['prescription'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));
        $updatePetMed = !empty($input['update_pet_record']);

        if ($petId <= 0 || $currentVetId <= 0) {
            hw_mobile_json_response(400, [
                'success' => false,
                'error' => 'invalid_input',
                'message' => 'Pet selection and verified attending veterinarian are required.',
            ]);
        }

        $petStmt = $conn->prepare('SELECT Pet_ID FROM pet WHERE Pet_ID = ? AND Deleted_At IS NULL LIMIT 1');
        $petStmt->bind_param('i', $petId);
        $petStmt->execute();
        $petExists = $petStmt->get_result()->num_rows > 0;
        $petStmt->close();
        if (!$petExists) {
            hw_mobile_json_response(404, [
                'success' => false,
                'error' => 'pet_not_found',
                'message' => 'Pet not found.',
            ]);
        }

        $combinedNotes = $notes;
        if ($diagnosis !== '') {
            $combinedNotes = '[Diagnosis: ' . $diagnosis . '] ' . $combinedNotes;
        }
        if ($prescription !== '') {
            $combinedNotes .= '[Rx: ' . $prescription . ']';
        }

        $encDiag = hw_encrypt_gcm($diagnosis);
        $encRx = hw_encrypt_gcm($prescription);
        $encNotes = hw_encrypt_gcm($combinedNotes);

        $stmt = $conn->prepare(
            'INSERT INTO pet_checkup (Pet_ID, Veterinarian_ID, Checkup_Date, Diagnosis, Prescription, Notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iissss', $petId, $currentVetId, $date, $encDiag, $encRx, $encNotes);
        $ok = $stmt->execute();
        $checkupId = (int)$stmt->insert_id;
        $stmt->close();

        if (!$ok) {
            hw_mobile_json_response(500, [
                'success' => false,
                'error' => 'save_failed',
                'message' => 'Could not save clinical note.',
            ]);
        }

        if ($updatePetMed) {
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

        hw_log_audit(
            $conn,
            $currentVetId,
            $currentVetRole,
            'save_clinical_note',
            'pet',
            (string)$petId,
            json_encode([
                'clinician_id' => $currentVetId,
                'clinician_role' => $currentVetRole,
                'prc_license_no' => $currentVetPrc,
                'action' => 'save_clinical_note',
                'pet_id' => $petId,
                'checkup_id' => $checkupId,
                'updated_pet_chart' => $updatePetMed,
            ], JSON_UNESCAPED_SLASHES)
        );

        $oLook = $conn->prepare('SELECT Owner_ID FROM pet WHERE Pet_ID = ? LIMIT 1');
        $oLook->bind_param('i', $petId);
        $oLook->execute();
        if ($oRow = $oLook->get_result()->fetch_assoc()) {
            hw_cache_invalidate('owner_pets_' . (int)$oRow['Owner_ID']);
        }
        $oLook->close();

        hw_mobile_json_response(201, [
            'success' => true,
            'action' => 'save_clinical_note',
            'message' => 'Clinical note saved.',
            'checkup_id' => $checkupId,
            'pet_id' => $petId,
            'updated_pet_record' => $updatePetMed,
            'cipher' => 'AES-256-GCM',
        ]);
    }

    if ($action === 'update_room') {
        $roomId = (int)($input['room_id'] ?? 0);
        $status = trim((string)($input['status'] ?? 'idle'));
        if ($roomId <= 0 || !in_array($status, ['idle', 'ongoing', 'booked'], true)) {
            hw_mobile_json_response(400, [
                'success' => false,
                'error' => 'invalid_input',
                'message' => 'room_id and valid status (idle, ongoing, booked) are required.',
            ]);
        }

        // Exact boundary: vet_staff may only modify rooms assigned to them; head_vet may modify any room.
        if ($currentVetRole !== 'head_vet') {
            $chkRoom = $conn->prepare(
                "SELECT r.Room_ID
                 FROM room r
                 LEFT JOIN emergency_booking e ON r.Current_Emergency_ID = e.Booking_ID
                 LEFT JOIN schedule s ON r.Current_Schedule_ID = s.Schedule_ID
                 WHERE r.Room_ID = ?
                   AND (r.Veterinarian_ID = ? OR e.Assigned_Vet_ID = ? OR s.Veterinarian_ID = ?)
                 LIMIT 1"
            );
            $chkRoom->bind_param('iiii', $roomId, $currentVetId, $currentVetId, $currentVetId);
            $chkRoom->execute();
            $canManageRoom = $chkRoom->get_result()->num_rows > 0;
            $chkRoom->close();
            if (!$canManageRoom) {
                hw_mobile_json_response(403, [
                    'success' => false,
                    'error' => 'forbidden',
                    'message' => 'Forbidden: Only a Head Veterinarian or the veterinarian assigned to this room may change its status.',
                ]);
            }
        }

        $stmt = $conn->prepare('UPDATE room SET Status = ? WHERE Room_ID = ?');
        $stmt->bind_param('si', $status, $roomId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if (!$ok) {
            hw_mobile_json_response(500, [
                'success' => false,
                'error' => 'update_failed',
                'message' => 'Could not update room status.',
            ]);
        }

        hw_log_audit(
            $conn,
            $currentVetId,
            $currentVetRole,
            'update_room',
            'room',
            (string)$roomId,
            json_encode([
                'clinician_id' => $currentVetId,
                'clinician_role' => $currentVetRole,
                'prc_license_no' => $currentVetPrc,
                'action' => 'update_room',
                'room_id' => $roomId,
                'status' => $status,
            ], JSON_UNESCAPED_SLASHES)
        );

        hw_mobile_json_response(200, [
            'success' => true,
            'action' => 'update_room',
            'message' => 'Room status updated.',
            'room_id' => $roomId,
            'status' => $status,
            'updated' => $affected >= 0,
        ]);
    }
} finally {
    $conn->close();
}
