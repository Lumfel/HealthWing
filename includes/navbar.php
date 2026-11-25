<?php
/**
 * HealthWing / VetSystem — Unified 3-Role Navigation Bar (includes/navbar.php)
 */
require_once __DIR__ . '/security_crypto.php';
hw_start_secure_session();

$hwIsLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);
$hwUsername = $hwIsLoggedIn ? $_SESSION['username'] : null;
$hwRole = hw_session_role();
$hwIsHeadVet = hw_is_head_vet();
$hwIsVet = hw_is_vet_role();
$hwIsOwner = hw_is_owner();
$hwCurrentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$hwCsrfToken = hw_csrf_token();

if (!function_exists('hw_nav_active')) {
    function hw_nav_active(array $pages, string $current): string
    {
        return in_array($current, $pages, true)
            ? 'background:rgba(255,255,255,0.22);color:#fff;font-weight:700;'
            : 'color:rgba(255,255,255,0.92);';
    }
}
?>
<meta name="hw-csrf-token" content="<?php echo htmlspecialchars($hwCsrfToken, ENT_QUOTES, 'UTF-8'); ?>"/>
<link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css"/>
<script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>

<header class="hw-site-header" style="background:linear-gradient(90deg,#1F3A3D 0%,#2B5864 60%,#3B7D91 100%);color:#fff;position:sticky;top:0;z-index:1000;box-shadow:0 4px 20px rgba(15,35,42,0.18);font-family:system-ui,-apple-system,sans-serif;">
  <div style="max-width:1320px;margin:0 auto;padding:10px 16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
    <div style="display:flex;align-items:center;justify-content:space-between;width:auto;gap:12px;" class="hw-brand-bar">
      <a href="index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:#fff;">
        <img src="assets/images/logo.png" alt="HealthWing" style="width:38px;height:38px;border-radius:10px;object-fit:contain;background:#fff;padding:3px;"/>
        <div>
          <div style="font-size:18px;font-weight:800;letter-spacing:-0.01em;line-height:1.1;display:flex;align-items:center;gap:6px;">
            <span>HealthWing</span>
            <span style="font-size:10px;background:rgba(76,175,125,0.28);border:1px solid rgba(110,231,183,0.5);color:#A7F3D0;padding:1px 6px;border-radius:999px;font-weight:700;" title="AES-256-GCM Encrypted Cookies and Cache Active">AES-256-GCM</span>
          </div>
          <div style="font-size:11px;color:#B8E1EC;font-weight:600;">Smart Veterinary Clinic &amp; Triage</div>
        </div>
      </a>

      <button type="button" id="hwMobileMenuBtn" class="hw-mobile-only-btn" aria-label="Toggle Menu"
              onclick="document.getElementById('hwCollapsibleNav').classList.toggle('hw-nav-open')"
              style="display:none;background:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.28);color:#fff;border-radius:10px;padding:8px 12px;font-size:14px;font-weight:800;cursor:pointer;">
        Menu
      </button>
    </div>

    <div id="hwCollapsibleNav" class="hw-collapsible-nav" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;flex:1;">
      <nav style="display:flex;flex-wrap:wrap;align-items:center;gap:5px;font-size:13.5px;margin:0 auto;">
        <a href="index.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['index.php'], $hwCurrentPage); ?>">Home</a>
        <a href="services.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['services.php'], $hwCurrentPage); ?>">Services</a>
        <a href="public_calendar.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['public_calendar.php'], $hwCurrentPage); ?>">Public Calendar</a>

        <?php if ($hwIsVet): ?>
          <a href="vetindex.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['vetindex.php'], $hwCurrentPage); ?>">Vet Workstation</a>
        <?php else: ?>
          <a href="appointment_schedule.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['appointment_schedule.php'], $hwCurrentPage); ?>">+ Book Appointment</a>
        <?php endif; ?>

        <?php if ($hwIsOwner): ?>
          <a href="owner_bookings.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['owner_bookings.php'], $hwCurrentPage); ?>">My Bookings</a>
          <a href="my_pets.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['my_pets.php'], $hwCurrentPage); ?>">My Pets</a>
        <?php endif; ?>

        <?php if ($hwIsLoggedIn): ?>
          <a href="receipts.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['receipts.php'], $hwCurrentPage); ?>">Receipts</a>
        <?php endif; ?>

        <a href="AboutUs.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['AboutUs.php'], $hwCurrentPage); ?>">About</a>
        <a href="contact.php" style="padding:8px 12px;border-radius:8px;text-decoration:none;transition:0.15s;<?php echo hw_nav_active(['contact.php'], $hwCurrentPage); ?>">Contact</a>

        <?php if ($hwIsHeadVet): ?>
          <a href="vetindex.php?tab=governance" style="padding:8px 12px;border-radius:8px;text-decoration:none;background:#E3A857;color:#16262A;font-weight:700;">Head Vet Console</a>
        <?php endif; ?>
      </nav>

      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;" class="hw-user-strip">
        <a href="index.php#emergency-booking" style="background:linear-gradient(135deg,#D8546F,#E3A857);color:#fff;text-decoration:none;padding:8px 13px;border-radius:999px;font-size:12.5px;font-weight:700;box-shadow:0 4px 12px rgba(216,84,111,0.35);">
          Emergency Triage
        </a>

        <?php if ($hwIsLoggedIn): ?>
          <span style="background:rgba(255,255,255,0.14);padding:6px 11px;border-radius:999px;font-size:12px;font-weight:600;">
            <?php echo $hwIsVet ? 'Dr. ' : ''; ?><?php echo htmlspecialchars((string)$hwUsername, ENT_QUOTES, 'UTF-8'); ?>
          </span>
          <form action="logout.php" method="post" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hwCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" style="background:#D8546F;color:#fff;border:none;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Logout</button>
          </form>
        <?php else: ?>
          <a href="login.php" style="background:#DBE48C;color:#16262A;text-decoration:none;padding:8px 13px;border-radius:8px;font-size:12.5px;font-weight:700;">Sign In</a>
          <a href="choose_role.php" style="border:1px solid rgba(255,255,255,0.5);color:#fff;text-decoration:none;padding:7px 12px;border-radius:8px;font-size:12.5px;font-weight:600;">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<nav id="hwMobileBottomDock" class="hw-mobile-bottom-dock" aria-label="Mobile Quick Navigation">
  <?php if ($hwIsVet): ?>
    <a href="vetindex.php" class="hw-dock-item <?php echo $hwCurrentPage === 'vetindex.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Triage</span>
    </a>
    <a href="public_calendar.php" class="hw-dock-item <?php echo $hwCurrentPage === 'public_calendar.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Calendar</span>
    </a>
    <a href="vetindex.php#vet-tab-pets" onclick="if(typeof switchVetTab==='function'){switchVetTab('pets', document.querySelectorAll('.tab-btn')[2]);return false;}" class="hw-dock-item">
      <span class="hw-dock-icon"></span><span>Charts</span>
    </a>
    <a href="receipts.php" class="hw-dock-item <?php echo $hwCurrentPage === 'receipts.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Receipts</span>
    </a>
    <button type="button" onclick="if(window.HWComm)HWComm.toggleChat();" class="hw-dock-item" style="background:none;border:none;cursor:pointer;">
      <span class="hw-dock-icon"></span><span>Comm</span>
    </button>
  <?php else: ?>
    <a href="index.php" class="hw-dock-item <?php echo $hwCurrentPage === 'index.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Home</span>
    </a>
    <a href="public_calendar.php" class="hw-dock-item <?php echo $hwCurrentPage === 'public_calendar.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Calendar</span>
    </a>
    <a href="appointment_schedule.php" class="hw-dock-item <?php echo $hwCurrentPage === 'appointment_schedule.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon">+</span><span>Book</span>
    </a>
    <a href="owner_bookings.php" class="hw-dock-item <?php echo $hwCurrentPage === 'owner_bookings.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span>Bookings</span>
    </a>
    <a href="<?php echo $hwIsLoggedIn ? 'receipts.php' : 'login.php'; ?>" class="hw-dock-item <?php echo $hwCurrentPage === 'receipts.php' ? 'active' : ''; ?>">
      <span class="hw-dock-icon"></span><span><?php echo $hwIsLoggedIn ? 'Receipts' : 'Sign In'; ?></span>
    </a>
  <?php endif; ?>
</nav>

<script src="api/asset_proxy.php?f=assets/js/call.js" defer></script>
