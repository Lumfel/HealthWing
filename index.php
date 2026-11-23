<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);
$username = $isLoggedIn ? $_SESSION['username'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <title>HealthWing | Smart Veterinary Clinic & Priority Triage</title>
    <link href="api/asset_proxy.php?f=vendor/flowbite/flowbite.min.css" rel="stylesheet"/>
    <link href="api/asset_proxy.php?f=vendor/swiper/swiper-bundle.min.css" rel="stylesheet"/>
    <script src="api/asset_proxy.php?f=vendor/tailwind/tailwind-browser.js"></script>
    <script src="api/asset_proxy.php?f=vendor/swiper/swiper-bundle.min.js"></script>
</head>
<body class="bg-[#F4F9FA] text-[#16262A]">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- Hero Section -->
<div class="relative w-full h-[420px] bg-cover bg-center flex items-center" style="background-image: url('assets/images/body.jpg');">
    <div class="absolute inset-0 bg-gradient-to-r from-[#16262A]/80 via-[#16262A]/45 to-transparent"></div>
    <div class="relative max-w-6xl mx-auto px-6 w-full">
        <div class="max-w-xl text-white">
            <span class="inline-block bg-[#4A9DBB] text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full mb-3">
                Automated Scheduling · Live Triage · Direct Video & Chat
            </span>
            <h1 class="text-4xl md:text-5xl font-extrabold leading-tight">Expert Veterinary Care for Your Pets</h1>
            <p class="text-base md:text-lg text-gray-100 mt-3">
                Smart conflict-free appointment scheduling, priority-driven emergency triage, and real-time WebSocket video & chat with our veterinarians.
            </p>
            <div class="flex flex-wrap gap-3 mt-6">
                <a href="appointment_schedule.php" class="px-6 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-[#4A9DBB] to-[#2B5864] shadow-lg hover:opacity-95 transition">
                    +  Book Smart Appointment
                </a>
                <a href="public_calendar.php" class="px-5 py-3 rounded-xl font-bold bg-white text-[#16262A] shadow hover:bg-gray-100 transition">
                     View Public Calendar
                </a>
                <a href="#emergency-booking" class="px-5 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-[#DA4A6B] to-[#E3A857] shadow hover:opacity-95 transition">
                     Emergency Triage
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Role & Feature Highlights -->
<div class="max-w-6xl mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-3 gap-6">
    <a href="public_calendar.php" class="block bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
        <div class="text-2xl mb-2"></div>
        <h2 class="text-lg font-bold text-gray-800">Privacy-Safe Public Calendar</h2>
        <p class="text-sm text-gray-600 mt-1">Check all booked dates, times, and live clinic room availability without exposing any private client or pet details.</p>
    </a>
    <a href="appointment_schedule.php" class="block bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
        <div class="text-2xl mb-2"></div>
        <h2 class="text-lg font-bold text-gray-800">Automated Smart Scheduling</h2>
        <p class="text-sm text-gray-600 mt-1">Our scheduling algorithm automatically matches your pet's service with the right specialist veterinarian and clinic room.</p>
    </a>
    <a href="contact.php" class="block bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
        <div class="text-2xl mb-2"></div>
        <h2 class="text-lg font-bold text-gray-800">WebSocket Video & Live Chat</h2>
        <p class="text-sm text-gray-600 mt-1">Direct peer-to-peer video/voice consults and instant real-time chat with your veterinarian from any page.</p>
    </a>
</div>

<!-- Services Grid -->
<div class="py-10 bg-cover bg-center" style="background-image: url('assets/images/background.jpg');">
    <h2 class="text-center text-3xl font-bold mb-6 text-white drop-shadow">Our Clinical Services</h2>
    <div class="max-w-6xl mx-auto px-5">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <a href="services.php?service=dental" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/Dental.svg" alt="Dental">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">DENTAL CARE (60 MIN)</p>
                    </div>
                </div>
            </a>
            <a href="services.php?service=surgery" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/Surgery.svg" alt="Surgery">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">SURGERY (90 MIN)</p>
                    </div>
                </div>
            </a>
            <a href="services.php?service=vaccination" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/Vaccination.svg" alt="Vaccination">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">VACCINATION (30 MIN)</p>
                    </div>
                </div>
            </a>
            <a href="services.php?service=wellness" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/Wellness.svg" alt="Wellness">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">WELLNESS CARE (30 MIN)</p>
                    </div>
                </div>
            </a>
            <a href="services.php?service=grooming" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/services.svg" alt="Grooming">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">GROOMING (60 MIN)</p>
                    </div>
                </div>
            </a>
            <a href="services.php?service=parasite" class="block">
                <div class="shadow-lg rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 bg-white bg-opacity-95 p-3">
                    <img class="h-52 mx-auto" src="assets/icons/Parasite.svg" alt="Parasite Control">
                    <div class="w-full bg-[#1F3A3D] text-center py-3 rounded-lg mt-2">
                        <p class="text-sm font-bold text-white">PARASITE CONTROL (30 MIN)</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Hours of Operation -->
<div class="bg-white py-10">
    <h2 class="text-center text-3xl font-bold text-gray-800 mb-5">Hours of Operation</h2>
    <div class="max-w-lg mx-auto bg-[#1F3A3D] text-white rounded-xl p-6 shadow-md">
        <ul class="space-y-2 text-base">
            <li class="flex justify-between"><span>Monday – Friday</span><span>8:00 AM – 6:00 PM</span></li>
            <li class="flex justify-between"><span>Saturday</span><span>9:00 AM – 3:00 PM</span></li>
            <li class="flex justify-between"><span>Sunday</span><span>Closed (Emergency Triage Only)</span></li>
        </ul>
    </div>
</div>

<!-- Emergency Booking Section -->
<div id="emergency-booking" class="bg-white border border-gray-200 p-8 rounded-2xl shadow-md my-10 mx-auto max-w-3xl">
    <h3 class="text-2xl font-bold text-gray-800 text-center mb-2">Emergency Booking & Priority Triage</h3>
    <p class="text-center text-sm text-gray-500 mb-5">
        When multiple emergencies arrive, our priority algorithm evaluates clinical severity, vitals, and room/vet availability to triage the most critical case first.
    </p>
    <div id="emergencyMsg" class="mb-4 hidden"></div>
    <form id="emergencyBookingForm" action="submit_emergency.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex flex-col md:col-span-2" id="nameFieldContainer" style="display:none;"></div>
        <div class="flex flex-col md:col-span-2" id="emailFieldContainer" style="display:none;"></div>
        <div class="flex flex-col md:col-span-2" id="phoneFieldContainer" style="display:none;"></div>
        <div class="flex flex-col md:col-span-2" id="detailsField">
            <label class="font-semibold text-gray-700 mb-1">Additional Details & Symptoms</label>
            <textarea name="details"required class="border border-gray-300 p-3 rounded-lg h-24" placeholder="Describe the emergency symptoms (e.g. difficulty breathing, accident, bleeding, vomiting)..."></textarea>
        </div>
        <div class="flex flex-col md:col-span-2" id="imageField">
            <label class="font-semibold text-gray-700 mb-1">Upload Image (Optional)</label>
            <input type="file" name="image" class="border border-gray-300 p-2 rounded-lg">
        </div>
        <div class="md:col-span-2 flex justify-center mt-2">
            <button type="submit" id="emergencySubmitBtn" class="text-white font-bold px-8 py-3 rounded-xl transition bg-gradient-to-r from-[#DA4A6B] to-[#E3A857] hover:opacity-95 shadow">
                Submit Emergency Request
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function showGuestFields() {
        document.getElementById('nameFieldContainer').style.display = '';
        document.getElementById('emailFieldContainer').style.display = '';
        document.getElementById('phoneFieldContainer').style.display = '';
        ['hiddenName', 'hiddenEmail', 'hiddenPhone'].forEach(id =>{
            const el = document.getElementById(id);
            if (el) el.remove();
        });
        document.getElementById('nameFieldContainer').innerHTML = `
            <label class="font-semibold text-gray-700">Name</label>
            <input type="text" id="bookingName" name="name" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your name"required>
        `;
        document.getElementById('emailFieldContainer').innerHTML = `
            <label class="font-semibold text-gray-700">Email</label>
            <input type="email" id="bookingEmail" name="email" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your email"required>
        `;
        document.getElementById('phoneFieldContainer').innerHTML = `
            <label class="font-semibold text-gray-700">Phone Number</label>
            <input type="tel" id="bookingPhone" name="phone" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your phone number"required>
        `;
    }

    function showAutoFilledFields(owner) {
        document.getElementById('nameFieldContainer').style.display = 'none';
        document.getElementById('emailFieldContainer').style.display = 'none';
        document.getElementById('phoneFieldContainer').style.display = 'none';
        ['hiddenName', 'hiddenEmail', 'hiddenPhone'].forEach(id =>{
            const el = document.getElementById(id);
            if (el) el.remove();
        });
        const form = document.getElementById('emergencyBookingForm');
        const hiddenName = document.createElement('input');
        hiddenName.type = 'hidden';
        hiddenName.name = 'name';
        hiddenName.value = owner.First_Name + ''+ owner.Last_Name;
        hiddenName.id = 'hiddenName';
        form.appendChild(hiddenName);
        const hiddenEmail = document.createElement('input');
        hiddenEmail.type = 'hidden';
        hiddenEmail.name = 'email';
        hiddenEmail.value = owner.Email;
        hiddenEmail.id = 'hiddenEmail';
        form.appendChild(hiddenEmail);
        const hiddenPhone = document.createElement('input');
        hiddenPhone.type = 'hidden';
        hiddenPhone.name = 'phone';
        hiddenPhone.value = owner.Contact;
        hiddenPhone.id = 'hiddenPhone';
        form.appendChild(hiddenPhone);
    }

    showGuestFields();

    fetch('check_session.php')
        .then(response => response.json())
        .then(data =>{
            if (!data.isLoggedIn) return;
            return fetch('get_owner_info.php')
                .then(res => res.json())
                .then(owner =>{
                    if (owner.status === 'success'&& owner.data) {
                        showAutoFilledFields(owner.data);
                    }
                });
        })
        .catch(() =>{});
});

document.getElementById('emergencyBookingForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('emergencySubmitBtn');
    const msg = document.getElementById('emergencyMsg');
    btn.disabled = true;
    btn.textContent = 'Running Priority Triage...';

    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(res => res.json())
        .then(data =>{
            msg.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
            if (data.success) {
                msg.classList.add('bg-green-100', 'text-green-700', 'p-3', 'rounded-lg', 'text-sm', 'font-semibold');
                msg.textContent = data.message || 'Emergency request submitted.';
                form.reset();
            } else {
                msg.classList.add('bg-red-100', 'text-red-700', 'p-3', 'rounded-lg', 'text-sm');
                msg.textContent = data.message || 'Something went wrong. Please try again.';
            }
        })
        .catch(() =>{
            msg.classList.remove('hidden');
            msg.classList.add('bg-red-100', 'text-red-700', 'p-3', 'rounded-lg');
            msg.textContent = 'Could not reach the server. Please check your connection and try again.';
        })
        .finally(() =>{
            btn.disabled = false;
            btn.textContent = 'Submit Emergency Request';
        });
});
</script>

<footer class="bg-[#1F3A3D] text-white mt-12 py-8">
    <div class="max-w-6xl mx-auto px-6 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-3">
            <img src="assets/images/logo.png" class="h-8 bg-white p-1 rounded" alt="Logo"/>
            <span class="text-xl font-bold">HealthWing Veterinary Clinic</span>
        </div>
        <div class="text-sm text-gray-300">
            &copy; <?php echo date('Y'); ?>HealthWing. All rights reserved.
        </div>
    </div>
</footer>
</body>
</html>