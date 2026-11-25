-- ============================================================================
-- HealthWing / VetSystem — Canonical Database Schema & Seed (database/veterinarydb.sql)
-- ============================================================================
-- 3-Role RBAC Model (standalone admin table retired):
--   1. Pet Owner (`owner`):                   user / User@1234     (user@healthwing.local)
--   2. Staff Veterinarian (`vet_staff`):      vet / Vet@1234       (vet@healthwing.local, PRC-VET-004821)
--   3. Head Veterinarian (`head_vet`):        headvet (headvet@healthwing.local, PRC-VET-001001) - seeded by includes/db.php when empty; set a strong password before deployment.
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `veterinarydb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `veterinarydb`;

DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `consent_log`;
DROP TABLE IF EXISTS `receipt_blocks`;
DROP TABLE IF EXISTS `call_ice_candidate`;
DROP TABLE IF EXISTS `call_session`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `traffic_log`;
DROP TABLE IF EXISTS `ratings`;
DROP TABLE IF EXISTS `pet_checkup`;
DROP TABLE IF EXISTS `emergency_booking`;
DROP TABLE IF EXISTS `schedule`;
DROP TABLE IF EXISTS `vet`;
DROP TABLE IF EXISTS `pet`;
DROP TABLE IF EXISTS `medical`;
DROP TABLE IF EXISTS `room`;
DROP TABLE IF EXISTS `veterinarian`;
DROP TABLE IF EXISTS `owner`;
DROP TABLE IF EXISTS `admin`;

-- 1. Table: owner (Pet Owners — RA 10173 Soft-Delete & Anonymization Ready)
CREATE TABLE `owner` (
  `Owner_ID` int(11) NOT NULL AUTO_INCREMENT,
  `First_Name` varchar(50) NOT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Age` int(11) DEFAULT NULL CHECK (`Age` >= 0),
  `Address` text DEFAULT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_At` timestamp NULL DEFAULT NULL,
  `Anonymized_At` timestamp NULL DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`Owner_ID`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_owner_deleted` (`Deleted_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `owner` (`Owner_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `username`, `email`, `password`) VALUES
(1, 'Leanne', 'Chua', 22, 'Brgy. Mandalagan, Bacolod City', '09931175267', 'user', 'user@healthwing.local', '$2y$10$iflurcWXPwMbtrGoSABLDeEzKv.2TABCs8gU5hxMUSaEgCKgUhcOq');

-- 2. Table: veterinarian (Unified Clinical Staff & Head Veterinarian / Super Admin)
CREATE TABLE `veterinarian` (
  `Veterinarian_ID` int(11) NOT NULL AUTO_INCREMENT,
  `First_Name` varchar(50) NOT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Age` int(11) DEFAULT NULL CHECK (`Age` >= 0),
  `Address` text DEFAULT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Specialties` text DEFAULT NULL,
  `Role_Level` enum('head_vet','vet_staff') NOT NULL DEFAULT 'vet_staff',
  `PRC_License_No` varchar(64) NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'Approved',
  `Is_Available` tinyint(1) NOT NULL DEFAULT 1,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_At` timestamp NULL DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`Veterinarian_ID`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_vet_prc` (`PRC_License_No`),
  KEY `idx_vet_status_avail` (`Status`, `Is_Available`),
  KEY `idx_vet_role_level` (`Role_Level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `veterinarian` (`Veterinarian_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `Specialties`, `Role_Level`, `PRC_License_No`, `Status`, `Is_Available`, `username`, `email`, `password`) VALUES
(1, 'Camille', 'Reyes', 29, 'Lacson St., Bacolod City', '09175550192', 'Emergency, Surgery, Dental, Vaccination, Wellness, Parasite, Grooming', 'vet_staff', 'PRC-VET-004821', 'Approved', 1, 'vet', 'vet@healthwing.local', '$2y$10$7gOivhqSOjfMq4nNFiSOo.FMZtvTtzpQ1E1T07XAhmRMyYQP7UvBe'),
(2, 'Marco', 'Villanueva', 38, 'Lacson St., Bacolod City', '09175550101', 'Clinic Director, Emergency, Surgery, Dental, Vaccination, Wellness', 'head_vet', 'PRC-VET-001001', 'Approved', 1, 'headvet', 'headvet@healthwing.local', '$2y$10$avVjvSsDccziJaF5b/tkpeuopSvi1tKdoIdWiuPfp8kcovNMIzogu');

-- 3. Table: room (5 Specialized Clinic Rooms)
CREATE TABLE `room` (
  `Room_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Room_Name` varchar(100) NOT NULL,
  `Room_Type` varchar(50) NOT NULL DEFAULT 'General',
  `Status` varchar(20) NOT NULL DEFAULT 'idle',
  `Capacity` int(11) NOT NULL DEFAULT 1,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Current_Emergency_ID` int(11) DEFAULT NULL,
  `Current_Schedule_ID` int(11) DEFAULT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Room_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  KEY `idx_room_type_status` (`Room_Type`, `Status`),
  CONSTRAINT `room_ibfk_1` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `room` (`Room_ID`, `Room_Name`, `Room_Type`, `Status`, `Capacity`, `Veterinarian_ID`, `Current_Emergency_ID`, `Current_Schedule_ID`) VALUES
(1, 'Main Consultation Room', 'General', 'idle', 1, 1, NULL, NULL),
(2, 'Grooming & Hygiene Suite', 'Grooming', 'idle', 2, 1, NULL, NULL),
(3, 'Emergency Trauma Bay 1', 'Emergency', 'ongoing', 1, 1, 1, NULL),
(4, 'Sterile Surgery Suite A', 'Surgery', 'idle', 1, 2, NULL, NULL),
(5, 'Dental & Procedure Room', 'Dental', 'idle', 1, 1, NULL, NULL);

-- 4. Table: pet (Non-Destructive FK to owner + Soft-Delete Column)
CREATE TABLE `pet` (
  `Pet_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Pet_Name` varchar(50) NOT NULL,
  `Pet_Type` varchar(50) NOT NULL,
  `Species` varchar(50) DEFAULT NULL,
  `Age` int(11) DEFAULT NULL,
  `Weight_Kg` decimal(5,2) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Past_Conditions` text DEFAULT NULL,
  `Current_Ailment` text DEFAULT NULL,
  `Current_Medications` text DEFAULT NULL,
  `Photo_Path` varchar(255) DEFAULT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_At` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Pet_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `idx_pet_deleted` (`Deleted_At`),
  CONSTRAINT `pet_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pet` (`Pet_ID`, `Owner_ID`, `Pet_Name`, `Pet_Type`, `Species`, `Age`, `Weight_Kg`, `Description`, `Past_Conditions`, `Current_Ailment`, `Current_Medications`, `Photo_Path`) VALUES
(1, 1, 'Mochi', 'Dog', 'Golden Retriever', 3, 24.50, 'Friendly golden retriever, microchipped', 'Mild seasonal skin allergy (resolved)', 'Annual wellness & booster check', 'Heartgard Plus monthly chewable', NULL);

-- 5. Table: pet_checkup (Clinical History — ON DELETE RESTRICT & Mandatory Attending Vet)
CREATE TABLE `pet_checkup` (
  `Checkup_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Pet_ID` int(11) NOT NULL,
  `Veterinarian_ID` int(11) NOT NULL,
  `Checkup_Date` date NOT NULL,
  `Notes` text DEFAULT NULL,
  `Diagnosis` text DEFAULT NULL,
  `Prescription` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_At` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Checkup_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  CONSTRAINT `pet_checkup_ibfk_1` FOREIGN KEY (`Pet_ID`) REFERENCES `pet` (`Pet_ID`) ON DELETE RESTRICT,
  CONSTRAINT `pet_checkup_ibfk_2` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pet_checkup` (`Checkup_ID`, `Pet_ID`, `Veterinarian_ID`, `Checkup_Date`, `Notes`, `Diagnosis`, `Prescription`) VALUES
(1, 1, 1, CURDATE(), 'Vital signs normal, temperature 38.5 C, clear lungs and coat.', 'Healthy adult canine - cleared for annual booster.', 'Rabies + DHPPiL booster administered; continue monthly heartworm prophylaxis.');

-- 6. Table: schedule (Non-Destructive FKs + Soft-Delete Column)
CREATE TABLE `schedule` (
  `Schedule_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Pet_ID` int(11) DEFAULT NULL,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Room_ID` int(11) DEFAULT NULL,
  `Services` text NOT NULL,
  `Appointment_Time` datetime NOT NULL,
  `Duration_Minutes` int(11) NOT NULL DEFAULT 30,
  `End_Time` datetime DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'Confirmed',
  `Notes` text DEFAULT NULL,
  `Auto_Assigned` tinyint(1) NOT NULL DEFAULT 1,
  `Preempted_By_Emergency_ID` int(11) DEFAULT NULL,
  `Previous_Appointment_Time` datetime DEFAULT NULL,
  `Last_Updated_At` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_At` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Schedule_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  KEY `Room_ID` (`Room_ID`),
  KEY `idx_schedule_interval` (`Appointment_Time`, `End_Time`, `Status`),
  KEY `idx_schedule_vet_slot` (`Veterinarian_ID`, `Status`, `Appointment_Time`),
  KEY `idx_schedule_room_slot` (`Room_ID`, `Status`, `Appointment_Time`),
  CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE SET NULL,
  CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`Pet_ID`) REFERENCES `pet` (`Pet_ID`) ON DELETE RESTRICT,
  CONSTRAINT `schedule_ibfk_4` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE RESTRICT,
  CONSTRAINT `schedule_ibfk_5` FOREIGN KEY (`Room_ID`) REFERENCES `room` (`Room_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `schedule` (`Schedule_ID`, `Owner_ID`, `Pet_ID`, `Veterinarian_ID`, `Room_ID`, `Services`, `Appointment_Time`, `Duration_Minutes`, `End_Time`, `Status`, `Notes`, `Auto_Assigned`) VALUES
(1, 1, 1, 1, 1, 'Wellness Care', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:00:00'), 30, CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:30:00'), 'Confirmed', 'Annual comprehensive physical examination for Mochi', 1);

-- 7. Table: emergency_booking (Non-Destructive FKs + Soft-Delete Column)
CREATE TABLE `emergency_booking` (
  `Booking_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Pet_ID` int(11) DEFAULT NULL,
  `Name` varchar(100) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Phone_Number` varchar(20) DEFAULT NULL,
  `Additional_Details` text DEFAULT NULL,
  `Image_Path` varchar(255) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` varchar(50) DEFAULT 'In Progress',
  `IP_Address` varchar(64) DEFAULT NULL,
  `Cancel_Requested` tinyint(1) NOT NULL DEFAULT 0,
  `Cancel_Reason` text DEFAULT NULL,
  `Resolved_At` timestamp NULL DEFAULT NULL,
  `Client_Notified` tinyint(1) NOT NULL DEFAULT 1,
  `Call_Enabled` tinyint(1) NOT NULL DEFAULT 1,
  `Call_Enabled_By` int(11) DEFAULT 1,
  `Call_Enabled_At` timestamp NULL DEFAULT current_timestamp(),
  `Severity_Level` varchar(30) NOT NULL DEFAULT 'Emergent',
  `Priority_Score` decimal(6,2) NOT NULL DEFAULT 78.50,
  `Queue_Position` int(11) DEFAULT 1,
  `Assigned_Vet_ID` int(11) DEFAULT 1,
  `Assigned_Room_ID` int(11) DEFAULT 3,
  `Preempted_Schedule_ID` int(11) DEFAULT NULL,
  `Triage_Rationale` text DEFAULT NULL,
  `Deleted_At` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Booking_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `Assigned_Vet_ID` (`Assigned_Vet_ID`),
  KEY `Assigned_Room_ID` (`Assigned_Room_ID`),
  KEY `idx_triage_queue` (`Status`, `Priority_Score`, `Created_At`),
  CONSTRAINT `emergency_booking_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE SET NULL,
  CONSTRAINT `emergency_booking_ibfk_2` FOREIGN KEY (`Pet_ID`) REFERENCES `pet` (`Pet_ID`) ON DELETE SET NULL,
  CONSTRAINT `emergency_booking_ibfk_3` FOREIGN KEY (`Assigned_Vet_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL,
  CONSTRAINT `emergency_booking_ibfk_4` FOREIGN KEY (`Assigned_Room_ID`) REFERENCES `room` (`Room_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `emergency_booking` (`Booking_ID`, `Owner_ID`, `Pet_ID`, `Name`, `Email`, `Phone_Number`, `Additional_Details`, `Status`, `Call_Enabled`, `Severity_Level`, `Priority_Score`, `Queue_Position`, `Assigned_Vet_ID`, `Assigned_Room_ID`, `Triage_Rationale`) VALUES
(1, 1, 1, 'Leanne Chua', 'user@healthwing.local', '09931175267', 'Acute allergic reaction with facial swelling after park walk', 'In Progress', 1, 'Emergent', 78.50, 1, 1, 3, 'Queue #1 | Score 78.5/100. Allocated Emergency Trauma Bay 1 & Dr. Camille Reyes.');

-- 8. Table: ratings
CREATE TABLE `ratings` (
  `Rating_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Schedule_ID` int(11) DEFAULT NULL,
  `Service` varchar(100) DEFAULT NULL,
  `Rating` int(11) NOT NULL,
  `Comment` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Rating_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Schedule_ID` (`Schedule_ID`),
  CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE SET NULL,
  CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`Schedule_ID`) REFERENCES `schedule` (`Schedule_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ratings` (`Rating_ID`, `Owner_ID`, `Schedule_ID`, `Service`, `Rating`, `Comment`) VALUES
(1, 1, 1, 'Wellness Care', 5, 'Fast scheduling and very attentive care from Dr. Camille Reyes.');

-- 9. Table: traffic_log
CREATE TABLE `traffic_log` (
  `Log_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IP_Address` varchar(64) NOT NULL,
  `Action` varchar(50) NOT NULL,
  `Status` enum('allowed','blocked') NOT NULL DEFAULT 'allowed',
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Log_ID`),
  KEY `Created_At` (`Created_At`),
  KEY `Status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `traffic_log` (`Log_ID`, `IP_Address`, `Action`, `Status`) VALUES
(1, '127.0.0.1', 'login', 'allowed');

-- 10. Table: call_session & call_ice_candidate
CREATE TABLE `call_session` (
  `Call_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Booking_ID` int(11) NOT NULL,
  `Call_Type` enum('audio','video') NOT NULL DEFAULT 'video',
  `Initiated_By` enum('owner','vet') NOT NULL,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Owner_ID` int(11) DEFAULT NULL,
  `Status` enum('ringing','active','declined','ended','missed') NOT NULL DEFAULT 'ringing',
  `Offer_SDP` longtext DEFAULT NULL,
  `Answer_SDP` longtext DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Answered_At` timestamp NULL DEFAULT NULL,
  `Ended_At` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Call_ID`),
  KEY `Booking_ID` (`Booking_ID`),
  KEY `Status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `call_ice_candidate` (
  `Candidate_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Call_ID` int(11) NOT NULL,
  `Sender` enum('owner','vet') NOT NULL,
  `Candidate` text NOT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Candidate_ID`),
  KEY `Call_ID` (`Call_ID`),
  CONSTRAINT `call_ice_ibfk_1` FOREIGN KEY (`Call_ID`) REFERENCES `call_session` (`Call_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Table: chat_messages (Composite Indexes for Hot History Query)
CREATE TABLE `chat_messages` (
  `Message_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Channel_Key` varchar(64) NOT NULL,
  `Booking_ID` int(11) DEFAULT NULL,
  `Schedule_ID` int(11) DEFAULT NULL,
  `Sender_Role` enum('owner','vet_staff','head_vet','system') NOT NULL DEFAULT 'owner',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `chat_messages` (`Message_ID`, `Channel_Key`, `Booking_ID`, `Sender_Role`, `Sender_ID`, `Sender_Name`, `Message_Text`, `Message_Type`, `Is_Read`) VALUES
(1, 'booking_1', 1, 'vet_staff', 1, 'Dr. Camille Reyes', 'Mochi is stable in Emergency Trauma Bay 1. Antihistamine administered and swelling is subsiding.', 'text', 1),
(2, 'booking_1', 1, 'owner', 1, 'Leanne Chua', 'Thank you so much, Doctor. I am in the waiting area now.', 'text', 1);

-- 12. Table: receipt_blocks (Immutable Hash-Chained Ledger + 1-Year Encrypted Archive Tracking)
CREATE TABLE `receipt_blocks` (
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
  KEY `idx_status` (`Status`),
  CONSTRAINT `receipt_blocks_ibfk_owner` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE SET NULL,
  CONSTRAINT `receipt_blocks_ibfk_vet` FOREIGN KEY (`Attending_Vet_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 13. Table: consent_log (RA 10173 Informed Consent Ledger)
CREATE TABLE IF NOT EXISTS `consent_log` (
  `Consent_ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) DEFAULT NULL,
  `Role` varchar(32) NOT NULL,
  `Action` varchar(64) NOT NULL,
  `Context_Type` varchar(64) DEFAULT NULL,
  `Context_ID` varchar(64) DEFAULT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `User_Agent` varchar(255) DEFAULT NULL,
  `Consented_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Consent_ID`),
  KEY `idx_consent_user` (`User_ID`, `Role`),
  KEY `idx_consent_action` (`Action`),
  KEY `idx_consent_time` (`Consented_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 14. Table: audit_log (Clinical & Operational Accountability Ledger)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `Audit_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Actor_ID` int(11) DEFAULT NULL,
  `Actor_Role` varchar(32) NOT NULL,
  `Action` varchar(128) NOT NULL,
  `Target_Type` varchar(64) DEFAULT NULL,
  `Target_ID` varchar(64) DEFAULT NULL,
  `Details` text DEFAULT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Audit_ID`),
  KEY `idx_audit_actor` (`Actor_ID`, `Actor_Role`),
  KEY `idx_audit_action` (`Action`),
  KEY `idx_audit_created` (`Created_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
