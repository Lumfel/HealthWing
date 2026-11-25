<?php
/**
 * HealthWing / VetSystem — Centralized Database, Advisory Locking & Schema Auto-Bootstrap
 */
require_once __DIR__ . '/security_crypto.php';
hw_start_secure_session();

if (!function_exists('hw_get_db')) {
    function hw_get_db(): mysqli
    {
        $host = getenv('HW_DB_HOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $user = getenv('HW_DB_USER') ?: (getenv('DB_USER') ?: 'root');
        $passEnv = getenv('HW_DB_PASS');
        $pass = ($passEnv !== false) ? $passEnv : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
        $name = getenv('HW_DB_NAME') ?: (getenv('DB_NAME') ?: 'veterinarydb');
        $port = (int)(getenv('HW_DB_PORT') ?: (getenv('DB_PORT') ?: 3306));

        $conn = @new mysqli($host, $user, $pass, $name, $port);
        if ($conn->connect_error) {
            throw new RuntimeException('Database connection unavailable.');
        }
        $conn->set_charset('utf8mb4');
        hw_ensure_schema($conn);
        return $conn;
    }

    /**
     * Acquires a named MySQL advisory lock (mutex) with timeout seconds.
     */
    function hw_db_acquire_lock(mysqli $conn, string $lockName, int $timeoutSec = 5): bool
    {
        $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS locked');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $lockName, $timeoutSec);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($row['locked']) && (int)$row['locked'] === 1;
    }

    /**
     * Releases a named MySQL advisory lock (always call inside a finally block).
     */
    function hw_db_release_lock(mysqli $conn, string $lockName): void
    {
        try {
            $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
            if ($stmt) {
                $stmt->bind_param('s', $lockName);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Suppress lock release noise if connection already dropped
        }
    }

    function hw_ensure_schema(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        // Ensure canonical columns exist on pre-existing databases (idempotent)
        $alterStatements = [
            "ALTER TABLE `owner` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `owner` ADD COLUMN `Anonymized_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `veterinarian` ADD COLUMN `Role_Level` ENUM('head_vet','vet_staff') NOT NULL DEFAULT 'vet_staff'",
            "ALTER TABLE `veterinarian` ADD COLUMN `PRC_License_No` VARCHAR(64) NOT NULL DEFAULT ''",
            "ALTER TABLE `veterinarian` ADD COLUMN `Status` VARCHAR(20) NOT NULL DEFAULT 'Approved'",
            "ALTER TABLE `veterinarian` ADD COLUMN `Is_Available` TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `veterinarian` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `pet` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `pet_checkup` ADD COLUMN `Diagnosis` TEXT DEFAULT NULL",
            "ALTER TABLE `pet_checkup` ADD COLUMN `Prescription` TEXT DEFAULT NULL",
            "ALTER TABLE `pet_checkup` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `room` ADD COLUMN `Status` VARCHAR(20) NOT NULL DEFAULT 'idle'",
            "ALTER TABLE `room` ADD COLUMN `Room_Name` VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE `room` ADD COLUMN `Capacity` INT(11) NOT NULL DEFAULT 1",
            "ALTER TABLE `room` ADD COLUMN `Current_Emergency_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `room` ADD COLUMN `Current_Schedule_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Room_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Duration_Minutes` INT(11) NOT NULL DEFAULT 30",
            "ALTER TABLE `schedule` ADD COLUMN `End_Time` DATETIME DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Notes` TEXT DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Auto_Assigned` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `schedule` ADD COLUMN `Preempted_By_Emergency_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Previous_Appointment_Time` DATETIME DEFAULT NULL",
            "ALTER TABLE `schedule` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `IP_Address` VARCHAR(64) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Cancel_Requested` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Cancel_Reason` TEXT DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Resolved_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Client_Notified` TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Call_Enabled` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Call_Enabled_By` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Call_Enabled_At` TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Pet_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Severity_Level` VARCHAR(30) NOT NULL DEFAULT 'Urgent'",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Priority_Score` DECIMAL(6,2) NOT NULL DEFAULT 50.00",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Queue_Position` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Assigned_Vet_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Assigned_Room_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Preempted_Schedule_ID` INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Triage_Rationale` TEXT DEFAULT NULL",
            "ALTER TABLE `emergency_booking` ADD COLUMN `Deleted_At` TIMESTAMP NULL DEFAULT NULL",
        ];

        $hasVetRole = $conn->query("SHOW COLUMNS FROM `veterinarian` LIKE 'Role_Level'");
        $hasDeletedOwner = $conn->query("SHOW COLUMNS FROM `owner` LIKE 'Deleted_At'");
        $hasPriority = $conn->query("SHOW COLUMNS FROM `emergency_booking` LIKE 'Priority_Score'");
        if (!$hasVetRole || $hasVetRole->num_rows === 0 || !$hasDeletedOwner || $hasDeletedOwner->num_rows === 0 || !$hasPriority || $hasPriority->num_rows === 0) {
            foreach ($alterStatements as $sql) {
                try {
                    @$conn->query($sql);
                } catch (Throwable $e) {
                }
            }
        }

        // Canonical receipt_blocks table (synchronized with database/veterinarydb.sql)
        $receiptCheck = $conn->query("SHOW TABLES LIKE 'receipt_blocks'");
        if (!$receiptCheck || $receiptCheck->num_rows === 0) {
            @$conn->query("CREATE TABLE IF NOT EXISTS `receipt_blocks` (
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

        // Seed 3-role defaults only if tables are completely empty (no standalone admin table)
        $ownerCount = $conn->query("SELECT COUNT(*) AS c FROM `owner`");
        if ($ownerCount && ($row = $ownerCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $stmt = $conn->prepare("INSERT INTO `owner` (`Owner_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `username`, `email`, `password`) VALUES (1, 'Leanne', 'Chua', 22, 'Brgy. Mandalagan, Bacolod City', '09931175267', 'user', 'user@healthwing.local', ?)");
            if ($stmt) {
                $hash = '$2y$10$iflurcWXPwMbtrGoSABLDeEzKv.2TABCs8gU5hxMUSaEgCKgUhcOq';
                $stmt->bind_param('s', $hash);
                @$stmt->execute();
                $stmt->close();
            }
        }

        $vetCount = $conn->query("SELECT COUNT(*) AS c FROM `veterinarian`");
        if ($vetCount && ($row = $vetCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $vStmt = $conn->prepare("INSERT INTO `veterinarian` (`Veterinarian_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `Specialties`, `Role_Level`, `PRC_License_No`, `Status`, `Is_Available`, `username`, `email`, `password`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', 1, ?, ?, ?)");
            if ($vStmt) {
                $seeds = [
                    [1, 'Camille', 'Reyes', 29, 'Lacson St., Bacolod City', '09175550192', 'Emergency, Surgery, Dental, Vaccination, Wellness, Parasite, Grooming', 'vet_staff', 'PRC-VET-004821', 'vet', 'vet@healthwing.local', '$2y$10$7gOivhqSOjfMq4nNFiSOo.FMZtvTtzpQ1E1T07XAhmRMyYQP7UvBe'],
                    [2, 'Marco', 'Villanueva', 38, 'Lacson St., Bacolod City', '09175550101', 'Clinic Director, Emergency, Surgery, Dental, Vaccination, Wellness', 'head_vet', 'PRC-VET-001001', 'headvet', 'headvet@healthwing.local', '$2y$10$avVjvSsDccziJaF5b/tkpeuopSvi1tKdoIdWiuPfp8kcovNMIzogu'],
                ];
                foreach ($seeds as $s) {
                    $vStmt->bind_param('ississssssss', $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $s[10], $s[11]);
                    @$vStmt->execute();
                }
                $vStmt->close();
            }
        }

        $chatCheck = $conn->query("SHOW TABLES LIKE 'chat_messages'");
        if (!$chatCheck || $chatCheck->num_rows === 0) {
            @$conn->query("CREATE TABLE IF NOT EXISTS `chat_messages` (
                `Message_ID` int(11) NOT NULL AUTO_INCREMENT,
                `Channel_Key` varchar(64) NOT NULL,
                `Booking_ID` int(11) DEFAULT NULL,
                `Schedule_ID` int(11) DEFAULT NULL,
                `Sender_Role` enum('owner','vet_staff','head_vet','vet','admin','system') NOT NULL DEFAULT 'owner',
                `Sender_ID` int(11) DEFAULT NULL,
                `Sender_Name` varchar(100) NOT NULL,
                `Message_Text` text NOT NULL,
                `Message_Type` enum('text','system_alert','triage_notice','call_event') NOT NULL DEFAULT 'text',
                `Is_Read` tinyint(1) NOT NULL DEFAULT 0,
                `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`Message_ID`),
                KEY `idx_chat_channel_msg` (`Channel_Key`, `Message_ID`),
                KEY `idx_chat_channel_created` (`Channel_Key`, `Created_At`),
                KEY `Booking_ID` (`Booking_ID`),
                KEY `Schedule_ID` (`Schedule_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        }

        $roomCount = $conn->query("SELECT COUNT(*) AS c FROM `room`");
        if ($roomCount && ($row = $roomCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $defaultRooms = [
                ['Main Consultation Room', 'General'],
                ['Grooming & Hygiene Suite', 'Grooming'],
                ['Emergency Trauma Bay 1', 'Emergency'],
                ['Sterile Surgery Suite A', 'Surgery'],
                ['Dental & Procedure Room', 'Dental'],
            ];
            $rChk = $conn->prepare("SELECT 1 FROM `room` WHERE `Room_Name` = ? LIMIT 1");
            $rIns = $conn->prepare("INSERT INTO `room` (`Room_Name`, `Room_Type`, `Status`) VALUES (?, ?, 'idle')");
            if ($rChk && $rIns) {
                foreach ($defaultRooms as [$rName, $rType]) {
                    $rChk->bind_param('s', $rName);
                    $rChk->execute();
                    if ($rChk->get_result()->num_rows === 0) {
                        $rIns->bind_param('ss', $rName, $rType);
                        @$rIns->execute();
                    }
                }
                $rChk->close();
                $rIns->close();
            }
        }
    }
}
