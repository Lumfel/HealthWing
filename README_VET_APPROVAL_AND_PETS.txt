FIVE CHANGES IN THIS UPDATE
=============================

1. ONE ACTIVE EMERGENCY REQUEST PER PERSON
   submit_emergency.php now checks for an existing Pending/In Progress
   request before accepting a new one — by Owner_ID for logged-in
   owners, by IP address for guests (a new IP_Address column was added
   to emergency_booking to make this possible). If someone already has
   one open, they get a message asking them to wait, instead of being
   able to submit unlimited requests.

2. OWNER PET DASHBOARD (my_pets.php)
   New page, linked as "My Pets" next to "My Bookings" in the main nav.
   Shows each of the owner's pets as a card: photo (or a paw icon if
   none uploaded yet), name, age, species, description, current
   ailment, current medications, and past conditions. Owners can
   upload/change their pet's photo directly from the card — the upload
   endpoint checks that the pet actually belongs to them before
   accepting the photo, so one owner can't overwrite another family's
   pet picture just by guessing an ID.

3. VET PIN REMOVED — REPLACED WITH ADMIN APPROVAL
   The '12345' one-time code is gone entirely (it was never secure —
   anyone could read it in script.js's source). Clicking "I am a
   Veterinarian" now goes straight to the signup form. New vet accounts
   are created with Status = 'Pending' and cannot log in until an admin
   approves them from Manage Users (a Status column and Approve/Reject
   buttons were added there). Existing vet accounts were automatically
   marked 'Approved' by the SQL patch, so nobody currently able to log
   in loses access.
   Bonus fix while in that file: adminphp.php's create/update/delete
   actions were building raw SQL strings from form input (a real SQL
   injection risk) — rewritten to use prepared statements throughout.

4. VET NOTIFICATIONS FOR NEW EMERGENCIES
   vetindex.php now polls every 10 seconds for new emergency bookings.
   When one arrives, the vet sees a red alert banner + hears a short
   beep, and the page refreshes shortly after so the emergency table
   shows the new entry. Same honesty note as the admin version: this is
   10-second polling, not instant push — indistinguishable in practice
   for a demo, but worth knowing it's not a "true" push notification.

HOW TO INSTALL
   1. Run vet_approval_and_pets_patch.sql once against your EXISTING
      veterinarydb — it only adds columns and backfills existing vets
      as Approved, nothing is deleted.
   2. Copy the rest of the files over your project.
   3. Test: sign up a new (test) vet account, confirm it can't log in
      yet, approve it from Manage Users, then confirm it can log in.
   4. Test: submit an emergency request, then try submitting a second
      one immediately — the second should be blocked with a message.
   5. Test: with a vet logged into vetindex.php in one tab, submit an
      emergency request from another tab/browser — watch for the alert
      and beep within ~10 seconds.
