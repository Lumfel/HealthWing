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
    <title>Veterinarian Sign Up | HealthWing</title>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/pen.css"/>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css"/>
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
</head>
<body class="bg-gradient-to-r from-green-100 to-blue-100 min-h-screen flex items-center justify-center">
    <div class="login-container bg-white shadow-lg rounded-lg p-8">
        <div class="login-box">
            <h2 class="text-2xl font-bold text-gray-700 mb-6">Veterinarian Sign Up</h2>
            <form id="signupVetForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="firstname" class="block text-gray-700 font-medium">First Name</label>
                <input type="text" id="firstname" name="firstname" placeholder="Enter your first name" autocomplete="given-name" required />

                <label for="lastname" class="block text-gray-700 font-medium">Last Name</label>
                <input type="text" id="lastname" name="lastname" placeholder="Enter your last name" autocomplete="family-name" required />

                <label for="email" class="block text-gray-700 font-medium">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" autocomplete="email" required />

                <label for="username" class="block text-gray-700 font-medium">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" autocomplete="username" required />

                <label for="password" class="block text-gray-700 font-medium">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" autocomplete="new-password" required />

                <label for="confirmPassword" class="block text-gray-700 font-medium">Confirm Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" autocomplete="new-password" required />

                <label for="age" class="block text-gray-700 font-medium">Age</label>
                <input type="number" id="age" name="age" placeholder="Enter your age" required />

                <label for="address" class="block text-gray-700 font-medium">Address</label>
                <input type="text" id="address" name="address" placeholder="Enter your address" autocomplete="street-address" required />

                <label for="contact" class="block text-gray-700 font-medium">Contact</label>
                <input type="text" id="contact" name="contact" placeholder="Enter your contact number" autocomplete="tel" required />

                <label for="specialties" class="block text-gray-700 font-medium">Clinical Specialties</label>
                <input type="text" id="specialties" name="specialties" placeholder="e.g., Surgery, Dental, Preventive Care" required />

                <label for="prc_license_no" class="block text-gray-700 font-medium">PRC License Number</label>
                <input type="text" id="prc_license_no" name="prc_license_no" placeholder="e.g., PRC-VET-005412" required />

                <div style="margin: 12px 0; padding: 10px 12px; background: #F4F9FA; border: 1px solid #D5E3E6; border-radius: 8px; font-size: 12.5px; color: #2B454A; text-align: left;">
                    <div style="font-weight: 700; margin-bottom: 4px; color: #1F3A3D;">Data Privacy Notice (RA 10173)</div>
                    <p style="margin: 0 0 8px 0; line-height: 1.45;">
                        HealthWing collects your personal details, clinical specialties, and PRC License Number solely for clinician credential verification, clinical accountability, and veterinary record auditing under the Philippine Data Privacy Act of 2012 (RA 10173). All clinical actions are logged for patient safety and regulatory compliance.
                    </p>
                    <label for="privacy_consent" style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required style="margin-top: 2px; width: auto;" />
                        <span>I have read the Data Privacy Notice and give my informed consent to the processing of my clinician account and PRC credential data under RA 10173.</span>
                    </label>
                </div>

                <div style="margin: 10px 0; display: flex; justify-content: center;">
                    <?php echo hw_turnstile_render_widget('signup'); ?>
                </div>

                <button type="submit" class="login-btn">Request Veterinarian Account</button>
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
