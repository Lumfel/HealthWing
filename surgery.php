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

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('images/surgerypage.jpg');" id="heroBanner">
  <div class="hero-dark-overlay"></div>
  <canvas class="glass-canvas" id="glassCanvas"></canvas>
  <div class="glass-card-text text-center" id="glassCardText">
    <h1 class="text-3xl font-extrabold text-white">Animal Surgery and Reproductive Care</h1>
      <p class="text-2xl text-white mt-4">In HealthWing</p>
  </div>
</div>
<script src="glass-shader.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText) {
      initGlassShader(canvas, 'images/surgerypage.jpg', cardText);
    }
  })();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
    When or if your pet needs to have surgery, you can count on our team to go above and beyond to keep them safe, comfortable, and anxiety-free. Healthwing Clinic is outfitted with a first-class surgical suite, where we routinely perform all kinds of procedures, including spay and neuter surgeries, mass removals, and even some orthopedics. Additionally, we comply with the American Animal Hospital Association’s strict surgical guidelines to ensure the best possible experience for your companion.
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
    <img src="images/surgerypic.jpg" alt="Dental care for pets" class="w-full h-auto rounded-lg shadow-lg">
  </div>

  <div class="w-full md:w-1/2 text-gray-800 text-base font-medium">
    <h2 class="text-2xl font-extrabold text-grey-800 mb-4">Surgical Safety Protocols at Healthwing Clinic</h2>
    <p class="mb-4">Your pet’s safety and comfort are our top priorities. At Healthwing Clinic, we understand how much trust it takes to place your beloved companion in our care, especially for surgery.

To minimize anesthesia risks and ensure the best possible outcome, we follow these key safety measures:</p>

   <ul class="list-disc list-outside text-grey-800 mb-4 pl-5">
  <li>Pre-surgical blood testing is required for pets of all ages to help us identify any hidden health issues and evaluate anesthetic risk.</li>
  <li>An IV catheter is placed to deliver fluids during surgery, helping maintain stable blood pressure and allowing us to administer pain relief efficiently.</li>
  <li>Continuous monitoring is performed throughout the procedure using advanced equipment and a dedicated surgical team to track your pet’s vital signs.</li>
  <li>Warm blankets and supportive care are provided to keep your pet comfortable before, during, and after surgery.</li>
  <li>Post-operative monitoring ensures your pet wakes up smoothly and safely. We make sure they’re alert, mobile, and comfortable before heading home.</li>
</ul>

    <p class="mb-4">At Healthwing Clinic, we treat your pet like family—because they are.</p>
  </div>
</div>

<div class="bg-[#89cbdf] py-8">
  <div class="container mx-auto px-4">
    <div class="flex flex-col lg:flex-row items-start gap-8">
      <div class="lg:w-2/3">
        <h2 class="text-2xl font-bold mb-4">Why We Perform Spay, Neuter, and Surgical Procedures at Healthwing Clinic</h2>
        <p class="mb-4">
          At Healthwing Clinic, we believe that surgical and reproductive care are essential to helping pets live longer, healthier lives. Spaying and neutering not only prevent unexpected litters, but they also reduce your pet’s risk for reproductive cancers, infections like pyometra, and hormone-related health issues. These proactive measures can spare your pet from serious illness and help you avoid costly treatments down the road.
        </p>

        <h3 class="text-xl font-bold mb-2">The Benefits of Reproductive Surgery</h3>
        <p class="mb-4">
          Beyond health protection, spaying and neutering can also support better behavior. While your pet’s personality will remain intact, reproductive surgery may help reduce or eliminate unwanted hormonal behaviors such as:
        </p>
        <ul class="list-disc list-outside pl-6 text-gray-900 mb-6">
          <li>Excessive vocalizing (barking, yowling, or caterwauling)</li>
          <li>Urine marking or spraying indoors</li>
          <li>Roaming or attempting to escape in search of a mate</li>
          <li>Mounting furniture, other pets, or people</li>
        </ul>
        <p>These changes often lead to a more relaxed and manageable companion.</p>
      </div>

      <div class="lg:w-1/3">
        <img src="images/surgerypic2.jpg" alt="Pet Surgery at Healthwing Clinic" class="w-full rounded-lg shadow-lg">
      </div>
    </div>

    <div class="mt-12">
      <h3 class="text-xl font-bold mb-2">When to Schedule Surgery</h3>
      <p class="mb-4">
        At Healthwing Clinic, we tailor surgical recommendations based on your pet’s age, size, and breed. While many cats and small dogs can be safely spayed or neutered at around 5–6 months of age, we may recommend delaying surgery for large and giant breed dogs until they’re 12–18 months old to ensure proper joint and bone development.
      </p>
      <p class="mb-4">
        Additionally, for at-risk breeds, we may offer gastropexy during spay or neuter surgery to prevent life-threatening gastric dilatation-volvulus (bloat or stomach twisting).
      </p>
      <p>
        Our goal is to provide safe, compassionate care through every stage of the surgical process—from pre-op consultation to post-op recovery—ensuring your pet stays healthy and happy.
      </p>
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