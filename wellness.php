<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);

$username = $isLoggedIn ? $_SESSION['username'] : null;
?>

<!DOCTYPE html>
<html>
  <head>
    <base href="/">
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <link href="vendor/flowbite/flowbite.min.css" rel="stylesheet"/>
    <link href="hero-glass.css" rel="stylesheet"/>
    <script src="vendor/tailwind/tailwind-browser.js"></script>
    <script src="vendor/motion/motion.js"></script>
    <script>
    const { animate, scroll } = Motion
    </script>
    <style type="text/tailwindcss">
      :root {
        --color-clifford: #da373d;
      }
    </style>
  </head>
  <body>
   <?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('images/wellnesspage.jpg');" id="heroBanner">
  <div class="hero-dark-overlay"></div>
  <canvas class="glass-canvas" id="glassCanvas"></canvas>
  <div class="glass-card-text text-center" id="glassCardText">
    <h1 class="text-3xl font-extrabold text-white">Taking a Proactive Approach to Pet Health Check-ups</h1>
      <p class="text-2xl text-white mt-4">In HealthWing</p>
  </div>
</div>
<script src="glass-shader.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText) {
      initGlassShader(canvas, 'images/wellnesspage.jpg', cardText);
    }
  })();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
   Preventing illness is essential to ensuring long-term health for our beloved pets. While we can address any current health concerns, our goal is to provide protection against potential future diseases. Regular wellness exams are the best way to monitor your pet’s overall health, detect early signs of illness, and intervene before problems become serious. At Healthwing Clinic, we recommend scheduling a comprehensive check-up at least once a year. This allows us to keep your pet in optimal health, while also offering the opportunity to address any concerns you may have and provide tailored advice to enhance their well-being.
  </p>

  <hr class="border-t-2 w-3/4 mx-auto mt-8 mb-2">
</div>

<div class="w-full bg-[#89cbdf] pt-2 pb-10 text-center">
  <p class="text-2xl font-semibold text-white">Book an appointment today for a healthier pet!</p>
  <button onclick="window.location.href='appointment_schedule.php'" class="mt-4 px-6 py-3 text-white font-medium rounded-lg focus:outline-none bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B]/50 hover:to-[#FFE023]/50">
    Request an Appointment
  </button>
</div>

<div class="flex items-center justify-between p-8">
  <div class="w-1/2">
    <img src="images/wellnesspic1.jpg" alt="Wellness Exam Image" class="w-full h-auto rounded-lg">
  </div>
  <div class="w-1/2 pl-8">
    <h2 class="text-3xl font-bold mb-4">What’s Included in a Wellness Exam?</h2>
    <p class="text-base mb-4">
      At Healthwing Clinic, we prioritize giving each pet the attention they deserve during their wellness visit. If you have any questions or concerns, this is an ideal time to speak with your vet and gain insights into your pet’s health. While the wellness exam focuses on maintaining your pet’s overall well-being, it also serves as a chance to educate you, the pet owner, and guide you in making the best decisions for your companion’s care.
    </p>
    <p class="text-base font-bold mb-2">A typical wellness exam for your pet includes:</p>
    <ul class="list-disc pl-5">
      <li class="text-base mb-2">A thorough physical examination, where we check vital signs and look for any abnormalities, including lumps, plaque on the teeth, swelling, inflammation, discoloration, odors, stiffness, and discomfort.</li>
      <li class="text-base mb-2">Administering necessary vaccinations to keep your pet protected.</li>
      <li class="text-base mb-2">Running blood tests and fecal exams to screen for infections and parasites.</li>
      <li class="text-base mb-2">Deworming treatment, if necessary.</li>
      <li class="text-base mb-2">Providing preventatives for fleas, ticks, and heartworms.</li>
      <li class="text-base mb-2">Discussing nutrition, training, and other aspects of your pet’s overall health and care.</li>
    </ul>
  </div>
</div>


<div class="bg-[#89cbdf] p-8">
  <div class="text-center max-w-4xl mx-auto mb-12">
    <h2 class="text-3xl font-bold text-gray-800">Why Parasite Prevention Matters for Your Pet’s Health</h2>
    <p class="mt-2 text-gray-800">
      Parasites are a persistent and serious threat to pets, especially in regions like ours where pests are active year-round.
      At Healthwing Clinic, we regularly see cases involving mosquitoes, fleas, ticks, heartworms, roundworms, hookworms, and more.
      These parasites are more than just a nuisance—they can lead to serious health issues in both pets and people.
    </p>
  </div>

  <div class="flex flex-col md:flex-row items-start justify-between max-w-6xl mx-auto gap-6">
    <div class="md:w-2/3 text-gray-800">
      <p class="font-semibold mb-2">Some common conditions caused by parasites include:</p>
      <ul class="list-disc list-inside mb-4">
        <li>Heartworm disease</li>
        <li>Intestinal worms (hookworms, roundworms, tapeworms)</li>
        <li>Flea allergy dermatitis</li>
        <li>Digestive issues like diarrhea</li>
      </ul>
      <p>
        The best way to protect your pet from these risks is with consistent, year-round parasite prevention.
        At Healthwing Clinic, we offer a full range of safe and effective preventatives to stop infestations before they start
        and keep your pet comfortable and protected.
      </p>
    </div>

    <div class="md:w-1/3 flex justify-center">
      <img src="images/wellnesspic2.png" alt="Parasite Prevention" class="w-64 h-auto rounded-lg"/>
    </div>
  </div>
</div>

<div class="bg-white py-10">
    <h2 class="text-center text-3xl font-bold text-gray-800 mb-5">Hours of Operation</h2>
    <div class="max-w-lg mx-auto bg-[#89cbdf] text-white rounded-lg p-6 shadow-md">
        <ul class="space-y-2 text-lg">
            <li class="flex justify-between">
                <span>Monday</span><span>8:00 AM - 6:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Tuesday</span><span>8:00 AM - 6:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Wednesday</span><span>8:00 AM - 6:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Thursday</span><span>8:00 AM - 6:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Friday</span><span>8:00 AM - 6:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Saturday</span><span>9:00 AM - 3:00 PM</span>
            </li>
            <li class="flex justify-between">
                <span>Sunday</span><span>Closed</span>
            </li>
        </ul>
    </div>
</div>

<div id="emergency-booking" class="bg-gray-100 p-6 rounded-lg shadow-md mt-10 mx-auto w-2/3">
    <h3 class="text-xl font-bold text-gray-700 text-center mb-4">Emergency Booking</h3>
    <form action="submit_emergency.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Name</label>
            <input type="text" name="name" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your name"required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Email</label>
            <input type="email" name="email" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your email"required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Phone Number</label>
            <input type="tel" name="phone" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your phone number"required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Additional Details</label>
            <textarea name="details" class="border border-gray-300 p-2 rounded-lg h-24" placeholder="Describe the emergency..."></textarea>
        </div>
        <div class="md:col-span-2 flex flex-col">
            <label class="font-semibold text-gray-700">Upload Image (Optional)</label>
            <input type="file" name="image" class="border border-gray-300 p-2 rounded-lg">
        </div>
        <div class="md:col-span-2 flex justify-center">
            <button type="submit" class="text-white px-5 py-2 rounded-lg transition bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B80] hover:to-[#FFE02380]">
                Submit
            </button>
        </div>
        </form>
    </div>
</div>

<footer class="bg-white dark:bg-gray-900 mt-8">
    <div class="mx-auto w-full max-w-screen-xl p-4 py-6 lg:py-8">
        <div class="md:flex md:justify-between">
          <div class="mb-6 md:mb-0">
              <a class="flex items-center">
                  <img src="images/logo.png" class="h-8 me-3" alt="Logo"/>
                  <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">HealthWing</span>
              </a>
          </div>
          <div class="grid grid-cols-2 gap-8 sm:gap-6 sm:grid-cols-3">
              <div>
                  <h2 class="mb-6 text-sm font-semibold text-gray-900 uppercase dark:text-white"></h2>
                  <ul class="text-gray-500 dark:text-gray-400 font-medium">
                      
                  </ul>
              </div>
              <div>
                  <h2 class="mb-6 text-sm font-semibold text-gray-900 uppercase dark:text-white">Follow us</h2>
                  <ul class="text-gray-500 dark:text-gray-400 font-medium">
                      <li class="mb-4">
                          <a class="hover:underline ">Facebook</a>
                      </li>
                      <li>
                          <a class="hover:underline">Instagram</a>
                      </li>
                  </ul>
              </div>
              <div>
                  <h2 class="mb-6 text-sm font-semibold text-gray-900 uppercase dark:text-white">Legal</h2>
                  <ul class="text-gray-500 dark:text-gray-400 font-medium">
                      <li class="mb-4">
                          <a href="#" class="hover:underline">Privacy Policy</a>
                      </li>
                      <li>
                          <a href="#" class="hover:underline">Terms &amp; Conditions</a>
                      </li>
                  </ul>
              </div>
          </div>
      </div>
      <hr class="my-6 border-gray-200 sm:mx-auto dark:border-gray-700 lg:my-8"/>
      
      </div>
    </div>
</footer>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
    fetch("check_session.php")
        .then(response => response.json())
        .then(data =>{
            const userSection = document.getElementById("user-section");

            if (data.isLoggedIn) {
                // User is logged in, show welcome message and logout button
                userSection.innerHTML = `
                    <span class="text-white font-semibold text-lg">Welcome, ${data.username}!</span>
                    <form action="logout.php" method="post" style="display:inline;">
        <button type="submit" class="text-red-500 hover:underline ml-4">Logout</button>
    </form>
                `;
            } else {
                
                userSection.innerHTML = `
                    <div class="flex items-center md:order-2 space-x-4 md:space-x-6 rtl:space-x-reverse">
                    <a href="chooserole.html">
                        <div class="flex space-x-1">
                            <button class="py-2 px-4 text-gray-900 rounded-lg hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 dark:text-white md:dark:hover:text-[#DBE48C] dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700 hover:border-blue-700 md:dark:hover:border-[#DBE48C] transition-all duration-300">Sign Up
                            </button>
                    </a>
                    <a href="Sign in html.html">
                            <button class="px-4 py-2 text-white bg-[#DBE48C] rounded-lg transition-all duration-300 hover:bg-[#c6d674] hover:shadow-lg">Sign In
                            </button>
                    </a>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => console.error("Error fetching session state:", error));
});
    </script>
    </h1>
    <script src="vendor/flowbite/flowbite.min.js"></script>
    <script src="JS.js"></script>
  </body>
</html>