THREE THINGS FIXED, ONE BIG FEATURE ADDED
=============================================

1. ROUTING BUG — FIXED (this explains the broken mobile screenshot)
   Every page uses relative paths like "vendor/flowbite/flowbite.min.css".
   That only resolves correctly if you're at the page's ACTUAL file
   location. Visiting a clean route like /vet made the browser think it
   was in a folder called "vet/", so it looked for "vet/vendor/..." —
   which doesn't exist. That's exactly what broke your mobile screenshot.
   Fix: added <base href="/"> (or <base href="/admin/"> for admin pages)
   to every page's <head>. This tells the browser to always resolve
   relative paths from the real root, no matter what clean URL is shown.

2. GLASS CARD MISALIGNMENT — FIXED
   The card position was only recalculated on window resize. A longer
   title (like "Preventing Illness in Pets...") wraps onto a 3rd line
   sometime AFTER the very first paint (web font finishing loading,
   etc.), and the shader kept drawing at the old, stale position — hence
   the disconnected dark rectangle in your screenshot. Fixed by watching
   the actual text box with a ResizeObserver (fires on ANY size change:
   font load, text wrap, orientation change) instead of only window
   resize. Tested with your exact vaccine-page title at both desktop and
   mobile widths — now tracks correctly at both.

3. "CANCEL DOESN'T DISAPPEAR" — explained + improved
   This was intentional in the original build (keep a visible record),
   but it wasn't communicated well. Cancelled/Completed rows now visibly
   dim (55% opacity) so they settle out of the way without vanishing —
   and per the new retention rule below, they now actually get deleted
   after a day anyway.

4. NEW: EMERGENCY CANCELLATION WORKFLOW
   - Owners can now Cancel their own emergency request directly within
     10 minutes of submitting it (owner_bookings.php).
   - After 10 minutes, cancelling instead sends a "cancellation request"
     that a vet or admin must review — protects against yanking a
     request the vet may already be responding to.
   - When a vet or admin cancels ANY request, a reason is now required
     (prompted before the action goes through) — this reason gets shown
     back to the owner automatically.
   - The owner sees that reason as a popup: a centered MODAL on phones,
     a corner PANEL on desktop (same content, different CSS at 768px+).
     Checked every 10 seconds — if they have the account open on two
     devices, both get it independently around the same time.
   - Resolved emergency bookings (Completed/Cancelled) are automatically
     deleted 1 day after being resolved — kept as a short record, not
     forever, matching the same pattern as the traffic log auto-expiry.

   NOTE ON SCOPE: this only covers logged-in owners. Guests (no account)
   currently have no page to view or cancel their own booking at all —
   giving them that would need something like an emailed magic link,
   which is a separate, bigger feature. Let me know if you want that
   built too.

HOW TO INSTALL
   1. Run emergency_cancel_patch.sql once against your EXISTING
      veterinarydb — only adds columns, doesn't touch existing data.
   2. Copy every file in this package over your project.
   3. Test: submit an emergency request as a logged-in owner, go to My
      Bookings, click Cancel within 10 minutes — should cancel instantly.
   4. Test: as a vet (or admin), try cancelling a DIFFERENT request —
      you should be blocked until you type a reason.
   5. Test: log back in as that owner and check My Bookings again within
      10 seconds — the reason should pop up as a modal (on a narrow
      browser window) or panel (wide window).
   6. Test the routing fix: visit /vet, /admin, /user on your phone —
      should now load fully styled, not broken like before.
