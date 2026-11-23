NETWORK MONITOR, ALERTS & CACHING — WHAT WAS ADDED
=====================================================

1. RATE-LIMIT ALERTS WITH IP REVEALED
   Every time the rate limiter allows or blocks a request, it now logs
   the event (IP address, which form, allowed/blocked, timestamp) to a
   new traffic_log table. The admin's new Network Monitor page polls
   for new events every 10 seconds — when a new blocked attempt shows
   up, you get an on-screen alert with a short beep, showing exactly
   which IP got blocked and on which form.

   Being honest about what this is: it's polling (checking every 10
   seconds), not a true instant push notification like a phone alert.
   For a class project this is indistinguishable in practice — you'll
   see it within 10 seconds — but a "real" push notification system
   (email, SMS, etc.) would need a mail server or SMS API, which is a
   much bigger addition for not much practical benefit here.

2. "NETWORK TAB" — WHAT IT ACTUALLY IS
   A literal packet-level network monitor (like your browser's DevTools
   Network tab, or Wireshark) needs system-level access that a PHP web
   app simply doesn't have — that's watching your computer's network
   card, not your website's code.
   What IS realistic and was built: every login, signup, admin login,
   and emergency booking request is now logged with IP, endpoint,
   status, and time — viewable live on the new Network Monitor admin
   page. This is genuinely useful security-monitoring data, just
   scoped to your application's own traffic rather than your whole PC's
   network activity.

3. CACHING
   - Browser caching for images/CSS/JS (from the earlier hosting
     update) is already in place.
   - NEW: the admin Overview dashboard's analytics query now caches its
     result for 20 seconds in a file (cache_dashboard_data.json,
     auto-created, blocked from public access). If you refresh the
     dashboard repeatedly, most of those refreshes now skip the
     database entirely.
   - NEW: the owners dropdown on Pet Records is cached in the browser
     (sessionStorage) after the first load, so switching between pages
     doesn't re-fetch the same list over and over. It refreshes
     automatically next time you log in.

4. REVERSE PROXY — RECOMMENDATION, NOT BUILT
   Nothing to install here: if you go with Cloudflare Tunnel (from the
   earlier hosting guide), Cloudflare already functions as a reverse
   proxy in front of your site, with its own caching layer included,
   for free. Adding a second, separate reverse proxy (like Nginx) on
   top of that — or in place of it, running locally in front of your
   Apache — would add a second piece of software to maintain without a
   real benefit at 44 users. If you want to learn how one works someday
   as its own exercise, that's a great thing to try later, just not
   something worth adding to this project right now.


HOW TO INSTALL
   1. Run traffic_log_patch.sql once against your EXISTING veterinarydb.
   2. Copy all the updated files over your project (rate_limit.php,
      the admin/ folder additions, .htaccess).
   3. Open the new "Network Monitor" page from the admin sidebar.
   4. Trigger the rate limiter again (the console script from before)
      and watch the alert appear on that page within ~10 seconds.
