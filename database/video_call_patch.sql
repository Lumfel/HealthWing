-- Adds the direct call / video call feature for emergency bookings.
-- Run once against your EXISTING veterinarydb.
-- (Already included in MASTER_DATABASE_PATCH.sql — only run one or the other.)

-- Per-emergency switch the vet controls: a client only ever sees a
-- "Call the vet"button when a vet has turned this on for that case.
ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled`tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled_By`int(11) DEFAULT NULL;
ALTER TABLE `emergency_booking`ADD COLUMN `Call_Enabled_At`timestamp NULL DEFAULT NULL;

-- Simple WebRTC signaling (offer/answer) over polling — same pattern
-- already used for the emergency alert toast, no extra server needed.
CREATE TABLE IF NOT EXISTS `call_session`(
  `Call_ID`int(11) NOT NULL AUTO_INCREMENT,
  `Booking_ID`int(11) NOT NULL,
  `Call_Type`enum('audio','video') NOT NULL DEFAULT 'video',
  `Initiated_By`enum('owner','vet') NOT NULL,
  `Veterinarian_ID`int(11) DEFAULT NULL,
  `Owner_ID`int(11) DEFAULT NULL,
  `Status`enum('ringing','active','declined','ended','missed') NOT NULL DEFAULT 'ringing',
  `Offer_SDP`longtext DEFAULT NULL,
  `Answer_SDP`longtext DEFAULT NULL,
  `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
  `Answered_At`timestamp NULL DEFAULT NULL,
  `Ended_At`timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Call_ID`),
  KEY `Booking_ID`(`Booking_ID`),
  KEY `Status`(`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ICE candidates exchanged between the two sides of a call.
CREATE TABLE IF NOT EXISTS `call_ice_candidate`(
  `Candidate_ID`int(11) NOT NULL AUTO_INCREMENT,
  `Call_ID`int(11) NOT NULL,
  `Sender`enum('owner','vet') NOT NULL,
  `Candidate`text NOT NULL,
  `Created_At`timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Candidate_ID`),
  KEY `Call_ID`(`Call_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
