<?php
/**
 * HealthWing / VetSystem — AES-256-GCM Cached Owner Pets Endpoint (get_my_pets.php)
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

hw_require_role(['owner'], true);

try {
    $conn = hw_get_db();
    $ownerId = !empty($_SESSION['owner_id']) ? (int)$_SESSION['owner_id'] : null;

    if (!$ownerId) {
        $stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $_SESSION['username']);
        $stmt->execute();
        if ($ownerRow = $stmt->get_result()->fetch_assoc()) {
            $ownerId = (int)$ownerRow['Owner_ID'];
            $_SESSION['owner_id'] = $ownerId;
        }
        $stmt->close();
    }

    if (!$ownerId) {
        echo json_encode(['success'=> false, 'message'=>'Owner not found.']);
        $conn->close();
        exit();
    }

    // Check AES-256-GCM encrypted cache first (unless ?fresh=1 is passed)
    $cacheKey = 'owner_pets_'. $ownerId;
    if (empty($_GET['fresh'])) {
        $cachedPets = hw_cache_get($cacheKey, 60);
        if (is_array($cachedPets)) {
            $conn->close();
            echo json_encode([
                'success'=> true,
                'cache_hit'=> true,
                'cipher'=>'AES-256-GCM',
                'pets'=>$cachedPets,
            ]);
            exit();
        }
    }

    $stmt = $conn->prepare(
        'SELECT Pet_ID, Pet_Name, Pet_Type, Age, Species, Description, Past_Conditions,
                Current_Ailment, Current_Medications, Photo_Path
         FROM pet WHERE Owner_ID = ? ORDER BY Pet_ID DESC'
    );
    $stmt->bind_param('i', $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // Decrypt any AES-256-GCM encrypted clinical fields transparently
    foreach ($pets as &$p) {
        foreach (['Description', 'Past_Conditions', 'Current_Ailment', 'Current_Medications'] as $field) {
            if (!empty($p[$field])) {
                $p[$field] = hw_decrypt_gcm($p[$field]) ?? $p[$field];
            }
        }
    }
    unset($p);

    hw_cache_set($cacheKey, $pets);

    echo json_encode([
        'success'=> true,
        'cache_hit'=> false,
        'cipher'=>'AES-256-GCM',
        'pets'=>$pets,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success'=> false, 'message'=>'Database connection failed.']);
}
