DIRECT CALL / VIDEO CALL FEATURE + FORCING HTTPS — WHAT WAS ADDED
=====================================================================

1. THE CALL FEATURE — HOW IT WORKS
   Every emergency booking now has a per-case switch, off by default.
   A client can NEVER call a vet unless a vet has explicitly turned it
   on for that specific emergency.

   - As the vet: when a new emergency comes in, a popup now appears
     (instead of the old small toast in the corner) showing the name
     and details, with a checkbox: "Enable the direct/video call
     feature so this client can call you about this emergency." Check
     it, and a "Call Vet" button appears on that client's booking page
     within a few seconds. You can also flip this on/off later from
     the checkbox that sits under each row in the Emergency Bookings
     table, any time before the case is marked Completed/Cancelled.
   - As the client (pet owner): once a vet enables it, two buttons
     ("📹 Video" and "📞 Call") appear next to that emergency on your
     "My Appointments & Emergency Bookings" page. Tapping one rings
     the vet dashboard with an incoming-call popup they can Accept or
     Decline.
   - The vet can also call the client back directly from the
     Emergency Bookings table (📹 Video Call / 📞 Call buttons), any
     time, without needing the checkbox — the checkbox only gates
     whether the CLIENT gets a button to call the CLINIC.
   - Guest emergency requests (submitted without an account) don't get
     a call button, since there's no logged-in session for the vet to
     ring — the vet should use the phone number on the request instead.

2. HOW THE CALL ITSELF WORKS (BEING HONEST ABOUT THE ARCHITECTURE)
   This is real peer-to-peer audio/video using WebRTC (the same
   underlying browser technology behind Google Meet, Messenger video
   calls, etc.) — once connected, the actual video/audio stream goes
   directly between the two browsers, not through your PHP server.

   What DOES go through the server is "signaling" — the two sides
   introducing themselves to each other (call_start.php,
   call_signal.php, call_poll.php, call_end.php) — and it uses the
   same polling pattern already used for the emergency alert toast
   (checking every 1.5–3 seconds) rather than a dedicated
   WebSocket/socket server. For a class project on a local network or
   small Cloudflare Tunnel audience, this is indistinguishable from
   "instant" in practice.

   Known limitations, on purpose kept simple for this project's scale:
   - Only a STUN server is configured (Google's public one), no TURN
     server. This works for the vast majority of home/office networks,
     but a small number of very restrictive networks (some corporate
     firewalls, some mobile carriers) can block the direct
     peer-to-peer connection with no fallback. A TURN server is a
     bigger, paid piece of infrastructure that isn't worth adding for
     44 users.
   - One call at a time per browser tab.
   - No call history/recording — Completed/Cancelled cases still
     auto-delete after a day, same as before, and call_session rows
     ride along with that (they're small and tied to the booking).

3. NETWORK MONITOR LOGGING
   Every call-related event — a vet enabling/disabling the feature,
   a call starting, being answered, ending, being declined, or going
   unanswered — is now logged to the same traffic_log table your
   Network Monitor admin page already reads from. Nothing new to
   build there: open Network Monitor and you'll see entries like
   call_feature_enabled, call_initiated_video, call_answered,
   call_ended, call_missed, etc. alongside your existing login/signup/
   emergency-submission events.

4. FORCING HTTPS
   .htaccess now redirects every visitor to https automatically,
   EXCEPT when the site is reached as localhost or 127.0.0.1 (so your
   local Laragon testing over plain http keeps working). Once a
   request is over https, an HSTS header is also sent so returning
   browsers remember to use https next time without waiting for the
   redirect.

   Why this matters for the call feature specifically: browsers only
   allow camera/microphone access (getUserMedia) on a "secure
   context" — https, or localhost. Your Cloudflare Tunnel setup
   (from the earlier hosting guide) already terminates https for you
   automatically, so nothing else is needed there. Testing locally at
   http://localhost also just works, because browsers specifically
   carve out localhost as secure even without a certificate — you do
   NOT need to install a local SSL certificate to test video calls on
   your own machine.

   If you ever want a green padlock on http://localhost too (not
   required, just nice for demos), Laragon can generate one for you
   with one click: right-click the Laragon tray icon → Apache → SSL →
   your site → "Auto Create SSL Certificate", then Reload Apache.

   Also added: any PHP session cookie set through the new call-feature
   files now sets Secure/HttpOnly/SameSite=Lax automatically once the
   request is detected as https, so the session cookie itself is never
   sent back in plain http once you've gone live over https.

HOW TO INSTALL
   1. Run video_call_patch.sql once against your EXISTING veterinarydb
      (or ignore it and just run MASTER_DATABASE_PATCH.sql instead —
      it already includes this, so only run one or the other).
   2. Copy all the updated/added files over your project: call.js,
      call_common.php, call_start.php, call_signal.php, call_poll.php,
      call_end.php, toggle_call_feature.php, vetindex.php,
      owner_bookings.php, .htaccess.
   3. Open the vet dashboard and submit a test emergency (from a
      second browser/incognito window, logged in as a pet owner) to
      see the new popup and try the checkbox.
   4. From the owner's "My Appointments & Emergency Bookings" page,
      click "📹 Video" or "📞 Call" and accept the incoming call on the
      vet dashboard in the other window.
