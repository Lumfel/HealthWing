THREE THINGS FIXED/ADDED THIS ROUND
======================================

1. EMERGENCY BOOKING LOGIC — FIXED (the real root cause)
   The bug wasn't actually in the front-end auto-fill logic — that part
   was already working correctly. The real problem was in
   submit_emergency.php itself: it ALWAYS required name/email/phone from
   the submitted form, no matter what. So if a logged-in owner's browser
   ever failed to auto-fill those hidden fields for any reason (a slow
   network, a timing hiccup), submission failed with "Name, email, and
   phone are required" — even though the server already knew exactly who
   they were from their session.

   Fixed: the server now looks up the logged-in owner's name/email/phone
   directly from their account when Owner_ID is known, and only requires
   the form fields at all when nobody is logged in (a true guest). This
   removes the fragile dependency on the client-side auto-fill entirely.

2. VET-SIDE UI — TWO FIXES
   - Every "Cancel"/destructive button across the admin panel (Cancel,
     Delete, Reject) was a white button with red text/outline — subtle,
     easy to miss as a serious action. Fixed in one place
     (admin/dashboard.css's .btn-danger) so it's now solid red site-wide.
   - On the vet's own emergency table, the "Update" button was always
     blue, even when "Cancelled" was selected in the dropdown — no visual
     warning before confirming a cancellation. It now turns red the
     moment "Cancelled" is selected, before the vet even clicks Update.

3. NEW: "TALK TO A VET RIGHT NOW" ON THE CONTACT PAGE
   Logged-in owners now see a card on the Contact page with Video Call
   and Voice Call buttons — no emergency booking required first. Clicking
   one creates a lightweight booking behind the scenes (Status = 'Contact',
   calling auto-enabled immediately, since the owner asked for this
   themselves) and starts the call using the same WebRTC system as
   before. Any vet currently viewing their dashboard will see it ring in,
   exactly like a real emergency call — this doubles as your easiest way
   to test the call feature without filing a fake emergency each time.

   These "Contact" entries are visually marked (💬 Direct contact request
   — not an emergency) and excluded from the Pending/urgent stat counts,
   so they won't get confused with real emergencies on the vet dashboard.

HOW TO INSTALL
   No new database changes needed for this round — everything reuses
   tables already in place from the video call feature. Just copy the
   files in this package over your project.

   Test order:
   1. Emergency form: submit while logged out (guest) — should require
      name/email/phone as before. Submit while logged in as an owner —
      should go through with only Additional Details filled in.
   2. Admin panel: any Cancel/Delete button should now look solidly red.
   3. Vet dashboard: select "Cancelled" in any emergency's dropdown —
      the Update button should immediately turn red.
   4. Contact page: log in as an owner, go to Contact, click Video Call —
      open vetindex.php as a vet in another browser/tab and confirm the
      incoming call modal appears.
