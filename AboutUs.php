<?php
// Session logic removed for public access
// $isLoggedIn and $username are not set unless you want to use them for UI only
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <link href="api/asset_proxy.php?f=vendor/flowbite/flowbite.min.css" rel="stylesheet"/>
    <script src="api/asset_proxy.php?f=vendor/tailwind/tailwind-browser.js"></script>
    <script src="api/asset_proxy.php?f=vendor/motion/motion.js"></script>
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

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center" style="background-image: url('assets/images/aboutpage.jpg');">
  <div class="text-center p-6 rounded-lg max-w-2xl">
    <h1 class="text-5xl font-extrabold text-white">Our Veterinary Team</h1>
    <p class="text-2xl text-white mt-4">Meet the team of HealthWing</p>
  </div>
</div>

<div class="w-full bg-[#89cbdf] py-10 text-center">
  <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">Allow us to share what drives us.
At the heart of our veterinary practice is a deep commitment to the well-being of animals and the trust of the people who care for them. Our mission and vision reflect the values we uphold every day—compassion, excellence, and dedication to providing the highest quality veterinary care. We’re here not only to treat pets, but to support the lifelong bond between you and your animal companions.</p>
</div>

<section class="my-12 px-4">
  <div class="max-w-3xl mx-auto space-y-16">
    <div id="visionBox" class="opacity-0 transform translate-x-10 transition-all duration-700 ease-out">
      <div class="bg-[#89cbdf] text-gray-800 p-6 rounded-xl shadow-lg">
        <h2 class="text-3xl font-bold mb-2">Our Vision</h2>
        <p>To be the most trusted and accessible online platform for veterinary care, empowering pet owners with expert guidance, seamless scheduling, and compassionate support to ensure the lifelong health and happiness of every animal.</p>
      </div>
    </div>

    <div id="missionBox" class="opacity-0 transform -translate-x-10 transition-all duration-700 ease-out">
      <div class="bg-[#89cbdf] text-gray-800 p-6 rounded-xl shadow-lg">
        <h2 class="text-3xl font-bold mb-2">Our Mission</h2>
        <p>Our mission is to connect pet owners with high-quality veterinary services through a user-friendly digital platform. We strive to offer convenient appointment booking, emergency support, educational resources, and a caring community—because every pet deserves exceptional care.</p>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener("DOMContentLoaded", () =>{
    const elements = [
      { el: document.getElementById("visionBox"), from: "translate-x-10"},
      { el: document.getElementById("missionBox"), from: "-translate-x-10"}
    ];

    const observer = new IntersectionObserver((entries) =>{
      entries.forEach(entry =>{
        const target = entry.target;
        const original = elements.find(e => e.el === target);
        if (entry.isIntersecting) {
          target.classList.remove("opacity-0", original.from);
          target.classList.add("opacity-100", "translate-x-0");
        } else {
          target.classList.remove("opacity-100", "translate-x-0");
          target.classList.add("opacity-0", original.from);
        }
      });
    }, {
      threshold: 0.3
    });

    elements.forEach(({ el }) => observer.observe(el));
  });
</script>

<section class="py-12 px-4 bg-[#89cbdf] text-center">
  <img src="assets/images/dev-icon.png" alt="Developer Icon" class="mx-auto mb-4 w-16 h-16">
  <h2 class="text-4xl font-bold text-gray-800 mb-10">Meet the Developers</h2>

  <div class="flex flex-col md:flex-row justify-center items-center gap-10">

    <div class="flex flex-col items-center">
      <img src="assets/images/dev1.jpg" alt="Developer 1" class="w-40 h-40 rounded-full object-cover shadow-lg mb-4">
      <h3 class="text-xl font-semibold text-white">Jesryl John Alacapa</h3>
      <p class="text-sm text-blue-100">Frontend Developer</p>
    </div>

    <div class="flex flex-col items-center">
      <img src="assets/images/dev2.jpg" alt="Developer 2" class="w-40 h-40 rounded-full object-cover shadow-lg mb-4">
      <h3 class="text-xl font-semibold text-white">Paul Felippe Tibo-oc</h3>
      <p class="text-sm text-blue-100">Backend Developer</p>
    </div>

    <div class="flex flex-col items-center">
      <img src="assets/images/dev3.jpg" alt="Developer 3" class="w-40 h-40 rounded-full object-cover shadow-lg mb-4">
      <h3 class="text-xl font-semibold text-white">Christian Barbas Jr.</h3>
      <p class="text-sm text-blue-100">Backend Developer</p>
    </div>
  </div>
</section>


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
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
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
                  <img src="assets/images/logo.png" class="h-8 me-3" alt="Logo"/>
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
                userSection.innerHTML = `
                    <span class="text-white font-semibold text-lg">Welcome, ${data.username}!</span>
                    <form action="logout.php" method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="text-red-500 hover:underline ml-4">Logout</button>
    </form>
                `;
            } else {
                userSection.innerHTML = `
                    <div class="flex items-center md:order-2 space-x-4 md:space-x-6 rtl:space-x-reverse">
                    <a href="choose_role.php">
                        <div class="flex space-x-1">
                            <button class="py-2 px-4 text-gray-900 rounded-lg hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 dark:text-white md:dark:hover:text-[#DBE48C] dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700 hover:border-blue-700 md:dark:hover:border-[#DBE48C] transition-all duration-300">Sign Up
                            </button>
                    </a>
                    <a href="login.php">
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
    <script src="api/asset_proxy.php?f=vendor/flowbite/flowbite.min.js"></script>
    <script src="api/asset_proxy.php?f=assets/js/JS.js"></script>
  </body>
</html>