<?php
require_once __DIR__ . '/includes/security_crypto.php';
require_once __DIR__ . '/includes/turnstile.php';
hw_start_secure_session();

if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    if (hw_is_head_vet()) {
        header('Location: admin/ADMIN_VET_1.php');
        exit;
    }
    if (hw_is_vet_staff()) {
        header('Location: vetindex.php');
        exit;
    }
    header('Location: index.php');
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
    <title>HealthWing | Sign In</title>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/CS.css">
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css">
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
</head>
<body>

    <div class="login-container">
        <div class="login-box">
            <h2>Sign In to HealthWing</h2>
            <form id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter username or email" autocomplete="username" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your password" autocomplete="current-password" required>
                </div>
                <div class="remember-me-container" style="margin: 10px 0;">
                    <input type="checkbox" id="rememberMe" checked>
                    <label for="rememberMe">Remember me</label>
                </div>
                <div style="margin: 10px 0; display: flex; justify-content: center;">
                    <?php echo hw_turnstile_render_widget('login'); ?>
                </div>
                <button type="submit" class="login-btn">Sign In</button>
            </form>
            <p class="register-text">Don't have an account? <a href="choose_role.php">Register</a></p>
        </div>
    </div>

    <script src="api/asset_proxy.php?f=assets/js/JS.js" defer></script>

</body>
</html>
