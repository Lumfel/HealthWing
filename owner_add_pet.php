<?php
/**
 * HealthWing / VetSystem — Owner Add Pet Endpoint with AES-256-GCM Encryption & Cache Invalidation (owner_add_pet.php)
 */
require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in as a pet owner.']);
    exit;
}

hw_require_csrf();

try {
    $conn = hw_get_db();
    $stmt = $conn->prepare("SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['username']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Owner account not found.']);
        $conn->close();
        exit;
    }
    $ownerId = (int)$row['Owner_ID'];

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $petName = trim((string)($input['pet_name'] ?? ''));
    $species = trim((string)($input['species'] ?? 'Dog'));
    $breed = trim((string)($input['pet_type'] ?? $species));
    $age = isset($input['age']) && $input['age'] !== '' ? (int)$input['age'] : null;
    $description = hw_encrypt_gcm(trim((string)($input['description'] ?? '')));
    $pastConditions = hw_encrypt_gcm(trim((string)($input['past_conditions'] ?? '')));

    if ($petName === '') {
        echo json_encode(['success' => false, 'message' => 'Pet name is required.']);
        $conn->close();
        exit;
    }

    $ins = $conn->prepare(
        "INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Species, Age, Description, Past_Conditions)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param('isssiss', $ownerId, $petName, $breed, $species, $age, $description, $pastConditions);
    $ok = $ins->execute();
    $newPetId = (int)$ins->insert_id;
    $ins->close();
    $conn->close();

    hw_cache_invalidate('owner_pets_' . $ownerId);

    echo json_encode(['success' => $ok, 'pet_id' => $newPetId, 'cipher' => 'AES-256-GCM']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Could not add pet.']);
}
