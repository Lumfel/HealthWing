-- Run this once against your EXISTING veterinarydb — it only adds what's
-- missing (the admin login table). It does NOT touch or delete any of
-- your current data. Safe to re-run; CREATE TABLE IF NOT EXISTS skips
-- silently if the table is already there.

CREATE TABLE IF NOT EXISTS `admin`(
  `Admin_ID`int(11) NOT NULL AUTO_INCREMENT,
  `Username`varchar(50) NOT NULL,
  `Email`varchar(100) DEFAULT NULL,
  `Password`varchar(255) NOT NULL,
  `TimeAndDateAdded`timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Admin_ID`),
  UNIQUE KEY `Username`(`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ratings`(
  `Rating_ID`int(11) NOT NULL AUTO_INCREMENT,
  `Owner_ID`int(11) DEFAULT NULL,
  `Schedule_ID`int(11) DEFAULT NULL,
  `Service`varchar(100) DEFAULT NULL,
  `Rating`int(11) NOT NULL,
  `Comment`text DEFAULT NULL,
  `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Rating_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- If you already successfully ran a previous patch and these next two lines
-- error with "Duplicate column name", that's fine — it means you already
-- have them. Just skip that line.
ALTER TABLE `room`ADD COLUMN `Status`varchar(20) NOT NULL DEFAULT 'idle';
ALTER TABLE `room`ADD COLUMN `Room_Name`varchar(100) DEFAULT NULL;
