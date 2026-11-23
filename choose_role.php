<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
$csrfToken = hw_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Choose Role | HealthWing</title>
    <script src="api/asset_proxy.php?f=vendor/tailwind/tailwind-browser.js"></script>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/pen.css"/>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css"/>
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
</head>
<body class="bg-gradient-to-r from-green-100 to-blue-100 min-h-screen flex items-center justify-center">
    <div class="role-container bg-white shadow-2xl rounded-2xl p-8 w-full max-w-3xl h-[400px] border-8 border-gray-300 flex flex-col items-center justify-center">
        <h2 class="text-3xl font-bold text-gray-700 mb-10 text-center">Choose Your Role</h2>
        <div class="flex flex-row gap-12 justify-center items-center w-full">
            <a href="register.php" class="flex flex-col items-center justify-center py-6 px-10 w-56 h-56 bg-gradient-to-r from-pink-500 to-yellow-400 text-white text-xl font-bold rounded-xl shadow-lg hover:from-pink-600 hover:to-yellow-500 transition-all">
                <img src="assets/images/owner.png" alt="Owner Icon" class="mx-auto mb-4 w-16 h-16">
                <span class="text-center">I am an Owner</span>
            </a>

            <a href="register_vet.php" class="flex flex-col items-center justify-center py-6 px-10 w-56 h-56 bg-gradient-to-r from-pink-500 to-yellow-400 text-white text-xl font-bold rounded-xl shadow-lg hover:from-pink-600 hover:to-yellow-500 transition-all">
                <img src="assets/images/veterinarian.png" alt="Veterinarian Icon" class="mx-auto mb-4 w-16 h-16">
                <span class="text-center">I am a Veterinarian</span>
            </a>
        </div>
        <p class="register-text mt-6"><a href="login.php">Already have an account? Sign In</a></p>
    </div>
</body>
</html>
