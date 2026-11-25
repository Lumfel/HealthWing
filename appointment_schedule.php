<?php
/**
 * HealthWing / VetSystem — Smart Automated Appointment Booking Page (appointment_schedule.php)
 *
 * Improvements over legacy version:
 * - Uses unified role-aware navigation (`includes/navbar.php`).
 * - Allows logged-in owners to select from their existing registered pets OR register a new pet inline.
 * - Live Smart Slot Preview: queries `api/scheduler.php?action=preview_slot`as the user selects a service
 *   and date/time, displaying the auto-assigned Veterinarian (matched by specialty & workload),
 *   Clinic Room, Duration, and any automatic conflict-resolution shift before they even submit.
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
require_once __DIR__ . '/includes/db.php';

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);
$username = $isLoggedIn ? $_SESSION['username'] : null;

$ownerInfo = null;
$ownerPets = [];
$vetsList = [];

try {
    $conn = hw_get_db();
    if ($isLoggedIn && hw_is_owner()) {
        $stmt = $conn->prepare('SELECT Owner_ID, First_Name, Last_Name, email, Contact FROM owner WHERE username = ? AND Deleted_At IS NULL LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $ownerInfo = $row;
            $pStmt = $conn->prepare('SELECT Pet_ID, Pet_Name, Species, Pet_Type, Age FROM pet WHERE Owner_ID = ? AND Deleted_At IS NULL ORDER BY Pet_Name ASC');
            $pStmt->bind_param('i', $row['Owner_ID']);
            $pStmt->execute();
            $ownerPets = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $pStmt->close();
        }
        $stmt->close();
    }

    $vRes = $conn->query("SELECT Veterinarian_ID, First_Name, Last_Name, Specialties FROM veterinarian WHERE COALESCE(Status, 'Approved') = 'Approved' AND Deleted_At IS NULL ORDER BY First_Name ASC");
    if ($vRes) {
        $vetsList = $vRes->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
} catch (Throwable $e) {
    // Fallback if DB is not reachable yet
}

$prefillDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
    ? ($_GET['date'] . 'T09:00')
    : date('Y-m-d\TH:00', strtotime('+2 hours'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <title>Smart Appointment Scheduling | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet"/>
    <link href="api/asset_proxy.php?f=admin/dashboard.css" rel="stylesheet"/>
    <style>
        body { background: #F4F9FA; color: #16262A; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .book-wrap { max-width: 860px; margin: 32px auto 70px; padding: 0 20px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: #16262A; margin-bottom: 6px; }
        .form-input {
            width: 100%; padding: 10px 12px; border: 1px solid #C8D8DC; border-radius: 8px;
            font-size: 13.5px; background: #fff; color: #16262A;
        }
        .form-input:read-only { background: #F1F6F7; color: #62787B; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="book-wrap">
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:16px;padding:28px;box-shadow:0 12px 32px rgba(15,35,42,0.06);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:6px;background:#E7F3F7;color:#1F5B70;font-size:11.5px;font-weight:800;padding:4px 10px;border-radius:999px;margin-bottom:6px;">
                     Automated Conflict-Free Scheduling Engine
                </div>
                <h1 style="font-size:24px;font-weight:800;margin:0;">Schedule a Veterinary Appointment</h1>
                <p style="color:#62787B;font-size:13.5px;margin:4px 0 0 0;">
                    Select your service and preferred time. Our algorithm automatically matches the best available specialist veterinarian and clinic room.
                </p>
            </div>
            <a href="public_calendar.php" class="btn btn-secondary btn-sm" style="text-decoration:none;">View Public Calendar</a>
        </div>

        <form action="submit_booking.php" method="POST" id="smartBookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:18px;">
                <div>
                    <label class="form-label">Owner Name</label>
                    <input type="text" name="owner_name" class="form-input"
                           value="<?php echo $ownerInfo ? htmlspecialchars(trim($ownerInfo['First_Name'] . ''. $ownerInfo['Last_Name'])) : ''; ?>"
                           <?php echo $ownerInfo ? 'readonly': 'required placeholder="Enter your full name"'; ?>/>
                </div>
                <div>
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input"
                           value="<?php echo $ownerInfo ? htmlspecialchars($ownerInfo['email']) : ''; ?>"
                           <?php echo $ownerInfo ? 'readonly': 'required placeholder="Enter your email"'; ?>/>
                </div>
                <div>
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="contact" class="form-input"
                           value="<?php echo $ownerInfo ? htmlspecialchars($ownerInfo['Contact']) : ''; ?>"
                           <?php echo $ownerInfo ? 'readonly': 'required placeholder="09XX-XXX-XXXX"'; ?>/>
                </div>

                <?php if (!empty($ownerPets)): ?>
                <div>
                    <label class="form-label">Select Your Pet</label>
                    <select name="pet_id" id="petSelect" class="form-input" onchange="toggleNewPetFields(this.value)">
                        <?php foreach ($ownerPets as $p): ?>
                            <option value="<?php echo (int)$p['Pet_ID']; ?>">
                                 <?php echo htmlspecialchars($p['Pet_Name']); ?>(<?php echo htmlspecialchars($p['Species'] ?: $p['Pet_Type']); ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="new">+ Register a New Pet...</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- New Pet Inline Fields (shown for guests, owners with 0 pets, or when "+ Register a New Pet"is chosen) -->
            <div id="newPetFields" style="<?php echo !empty($ownerPets) ? 'display:none;': 'display:grid;'; ?>grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:18px;background:#F6FAFA;padding:14px;border-radius:10px;border:1px dashed #B5DCE8;">
                <div>
                    <label class="form-label">Pet Name</label>
                    <input type="text" name="pet_name" id="petNameInput" class="form-input" placeholder="e.g. Mochi, Bruno"<?php echo empty($ownerPets) ? 'required': ''; ?>/>
                </div>
                <div>
                    <label class="form-label">Pet Species</label>
                    <select name="pet_species" class="form-input">
                        <option value="Dog">Dog</option>
                        <option value="Cat">Cat</option>
                        <option value="Bird">Bird</option>
                        <option value="Rabbit">Rabbit</option>
                        <option value="Small Mammal / Exotic">Small Mammal / Exotic</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:18px;">
                <div>
                    <label class="form-label">Clinical Service</label>
                    <select name="service" id="serviceSelect" class="form-input"required onchange="previewSmartSlot()">
                        <option value="Wellness Care">Wellness Care (30 mins)</option>
                        <option value="Vaccination">Vaccination (30 mins)</option>
                        <option value="Parasite Control">Parasite Control (30 mins)</option>
                        <option value="Dental">Dental Cleaning & Care (60 mins)</option>
                        <option value="Grooming">Full Grooming (60 mins)</option>
                        <option value="Surgery">Veterinary Surgery (90 mins)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Preferred Date & Time</label>
                    <input type="datetime-local" name="appointment_time" id="appointmentTimeInput" class="form-input"
                           value="<?php echo htmlspecialchars($prefillDate); ?>"required onchange="previewSmartSlot()"/>
                </div>
                <div>
                    <label class="form-label">Preferred Veterinarian (Optional)</label>
                    <select name="preferred_vet_id" id="preferredVetSelect" class="form-input" onchange="previewSmartSlot()">
                        <option value="">Auto-Assign Best Specialist</option>
                        <?php foreach ($vetsList as $v): ?>
                            <option value="<?php echo (int)$v['Veterinarian_ID']; ?>">
                                Dr. <?php echo htmlspecialchars($v['First_Name'] . ''. $v['Last_Name']); ?>(<?php echo htmlspecialchars($v['Specialties'] ?: 'General'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Live Algorithmic Slot & Resource Assignment Preview -->
            <div id="smartSlotPreview" style="background:#EEF7F9;border:1px solid #B5DCE8;border-radius:12px;padding:15px 18px;margin-bottom:18px;font-size:13px;">
                <div style="font-weight:800;color:#1F5B70;margin-bottom:4px;">Smart Scheduler Preview</div>
                <div id="smartSlotPreviewBody" style="color:#16262A;">Checking clinic room & veterinarian availability...</div>
            </div>

            <div style="margin-bottom:20px;">
                <label class="form-label">Additional Clinical Notes / Symptoms (Optional)</label>
                <textarea name="details" class="form-input" rows="3" placeholder="Share any symptoms, dietary notes, or special requests..."></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <a href="index.php" class="btn btn-secondary" style="text-decoration:none;padding:11px 20px;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding:11px 24px;font-size:14px;">
                    Confirm & Auto-Lock Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleNewPetFields(val) {
    const box = document.getElementById('newPetFields');
    const nameInp = document.getElementById('petNameInput');
    if (val === 'new') {
        box.style.display = 'grid';
        nameInp.required = true;
    } else {
        box.style.display = 'none';
        nameInp.required = false;
        nameInp.value = '';
    }
}

async function previewSmartSlot() {
    const service = document.getElementById('serviceSelect').value;
    const apptTime = document.getElementById('appointmentTimeInput').value;
    const vetId = document.getElementById('preferredVetSelect').value;
    const bodyEl = document.getElementById('smartSlotPreviewBody');

    if (!service || !apptTime) return;
    if (window.HWBone) { bodyEl.innerHTML = HWBone.slotPreview(); }
    try {
        const res = await fetch('api/scheduler.php?action=preview_slot', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({
                service,
                appointment_time: apptTime.replace('T', ''),
                vet_id: vetId || null,
            }),
        });
        const data = await res.json();
        if (!data.success) return;

        const shiftBanner = data.was_shifted
            ? `<div style="color:#8A5A00;background:#FFF8E7;padding:6px 10px;border-radius:6px;margin-bottom:8px;font-weight:600;">
                 [Notice] Requested time (${data.requested_time}) falls outside clinic hours or has a conflict. Auto-selected nearest conflict-free slot: <strong>${data.appointment_time}</strong>.
               </div>`
            : `<div style="color:#246A46;font-weight:700;margin-bottom:6px;">Requested time slot is open and conflict-free!</div>`;

        bodyEl.innerHTML = `
            ${shiftBanner}
            <div style="display:flex;flex-wrap:wrap;gap:18px;font-size:12.5px;">
                <span><strong>Slot:</strong>${data.appointment_time} – ${data.end_time.slice(11, 16)} (${data.duration_minutes} mins)</span>
                <span><strong>Matched Vet:</strong>Dr. ${data.vet_name} (${data.vet_specialty})</span>
                <span><strong>Reserved Room:</strong>${data.room_name} (${data.room_type})</span>
            </div>
        `;
    } catch (e) {
        bodyEl.textContent = 'Slot will be automatically validated and assigned upon submission.';
    }
}

document.addEventListener('DOMContentLoaded', previewSmartSlot);
</script>
</body>
</html>
