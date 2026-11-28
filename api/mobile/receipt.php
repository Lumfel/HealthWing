<?php
/**
 * HealthWing Mobile API - Receipt Verification
 * GET ?code=...
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/receipt_blockchain.php';

hw_mobile_require_method('GET');
$identity = hw_mobile_require_role(['owner', 'vet_staff', 'head_vet']);

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    hw_mobile_json_response(400, [
        'success' => false,
        'error' => 'missing_code',
        'message' => 'Receipt code query parameter is required.',
    ]);
}

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
    hw_receipt_ensure_table($conn);
    hw_receipt_purge_expired($conn);

    $stmt = $conn->prepare(
        'SELECT Block_ID, Block_Index, Receipt_Code, Owner_ID, Reference_Type, Reference_ID, Service_Summary, Amount, Currency,
                Merkle_Root, Prev_Hash, Block_Hash, Payload_Ciphertext, Archive_Filename, Issued_By,
                Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At
         FROM receipt_blocks
         WHERE Receipt_Code = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        hw_mobile_json_response(404, [
            'success' => false,
            'error' => 'receipt_not_found',
            'message' => 'Receipt not found.',
        ]);
    }

    $role = (string)$identity['role'];
    $ownerId = (int)$identity['user_id'];
    if ($role === 'owner') {
        $receiptOwnerId = $row['Owner_ID'] !== null ? (int)$row['Owner_ID'] : 0;
        $isActive = (string)$row['Status'] === 'ACTIVE' && strtotime((string)$row['Expires_At']) > time();
        if ($receiptOwnerId !== $ownerId || !$isActive) {
            hw_mobile_json_response(403, [
                'success' => false,
                'error' => 'forbidden',
                'message' => 'You do not have access to this receipt.',
            ]);
        }
    }

    $receiptCode = (string)$row['Receipt_Code'];
    $cipher = null;
    if ((string)$row['Status'] === 'ACTIVE' && !empty($row['Archive_Filename'])) {
        $archivePath = HW_RECEIPT_ARCHIVE_DIR . DIRECTORY_SEPARATOR . basename((string)$row['Archive_Filename']);
        if (is_file($archivePath)) {
            $cipher = (string)@file_get_contents($archivePath);
        }
    }
    if ($cipher === null || $cipher === '') {
        $cipher = (string)$row['Payload_Ciphertext'];
    }

    $decrypted = hw_decrypt_gcm($cipher, 'receipt:' . $receiptCode);
    $payload = $decrypted ? json_decode($decrypted, true) : null;
    if (!is_array($payload)) {
        hw_mobile_json_response(500, [
            'success' => false,
            'error' => 'receipt_decrypt_failed',
            'message' => 'Receipt payload could not be decrypted.',
        ]);
    }

    $payload['owner_id'] = $row['Owner_ID'] !== null ? (int)$row['Owner_ID'] : null;
    $payload['pet_id'] = array_key_exists('pet_id', $payload) && $payload['pet_id'] !== null
        ? (int)$payload['pet_id']
        : null;
    $payload['currency'] = (string)$row['Currency'];
    $payload['status'] = (string)$row['Status'];
    $payload['created_at'] = (string)$row['Created_At'];
    $payload['expires_at'] = (string)$row['Expires_At'];

    $recomputedMerkle = hw_receipt_compute_merkle_root(hw_receipt_build_leaves($payload));
    $recomputedHash = hash('sha256', implode('|', [
        (int)$row['Block_Index'],
        $receiptCode,
        (string)$row['Merkle_Root'],
        (string)$row['Prev_Hash'],
        (string)$row['Created_At'],
    ]));
    $verified = hash_equals((string)$row['Merkle_Root'], $recomputedMerkle)
        && hash_equals((string)$row['Block_Hash'], $recomputedHash);

    hw_log_audit(
        $conn,
        (int)$identity['user_id'],
        (string)$identity['role'],
        'mobile_receipt_view',
        'receipt_block',
        (string)(int)$row['Block_ID'],
        json_encode([
            'receipt_code' => $receiptCode,
            'owner_id' => $row['Owner_ID'] !== null ? (int)$row['Owner_ID'] : null,
            'verified' => $verified,
        ], JSON_UNESCAPED_SLASHES)
    );

    hw_mobile_json_response(200, [
        'success' => true,
        'receipt' => [
            'block_id' => (int)$row['Block_ID'],
            'block_index' => (int)$row['Block_Index'],
            'receipt_code' => $receiptCode,
            'owner_id' => $row['Owner_ID'] !== null ? (int)$row['Owner_ID'] : null,
            'pet_id' => array_key_exists('pet_id', $payload) && $payload['pet_id'] !== null
                ? (int)$payload['pet_id']
                : null,
            'reference_type' => (string)$row['Reference_Type'],
            'reference_id' => $row['Reference_ID'] !== null ? (int)$row['Reference_ID'] : null,
            'service_summary' => (string)$row['Service_Summary'],
            'amount' => (float)$row['Amount'],
            'currency' => (string)$row['Currency'],
            'status' => (string)$row['Status'],
            'issued_by' => (string)$row['Issued_By'],
            'created_at' => (string)$row['Created_At'],
            'expires_at' => (string)$row['Expires_At'],
            'chain' => [
                'block_hash' => (string)$row['Block_Hash'],
                'prev_hash' => (string)$row['Prev_Hash'],
                'merkle_root' => (string)$row['Merkle_Root'],
                'verified' => $verified,
            ],
            'attending_vet' => [
                'veterinarian_id' => (int)$row['Attending_Vet_ID'],
                'name' => (string)$row['Attending_Vet_Name'],
                'prc_license_no' => (string)$row['Attending_Clinician_PRC'],
            ],
            'payload' => $payload,
        ],
    ]);
} finally {
    $conn->close();
}
