<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);
$username = $isLoggedIn ? $_SESSION['username'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/">
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <title>Contact & Direct Video Consult | HealthWing</title>
    <link href="vendor/flowbite/flowbite.min.css" rel="stylesheet"/>
    <script src="vendor/tailwind/tailwind-browser.js"></script>
</head>
<body class="bg-[#F4F9FA] text-[#16262A]">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="py-12 max-w-5xl mx-auto px-6">
    <h1 class="text-3xl font-extrabold text-center mb-3 text-gray-800">Get in Touch & Direct Vet Consult</h1>
    <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
        Reach our veterinary team directly via WebSocket Live Chat, WebRTC Video/Voice Call, or visit our Bacolod clinic.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center text-center">
            <img src="images/location.png" alt="Address Icon" class="w-14 h-14 mb-3">
            <h3 class="text-lg font-bold mb-1 text-gray-800">Clinic Address</h3>
            <p class="text-sm text-gray-600">Along Circumferential Road, Bacolod City</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center text-center">
            <img src="images/phone.png" alt="Phone Icon" class="w-14 h-14 mb-3">
            <h3 class="text-lg font-bold mb-1 text-gray-800">Emergency Hotline</h3>
            <p class="text-sm text-gray-600">(00)-0000-0000</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center text-center">
            <img src="images/gmail.png" alt="Email Icon" class="w-14 h-14 mb-3">
            <h3 class="text-lg font-bold mb-1 text-gray-800">Email</h3>
            <p class="text-sm text-gray-600">contact@healthwingclinic.com</p>
        </div>
    </div>

    <?php if ($isLoggedIn && ($_SESSION['role'] ?? '') === 'owner'): ?>
    <div class="max-w-xl mx-auto mb-10">
        <div class="bg-white rounded-2xl shadow-md p-6 text-center border-2 border-[#4A9DBB]">
            <h3 class="text-xl font-bold text-gray-800 mb-2">Direct WebSocket Video, Voice & Chat with a Vet</h3>
            <p class="text-sm text-gray-600 mb-4">
                Signed in as an owner? Connect with an available veterinarian immediately by video, voice, or real-time chat.
            </p>
            <div id="contactCallMsg" class="hidden mb-3 text-sm p-2 rounded-lg"></div>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" onclick="startContactCall('video')" class="px-5 py-2.5 bg-[#4A9DBB] text-white rounded-lg font-bold hover:opacity-90">Video Call</button>
                <button type="button" onclick="startContactCall('audio')" class="px-5 py-2.5 bg-[#2B4E52] text-white rounded-lg font-bold hover:opacity-90">Voice Call</button>
                <button type="button" onclick="HWComm.openChat()" class="px-5 py-2.5 bg-[#1F3A3D] text-white rounded-lg font-bold hover:opacity-90">Open Live Chat</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function startContactCall(callType) {
    const msg = document.getElementById('contactCallMsg');
    msg.classList.remove('hidden', 'bg-red-100', 'text-red-700');
    msg.classList.add('bg-blue-100', 'text-blue-700');
    msg.textContent = 'Connecting to on-call veterinarian...';

    fetch('start_contact_call.php', { method: 'POST'})
        .then(res => res.json())
        .then(data =>{
            if (!data.success) {
                msg.classList.remove('bg-blue-100', 'text-blue-700');
                msg.classList.add('bg-red-100', 'text-red-700');
                msg.textContent = data.message || 'Could not start the call.';
                return;
            }
            msg.classList.add('hidden');
            HWCall.startCall(data.bookingId, callType, data.callerName || 'the Veterinarian');
        })
        .catch(() =>{
            msg.classList.remove('bg-blue-100', 'text-blue-700');
            msg.classList.add('bg-red-100', 'text-red-700');
            msg.textContent = 'Could not reach the server. Please try again.';
        });
}
</script>
</body>
</html>