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

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('images/vaccinationpage.jpg');" id="heroBanner">
  <div class="hero-dark-overlay"></div>
  <canvas class="glass-canvas" id="glassCanvas"></canvas>
  <div class="glass-card-text text-center" id="glassCardText">
    <h1 class="text-3xl font-extrabold text-white">Preventing Illness in Pets with Essential Dog and Cat Vaccines</h1>
      <p class="text-2xl text-white mt-4">In HealthWing</p>
  </div>
</div>
<script src="glass-shader.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText) {
      initGlassShader(canvas, 'images/vaccinationpage.jpg', cardText);
    }
  })();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
   It’s no surprise that our beloved pets can be exposed to various health risks just by exploring their surroundings or interacting with other animals. Everyday activities can lead them into contact with dangerous viruses like rabies, parvovirus, and more. At Healthwing Clinic, we strongly recommend core vaccinations for both cats and dogs to help prevent the spread of these serious illnesses. Vaccines are a simple yet powerful way to keep your pet safe, healthy, and thriving throughout their life.
  </p>

  <hr class="border-t-2 w-3/4 mx-auto mt-8 mb-2">
</div>

<div class="w-full bg-[#89cbdf] pt-2 pb-10 text-center">
  <p class="text-2xl font-semibold text-white">Book an appointment today for a healthier pet!</p>
  <button onclick="window.location.href='appointment_schedule.php'" class="mt-4 px-6 py-3 text-white font-medium rounded-lg focus:outline-none bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B]/50 hover:to-[#FFE023]/50">
    Request an Appointment
  </button>
</div>

<script src="vendor/alpine/alpine.min.js"defer></script>

<div class="flex flex-col lg:flex-row items-start justify-between bg-white p-8 gap-8">
  <div class="lg:w-1/2 text-center" x-data="{ activeTab: ''}">
    <h2 class="text-2xl font-bold mb-4">
      Diseases Our Cat and Dog Vaccines Protect Against
    </h2>
    <p class="mb-6 text-gray-700">
      Routine vaccinations help to maintain your companion’s immunity to various illnesses. Our primary disease concerns for dogs and cats include:
    </p>

    <div class="flex flex-col items-center gap-4">
      <div class="w-full">
        <button
          class="px-4 py-2 border rounded-lg w-full text-center"
          :class="{ 'bg-blue-600 text-white': activeTab === 'rabies'}"
          @click="activeTab = activeTab === 'rabies'? '': 'rabies'"
        >
          Rabies
        </button>
        <div x-show="activeTab === 'rabies'"x-transition class="mt-2 text-gray-700 text-center">
          <p><strong>Rabies:</strong>Rabies is a highly dangerous virus that affects both dogs and cats, often with fatal consequences. It targets the nervous system and is always lethal if not treated. Wild animals such as raccoons, bats, skunks, and foxes are common carriers of the virus. Symptoms of rabies in pets include aggressive behavior, excessive drooling, difficulty swallowing, seizures, and paralysis.</p>
        </div>
      </div>

      <div class="w-full">
        <button
          class="px-4 py-2 border rounded-lg w-full text-center"
          :class="{ 'bg-blue-600 text-white': activeTab === 'parvo'}"
          @click="activeTab = activeTab === 'parvo'? '': 'parvo'"
        >
          Parvovirus
        </button>
        <div x-show="activeTab === 'parvo'"x-transition class="mt-2 text-gray-700 text-center">
          <p><strong>Parvovirus:</strong>Parvo, caused by the canine parvovirus, is a highly contagious and potentially fatal virus, particularly dangerous for puppies and older dogs. Symptoms of parvo include loss of appetite, vomiting, and severe bloody diarrhea. Immediate treatment is crucial to prevent dehydration and septic shock in affected pets.</p>
        </div>
      </div>

      <div class="w-full">
        <button
          class="px-4 py-2 border rounded-lg w-full text-center"
          :class="{ 'bg-blue-600 text-white': activeTab === 'distemper'}"
          @click="activeTab = activeTab === 'distemper'? '': 'distemper'"
        >
          Distemper
        </button>
        <div x-show="activeTab === 'distemper'"x-transition class="mt-2 text-gray-700 text-center">
          <p><strong>Distemper:</strong>Distemper is a severe and potentially fatal virus that impacts various organ systems, including the respiratory and gastrointestinal systems. Common symptoms include fever, vomiting, loss of appetite, fatigue, eye discharge, and persistent coughing.</p>
        </div>
      </div>

      <div class="w-full">
        <button
          class="px-4 py-2 border rounded-lg w-full text-center"
          :class="{ 'bg-blue-600 text-white': activeTab === 'leukemia'}"
          @click="activeTab = activeTab === 'leukemia'? '': 'leukemia'"
        >
          Feline Leukemia
        </button>
        <div x-show="activeTab === 'leukemia'"x-transition class="mt-2 text-gray-700 text-center">
          <p><strong>Feline Leukemia:</strong>If your cat spends time outdoors, they face a higher risk of contracting the feline leukemia virus. This retrovirus is transmitted through nasal secretions (such as sneezing) and saliva from infected cats. Feline leukemia can severely compromise your cat's immune system and may be fatal. Symptoms of infection often include fever, loss of appetite, weight loss, lethargy, and pale gums.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="lg:w-1/2">
    <img src="images/vaccinationpage.jpg" alt="Cat and Dog Vaccination" class="w-full rounded-lg shadow-md">
  </div>
</div>

<div class="bg-[#89cbdf] px-8 pb-12 pt-12 mt-4 w-full">
  <div class="max-w-6xl mx-auto">
    <h3 class="text-2xl font-bold mb-4 text-white">Tailored Vaccination Plans for Every Pet</h3>
    <p class="text-white">
      At Healthwing Clinic, we understand that no two pets are exactly alike. That’s why we customize vaccination plans based on your pet’s individual needs and risk factors.
      Before we recommend or administer any vaccines, we take the time to perform a thorough physical exam, review their health history, and consider their daily routine and environment.
      Pets that frequently travel, visit groomers, board at kennels, or live in high-risk areas may need additional protection compared to pets with a more home-based lifestyle.
      Our goal is to provide the safest, most effective vaccine strategy to keep your companion healthy and thriving.
    </p>
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