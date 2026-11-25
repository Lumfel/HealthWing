<?php
/**
 * HealthWing / VetSystem — Receipt Blockchain & Encrypted Archive API (api/receipt_api.php)
 */

require_once __DIR__ . '/../includes/receipt_blockchain.php';
hw_start_secure_session();
header('Content-Type: application/json');

hw_require_role(['owner', 'vet_staff', 'head_vet'], true);

try {
    $conn = hw_get_db();
    hw_receipt_ensure_table($conn);

    $viewerRole = hw_session_role();
    $viewerOwnerId = null;
    $viewerVetId = (int)($_SESSION['vet_id'] ?? 0);
    $issuerLabel = (string)($_SESSION['username'] ?? 'Clinic');

    if ($viewerRole === 'owner') {
        $oStmt = $conn->prepare("SELECT Owner_ID FROM owner WHERE username = ? AND Deleted_At IS NULL LIMIT 1");
        $oStmt->bind_param('s', $_SESSION['username']);
        $oStmt->execute();
        if ($oRow = $oStmt->get_result()->fetch_assoc()) {
            $viewerOwnerId = (int)$oRow['Owner_ID'];
        }
        $oStmt->close();
        if ($viewerOwnerId === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Owner account not found.']);
            $conn->close();
            exit;
        }
    } else {
        $vStmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name FROM veterinarian WHERE username = ? AND Deleted_At IS NULL LIMIT 1");
        $vStmt->bind_param('s', $_SESSION['username']);
        $vStmt->execute();
        if ($vRow = $vStmt->get_result()->fetch_assoc()) {
            $viewerVetId = (int)$vRow['Veterinarian_ID'];
            $issuerLabel = 'Dr. ' . trim($vRow['First_Name'] . ' ' . $vRow['Last_Name']);
        }
        $vStmt->close();
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

    if ($action === 'list') {
        $purged = hw_receipt_purge_expired($conn);
        if ($viewerRole === 'owner') {
            $stmt = $conn->prepare(
                "SELECT Block_ID, Block_Index, Receipt_Code, Owner_ID, Reference_Type, Reference_ID,
                        Service_Summary, Amount, Currency, Merkle_Root, Prev_Hash, Block_Hash,
                        Archive_Filename, Issued_By, Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At
                 FROM receipt_blocks
                 WHERE Owner_ID = ? AND Status = 'ACTIVE' AND Expires_At > NOW()
                 ORDER BY Block_Index DESC
                 LIMIT 100"
            );
            $stmt->bind_param('i', $viewerOwnerId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $res = $conn->query(
                "SELECT Block_ID, Block_Index, Receipt_Code, Owner_ID, Reference_Type, Reference_ID,
                        Service_Summary, Amount, Currency, Merkle_Root, Prev_Hash, Block_Hash,
                        Archive_Filename, Issued_By, Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At
                 FROM receipt_blocks
                 ORDER BY Block_Index DESC
                 LIMIT 200"
            );
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        echo json_encode([
            'success' => true,
            'role' => $viewerRole,
            'purged_expired' => $purged,
            'receipts' => $rows,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'view') {
        $blockId = (int)($_GET['id'] ?? 0);
        if (!$blockId) {
            echo json_encode(['success' => false, 'message' => 'Missing receipt id.']);
            $conn->close();
            exit;
        }
        $record = hw_receipt_read_for_viewer($conn, $blockId, $viewerRole, $viewerOwnerId);
        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Receipt not found or expired.']);
            $conn->close();
            exit;
        }
        echo json_encode(['success' => true, 'data' => $record]);
        $conn->close();
        exit;
    }

    if ($action === 'verify') {
        $result = hw_receipt_verify_chain($conn);
        echo json_encode(['success' => true, 'verification' => $result]);
        $conn->close();
        exit;
    }

    if ($action === 'issue') {
        hw_require_role(['vet_staff', 'head_vet'], true);
        hw_require_csrf();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $ownerId = !empty($input['owner_id']) ? (int)$input['owner_id'] : null;
        $ownerName = trim((string)($input['owner_name'] ?? ''));

        if ($ownerId && $ownerName === '') {
            $oQ = $conn->prepare("SELECT First_Name, Last_Name FROM owner WHERE Owner_ID = ? LIMIT 1");
            $oQ->bind_param('i', $ownerId);
            $oQ->execute();
            if ($oRow = $oQ->get_result()->fetch_assoc()) {
                $ownerName = trim($oRow['First_Name'] . ' ' . $oRow['Last_Name']);
            }
            $oQ->close();
        }

        $attendingVetId = !empty($input['attending_vet_id']) ? (int)$input['attending_vet_id'] : $viewerVetId;

        $issued = hw_receipt_issue($conn, [
            'reference_type' => $input['reference_type'] ?? 'manual',
            'reference_id' => !empty($input['reference_id']) ? (int)$input['reference_id'] : null,
            'owner_id' => $ownerId,
            'owner_name' => $ownerName ?: 'Clinic Client',
            'pet_name' => trim((string)($input['pet_name'] ?? 'Companion Pet')),
            'service_summary' => trim((string)($input['service_summary'] ?? 'Veterinary Service')),
            'amount' => (float)($input['amount'] ?? 0),
            'notes' => trim((string)($input['notes'] ?? '')),
            'attending_vet_id' => $attendingVetId,
            'issued_by' => $issuerLabel,
        ]);

        echo json_encode(['success' => true, 'result' => $issued]);
        $conn->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Receipt service error.']);
}
