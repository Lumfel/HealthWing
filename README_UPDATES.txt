THREE THINGS FIXED/BUILT IN THIS UPDATE
=========================================

1. EMERGENCY BOOKING NOW ACTUALLY WORKS (guest or logged in)
   The bug: the homepage's Emergency Booking form was posting to
   submit_booking.php — a handler built for routine appointments. It
   never touched the emergency_booking table at all, so submissions
   silently vanished no matter who submitted them.
   The fix: a new submit_emergency.php actually inserts into
   emergency_booking, handles the optional photo upload (saved to a
   new emergency_uploads/ folder), and correctly attaches the booking
   to a logged-in owner's account OR leaves it as a guest booking with
   just name/email/phone — both paths now work. The form also now
   shows a real success/error message instead of going nowhere.

2. VET-SIDE TABLES NOW MATCH THE ADMIN DESIGN SYSTEM
   vetindex.php's two tables (Emergency Bookings, Appointment Schedule)
   used ad-hoc gray-bordered HTML tables and different colored boxes
   than the admin dashboard. They now share the exact same dashboard.css
   classes as the admin panel — same stat cards, same status badges,
   same table style — so the whole app looks like one product instead
   of two different tools bolted together.
   Also fixed: the admin's Emergency Requests page used different
   status names (Accepted/Denied) than the vet page (In Progress/
   Cancelled) for the same data — they're now aligned to one vocabulary.

3. PET RECORDS — actually built out, not just a name and a species guess
   pet_records_patch.sql adds real fields to the `pet` table: Age,
   Species, Description, Past_Conditions, Current_Ailment,
   Current_Medications — plus a brand new pet_checkup table so a pet
   can have a full history of past visits, not just one snapshot.

   New admin page: Pet Records (in the sidebar, admin/pet-records.php)
     - Stat cards: total pets on file, how many distinct owners have a
       pet on record, and how many pets have never had a checkup logged
     - One row per pet — click the arrow to expand and see the full
       medical picture plus their checkup history
     - Add/edit pets through a form, log new checkups against any pet,
       delete outdated checkup entries

HOW TO INSTALL
   1. Back up your current project and database first.
   2. Run pet_records_patch.sql once against your EXISTING veterinarydb
      — it only adds columns/a table, doesn't touch existing data.
   3. Copy all the files in this package over your project.
   4. Test: submit an emergency booking while logged OUT, then again
      while logged IN as an owner — both should show a success message
      and appear in both vetindex.php's Emergency Bookings table and
      the admin's Emergency Requests page.
   5. Open the new Pet Records page from the admin sidebar and try
      adding a pet with a full profile, then add a checkup to it.
