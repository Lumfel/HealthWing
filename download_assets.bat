@echo off
REM ============================================================
REM  HealthWing / VetSystem — one-time offline setup
REM  Run this ONCE while you have internet. It downloads every
REM  external file your site currently pulls from the internet
REM  (Tailwind, Flowbite, Swiper, Alpine, Chart.js, Motion) and
REM  saves them inside a "vendor" folder in this project.
REM  After this finishes, your whole site works with NO internet.
REM ============================================================

echo Creating vendor folders...
mkdir vendor\tailwind 2>nul
mkdir vendor\flowbite 2>nul
mkdir vendor\swiper 2>nul
mkdir vendor\alpine 2>nul
mkdir vendor\chartjs 2>nul
mkdir vendor\motion 2>nul

echo Downloading Tailwind CSS (v2, static)...
curl -L -o vendor\tailwind\tailwind.min.css "https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"

echo Downloading Tailwind CSS (v4, browser build)...
curl -L -o vendor\tailwind\tailwind-browser.js "https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"

echo Downloading Flowbite CSS...
curl -L -o vendor\flowbite\flowbite.min.css "https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css"

echo Downloading Flowbite JS...
curl -L -o vendor\flowbite\flowbite.min.js "https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"

echo Downloading Swiper CSS...
curl -L -o vendor\swiper\swiper-bundle.min.css "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"

echo Downloading Swiper JS...
curl -L -o vendor\swiper\swiper-bundle.min.js "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"

echo Downloading Alpine.js...
curl -L -o vendor\alpine\alpine.min.js "https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"

echo Downloading Chart.js...
curl -L -o vendor\chartjs\chart.min.js "https://cdn.jsdelivr.net/npm/chart.js"

echo Downloading Motion...
curl -L -o vendor\motion\motion.js "https://cdn.jsdelivr.net/npm/motion@latest/dist/motion.js"

echo.
echo ============================================================
echo  Done! Check the "vendor" folder just created next to this
echo  script — each file should be a few KB to a few hundred KB,
echo  NOT 0 bytes. If any file is 0 bytes or missing, that one
echo  download failed (check your internet connection and re-run
echo  this script).
echo ============================================================
pause
