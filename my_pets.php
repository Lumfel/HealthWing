<?php
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
require_once __DIR__ . '/guest_notice.php';
require_once __DIR__ . '/includes/db.php';

if (!hw_is_owner()) {
    show_guest_notice("You need to sign in as a pet owner to view and manage your pets.");
    exit();
}

$conn = hw_get_db();
$stmt = $conn->prepare('SELECT Owner_ID, First_Name FROM owner WHERE username = ?');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$ownerId = null;
$ownerFirstName = '';
if ($result && $row = $result->fetch_assoc()) {
    $ownerId = $row['Owner_ID'];
    $ownerFirstName = $row['First_Name'];
}
$stmt->close();
$conn->close();

if (!$ownerId) {
    show_guest_notice("We couldn't find an owner account matching your session. Please sign in again.");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content=" width=device-width, initial-scale=1.0">
    <title>My Pets · HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet"/>
    <style>
        :root { --bg: #F4F9FA; --surface: #FFFFFF; --border: #E3EBEC; }
        body { background: var(--bg); font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .pet-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .pet-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,0.08); }
        .pet-photo-wrap {
            width: 100%; height: 190px; background: var(--accent-soft);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative;
        }
        .pet-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .pet-photo-placeholder { color: var(--accent); opacity: 0.5; }
        .pet-card-body { padding: 16px 18px; }
        .pet-name { font-size: 17px; font-weight: 800; color: var(--text); margin: 0 0 2px 0; }
        .pet-meta { font-size: 12.5px; color: var(--text-muted); margin-bottom: 10px; }
        .pet-field { font-size: 12.5px; margin-bottom: 6px; }
        .pet-field strong { color: var(--text); }
        .upload-label {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600; color: var(--accent);
            cursor: pointer; margin-top: 8px;
        }
        .upload-label input { display: none; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,35,42,0.55); z-index: 2000; align-items: center; justify-content: center; padding: 16px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #fff; border-radius: 14px; padding: 22px; width: min(94vw, 460px); }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div style="max-width: 1180px; margin: 28px auto 60px; padding: 0 20px;">
    <div class="page-head">
        <div>
            <h1>My Pets & Medical Profiles</h1>
            <p>Manage <?php echo htmlspecialchars($ownerFirstName); ?>'s pets, medical history, and photos</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary" onclick="document.getElementById('addOwnerPetModal').classList.add('open')">+ Register New Pet</button>
            <a href="appointment_schedule.php" class="btn btn-secondary" style="text-decoration:none;">Book Checkup →</a>
        </div>
    </div>

    <div id="petGrid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px;">
        <div class="empty">Loading your pets...</div>
    </div>
</div>

<!-- Add Pet Modal for Owner -->
<div class="modal-overlay" id="addOwnerPetModal">
    <div class="modal-box">
        <h3 style="margin:0 0 14px 0;font-size:18px;">Register a New Pet</h3>
        <form id="ownerAddPetForm">
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Pet Name</label>
                <input type="text" id="opName"required placeholder="e.g. Luna" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Species</label>
                    <select id="opSpecies" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
                        <option value="Dog">Dog</option>
                        <option value="Cat">Cat</option>
                        <option value="Bird">Bird</option>
                        <option value="Rabbit">Rabbit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Age (Years)</label>
                    <input type="number" id="opAge" min="0" placeholder="e.g. 3" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Breed / Type</label>
                <input type="text" id="opBreed" placeholder="e.g. Golden Retriever, Persian" style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Known Allergies / Past Conditions (Optional)</label>
                <textarea id="opPast" rows="2" placeholder="Important for our emergency triage algorithm..." style="width:100%;padding:9px;border:1px solid #C8D8DC;border-radius:8px;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addOwnerPetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Pet</button>
            </div>
        </form>
    </div>
</div>

<script>
function speciesIcon() {
    return `<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="pet-photo-placeholder">
        <path d="M4.5 20.5c0-3 2.5-5 5-5h5c2.5 0 5 2 5 5"/>
        <circle cx="7" cy="6.5" r="2"/><circle cx="12" cy="4.5" r="2"/>
        <circle cx="17" cy="6.5" r="2"/><circle cx="12" cy="10.5" r="2.2"/>
    </svg>`;
}

function loadPets() {
    const g = document.getElementById("petGrid");
    if (g && window.HWBone) g.innerHTML = HWBone.petCards(3);
    fetch('get_my_pets.php')
        .then(res => res.json())
        .then(data =>{
            const grid = document.getElementById('petGrid');
            if (!data.success || data.pets.length === 0) {
                grid.innerHTML = `<div class="empty">No pets registered yet. Click "+ Register New Pet"above to add your pet!</div>`;
                return;
            }
            grid.innerHTML = data.pets.map(p =>`
                <div class="pet-card">
                    <div class="pet-photo-wrap" id="photoWrap-${p.Pet_ID}">
                        ${p.Photo_Path ? `<img src="${p.Photo_Path}" alt="${p.Pet_Name}">`: speciesIcon()}
                    </div>
                    <div class="pet-card-body">
                        <div class="pet-name">${p.Pet_Name}</div>
                        <div class="pet-meta">${p.Species || p.Pet_Type || 'Pet'}${p.Age ? '· '+ p.Age + 'yrs old': ''}</div>
                        ${p.Description ? `<div class="pet-field">${p.Description}</div>`: ''}
                        <div class="pet-field"><strong>Current ailment:</strong>${p.Current_Ailment || 'None noted'}</div>
                        <div class="pet-field"><strong>Current medications:</strong>${p.Current_Medications || 'None'}</div>
                        <div class="pet-field"><strong>Past conditions:</strong>${p.Past_Conditions || 'None on file'}</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                            <label class="upload-label" style="margin-top:0;">
                                <input type="file" accept="image/*" onchange="uploadPhoto(${p.Pet_ID}, this)">
                                 ${p.Photo_Path ? 'Change photo': 'Add photo'}
                            </label>
                            <a href="appointment_schedule.php" style="font-size:12px;font-weight:700;color:#1F3A3D;text-decoration:none;">Book Visit →</a>
                        </div>
                    </div>
                </div>
            `).join('');
        });
}

document.getElementById('ownerAddPetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('owner_add_pet.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json'},
        body: JSON.stringify({
            pet_name: document.getElementById('opName').value,
            species: document.getElementById('opSpecies').value,
            age: document.getElementById('opAge').value,
            pet_type: document.getElementById('opBreed').value,
            past_conditions: document.getElementById('opPast').value,
        })
    })
    .then(r => r.json())
    .then(data =>{
        if (data.success) {
            document.getElementById('addOwnerPetModal').classList.remove('open');
            e.target.reset();
            loadPets();
        } else {
            alert(data.message || 'Could not save pet.');
        }
    });
});

window.uploadPhoto = function(petId, input) {
    if (!input.files || !input.files[0]) return;
    const formData = new FormData();
    formData.append('petId', petId);
    formData.append('image', input.files[0]);
    fetch('upload_pet_photo.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data =>{
            if (data.success) loadPets();
            else alert(data.message || 'Could not upload photo.');
        });
};

loadPets();
</script>
</body>
</html>
