-- Adds real fields to `pet`and a proper checkup-history table.
-- Run this once against your EXISTING veterinarydb. Doesn't touch existing rows.

ALTER TABLE `pet`ADD COLUMN `Age`int(11) DEFAULT NULL;
ALTER TABLE `pet`ADD COLUMN `Species`varchar(50) DEFAULT NULL;
ALTER TABLE `pet`ADD COLUMN `Description`text DEFAULT NULL;
ALTER TABLE `pet`ADD COLUMN `Past_Conditions`text DEFAULT NULL;
ALTER TABLE `pet`ADD COLUMN `Current_Ailment`text DEFAULT NULL;
ALTER TABLE `pet`ADD COLUMN `Current_Medications`text DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `pet_checkup`(
  `Checkup_ID`int(11) NOT NULL AUTO_INCREMENT,
  `Pet_ID`int(11) NOT NULL,
  `Veterinarian_ID`int(11) DEFAULT NULL,
  `Checkup_Date`date NOT NULL,
  `Notes`text DEFAULT NULL,
  `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Checkup_ID`),
  KEY `Pet_ID`(`Pet_ID`),
  CONSTRAINT `pet_checkup_ibfk_1`FOREIGN KEY (`Pet_ID`) REFERENCES `pet`(`Pet_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
