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

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('images/parasitepage.jpg');" id="heroBanner">
  <div class="hero-dark-overlay"></div>
  <canvas class="glass-canvas" id="glassCanvas"></canvas>
  <div class="glass-card-text text-center" id="glassCardText">
    <h1 class="text-3xl font-extrabold text-white">Limiting Health Risks with Effective Parasite Control for Cats and Dogs</h1>
      <p class="text-2xl text-white mt-4">In HealthWing</p>
  </div>
</div>
<script src="glass-shader.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText) {
      initGlassShader(canvas, 'images/parasitepage.jpg', cardText);
    }
  })();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
   It’s no secret that our beloved pets are exposed to a variety of parasites in their environment, whether they’re playing outdoors, interacting with other animals, or simply enjoying daily activities. Fleas, ticks, heartworms, and intestinal parasites can pose serious health risks. At Healthwing Clinic, we recommend year-round parasite prevention for all pets to keep them healthy, safe, and comfortable. Regular parasite control can help protect your pet from harmful infections and ensure they continue to live their best life.</p>
  <hr class="border-t-2 w-3/4 mx-auto mt-8 mb-2">
</div>

<div class="w-full bg-[#89cbdf] pt-2 pb-10 text-center">
  <p class="text-2xl font-semibold text-white">Book an appointment today for a healthier pet!</p>
  <button onclick="window.location.href='appointment_schedule.php'" class="mt-4 px-6 py-3 text-white font-medium rounded-lg focus:outline-none bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:from-[#DA4A6B]/50 hover:to-[#FFE023]/50">
    Request an Appointment
  </button>
</div>

<div class="flex items-center bg-white p-8">
  <div class="w-1/2 pr-8">
    <img src="images/parasitepic1.jpg" alt="Parasite Infestation in Pets" class="w-full h-auto rounded-lg">
  </div>

  <div class="w-1/2 text-left">
    <h2 class="text-4xl font-bold mb-6">Common Signs of Parasite Infestations in Dogs and Cats</h2>
    <p class="text-lg mb-6">
      Parasites such as fleas, ticks, and worms can cause a range of symptoms in pets. Some of the most common signs of a parasite infestation include:
    </p>
    <ul class="list-disc pl-8 text-lg mb-6">
      <li>Excessive scratching or biting at the skin, especially around the ears and tail</li>
      <li>Visible fleas or flea dirt in the coat</li>
      <li>Red, inflamed, or irritated skin</li>
      <li>Hair loss or bald patches, particularly from excessive grooming</li>
      <li>Lethargy or decreased energy levels</li>
      <li>Vomiting or diarrhea, which could be a sign of intestinal worms</li>
      <li>Coughing, labored breathing, or weight loss, which can indicate heartworm infestation</li>
      <li>Visible worms in the stool or around the anus</li>
    </ul>
    <p class="text-lg">
      These symptoms often arise as a result of pets’ natural attempts to relieve discomfort caused by parasites. Timely parasite control can prevent these issues and ensure your pet stays healthy and happy.
    </p>
  </div>
</div>

<div class="bg-[#89cbdf] p-8">
  <div class="max-w-screen-lg mx-auto">
    
    <div class="mb-8 text-justify">
      <h2 class="text-2xl font-bold mb-4 text-center">What Can Pets Get from Parasites?</h2>
      <p class="text-sm mb-4">
        Parasites pose a serious threat to our pets'health, causing various issues, from minor irritations to severe conditions. Fleas, ticks, heartworms, and intestinal worms are common parasites that affect both dogs and cats. These parasites can lead to a variety of symptoms and health complications.
      </p>
      <h3 class="font-semibold mt-4">Flea Allergy Dermatitis</h3>
      <p class="text-sm mb-4">
        Fleas are not only annoying but can also trigger flea allergy dermatitis in pets. This condition occurs when a pet’s immune system overreacts to flea saliva, causing severe itching, hair loss, and skin infections.
      </p>
      <h3 class="font-semibold mt-4">Heartworms</h3>
      <p class="text-sm mb-4">
        Heartworms are spread by mosquitoes and can cause severe heart and lung damage in dogs and cats. Without proper prevention, heartworms can be life-threatening, leading to coughing, fatigue, and difficulty breathing.
      </p>
      <h3 class="font-semibold mt-4">Intestinal Worms</h3>
      <p class="text-sm mb-4">
        Pets can also suffer from intestinal worms, including roundworms, hookworms, tapeworms, and whipworms. These worms can cause symptoms such as vomiting, diarrhea, weight loss, and lethargy. Some types of worms can even be transmitted to humans, making regular parasite control crucial.
      </p>
      <h3 class="font-semibold mt-4">Ticks and Lyme Disease</h3>
      <p class="text-sm mb-4">
        Ticks can transmit Lyme disease, which affects both pets and humans. Symptoms include fever, joint pain, and lethargy. Ticks can be found in grassy, wooded areas, and they latch onto pets to feed on their blood.
      </p>
    </div>

    <hr class="border-t-2 border-gray-900 mb-8">

    <div class="text-justify">
      <h2 class="text-2xl font-bold mb-4 text-center">How Our Team Can Help</h2>
      <p class="text-sm mb-4">
        Parasites are a persistent problem, but with the right care, your pet can be protected. At Healthwing Clinic, we offer comprehensive parasite control and prevention strategies to keep your pet safe from these threats.
      </p>
      <h3 class="font-semibold mt-4">Preventative Care</h3>
      <p class="text-sm mb-4">
        We provide year-round parasite prevention programs tailored to your pet’s needs. These programs may include oral medications, topical treatments, and vaccinations that help prevent flea, tick, heartworm, and intestinal parasite infestations.
      </p>
      <h3 class="font-semibold mt-4">Fecal Exams and Testing</h3>
      <p class="text-sm mb-4">
        Routine fecal exams allow us to detect intestinal worms early. Our in-house lab can perform tests to identify any parasitic infections your pet might have and provide treatment options right away.
      </p>
      <h3 class="font-semibold mt-4">Treatment for Active Infections</h3>
      <p class="text-sm mb-4">
        If your pet is already dealing with a parasite infestation, we offer treatments to eliminate the parasites and provide relief. This can include:
      </p>
      <ul class="list-disc pl-8 text-sm mb-4">
        <li>Oral medications for worms</li>
        <li>Topical treatments for fleas and ticks</li>
        <li>Heartworm treatments</li>
      </ul>
      <h3 class="font-semibold mt-4">Education and Support</h3>
      <p class="text-sm mb-4">
        Understanding how to prevent parasite infestations is key. Our team will educate you on how to protect your pet from future infestations, recommend the best products, and help you maintain a regular parasite prevention schedule.
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