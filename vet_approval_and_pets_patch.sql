-- Run once against your EXISTING veterinarydb.

-- 1. Vet accounts now need admin approval before they can log in.
--    Existing vet accounts (already trusted) are auto-approved below.
ALTER TABLE `veterinarian`ADD COLUMN `Status`varchar(20) NOT NULL DEFAULT 'Pending';
UPDATE `veterinarian`SET `Status`= 'Approved'WHERE `Status`= 'Pending';

-- 2. Pets can now have an optional photo for the owner's dashboard cards.
ALTER TABLE `pet`ADD COLUMN `Photo_Path`varchar(255) DEFAULT NULL;

-- 3. Emergency bookings now track the submitter's IP, so guests (no
--    account) can still be limited to one active request at a time.
ALTER TABLE `emergency_booking`ADD COLUMN `IP_Address`varchar(64) DEFAULT NULL;
