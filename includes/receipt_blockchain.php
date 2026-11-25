<?php
/**
 * HealthWing / VetSystem — Hash-Chained Receipt Blockchain & 1-Year AES-256-GCM Owner Archive
 * (includes/receipt_blockchain.php)
 */

require_once __DIR__ . '/db.php';

if (!defined('HW_RECEIPT_BLOCKCHAIN_LOADED')) {
    define('HW_RECEIPT_BLOCKCHAIN_LOADED', true);
    define('HW_RECEIPT_ARCHIVE_DIR', dirname(__DIR__) . '/archives/owner_receipts');
    define('HW_RECEIPT_RETENTION_DAYS', 365);

    /**
     * Ensures the canonical receipt_blocks ledger table exists (matches database/veterinarydb.sql).
     */
    function hw_receipt_ensure_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS `receipt_blocks` (
            `Block_ID` int(11) NOT NULL AUTO_INCREMENT,
            `Block_Index` int(11) NOT NULL,
            `Receipt_Code` varchar(64) NOT NULL,
            `Owner_ID` int(11) DEFAULT NULL,
            `Reference_Type` varchar(32) NOT NULL DEFAULT 'schedule',
            `Reference_ID` int(11) DEFAULT NULL,
            `Service_Summary` varchar(255) NOT NULL,
            `Amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `Currency` varchar(10) NOT NULL DEFAULT 'PHP',
            `Merkle_Root` char(64) NOT NULL,
            `Prev_Hash` char(64) NOT NULL,
            `Block_Hash` char(64) NOT NULL,
            `Payload_Ciphertext` mediumtext NOT NULL,
            `Archive_Filename` varchar(128) DEFAULT NULL,
            `Issued_By` varchar(100) NOT NULL,
            `Attending_Vet_ID` int(11) NOT NULL,
            `Attending_Vet_Name` varchar(150) NOT NULL,
            `Attending_Clinician_PRC` varchar(64) NOT NULL,
            `Status` enum('ACTIVE','PURGED_1YR') NOT NULL DEFAULT 'ACTIVE',
            `Created_At` datetime NOT NULL,
            `Expires_At` datetime NOT NULL,
            PRIMARY KEY (`Block_ID`),
            UNIQUE KEY `uniq_receipt_code` (`Receipt_Code`),
            UNIQUE KEY `uniq_block_index` (`Block_Index`),
            UNIQUE KEY `uq_receipt_ref` (`Reference_Type`, `Reference_ID`),
            KEY `idx_owner_id` (`Owner_ID`),
            KEY `idx_attending_vet` (`Attending_Vet_ID`),
            KEY `idx_expires_at` (`Expires_At`),
            KEY `idx_status` (`Status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /**
     * Computes a SHA-256 Merkle root over receipt leaf strings.
     */
    function hw_receipt_compute_merkle_root(array $leaves): string
    {
        if (empty($leaves)) {
            return hash('sha256', 'EMPTY_MERKLE_TREE');
        }
        $level = [];
        foreach ($leaves as $leaf) {
            $level[] = hash('sha256', (string)$leaf);
        }
        while (count($level) > 1) {
            if (count($level) % 2 !== 0) {
                $level[] = $level[count($level) - 1];
            }
            $next = [];
            for ($i = 0; $i < count($level); $i += 2) {
                $next[] = hash('sha256', $level[$i] . $level[$i + 1]);
            }
            $level = $next;
        }
        return $level[0];
    }

    /**
     * Converts a receipt payload into canonical leaf strings for Merkle tree hashing.
     */
    function hw_receipt_build_leaves(array $payload): array
    {
        $leaves = [
            'receipt_code:' . ($payload['receipt_code'] ?? ''),
            'owner_id:' . ($payload['owner_id'] ?? ''),
            'owner_name:' . ($payload['owner_name'] ?? ''),
            'pet_name:' . ($payload['pet_name'] ?? ''),
            'reference:' . ($payload['reference_type'] ?? '') . '#' . ($payload['reference_id'] ?? ''),
            'service_summary:' . ($payload['service_summary'] ?? ''),
            'amount:' . number_format((float)($payload['amount'] ?? 0), 2, '.', ''),
            'currency:' . ($payload['currency'] ?? 'PHP'),
            'issued_by:' . ($payload['issued_by'] ?? ''),
            'attending_vet_id:' . ($payload['attending_vet_id'] ?? ''),
            'attending_vet_name:' . ($payload['attending_vet_name'] ?? ''),
            'attending_clinician_prc:' . ($payload['attending_clinician_prc'] ?? ''),
            'created_at:' . ($payload['created_at'] ?? ''),
            'expires_at:' . ($payload['expires_at'] ?? ''),
        ];
        if (!empty($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $idx => $item) {
                $desc = (string)($item['description'] ?? 'Service');
                $qty = (int)($item['qty'] ?? 1);
                $price = number_format((float)($item['price'] ?? 0), 2, '.', '');
                $leaves[] = "item_{$idx}:{$desc}|qty={$qty}|price={$price}";
            }
        }
        return $leaves;
    }

    /**
     * Purges encrypted owner copies in archives/owner_receipts/ after 365 days and marks Status = 'PURGED_1YR'.
     * Never deletes immutable receipt_blocks ledger rows and only marks PURGED_1YR after verifying unlink.
     */
    function hw_receipt_purge_expired(mysqli $conn): int
    {
        hw_receipt_ensure_table($conn);
        $purged = 0;
        $archiveDir = HW_RECEIPT_ARCHIVE_DIR;

        $res = $conn->query(
            "SELECT Block_ID, Archive_Filename, Status
             FROM receipt_blocks
             WHERE Expires_At <= NOW() AND Status = 'ACTIVE'"
        );
        if ($res) {
            $upd = $conn->prepare("UPDATE receipt_blocks SET Archive_Filename = NULL, Status = 'PURGED_1YR' WHERE Block_ID = ?");
            while ($row = $res->fetch_assoc()) {
                $file = basename((string)($row['Archive_Filename'] ?? ''));
                $unlinkedOrAbsent = true;
                if ($file !== '') {
                    $full = $archiveDir . DIRECTORY_SEPARATOR . $file;
                    if (is_file($full)) {
                        $unlinkedOrAbsent = @unlink($full);
                    }
                }
                if ($unlinkedOrAbsent && $upd) {
                    $bId = (int)$row['Block_ID'];
                    $upd->bind_param('i', $bId);
                    $upd->execute();
                    $purged++;
                }
            }
            if ($upd) {
                $upd->close();
            }
        }

        return $purged;
    }

    /**
     * Default service fee lookup for automatic appointment/emergency receipts.
     */
    function hw_receipt_default_fee(string $serviceSummary): float
    {
        $s = strtolower($serviceSummary);
        if (strpos($s, 'surgery') !== false) return 3500.00;
        if (strpos($s, 'emergency') !== false) return 1500.00;
        if (strpos($s, 'dental') !== false) return 1800.00;
        if (strpos($s, 'vaccin') !== false) return 850.00;
        if (strpos($s, 'parasite') !== false) return 750.00;
        if (strpos($s, 'groom') !== false) return 650.00;
        return 600.00;
    }

    /**
     * Resolves verified attending veterinarian accountability metadata from the veterinarian table.
     */
    function hw_receipt_resolve_attending_vet(mysqli $conn, array $input): array
    {
        $vetId = !empty($input['attending_vet_id']) ? (int)$input['attending_vet_id'] : (int)($_SESSION['vet_id'] ?? 0);
        if ($vetId <= 0) {
            throw new RuntimeException('An attending veterinarian (Attending_Vet_ID) is required to issue a clinical receipt.');
        }

        $stmt = $conn->prepare("SELECT Veterinarian_ID, First_Name, Last_Name, PRC_License_No FROM veterinarian WHERE Veterinarian_ID = ? AND Deleted_At IS NULL LIMIT 1");
        $stmt->bind_param('i', $vetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || trim((string)($row['PRC_License_No'] ?? '')) === '') {
            throw new RuntimeException('Attending veterinarian account or PRC license number could not be verified.');
        }

        return [
            'vet_id' => (int)$row['Veterinarian_ID'],
            'vet_name' => 'Dr. ' . trim($row['First_Name'] . ' ' . $row['Last_Name']),
            'prc_license_no' => trim((string)$row['PRC_License_No']),
        ];
    }

    /**
     * Issues a new hash-chained receipt block (starting at Genesis Block #0) and writes
     * a 365-day AES-256-GCM encrypted owner archive copy. Strictly serialized with `hw_receipt_ledger` mutex.
     */
    function hw_receipt_issue(mysqli $conn, array $input): array
    {
        hw_receipt_ensure_table($conn);

        $refType = in_array($input['reference_type'] ?? 'schedule', ['schedule', 'emergency', 'manual'], true)
            ? (string)$input['reference_type']
            : 'manual';
        $refId = ($refType !== 'manual' && !empty($input['reference_id'])) ? (int)$input['reference_id'] : null;

        $attending = hw_receipt_resolve_attending_vet($conn, $input);

        $ownerId = !empty($input['owner_id']) ? (int)$input['owner_id'] : null;
        $ownerName = trim((string)($input['owner_name'] ?? 'Clinic Client'));
        $petName = trim((string)($input['pet_name'] ?? 'Companion Pet'));
        $serviceSummary = trim((string)($input['service_summary'] ?? 'Veterinary Consultation'));
        $issuedBy = trim((string)($input['issued_by'] ?? $attending['vet_name']));
        $currency = 'PHP';

        $items = $input['items'] ?? null;
        if (!is_array($items) || empty($items)) {
            $fee = isset($input['amount']) && (float)$input['amount'] > 0
                ? (float)$input['amount']
                : hw_receipt_default_fee($serviceSummary);
            $items = [
                ['description' => $serviceSummary, 'qty' => 1, 'price' => $fee],
            ];
        }

        $totalAmount = 0.0;
        $cleanItems = [];
        foreach ($items as $it) {
            $qty = max(1, (int)($it['qty'] ?? 1));
            $price = round(max(0, (float)($it['price'] ?? 0)), 2);
            $desc = trim((string)($it['description'] ?? $serviceSummary));
            $cleanItems[] = ['description' => $desc, 'qty' => $qty, 'price' => $price];
            $totalAmount += $qty * $price;
        }
        $totalAmount = round($totalAmount, 2);

        $lockAcquired = false;
        $startedTx = false;
        $writtenArchivePath = null;

        try {
            $lockAcquired = hw_db_acquire_lock($conn, 'hw_receipt_ledger', 5);
            if (!$lockAcquired) {
                throw new RuntimeException('Receipt ledger is busy. Please retry.');
            }

            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start receipt ledger transaction.');
            }
            $startedTx = true;

            // Idempotent check before insert
            if ($refType !== 'manual' && $refId !== null) {
                $chk = $conn->prepare(
                    "SELECT Block_ID, Block_Index, Receipt_Code, Merkle_Root, Prev_Hash, Block_Hash, Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At
                     FROM receipt_blocks
                     WHERE Reference_Type = ? AND Reference_ID = ?
                     LIMIT 1 FOR UPDATE"
                );
                $chk->bind_param('si', $refType, $refId);
                $chk->execute();
                $existing = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($existing) {
                    $conn->rollback();
                    $startedTx = false;
                    return ['already_exists' => true, 'receipt' => $existing];
                }
            }

            // Genesis Block #0 when table is empty; strictly serialized by hw_receipt_ledger lock
            $lastRes = $conn->query("SELECT Block_Index, Block_Hash FROM receipt_blocks ORDER BY Block_Index DESC LIMIT 1 FOR UPDATE");
            $lastRow = $lastRes ? $lastRes->fetch_assoc() : null;
            $nextIndex = $lastRow ? ((int)$lastRow['Block_Index'] + 1) : 0;
            $prevHash = $lastRow ? (string)$lastRow['Block_Hash'] : str_repeat('0', 64);

            $nowTs = time();
            $createdAt = date('Y-m-d H:i:s', $nowTs);
            $expiresAt = date('Y-m-d H:i:s', $nowTs + (HW_RECEIPT_RETENTION_DAYS * 86400));
            $receiptCode = 'HW-RCPT-' . date('Ymd', $nowTs) . '-' . strtoupper(bin2hex(random_bytes(4)));

            $payload = [
                'receipt_code' => $receiptCode,
                'owner_id' => $ownerId,
                'owner_name' => $ownerName,
                'pet_name' => $petName,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'service_summary' => $serviceSummary,
                'items' => $cleanItems,
                'amount' => $totalAmount,
                'currency' => $currency,
                'notes' => trim((string)($input['notes'] ?? '')),
                'issued_by' => $issuedBy,
                'attending_vet_id' => $attending['vet_id'],
                'attending_vet_name' => $attending['vet_name'],
                'attending_clinician_prc' => $attending['prc_license_no'],
                'created_at' => $createdAt,
                'expires_at' => $expiresAt,
            ];

            $merkleRoot = hw_receipt_compute_merkle_root(hw_receipt_build_leaves($payload));
            $blockHash = hash('sha256', implode('|', [
                $nextIndex,
                $receiptCode,
                $merkleRoot,
                $prevHash,
                $createdAt,
            ]));

            $ciphertext = hw_encrypt_gcm((string)json_encode($payload), 'receipt:' . $receiptCode);
            $archiveFilename = $receiptCode . '.gcm';
            $status = 'ACTIVE';

            $stmt = $conn->prepare(
                "INSERT INTO receipt_blocks
                 (Block_Index, Receipt_Code, Owner_ID, Reference_Type, Reference_ID, Service_Summary, Amount, Currency, Merkle_Root, Prev_Hash, Block_Hash, Payload_Ciphertext, Archive_Filename, Issued_By, Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'isisisdsssssssisssss',
                $nextIndex,
                $receiptCode,
                $ownerId,
                $refType,
                $refId,
                $serviceSummary,
                $totalAmount,
                $currency,
                $merkleRoot,
                $prevHash,
                $blockHash,
                $ciphertext,
                $archiveFilename,
                $issuedBy,
                $attending['vet_id'],
                $attending['vet_name'],
                $attending['prc_license_no'],
                $status,
                $createdAt,
                $expiresAt
            );

            try {
                $ok = $stmt->execute();
                $errNo = $stmt->errno;
                $blockId = (int)$stmt->insert_id;
                $stmt->close();
                if (!$ok) {
                    throw new mysqli_sql_exception('Receipt insert failed', $errNo);
                }
            } catch (mysqli_sql_exception $sqlEx) {
                if ((int)$sqlEx->getCode() === 1062 && $refType !== 'manual' && $refId !== null) {
                    $conn->rollback();
                    $startedTx = false;
                    $dup = $conn->prepare(
                        "SELECT Block_ID, Block_Index, Receipt_Code, Merkle_Root, Prev_Hash, Block_Hash, Attending_Vet_ID, Attending_Vet_Name, Attending_Clinician_PRC, Status, Created_At, Expires_At
                         FROM receipt_blocks
                         WHERE Reference_Type = ? AND Reference_ID = ? LIMIT 1"
                    );
                    $dup->bind_param('si', $refType, $refId);
                    $dup->execute();
                    $existing = $dup->get_result()->fetch_assoc();
                    $dup->close();
                    if ($existing) {
                        return ['already_exists' => true, 'receipt' => $existing];
                    }
                }
                throw $sqlEx;
            }

            if (!is_dir(HW_RECEIPT_ARCHIVE_DIR)) {
                @mkdir(HW_RECEIPT_ARCHIVE_DIR, 0750, true);
            }
            $writtenArchivePath = HW_RECEIPT_ARCHIVE_DIR . DIRECTORY_SEPARATOR . $archiveFilename;
            if (@file_put_contents($writtenArchivePath, $ciphertext, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write encrypted owner receipt archive.');
            }

            $conn->commit();
            $startedTx = false;

            return [
                'already_exists' => false,
                'receipt' => [
                    'Block_ID' => $blockId,
                    'Block_Index' => $nextIndex,
                    'Receipt_Code' => $receiptCode,
                    'Owner_ID' => $ownerId,
                    'Service_Summary' => $serviceSummary,
                    'Amount' => $totalAmount,
                    'Currency' => $currency,
                    'Merkle_Root' => $merkleRoot,
                    'Prev_Hash' => $prevHash,
                    'Block_Hash' => $blockHash,
                    'Archive_Filename' => $archiveFilename,
                    'Issued_By' => $issuedBy,
                    'Attending_Vet_ID' => $attending['vet_id'],
                    'Attending_Vet_Name' => $attending['vet_name'],
                    'Attending_Clinician_PRC' => $attending['prc_license_no'],
                    'Status' => $status,
                    'Created_At' => $createdAt,
                    'Expires_At' => $expiresAt,
                ],
            ];
        } catch (Throwable $e) {
            if ($startedTx) {
                @$conn->rollback();
            }
            if ($writtenArchivePath !== null && is_file($writtenArchivePath)) {
                @unlink($writtenArchivePath);
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                hw_db_release_lock($conn, 'hw_receipt_ledger');
            }
        }
    }

    /**
     * Verifies cryptographic hash-chain linkage (Genesis Block #0 onward), block hashes, and Merkle roots.
     */
    function hw_receipt_verify_chain(mysqli $conn): array
    {
        hw_receipt_ensure_table($conn);
        $purged = hw_receipt_purge_expired($conn);

        $res = $conn->query("SELECT * FROM receipt_blocks ORDER BY Block_Index ASC");
        $expectedPrev = str_repeat('0', 64);
        $expectedIdx = 0;
        $checked = 0;
        $errors = [];

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $checked++;
                $bIdx = (int)$row['Block_Index'];
                $code = (string)$row['Receipt_Code'];

                if ($bIdx !== $expectedIdx) {
                    $errors[] = "Block index gap at {$code}: expected {$expectedIdx}, got {$bIdx}.";
                }
                if ((string)$row['Prev_Hash'] !== $expectedPrev) {
                    $errors[] = "Broken Prev_Hash link at block #{$bIdx} ({$code}).";
                }

                $recomputedHash = hash('sha256', implode('|', [
                    $bIdx,
                    $code,
                    $row['Merkle_Root'],
                    $row['Prev_Hash'],
                    $row['Created_At'],
                ]));
                if (!hash_equals((string)$row['Block_Hash'], $recomputedHash)) {
                    $errors[] = "Block_Hash mismatch at block #{$bIdx} ({$code}).";
                }

                $decrypted = hw_decrypt_gcm((string)$row['Payload_Ciphertext'], 'receipt:' . $code);
                $payload = $decrypted ? json_decode($decrypted, true) : null;
                if (!is_array($payload)) {
                    $errors[] = "Payload decryption failed at block #{$bIdx} ({$code}).";
                } else {
                    $recomputedMerkle = hw_receipt_compute_merkle_root(hw_receipt_build_leaves($payload));
                    if (!hash_equals((string)$row['Merkle_Root'], $recomputedMerkle)) {
                        $errors[] = "Merkle_Root mismatch at block #{$bIdx} ({$code}).";
                    }
                }

                $expectedPrev = (string)$row['Block_Hash'];
                $expectedIdx = $bIdx + 1;
            }
        }

        return [
            'valid' => count($errors) === 0,
            'total_blocks' => $checked,
            'purged_expired' => $purged,
            'head_hash' => $expectedPrev,
            'errors' => $errors,
        ];
    }

    /**
     * Loads and decrypts a receipt for an authorized Owner, Vet Staff, or Head Vet.
     */
    function hw_receipt_read_for_viewer(mysqli $conn, int $blockId, string $role, ?int $ownerId): ?array
    {
        hw_receipt_ensure_table($conn);
        hw_receipt_purge_expired($conn);

        $role = hw_normalize_role($role);
        $stmt = $conn->prepare("SELECT * FROM receipt_blocks WHERE Block_ID = ? LIMIT 1");
        $stmt->bind_param('i', $blockId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        if ($role === 'owner') {
            if ($ownerId === null || (int)$row['Owner_ID'] !== $ownerId) {
                return null;
            }
            if (($row['Status'] ?? 'ACTIVE') === 'PURGED_1YR' || strtotime((string)$row['Expires_At']) <= time()) {
                return null;
            }
        }

        $code = (string)$row['Receipt_Code'];
        $cipher = null;
        if (($row['Status'] ?? 'ACTIVE') === 'ACTIVE' && !empty($row['Archive_Filename'])) {
            $archivePath = HW_RECEIPT_ARCHIVE_DIR . DIRECTORY_SEPARATOR . basename((string)$row['Archive_Filename']);
            if (is_file($archivePath)) {
                $cipher = (string)@file_get_contents($archivePath);
            }
        }
        if (!$cipher) {
            $cipher = (string)$row['Payload_Ciphertext'];
        }

        $decrypted = hw_decrypt_gcm($cipher, 'receipt:' . $code);
        $payload = $decrypted ? json_decode($decrypted, true) : null;
        if (!is_array($payload)) {
            return null;
        }

        $recomputedMerkle = hw_receipt_compute_merkle_root(hw_receipt_build_leaves($payload));
        $recomputedHash = hash('sha256', implode('|', [
            (int)$row['Block_Index'],
            $code,
            $row['Merkle_Root'],
            $row['Prev_Hash'],
            $row['Created_At'],
        ]));

        return [
            'block' => [
                'Block_ID' => (int)$row['Block_ID'],
                'Block_Index' => (int)$row['Block_Index'],
                'Receipt_Code' => $code,
                'Merkle_Root' => $row['Merkle_Root'],
                'Prev_Hash' => $row['Prev_Hash'],
                'Block_Hash' => $row['Block_Hash'],
                'Attending_Vet_ID' => (int)$row['Attending_Vet_ID'],
                'Attending_Vet_Name' => $row['Attending_Vet_Name'],
                'Attending_Clinician_PRC' => $row['Attending_Clinician_PRC'],
                'Status' => $row['Status'],
                'Created_At' => $row['Created_At'],
                'Expires_At' => $row['Expires_At'],
                'Archive_Active' => ($row['Status'] === 'ACTIVE') && !empty($row['Archive_Filename']) && strtotime((string)$row['Expires_At']) > time(),
                'Verified' => hash_equals((string)$row['Merkle_Root'], $recomputedMerkle)
                           && hash_equals((string)$row['Block_Hash'], $recomputedHash),
            ],
            'receipt' => $payload,
        ];
    }
}
