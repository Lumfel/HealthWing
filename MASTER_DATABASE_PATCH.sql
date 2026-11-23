-- ============================================================================
-- HealthWing / VetSystem — Clean Rebuilt Database Schema & Seed (veterinarydb.sql)
-- ============================================================================
-- Includes:
--   - All 14 normalized & overhauled InnoDB tables (utf8mb4)
--   - All legacy/junk rows removed — seeded with 1 User (Owner), 1 Veterinarian, 1 Admin
--   - 5 Specialized Clinic Rooms, 1 Sample Pet, 1 Checkup, 1 Scheduled Visit, 1 Triage Case
--
-- Default Seed Login Credentials:
--   1. Pet Owner (User):  username: user   | email: user@healthwing.local  | password: User@1234
--   2. Veterinarian:      username: vet    | email: vet@healthwing.local   | password: Vet@1234
--   3. Administrator:     username: admin  | email: admin@healthwing.local | password: Admin@1234
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `veterinarydb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `veterinarydb`;

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

-- 1. Table: admin (1 Seed Account)
CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Admin_ID`),
  UNIQUE KEY `Username` (`Username`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin` (`Admin_ID`, `Username`, `Email`, `Password`, `TimeAndDateAdded`) VALUES
(1, 'admin', 'admin@healthwing.local', '$2y$10$avVjvSsDccziJaF5b/tkpeuopSvi1tKdoIdWiuPfp8kcovNMIzogu', CURRENT_TIMESTAMP);

-- 2. Table: owner (1 Seed Account)
CREATE TABLE `owner` (
  `Owner_ID` int(11) NOT NULL AUTO_INCREMENT,
  `First_Name` varchar(50) NOT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Age` int(11) DEFAULT NULL CHECK (`Age` >= 0),
  `Address` text DEFAULT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`Owner_ID`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `owner` (`Owner_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `username`, `email`, `password`) VALUES
(1, 'Leanne', 'Chua', 22, 'Brgy. Mandalagan, Bacolod City', '09931175267', 'user', 'user@healthwing.local', '$2y$10$iflurcWXPwMbtrGoSABLDeEzKv.2TABCs8gU5hxMUSaEgCKgUhcOq');

-- 3. Table: veterinarian (1 Seed Account)
CREATE TABLE `veterinarian` (
  `Veterinarian_ID` int(11) NOT NULL AUTO_INCREMENT,
  `First_Name` varchar(50) NOT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Age` int(11) DEFAULT NULL CHECK (`Age` >= 0),
  `Address` text DEFAULT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Specialties` text DEFAULT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'Approved',
  `Is_Available` tinyint(1) NOT NULL DEFAULT 1,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`Veterinarian_ID`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_vet_status_avail` (`Status`, `Is_Available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `veterinarian` (`Veterinarian_ID`, `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `Specialties`, `Status`, `Is_Available`, `username`, `email`, `password`) VALUES
(1, 'Camille', 'Reyes', 29, 'Lacson St., Bacolod City', '09175550192', 'Emergency, Surgery, Dental, Vaccination, Wellness, Parasite, Grooming', 'Approved', 1, 'vet', 'vet@healthwing.local', '$2y$10$7gOivhqSOjfMq4nNFiSOo.FMZtvTtzpQ1E1T07XAhmRMyYQP7UvBe');

-- 4. Table: room (5 Specialized Clinic Rooms)
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
(3, 'Emergency Trauma Bay 1', 'Emergency', 'occupied', 1, 1, 1, NULL),
(4, 'Sterile Surgery Suite A', 'Surgery', 'idle', 1, 1, NULL, NULL),
(5, 'Dental & Procedure Room', 'Dental', 'idle', 1, 1, NULL, NULL);

-- 5. Table: medical (Legacy Compatibility Table)
CREATE TABLE `medical` (
  `Medical_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Medical_Records` text NOT NULL,
  `Size` varchar(50) DEFAULT NULL,
  `Skin` varchar(50) DEFAULT NULL,
  `Blindness` tinyint(1) DEFAULT 0,
  `Diagnostics` text DEFAULT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Medical_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  CONSTRAINT `medical_ibfk_1` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Table: pet (1 Seed Pet Owned by Owner_ID = 1)
CREATE TABLE `pet` (
  `Pet_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Medical_ID` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`Pet_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Medical_ID` (`Medical_ID`),
  CONSTRAINT `pet_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE CASCADE,
  CONSTRAINT `pet_ibfk_2` FOREIGN KEY (`Medical_ID`) REFERENCES `medical` (`Medical_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pet` (`Pet_ID`, `Owner_ID`, `Medical_ID`, `Pet_Name`, `Pet_Type`, `Species`, `Age`, `Weight_Kg`, `Description`, `Past_Conditions`, `Current_Ailment`, `Current_Medications`, `Photo_Path`) VALUES
(1, 1, NULL, 'Mochi', 'Dog', 'Golden Retriever', 3, 24.50, 'Friendly golden retriever, microchipped', 'Mild seasonal skin allergy (resolved)', 'Annual wellness & booster check', 'Heartgard Plus monthly chewable', NULL);

-- 7. Table: pet_checkup (Clinical History & Rx Log for Pet_ID = 1)
CREATE TABLE `pet_checkup` (
  `Checkup_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Pet_ID` int(11) NOT NULL,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Checkup_Date` date NOT NULL,
  `Notes` text DEFAULT NULL,
  `Diagnosis` text DEFAULT NULL,
  `Prescription` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Checkup_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  CONSTRAINT `pet_checkup_ibfk_1` FOREIGN KEY (`Pet_ID`) REFERENCES `pet` (`Pet_ID`) ON DELETE CASCADE,
  CONSTRAINT `pet_checkup_ibfk_2` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pet_checkup` (`Checkup_ID`, `Pet_ID`, `Veterinarian_ID`, `Checkup_Date`, `Notes`, `Diagnosis`, `Prescription`) VALUES
(1, 1, 1, CURDATE(), 'Vital signs normal, temperature 38.5 C, clear lungs and coat.', 'Healthy adult canine — cleared for annual booster.', 'Rabies + DHPPiL booster administered; continue monthly heartworm prophylaxis.');

-- 8. Table: vet (Legacy Service-to-Room Link Table)
CREATE TABLE `vet` (
  `Vet_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Room_ID` int(11) DEFAULT NULL,
  `Veterinarian_ID` int(11) DEFAULT NULL,
  `Services` text NOT NULL,
  `TimeAndDateAdded` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Vet_ID`),
  KEY `Room_ID` (`Room_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  CONSTRAINT `vet_ibfk_1` FOREIGN KEY (`Room_ID`) REFERENCES `room` (`Room_ID`) ON DELETE SET NULL,
  CONSTRAINT `vet_ibfk_2` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `vet` (`Vet_ID`, `Room_ID`, `Veterinarian_ID`, `Services`) VALUES
(1, 1, 1, 'Emergency, Surgery, Dental, Vaccination, Wellness, Parasite, Grooming');

-- 9. Table: schedule (1 Sample Conflict-Free Scheduled Appointment)
CREATE TABLE `schedule` (
  `Schedule_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Pet_ID` int(11) DEFAULT NULL,
  `Vet_ID` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`Schedule_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `Vet_ID` (`Vet_ID`),
  KEY `Veterinarian_ID` (`Veterinarian_ID`),
  KEY `Room_ID` (`Room_ID`),
  KEY `idx_schedule_interval` (`Appointment_Time`, `End_Time`, `Status`),
  CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE CASCADE,
  CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`Pet_ID`) REFERENCES `pet` (`Pet_ID`) ON DELETE CASCADE,
  CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`Vet_ID`) REFERENCES `vet` (`Vet_ID`) ON DELETE SET NULL,
  CONSTRAINT `schedule_ibfk_4` FOREIGN KEY (`Veterinarian_ID`) REFERENCES `veterinarian` (`Veterinarian_ID`) ON DELETE SET NULL,
  CONSTRAINT `schedule_ibfk_5` FOREIGN KEY (`Room_ID`) REFERENCES `room` (`Room_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `schedule` (`Schedule_ID`, `Owner_ID`, `Pet_ID`, `Vet_ID`, `Veterinarian_ID`, `Room_ID`, `Services`, `Appointment_Time`, `Duration_Minutes`, `End_Time`, `Status`, `Notes`, `Auto_Assigned`) VALUES
(1, 1, 1, 1, 1, 1, 'Wellness Care', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:00:00'), 30, CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:30:00'), 'Confirmed', 'Annual comprehensive physical examination for Mochi', 1);

-- 10. Table: emergency_booking (1 Sample Triage Queue Record)
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
  `Status` varchar(50) DEFAULT 'In Treatment',
  `IP_Address` varchar(64) DEFAULT NULL,
  `Cancel_Requested` tinyint(1) NOT NULL DEFAULT 0,
  `Cancel_Reason` text DEFAULT NULL,
  `Resolved_At` timestamp NULL DEFAULT NULL,
  `Client_Notified` tinyint(1) NOT NULL DEFAULT 1,
  `Call_Enabled` tinyint(1) NOT NULL DEFAULT 1,
  `Call_Enabled_By` int(11) DEFAULT 1,
  `Call_Enabled_At` timestamp NULL DEFAULT current_timestamp(),
  `Severity_Level` varchar(30) NOT NULL DEFAULT 'High',
  `Priority_Score` decimal(6,2) NOT NULL DEFAULT 78.50,
  `Queue_Position` int(11) DEFAULT 1,
  `Assigned_Vet_ID` int(11) DEFAULT 1,
  `Assigned_Room_ID` int(11) DEFAULT 3,
  `Preempted_Schedule_ID` int(11) DEFAULT NULL,
  `Triage_Rationale` text DEFAULT NULL,
  PRIMARY KEY (`Booking_ID`),
  KEY `Owner_ID` (`Owner_ID`),
  KEY `Pet_ID` (`Pet_ID`),
  KEY `idx_triage_queue` (`Status`, `Priority_Score`, `Created_At`),
  CONSTRAINT `emergency_booking_ibfk_1` FOREIGN KEY (`Owner_ID`) REFERENCES `owner` (`Owner_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `emergency_booking` (`Booking_ID`, `Owner_ID`, `Pet_ID`, `Name`, `Email`, `Phone_Number`, `Additional_Details`, `Status`, `Call_Enabled`, `Severity_Level`, `Priority_Score`, `Queue_Position`, `Assigned_Vet_ID`, `Assigned_Room_ID`, `Triage_Rationale`) VALUES
(1, 1, 1, 'Leanne Chua', 'user@healthwing.local', '09931175267', 'Acute allergic reaction with facial swelling after park walk', 'In Treatment', 1, 'High', 78.50, 1, 1, 3, 'Queue #1 | Score 78.5/100 (High: Acuity 38 + Vulnerability 10 + Wait 30.5). Allocated Emergency Trauma Bay 1 & Dr. Camille Reyes.');

-- 11. Table: ratings
CREATE TABLE `ratings` (
  `Rating_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID` int(11) DEFAULT NULL,
  `Schedule_ID` int(11) DEFAULT NULL,
  `Service` varchar(100) DEFAULT NULL,
  `Rating` int(11) NOT NULL,
  `Comment` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Rating_ID`),
  KEY `Owner_ID` (`Owner_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ratings` (`Rating_ID`, `Owner_ID`, `Schedule_ID`, `Service`, `Rating`, `Comment`) VALUES
(1, 1, 1, 'Wellness Care', 5, 'Fast scheduling and very attentive care from Dr. Camille Reyes.');

-- 12. Table: traffic_log
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

-- 13. Table: call_session & call_ice_candidate
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
  KEY `Call_ID` (`Call_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 14. Table: chat_messages
CREATE TABLE `chat_messages` (
  `Message_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Channel_Key` varchar(64) NOT NULL,
  `Booking_ID` int(11) DEFAULT NULL,
  `Schedule_ID` int(11) DEFAULT NULL,
  `Sender_Role` enum('owner','vet','admin','system') NOT NULL DEFAULT 'owner',
  `Sender_ID` int(11) DEFAULT NULL,
  `Sender_Name` varchar(100) NOT NULL,
  `Message_Text` text NOT NULL,
  `Message_Type` enum('text','system_alert','triage_notice','call_event') NOT NULL DEFAULT 'text',
  `Is_Read` tinyint(1) NOT NULL DEFAULT 0,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Message_ID`),
  KEY `Channel_Key` (`Channel_Key`),
  KEY `Booking_ID` (`Booking_ID`),
  KEY `Schedule_ID` (`Schedule_ID`),
  KEY `Created_At` (`Created_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `chat_messages` (`Message_ID`, `Channel_Key`, `Booking_ID`, `Sender_Role`, `Sender_ID`, `Sender_Name`, `Message_Text`, `Message_Type`, `Is_Read`) VALUES
(1, 'booking_1', 1, 'vet', 1, 'Dr. Camille Reyes', 'Mochi is stable in Emergency Trauma Bay 1. Antihistamine administered and swelling is subsiding.', 'text', 1),
(2, 'booking_1', 1, 'owner', 1, 'Leanne Chua', 'Thank you so much, Doctor. I am in the waiting area now.', 'text', 1);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
