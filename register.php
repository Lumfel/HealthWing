<?php
require_once __DIR__ . '/includes/security_crypto.php';
require_once __DIR__ . '/includes/turnstile.php';
hw_start_secure_session();
$csrfToken = hw_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Owner Sign Up | HealthWing</title>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/pen.css"/>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css"/>
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
</head>
<body class="bg-gradient-to-r from-green-100 to-blue-100 min-h-screen flex items-center justify-center">
    <div class="login-container bg-white shadow-lg rounded-lg p-8">
        <div class="login-box">
            <h2 class="text-2xl font-bold text-gray-700 mb-6">Owner Sign Up</h2>
            <form id="signupOwnerForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="firstname" class="block text-gray-700 font-medium">First Name</label>
                <input type="text" id="firstname" name="firstname" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your first name" autocomplete="given-name" required />

                <label for="lastname" class="block text-gray-700 font-medium">Last Name</label>
                <input type="text" id="lastname" name="lastname" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your last name" autocomplete="family-name" required />

                <label for="email" class="block text-gray-700 font-medium">Email</label>
                <input type="email" id="email" name="email" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your email" autocomplete="email" required />

                <label for="username" class="block text-gray-700 font-medium">Username</label>
                <input type="text" id="username" name="username" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Choose a username" autocomplete="username" required />

                <label for="password" class="block text-gray-700 font-medium">Password</label>
                <input type="password" id="password" name="password" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Create a password" autocomplete="new-password" required />

                <label for="confirmPassword" class="block text-gray-700 font-medium">Confirm Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Confirm your password" autocomplete="new-password" required />

                <label for="age" class="block text-gray-700 font-medium">Age</label>
                <input type="number" id="age" name="age" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your age" required />

                <label for="address" class="block text-gray-700 font-medium">Address</label>
                <input type="text" id="address" name="address" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your address" autocomplete="street-address" required />

                <label for="contact" class="block text-gray-700 font-medium">Contact</label>
                <input type="text" id="contact" name="contact" class="border border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your contact number" autocomplete="tel" required />

                <div style="margin: 12px 0; padding: 10px 12px; background: #F4F9FA; border: 1px solid #D5E3E6; border-radius: 8px; font-size: 12.5px; color: #2B454A; text-align: left;">
                    <div style="font-weight: 700; margin-bottom: 4px; color: #1F3A3D;">Data Privacy Notice (RA 10173)</div>
                    <p style="margin: 0 0 8px 0; line-height: 1.45;">
                        HealthWing collects your personal details and pet clinical records solely to provide veterinary scheduling, triage, treatment, and billing services under the Philippine Data Privacy Act of 2012 (RA 10173). Sensitive medical data and 365-day owner receipt copies are encrypted with AES-256-GCM, and you may request access, correction, or account PII anonymization at any time.
                    </p>
                    <label for="privacy_consent" style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required style="margin-top: 2px; width: auto;" />
                        <span>I have read the Data Privacy Notice and give my informed consent to the processing of my personal and pet clinical data under RA 10173.</span>
                    </label>
                </div>

                <div style="margin: 10px 0; display: flex; justify-content: center;">
                    <?php echo hw_turnstile_render_widget('signup'); ?>
                </div>

                <button type="submit" class="login-btn w-full py-3 text-white text-lg rounded-lg bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B80] hover:to-[#FFE02380]">
                    Sign Up
                </button>
            </form>

            <p class="register-text">
                Already have an account?
                <a href="login.php">Sign In</a>
            </p>
        </div>
    </div>
    <script src="api/asset_proxy.php?f=assets/js/script.js" defer></script>
</body>
</html>
