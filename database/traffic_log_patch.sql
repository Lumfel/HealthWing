-- Adds a table to track rate-limit events (allowed + blocked requests)
-- for the admin's new Network Monitor page. Run once against veterinarydb.

CREATE TABLE IF NOT EXISTS `traffic_log`(
  `Log_ID`int(11) NOT NULL AUTO_INCREMENT,
  `IP_Address`varchar(64) NOT NULL,
  `Action`varchar(50) NOT NULL,
  `Status`enum('allowed','blocked') NOT NULL DEFAULT 'allowed',
  `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Log_ID`),
  KEY `Created_At`(`Created_At`),
  KEY `Status`(`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
