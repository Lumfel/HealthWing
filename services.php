<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();

$services = [
    'wellness' => [
        'title' => 'Wellness & Prevention',
        'hero_title' => 'Taking a Proactive Approach to Pet Health Check-ups',
        'hero_image' => 'assets/images/wellnesspage.jpg',
        'intro' => 'Preventing illness is essential to ensuring long-term health for our beloved pets. Regular wellness exams help monitor your pet\'s overall health, detect early signs of illness, and intervene before problems become serious. At HealthWing Clinic, we recommend scheduling a comprehensive check-up at least once a year.',
        'primary_image' => 'assets/images/wellnesspic1.jpg',
        'primary_alt' => 'Wellness Exam Image',
        'section1_title' => 'What Is Included in a Wellness Exam?',
        'section1_lead' => 'At HealthWing Clinic, we prioritize giving each pet the attention they deserve during their wellness visit while guiding pet owners in making the best care decisions.',
        'section1_points_title' => 'A typical wellness exam includes:',
        'section1_points' => [
            'A thorough physical examination checking vital signs, coat, skin, eyes, ears, and oral health.',
            'Administering necessary core and lifestyle vaccinations.',
            'Running blood tests and fecal exams to screen for infections and parasites.',
            'Deworming treatment and flea, tick, and heartworm prevention guidance.',
            'Discussing tailored nutrition, weight management, and daily care.',
        ],
        'secondary_title' => 'Why Parasite Prevention Matters',
        'secondary_body' => 'Year-round parasite prevention protects pets against heartworm disease, intestinal worms, and flea allergy dermatitis before infestations take hold.',
        'secondary_image' => 'assets/images/wellnesspic2.png',
    ],
    'vaccination' => [
        'title' => 'Vaccinations',
        'hero_title' => 'Preventing Illness in Pets with Essential Dog and Cat Vaccines',
        'hero_image' => 'assets/images/vaccinationpage.jpg',
        'intro' => 'Everyday outdoor activity can expose dogs and cats to serious infectious diseases such as rabies, parvovirus, distemper, and feline leukemia. HealthWing Clinic provides safe, tailored vaccination protocols for puppies, kittens, and adult pets.',
        'primary_image' => 'assets/images/vaccinationpic.jpg',
        'primary_alt' => 'Cat and Dog Vaccination',
        'section1_title' => 'Diseases Our Vaccines Protect Against',
        'section1_lead' => 'Routine immunizations maintain your companion\'s immunity against contagious and life-threatening illnesses:',
        'section1_points_title' => 'Core and targeted protection:',
        'section1_points' => [
            'Rabies: Prevents a fatal viral infection affecting the nervous system in both pets and humans.',
            'Canine Parvovirus: Protects puppies and adult dogs from severe gastrointestinal illness.',
            'Distemper: Guards against a systemic virus affecting the respiratory and nervous systems.',
            'Feline Leukemia: Protects cats from viral immune suppression transmitted through close contact.',
        ],
        'secondary_title' => 'Tailored Vaccination Plans for Every Pet',
        'secondary_body' => 'Before recommending or administering vaccines, our veterinarians perform a physical exam and review your pet\'s age, health history, travel habits, and daily environment.',
        'secondary_image' => 'assets/images/PetVaccine.png',
    ],
    'dental' => [
        'title' => 'Dental Care',
        'hero_title' => 'Protect Your Pet\'s Health with Regular Dog and Cat Dental Cleanings',
        'hero_image' => 'assets/images/dentalpage.jpg',
        'intro' => 'Dental disease is one of the most common health issues in adult dogs and cats. HealthWing Clinic provides professional ultrasonic cleanings, oral exams, and home-care guidance to support a lifetime of healthy smiles.',
        'primary_image' => 'assets/images/dentalpic.jpg',
        'primary_alt' => 'Dental care for pets',
        'section1_title' => 'How to Spot Dental Problems in Dogs and Cats',
        'section1_lead' => 'Pets naturally hide oral discomfort, so recognizing early indicators helps prevent painful periodontal disease:',
        'section1_points_title' => 'Common signs include:',
        'section1_points' => [
            'Persistent bad breath or inflamed, red gums.',
            'Yellow or brown tartar buildup along the gum line.',
            'Excessive drooling, pawing at the mouth, or dropping food.',
            'Difficulty chewing or sudden disinterest in chew toys.',
        ],
        'secondary_title' => 'Comprehensive Clinical Dental Procedures',
        'secondary_body' => 'Our dental service includes thorough oral evaluations, ultrasonic scaling above and below the gum line, enamel polishing, fluoride care, and safe extractions when medically necessary.',
        'secondary_image' => 'assets/images/DogTeeth.jpg',
    ],
    'surgery' => [
        'title' => 'Surgical & Reproductive Care',
        'hero_title' => 'Animal Surgery and Reproductive Care',
        'hero_image' => 'assets/images/surgerypage.jpg',
        'intro' => 'HealthWing Clinic maintains a modern surgical suite for spay and neuter procedures, mass removals, and soft-tissue operations, adhering to strict anesthetic monitoring and pain-management standards.',
        'primary_image' => 'assets/images/surgerypic.jpg',
        'primary_alt' => 'Veterinary surgical suite care',
        'section1_title' => 'Surgical Safety Protocols',
        'section1_lead' => 'To minimize anesthetic risk and support smooth recovery, every surgical patient follows strict safety steps:',
        'section1_points_title' => 'Key safety measures:',
        'section1_points' => [
            'Pre-surgical blood screening to assess organ function and anesthetic readiness.',
            'IV catheter placement for fluid balance and rapid medication support.',
            'Continuous vital-sign monitoring throughout the procedure.',
            'Thermal support and dedicated post-operative recovery observation.',
        ],
        'secondary_title' => 'Spay, Neuter & Preventive Procedures',
        'secondary_body' => 'Timely reproductive surgery helps reduce the risk of uterine infections and hormone-related cancers while curbing unwanted roaming and marking behaviors.',
        'secondary_image' => 'assets/images/surgerypic2.jpg',
    ],
    'parasite' => [
        'title' => 'Parasite Control',
        'hero_title' => 'Limiting Health Risks with Effective Parasite Control',
        'hero_image' => 'assets/images/parasitepage.jpg',
        'intro' => 'Fleas, ticks, mosquitoes, and intestinal worms remain active year-round. Consistent diagnostic screening and veterinarian-recommended preventatives keep pets safe from vector-borne disease and discomfort.',
        'primary_image' => 'assets/images/parasitepic1.jpg',
        'primary_alt' => 'Parasite Prevention',
        'section1_title' => 'Common Signs of Parasite Infestations',
        'section1_lead' => 'Early detection prevents minor irritation from turning into systemic illness:',
        'section1_points_title' => 'Signs to watch for:',
        'section1_points' => [
            'Frequent scratching, biting at the skin, or visible fleas and flea dirt.',
            'Hair loss, localized redness, or allergic dermatitis.',
            'Vomiting, diarrhea, weight loss, or visible intestinal parasites.',
            'Coughing or exercise intolerance associated with heartworm infection.',
        ],
        'secondary_title' => 'Testing, Treatment & Prevention',
        'secondary_body' => 'We combine in-house fecal testing with safe oral and topical preventatives tailored to your pet\'s weight, species, and outdoor exposure.',
        'secondary_image' => 'assets/images/PetParasite.jpg',
    ],
    'grooming' => [
        'title' => 'Grooming & Hygiene',
        'hero_title' => 'Regular Pet Grooming for Health and Wellbeing',
        'hero_image' => 'assets/images/groomingpage.jpg',
        'intro' => 'Routine grooming supports skin and coat health while allowing trained staff to notice lumps, ear irritation, or coat parasites early. Our sessions focus on pet comfort using gentle, pet-safe products.',
        'primary_image' => 'assets/images/groomingpic1.jpg',
        'primary_alt' => 'Pet Grooming Session',
        'section1_title' => 'What Is Included in a Grooming Session?',
        'section1_lead' => 'Whether your pet needs a routine bath, sanitary trim, or full coat styling, we prioritize calm handling and thorough hygiene:',
        'section1_points_title' => 'Standard grooming care:',
        'section1_points' => [
            'Bathing with mild, pet-safe shampoos and skin-soothing conditioners.',
            'De-shedding and gentle brushing to prevent painful coat matting.',
            'Safe nail trimming to maintain healthy paw posture.',
            'Routine ear cleaning and hygienic coat trimming.',
        ],
        'secondary_title' => 'Supporting Everyday Comfort',
        'secondary_body' => 'Scheduled grooming keeps coats breathable, reduces shedding around the home, and prevents overgrown nails and trapped moisture in ears.',
        'secondary_image' => 'assets/images/interior-design-veterinary-clinic.jpg',
    ],
];

$slug = strtolower(trim((string)($_GET['service'] ?? 'wellness')));
if (!isset($services[$slug])) {
    $slug = 'wellness';
}
$current = $services[$slug];
$csrfToken = hw_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($current['title'], ENT_QUOTES, 'UTF-8'); ?> | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/flowbite/flowbite.min.css" rel="stylesheet"/>
    <link href="api/asset_proxy.php?f=assets/css/hero-glass.css" rel="stylesheet"/>
    <script src="api/asset_proxy.php?f=vendor/tailwind/tailwind-browser.js"></script>
</head>
<body class="bg-white text-gray-800">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="bg-[#1F3A3D] border-t border-white/15">
    <div class="max-w-6xl mx-auto px-4 py-2.5 flex flex-wrap gap-2 justify-center">
        <?php foreach ($services as $key => $item): ?>
            <a href="services.php?service=<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?php echo $key === $slug ? 'bg-[#DBE48C] text-[#16262A]' : 'text-white hover:bg-white/10'; ?>">
                <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="relative w-full h-105 bg-cover bg-center flex items-center justify-center hero-banner" style="background-image: url('<?php echo htmlspecialchars($current['hero_image'], ENT_QUOTES, 'UTF-8'); ?>');" id="heroBanner">
    <div class="hero-dark-overlay"></div>
    <canvas class="glass-canvas" id="glassCanvas"></canvas>
    <div class="glass-card-text text-center" id="glassCardText">
        <h1 class="text-3xl font-extrabold text-white"><?php echo htmlspecialchars($current['hero_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="text-2xl text-white mt-4">HealthWing Veterinary Clinic</p>
    </div>
</div>

<script src="api/asset_proxy.php?f=assets/js/glass-shader.js"></script>
<script>
(function() {
    const canvas = document.getElementById('glassCanvas');
    const cardText = document.getElementById('glassCardText');
    if (canvas && cardText && typeof initGlassShader === 'function') {
        initGlassShader(canvas, <?php echo json_encode($current['hero_image']); ?>, cardText);
    }
})();
</script>

<div class="w-full bg-[#89cbdf] py-10 text-center">
    <p class="text-base font-medium text-gray-800 max-w-4xl mx-auto px-4">
        <?php echo htmlspecialchars($current['intro'], ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <hr class="border-t-2 w-3/4 mx-auto mt-8 mb-4">
    <p class="text-2xl font-semibold text-white">Book an appointment today for a healthier pet</p>
    <a href="appointment_schedule.php" class="inline-block mt-4 px-6 py-3 text-white font-medium rounded-lg bg-gradient-to-r from-[#DA4A6B] to-[#FFE023] hover:opacity-90">
        Request an Appointment
    </a>
</div>

<div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-8 bg-white p-6 md:p-12">
    <div class="w-full md:w-1/2">
        <img src="<?php echo htmlspecialchars($current['primary_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($current['primary_alt'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-auto rounded-lg shadow-lg">
    </div>
    <div class="w-full md:w-1/2 text-gray-800">
        <h2 class="text-2xl font-extrabold mb-4"><?php echo htmlspecialchars($current['section1_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="mb-4 text-base"><?php echo htmlspecialchars($current['section1_lead'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="font-bold mb-2 text-base"><?php echo htmlspecialchars($current['section1_points_title'], ENT_QUOTES, 'UTF-8'); ?></p>
        <ul class="list-disc pl-5 space-y-2 text-base">
            <?php foreach ($current['section1_points'] as $pt): ?>
                <li><?php echo htmlspecialchars($pt, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="bg-[#89cbdf] py-10 px-6">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-8">
        <div class="md:w-2/3 text-gray-900">
            <h2 class="text-2xl font-extrabold mb-3"><?php echo htmlspecialchars($current['secondary_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="text-base leading-relaxed"><?php echo htmlspecialchars($current['secondary_body'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="md:w-1/3 flex justify-center">
            <img src="<?php echo htmlspecialchars($current['secondary_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($current['secondary_title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-64 h-auto rounded-lg shadow-md">
        </div>
    </div>
</div>

<div class="bg-white py-10 px-4">
    <h2 class="text-center text-3xl font-bold text-gray-800 mb-5">Hours of Operation</h2>
    <div class="max-w-lg mx-auto bg-[#89cbdf] text-white rounded-lg p-6 shadow-md">
        <ul class="space-y-2 text-lg">
            <li class="flex justify-between"><span>Monday</span><span>8:00 AM - 6:00 PM</span></li>
            <li class="flex justify-between"><span>Tuesday</span><span>8:00 AM - 6:00 PM</span></li>
            <li class="flex justify-between"><span>Wednesday</span><span>8:00 AM - 6:00 PM</span></li>
            <li class="flex justify-between"><span>Thursday</span><span>8:00 AM - 6:00 PM</span></li>
            <li class="flex justify-between"><span>Friday</span><span>8:00 AM - 6:00 PM</span></li>
            <li class="flex justify-between"><span>Saturday</span><span>9:00 AM - 3:00 PM</span></li>
            <li class="flex justify-between"><span>Sunday</span><span>Closed</span></li>
        </ul>
    </div>
</div>

<div id="emergency-booking" class="bg-gray-100 p-6 rounded-lg shadow-md mt-6 mx-auto max-w-3xl">
    <h3 class="text-xl font-bold text-gray-700 text-center mb-4">Emergency Booking</h3>
    <form action="submit_emergency.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Name</label>
            <input type="text" name="name" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your name" required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Email</label>
            <input type="email" name="email" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your email" required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Phone Number</label>
            <input type="tel" name="phone" class="border border-gray-300 p-2 rounded-lg" placeholder="Enter your phone number" required>
        </div>
        <div class="flex flex-col">
            <label class="font-semibold text-gray-700">Additional Details</label>
            <textarea name="details" class="border border-gray-300 p-2 rounded-lg h-24" placeholder="Describe the emergency..."></textarea>
        </div>
        <div class="md:col-span-2 flex flex-col">
            <label class="font-semibold text-gray-700">Upload Image (Optional)</label>
            <input type="file" name="image" accept="image/*" class="border border-gray-300 p-2 rounded-lg">
        </div>
        <div class="md:col-span-2 flex justify-center">
            <button type="submit" class="text-white px-5 py-2 rounded-lg transition bg-gradient-to-r from-[#DA4A6B] to-[#FFE023]">Submit</button>
        </div>
    </form>
</div>

<footer class="bg-white mt-8 border-t border-gray-200">
    <div class="mx-auto w-full max-w-screen-xl p-4 py-6 flex flex-wrap justify-between items-center gap-4">
        <a href="index.php" class="flex items-center">
            <img src="assets/images/logo.png" class="h-8 me-3" alt="HealthWing Logo"/>
            <span class="text-2xl font-semibold text-gray-900">HealthWing</span>
        </a>
        <div class="text-sm text-gray-500">Comprehensive Veterinary Care</div>
    </div>
</footer>

<script src="api/asset_proxy.php?f=vendor/flowbite/flowbite.min.js"></script>
<script src="api/asset_proxy.php?f=assets/js/JS.js"></script>
</body>
</html>
