-- Run once against your EXISTING veterinarydb.

-- Tracks a client's request to cancel (needs vet/admin review after the
-- 10-minute grace period), the reason a vet/admin gives when they cancel
-- something, when a booking was resolved (for auto-cleanup), and whether
-- the client has already seen that reason (so it doesn't re-alert forever).
ALTER TABLE `emergency_booking`ADD COLUMN `Cancel_Requested`tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `emergency_booking`ADD COLUMN `Cancel_Reason`text DEFAULT NULL;
ALTER TABLE `emergency_booking`ADD COLUMN `Resolved_At`timestamp NULL DEFAULT NULL;
ALTER TABLE `emergency_booking`ADD COLUMN `Client_Notified`tinyint(1) NOT NULL DEFAULT 1;
