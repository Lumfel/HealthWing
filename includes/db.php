<?php
/**
 * HealthWing / VetSystem — Centralized Database, AES-256-GCM Security & Schema Auto-Bootstrap
 */
require_once __DIR__ . '/security_crypto.php';
hw_start_secure_session();

if (!function_exists('hw_get_db')) {
    function hw_get_db(): mysqli
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'veterinarydb';

        $conn = @new mysqli($host, $user, $pass, $name);
        if ($conn->connect_error) {
            throw new RuntimeException('Database connection failed: '. $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
        hw_ensure_overhaul_schema($conn);
        return $conn;
    }

    function hw_ensure_overhaul_schema(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $check = $conn->query("SHOW TABLES LIKE 'chat_messages'");
        $hasChat = $check && $check->num_rows >0;

        $colCheck = $conn->query("SHOW COLUMNS FROM `emergency_booking`LIKE 'Priority_Score'");
        $hasPriority = $colCheck && $colCheck->num_rows >0;

        $schedColCheck = $conn->query("SHOW COLUMNS FROM `schedule`LIKE 'Room_ID'");
        $hasSchedRoom = $schedColCheck && $schedColCheck->num_rows >0;

        
        // Ensure default 1 User (user / User@1234), 1 Vet (vet / Vet@1234), and 1 Admin (admin / Admin@1234) exist if empty
        $adminCount = $conn->query("SELECT COUNT(*) AS c FROM `admin`");
        if ($adminCount && ($row = $adminCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $conn->query("INSERT INTO `admin` (`Username`, `Email`, `Password`) VALUES ('admin', 'admin@healthwing.local', '$2y$10$avVjvSsDccziJaF5b/tkpeuopSvi1tKdoIdWiuPfp8kcovNMIzogu')");
        }
        $ownerCount = $conn->query("SELECT COUNT(*) AS c FROM `owner`");
        if ($ownerCount && ($row = $ownerCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $conn->query("INSERT INTO `owner` (`Owner_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `username`, `email`, `password`) VALUES (1, 'Leanne', 'Chua', 22, 'Brgy. Mandalagan, Bacolod City', '09931175267', 'user', 'user@healthwing.local', '$2y$10$iflurcWXPwMbtrGoSABLDeEzKv.2TABCs8gU5hxMUSaEgCKgUhcOq')");
        }
        $vetCount = $conn->query("SELECT COUNT(*) AS c FROM `veterinarian`");
        if ($vetCount && ($row = $vetCount->fetch_assoc()) && (int)$row['c'] === 0) {
            $conn->query("INSERT INTO `veterinarian` (`Veterinarian_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `Specialties`, `Status`, `Is_Available`, `username`, `email`, `password`) VALUES (1, 'Camille', 'Reyes', 29, 'Lacson St., Bacolod City', '09175550192', 'Emergency, Surgery, Dental, Vaccination, Wellness, Parasite, Grooming', 'Approved', 1, 'vet', 'vet@healthwing.local', '$2y$10$7gOivhqSOjfMq4nNFiSOo.FMZtvTtzpQ1E1T07XAhmRMyYQP7UvBe')");
        }

        if ($hasChat && $hasPriority && $hasSchedRoom) {
            return;
        }

        $alterStatements = [
            "ALTER TABLE `room`ADD COLUMN `Status`VARCHAR(20) NOT NULL DEFAULT 'idle'",
            "ALTER TABLE `room`ADD COLUMN `Room_Name`VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE `room`ADD COLUMN `Capacity`INT(11) NOT NULL DEFAULT 1",
            "ALTER TABLE `room`ADD COLUMN `Current_Emergency_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `room`ADD COLUMN `Current_Schedule_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `veterinarian`ADD COLUMN `Status`VARCHAR(20) NOT NULL DEFAULT 'Approved'",
            "ALTER TABLE `veterinarian`ADD COLUMN `Is_Available`TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `schedule`ADD COLUMN `Room_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `schedule`ADD COLUMN `Duration_Minutes`INT(11) NOT NULL DEFAULT 30",
            "ALTER TABLE `schedule`ADD COLUMN `End_Time`DATETIME DEFAULT NULL",
            "ALTER TABLE `schedule`ADD COLUMN `Notes`TEXT DEFAULT NULL",
            "ALTER TABLE `schedule`ADD COLUMN `Auto_Assigned`TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `schedule`ADD COLUMN `Preempted_By_Emergency_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `schedule`ADD COLUMN `Previous_Appointment_Time`DATETIME DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `IP_Address`VARCHAR(64) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Cancel_Requested`TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Cancel_Reason`TEXT DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Resolved_At`TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Client_Notified`TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled`TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled_By`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled_At`TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Pet_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Severity_Level`VARCHAR(30) NOT NULL DEFAULT 'Urgent'",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Priority_Score`DECIMAL(6,2) NOT NULL DEFAULT 50.00",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Queue_Position`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Assigned_Vet_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Assigned_Room_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Preempted_Schedule_ID`INT(11) DEFAULT NULL",
            "ALTER TABLE `emergency_booking`ADD COLUMN `Triage_Rationale`TEXT DEFAULT NULL",
            "ALTER TABLE `pet_checkup`ADD COLUMN `Diagnosis`TEXT DEFAULT NULL",
            "ALTER TABLE `pet_checkup`ADD COLUMN `Prescription`TEXT DEFAULT NULL"
        ];

        foreach ($alterStatements as $sql) {
            try {
                @$conn->query($sql);
            } catch (Throwable $e) {
            }
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS `chat_messages`(
            `Message_ID`int(11) NOT NULL AUTO_INCREMENT,
            `Channel_Key`varchar(64) NOT NULL,
            `Booking_ID`int(11) DEFAULT NULL,
            `Schedule_ID`int(11) DEFAULT NULL,
            `Sender_Role`enum('owner','vet','admin','system') NOT NULL DEFAULT 'owner',
            `Sender_ID`int(11) DEFAULT NULL,
            `Sender_Name`varchar(100) NOT NULL,
            `Message_Text`text NOT NULL,
            `Message_Type`enum('text','system_alert','triage_notice','call_event') NOT NULL DEFAULT 'text',
            `Is_Read`tinyint(1) NOT NULL DEFAULT 0,
            `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`Message_ID`),
            KEY `Channel_Key`(`Channel_Key`),
            KEY `Booking_ID`(`Booking_ID`),
            KEY `Schedule_ID`(`Schedule_ID`),
            KEY `Created_At`(`Created_At`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $defaultRooms = [
            ['Main Room', 'General'],
            ['Grooming Room', 'Grooming'],
            ['Emergency Trauma Bay 1', 'Emergency'],
            ['Surgery Suite A', 'Surgery'],
            ['Dental & Procedure Room', 'Dental'],
        ];
        foreach ($defaultRooms as [$rName, $rType]) {
            $escName = $conn->real_escape_string($rName);
            $escType = $conn->real_escape_string($rType);
            @$conn->query("INSERT INTO `room`(`Room_Name`, `Room_Type`, `Status`)
                           SELECT '$escName', '$escType', 'idle'
                           WHERE NOT EXISTS (SELECT 1 FROM `room`WHERE `Room_Name`= '$escName')");
        }
    }
}
