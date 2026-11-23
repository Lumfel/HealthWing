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

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('images/dentalpage.jpg');" id="heroBanner">
  <div class="hero-dark-overlay"></div>
  <canvas class="glass-canvas" id="glassCanvas"></canvas>
  <div class="glass-card-text text-center" id="glassCardText">
    <h1 class="text-3xl font-extrabold text-white">Protect Your Pet’s Health with Regular Dog and Cat Dental Cleanings</h1>
      <p class="text-2xl text-white mt-4">In HealthWing</p>
  </div>
</div>
<script src="glass-shader.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText) {
      initGlassShader(canvas, 'images/dentalpage.jpg', cardText);
    }
  })();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
    A nutritious diet, regular exercise, vaccinations, and parasite control are all key components of keeping your pets healthy. However, one crucial aspect that’s frequently forgotten is dental care for cats and dogs. Since dental disease is one of the most common health issues in adult pets, it’s vital for pet owners to understand the risks and how to prevent them. At Daleville Animal Hospital, we provide professional dental cleanings for both dogs and cats, and we’re here to help you learn how to maintain your pet’s oral health at home for a lifetime of healthy smiles.
  </p>

  <hr class="border-t-2 w-3/4 mx-auto mt-8 mb-2">
</div>

<div class="w-full bg-[#89cbdf] pt-2 pb-10 text-center">
  <p class="text-2xl font-semibold text-white">Book an appointment today for a healthier pet!</p>
  <button onclick="window.location.href='appointment_schedule.php'" class="mt-4 px-6 py-3 text-white font-medium rounded-lg focus:outline-none bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B]/50 hover:to-[#FFE023]/50">
    Request an Appointment
  </button>
</div>

<div class="flex flex-col md:flex-row items-center bg-white p-6 md:p-12">
  <div class="w-full md:w-1/2 mb-6 md:mb-0 md:mr-8">
    <img src="images/dentalpic.jpg" alt="Dental care for pets" class="w-full h-auto rounded-lg shadow-lg">
  </div>

  <div class="w-full md:w-1/2 text-gray-800 text-base font-medium">
    <h2 class="text-2xl font-extrabold text-grey-800 mb-4">How to Spot Dental Problems in Dogs and Cats</h2>
    <p class="mb-4">Pets are great at hiding discomfort, so dental disease can often go unnoticed until it becomes more serious. To help catch any issues early, here are some common signs that your pet might be experiencing dental problems:</p>

    <ul class="list-disc list-inside text-grey-800 mb-4">
      <li>Unpleasant breath</li>
      <li>Inflamed or red gums</li>
      <li>Excessive drooling</li>
      <li>Difficulty chewing or dropping food</li>
      <li>Disinterest in chew toys or tug games</li>
      <li>Changes in behavior, such as increased sleep or reduced social interaction</li>
    </ul>

    <p class="mb-4">We also suggest gently lifting your pet’s lip to take a closer look at their teeth. If you see yellow or brown tartar buildup, it’s likely time for a professional dental cleaning. Regular checks and early detection can help keep your pet healthy and comfortable.</p>
  </div>
</div>

<div class="flex flex-col md:flex-row items-start bg-[#89cbdf] p-6 md:p-12">
  <div class="w-full md:w-1/2 mb-6 md:mb-0 md:mr-8">
    <h2 class="text-xl font-extrabold text-grey-800 mb-4">Preventing Dental Disease in Cats and Dogs: A Healthy Smile for Life</h2>
    <p class="text-lg font-small text-grey-800 mb-4">
      Dental disease doesn’t have to be a part of your pet’s life. With the right care at home and regular professional cleanings, you can help your furry friend maintain a healthy mouth and live a longer, happier life.
    </p>
    <p class="text-lg font-small text-grey-800 mb-4">
      At HealthWing, we offer comprehensive dental care to not only treat dental disease but also prevent it from returning. Our services include:
    </p>
    <ul class="list-disc list-inside font-small text-grey-800 mb-4">
      <li>A thorough oral examination to detect signs of dental disease, such as gum inflammation, plaque buildup, and loose teeth</li>
      <li>Ultrasonic scaling to remove plaque and tartar from above and below the gum line</li>
      <li>Polishing of the teeth to smooth surfaces and reduce future buildup</li>
      <li>Fluoride treatments to protect the teeth from further damage</li>
      <li>Tooth extractions when necessary to remove damaged or infected teeth</li>
    </ul>
  </div>

  <div class="w-full md:w-1/2 text-grey-800">
    <h2 class="text-xl font-extrabold text-grey-800 mb-4">Caring for Your Pet’s Teeth at Home</h2>
    <p class="text-lg font-small text-grey-800 mb-4">
      Your pet’s dental health starts with you! It’s crucial to establish a dental care routine early in your pet’s life so they get comfortable with brushing and other oral care practices. Regular brushing helps prevent plaque and tartar buildup and protects against infections.
    </p>
    <p class="text-lg font-small text-grey-800 mb-4">
      If you need guidance on brushing your pet’s teeth, we’re here to help with tips and techniques. There are also several products available, such as dental diets, chews, oral wipes, and water additives, that can make caring for your pet’s teeth even easier.
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