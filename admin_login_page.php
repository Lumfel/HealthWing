<?php
require_once __DIR__ . '/includes/security_crypto.php';
require_once __DIR__ . '/includes/turnstile.php';
hw_start_secure_session();

if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
    header('Location: admin/ADMIN_VET_1.php');
    exit;
}

$csrfToken = hw_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="api/asset_proxy.php?f=admin/dashboard.css">
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css">
    <title>Admin Login | HealthWing</title>
    <style>
        body.login-body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 30px 28px;
        }
        .login-card .brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }
        .login-card h1 {
            font-size: 18px;
            font-weight: 800;
            margin: 0;
        }
        .login-card p.sub {
            color: var(--text-muted);
            font-size: 13px;
            margin: 0 0 20px 0;
        }
        .field-group { margin-bottom: 14px; }
        .field-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }
        .field-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            background: var(--bg);
        }
        .field-group input:focus {
            outline: 2px solid var(--accent-soft);
            border-color: var(--accent);
        }
        .login-msg {
            font-size: 12.5px;
            margin-bottom: 12px;
            padding: 8px 10px;
            border-radius: 7px;
            display: none;
        }
        .login-msg.error { background: #FBE7EC; color: #B23F58; display: block; }
        .login-msg.success { background: #E7F5EC; color: #2E8659; display: block; }
        .toggle-row {
            text-align: center;
            margin-top: 16px;
            font-size: 12.5px;
            color: var(--text-muted);
        }
        .toggle-row a { color: var(--accent); text-decoration: none; font-weight: 600; cursor: pointer; }
        #setupForm { display: none; }
    </style>
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="admin-body login-body">
    <div class="login-card">
        <div class="brand-row">
            <img src="assets/images/logo.png" width="26" height="26" alt="HealthWing logo" style="border-radius:6px;" onerror="this.style.display='none'">
            <h1>HealthWing Admin</h1>
        </div>

        <div id="msg" class="login-msg"></div>

        <form id="loginForm">
            <p class="sub">Sign in to manage the clinic.</p>
            <div class="field-group">
                <label>Username</label>
                <input type="text" id="loginUsername" required autocomplete="username">
            </div>
            <div class="field-group">
                <label>Password</label>
                <input type="password" id="loginPassword" required autocomplete="current-password">
            </div>
            <div style="margin: 10px 0; display: flex; justify-content: center;">
                <?php echo hw_turnstile_render_widget('admin_login'); ?>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%; justify-content:center;">Sign In</button>
        </form>

        <form id="setupForm">
            <p class="sub">No admin account exists yet. Create the first one; this form disables itself once an account is created.</p>
            <div class="field-group">
                <label>Username</label>
                <input type="text" id="setupUsername" required>
            </div>
            <div class="field-group">
                <label>Email (optional)</label>
                <input type="email" id="setupEmail">
            </div>
            <div class="field-group">
                <label>Password (min 8 characters)</label>
                <input type="password" id="setupPassword" required minlength="8">
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%; justify-content:center;">Create Admin Account</button>
        </form>

        <div class="toggle-row">
            <span id="toggleToSetup">First time here? <a onclick="showSetup()">Create an admin account</a></span>
            <span id="toggleToLogin" style="display:none;">Already have an account? <a onclick="showLogin()">Sign in</a></span>
        </div>
        <div class="toggle-row" style="margin-top:8px;">
            <a href="login.php">User Sign In</a> &middot; <a href="index.php">Clinic Home</a>
        </div>
    </div>

<script>
const msg = document.getElementById('msg');
function showMsg(text, type) {
    msg.textContent = text;
    msg.className = 'login-msg ' + type;
}

function showSetup() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('setupForm').style.display = 'block';
    document.getElementById('toggleToSetup').style.display = 'none';
    document.getElementById('toggleToLogin').style.display = 'inline';
    msg.className = 'login-msg';
}
function showLogin() {
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('setupForm').style.display = 'none';
    document.getElementById('toggleToSetup').style.display = 'inline';
    document.getElementById('toggleToLogin').style.display = 'none';
    msg.className = 'login-msg';
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const cfInput = this.querySelector('[name="cf-turnstile-response"]');
    fetch('admin_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            username: document.getElementById('loginUsername').value,
            password: document.getElementById('loginPassword').value,
            cf_turnstile_response: cfInput ? cfInput.value : ''
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'admin/ADMIN_VET_1.php';
        } else {
            showMsg(data.message || 'Login failed.', 'error');
            if (window.turnstile && typeof window.turnstile.reset === 'function') {
                window.turnstile.reset();
            }
        }
    })
    .catch(() => showMsg('Something went wrong. Try again.', 'error'));
});

document.getElementById('setupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('admin_setup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            username: document.getElementById('setupUsername').value,
            email: document.getElementById('setupEmail').value,
            password: document.getElementById('setupPassword').value
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showMsg('Admin account created. You can sign in now.', 'success');
            showLogin();
        } else {
            showMsg(data.message || 'Could not create account.', 'error');
        }
    })
    .catch(() => showMsg('Something went wrong. Try again.', 'error'));
});
</script>
</body>
</html>
