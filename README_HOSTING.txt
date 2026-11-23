HOSTING VETSYSTEM FOR 44 PEOPLE — SETUP GUIDE
================================================

WHAT WAS BUILT IN THIS UPDATE
------------------------------
1. Clean URLs (.htaccess)
   You can now use friendlier links alongside the old ones — nothing
   old breaks, these are just nicer aliases:
     /user            -> owner bookings page
     /user/book       -> appointment booking page
     /vet             -> vet dashboard
     /admin           -> admin panel
     /signin, /signup -> the sign in / sign up pages
     /about, /contact
     /services/dental, /services/surgery, /services/vaccination,
     /services/wellness, /services/grooming, /services/parasite

   This needs Apache's "AllowOverride All" to be active for your
   project folder. Laragon usually has this on by default. To check:
   if the clean URLs above give a 404, open Laragon's Apache config
   for your site and make sure AllowOverride is set to All, then
   restart Apache.

2. Caching + compression (also in .htaccess)
   Images, CSS, and JS are now cached in each visitor's browser for
   days, and text responses are compressed before sending. Repeat
   page loads are much faster, and your server does less work per
   visitor.

3. Rate limiting (rate_limit.php)
   Login, signup, admin login, and emergency booking now block an IP
   address temporarily if it sends too many requests too fast (e.g.
   more than 10 login attempts in a minute). This stops someone from
   spamming your forms or brute-forcing a password. It needs no
   database or extra software — just small tracking files it creates
   itself in a new rate_limit_data/ folder (already blocked from
   public access in .htaccess).

4. A few extra .htaccess protections
   - Your .sql files can no longer be downloaded by URL
   - Folder browsing is disabled (nobody can see a raw file listing)
   - Uploaded emergency photos can never be executed as code, even if
     someone tried to disguise a script as an image


HOW TO ACTUALLY GO PUBLIC — TWO OPTIONS
------------------------------------------

OPTION A (recommended): Cloudflare Tunnel — no port forwarding at all
  Why this is better for you specifically: you don't touch your
  router's settings, your home IP address is never exposed, and
  Cloudflare's network sits in front of your site absorbing traffic
  spikes and basic abuse before it ever reaches your PC.

  1. Go to the Cloudflare Zero Trust dashboard (free account) and
     create a Tunnel — it walks you through installing a small program
     (cloudflared) on your PC.
  2. Point the tunnel at http://localhost (your Laragon site).
  3. Cloudflare gives you a public URL. Share that with your 44
     people — nothing on your router changes at all.

OPTION B: Port forwarding on your router (only if you really want this)
  1. In your router settings, forward port 80 (and 443 if you set up
     HTTPS) to your PC's LOCAL IP address only — not your router's
     own admin interface.
  2. Make sure "Remote Management" / "Remote Administration" is OFF
     in your router settings — that's the actual setting that would
     expose your router's login page to the internet, and it's
     unrelated to forwarding a port to your PC.
  3. Change your router's admin password from the default if you
     haven't already.
  4. Turn on Windows Firewall and only allow the specific port you're
     forwarding.
  5. Share your public IP address (check "what is my ip" while
     connected) with your 44 people. Turn port forwarding back off
     once you're done presenting.


A HONEST NOTE ON SCALE
------------------------
44 people is genuinely not a "traffic problem" for a normal PC — this
setup is already more protection than that number of users actually
requires. The rate limiting and caching here are good practice
regardless, but you don't need to worry about your server "going
down" from 44 people clicking around.
