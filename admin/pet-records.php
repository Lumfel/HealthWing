<?php
$ADMIN_GUARD_IS_API = false;
require_once __DIR__ . '/auth_guard.php';
$admin_display_name = htmlspecialchars($_SESSION['username'] ?? 'Head Vet', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content=" width=device-width, initial-scale=1.0">
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../api/asset_proxy.php?f=admin/dashboard.css">
    <title>Pet Records · HealthWing Admin</title>
    <style>
        .pet-detail-row { display: none; background: var(--bg); }
        .pet-detail-row.open { display: table-row; }
        .pet-detail-cell { padding: 16px 20px !important; }
        .field-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 3px; }
        .field-value { font-size: 13px; color: var(--text); margin-bottom: 12px; }
        .checkup-item { border-bottom: 1px solid var(--border); padding: 8px 0; font-size: 13px; }
        .checkup-item:last-child { border-bottom: none; }
        .expand-btn { cursor: pointer; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(20,30,32,0.45); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: var(--surface); border-radius: var(--radius); padding: 22px; width: 520px; max-width: 92vw; max-height: 85vh; overflow-y: auto; }
        .modal-box h3 { margin-top: 0; font-size: 16px; }
    </style>
    <link rel="stylesheet" href="../api/asset_proxy.php?f=assets/css/loader.css">
    <script src="../api/asset_proxy.php?f=assets/js/loader.js" defer></script>
</head>
<body class="admin-body">
    <button id="toggleDashboard1"></button>

    <aside class="side" id="dashboard">
        <div class="brand">
            <img src="../assets/images/logo.png" width="26" height="26" alt="HealthWing logo" style="border-radius: 6px;"/>
            <div class="brand-text">
                <strong>HealthWing</strong>
                <span>Admin panel</span>
            </div>
        </div>

        <nav>
            <a href="ADMIN_VET_1.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                Overview
            </a>
            <a href="CRUD.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.7-3.4 3-5.3 5.5-5.3s4.8 1.9 5.5 5.3"/><circle cx="17.5" cy="8.5" r="2.4"/><path d="M15.5 14.3c2.2.3 3.9 2 4.5 5"/></svg>
                Manage Users
            </a>
            <a href="appointment-report.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v3M16 3v3"/></svg>
                Appointment Summary
            </a>
            <a href="Schedule-Management.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                Schedule Management
            </a>
            <a href="service-utilization.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20V10M12 20V4M20 20v-7"/></svg>
                Service Utilization
            </a>
            <a href="client-activity.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3.5 17.5 9 11l4 3 7.5-8.5"/><path d="M15.5 5.5H20.5V10.5"/></svg>
                Client Activity
            </a>
            <a href="ratings-report.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3.5l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.4l-5.1 2.8 1-5.7-4.1-4 5.7-.8Z" stroke-linejoin="round"/></svg>
                Ratings Report
            </a>
            <a href="room-management.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M15 12v.01"/></svg>
                Room Management
            </a>
            <a href="emergency-cases.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3 3 8.5v7L12 21l9-5.5v-7Z" stroke-linejoin="round"/><path d="M12 8v5M12 15.5v.1"/></svg>
                Emergency Requests
            </a>
            <a href="pet-records.php" class="current">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4.5 20.5c0-3 2.5-5 5-5h5c2.5 0 5 2 5 5"/><circle cx="7" cy="6.5" r="2"/><circle cx="12" cy="4.5" r="2"/><circle cx="17" cy="6.5" r="2"/><circle cx="12" cy="10.5" r="2.2"/></svg>
                Pet Records
            </a>
            <a href="network-monitor.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="2"/><path d="M4.5 19.5c0-1.5 3-2.5 7.5-2.5s7.5 1 7.5 2.5"/><path d="M6 12a6 6 0 0112 0M3.5 8.5a10 10 0 0117 0"/></svg>
                Network Monitor
            </a>
            <a href="client-history.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="8.5"/></svg>
                Client History
            </a>
            <a href="../receipts.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                Receipts
            </a>
        </nav>

        <div class="exit-row">
            <div style="font-size:11.5px; color:#9FBEC0; padding: 0 4px 8px 4px;">Signed in as <strong style="color:#E4EDEC;"><?php echo $admin_display_name; ?></strong></div>
            <button class="exit-btn" onclick="window.location.href='../index.php'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H5a2 2 0 00-2 2v10a2 2 0 002 2h4M16 15l4-4-4-4M20 11H9"/></svg>
                View Site
            </button>
            <button class="exit-btn" style="margin-top:6px;" onclick="window.location.href='admin_logout.php'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Log Out
            </button>
        </div>
    </aside>

    <div class="main" id="mainArea">
        <div class="page-head">
            <div>
                <h1>Pet Records</h1>
                <p>Every pet on file, who owns them, and their medical history</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">+ Add Pet</button>
        </div>

        <div class="stats" id="petStats" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat">
                <div class="label">Total Pets on File</div>
                <div class="value" id="statTotalPets">–</div>
            </div>
            <div class="stat good">
                <div class="label">Owners With a Pet on File</div>
                <div class="value" id="statOwnersWithPets">–</div>
            </div>
            <div class="stat warn">
                <div class="label">Pets With No Checkup Yet</div>
                <div class="value" id="statNoCheckup">–</div>
            </div>
        </div>

        <div class="panel">
            <div style="overflow-x:auto;">
                <table class="data-table" id="petTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Pet</th>
                            <th>Owner</th>
                            <th>Age</th>
                            <th>Species</th>
                            <th>Current Ailment</th>
                            <th>Checkups</th>
                            <th>Last Checkup</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add / Edit Pet modal -->
    <div class="modal-overlay" id="petModal">
        <div class="modal-box">
            <h3 id="petModalTitle">Add Pet</h3>
            <form id="petForm">
                <input type="hidden" id="petId">
                <div class="form-row" style="margin-bottom:10px;">
                    <select id="ownerId"required style="flex:1; min-width:100%;">
                        <option value="">Select owner...</option>
                    </select>
                </div>
                <div class="form-row" style="margin-bottom:10px;">
                    <input type="text" id="petName" placeholder="Pet name"required style="flex:1; min-width:45%;">
                    <input type="number" id="petAge" placeholder="Age (years)" min="0" style="flex:1; min-width:45%;">
                </div>
                <div class="form-row" style="margin-bottom:10px;">
                    <input type="text" id="petSpecies" placeholder="Species (e.g. Dog, Cat, Bird)" style="flex:1; min-width:45%;">
                    <input type="text" id="petType" placeholder="Breed / type" style="flex:1; min-width:45%;">
                </div>
                <textarea id="petDescription" placeholder="Description (color, temperament, notable traits...)" style="width:100%; min-height:50px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13px; margin-bottom:10px;"></textarea>
                <textarea id="petPastConditions" placeholder="Past conditions" style="width:100%; min-height:50px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13px; margin-bottom:10px;"></textarea>
                <textarea id="petCurrentAilment" placeholder="Current ailment (if any)" style="width:100%; min-height:50px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13px; margin-bottom:10px;"></textarea>
                <textarea id="petCurrentMedications" placeholder="Current medications" style="width:100%; min-height:50px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13px; margin-bottom:14px;"></textarea>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Pet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        const toggleButton = document.getElementById("toggleDashboard1");
        const dashboard = document.getElementById("dashboard");
        const mainArea = document.getElementById("mainArea");
        toggleButton.addEventListener("click", () =>{
            dashboard.classList.toggle("collapsed");
            mainArea.classList.toggle("expanded");
        });
    })();

    let ownersCache = [];

    function loadOwners() {
        // Client-side caching: the owner list rarely changes within a single
        // admin session, so reuse it from sessionStorage instead of hitting
        // the server again every time this page loads.
        const cached = sessionStorage.getItem('hw_owners_cache');
        if (cached) {
            ownersCache = JSON.parse(cached);
            const sel = document.getElementById('ownerId');
            sel.innerHTML = '<option value="">Select owner...</option>'+
                ownersCache.map(o =>`<option value="${o.Owner_ID}">${o.First_Name} ${o.Last_Name}</option>`).join('');
            return Promise.resolve();
        }
        return fetch('manage_pets.php?action=owners')
            .then(res => res.json())
            .then(data =>{
                ownersCache = data.success ? data.owners : [];
                sessionStorage.setItem('hw_owners_cache', JSON.stringify(ownersCache));
                const sel = document.getElementById('ownerId');
                sel.innerHTML = '<option value="">Select owner...</option>'+
                    ownersCache.map(o =>`<option value="${o.Owner_ID}">${o.First_Name} ${o.Last_Name}</option>`).join('');
            });
    }

    function loadPets() {
        fetch('manage_pets.php?action=read')
            .then(res => res.json())
            .then(data =>{
                const tbody = document.querySelector('#petTable tbody');
                if (!data.success || data.pets.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" class="empty">No pets on file yet. Click "+ Add Pet"to add one.</td></tr>`;
                    document.getElementById('statTotalPets').textContent = 0;
                    document.getElementById('statOwnersWithPets').textContent = 0;
                    document.getElementById('statNoCheckup').textContent = 0;
                    return;
                }

                document.getElementById('statTotalPets').textContent = data.total_pets;
                document.getElementById('statOwnersWithPets').textContent = data.owners_with_pets;
                document.getElementById('statNoCheckup').textContent = data.pets.filter(p => p.CheckupCount == 0).length;

                tbody.innerHTML = data.pets.map(p =>`
                    <tr>
                        <td><span class="expand-btn" onclick="toggleDetail(${p.Pet_ID})">▸</span></td>
                        <td><strong>${p.Pet_Name}</strong></td>
                        <td>${p.OwnerFirstName ? p.OwnerFirstName + ''+ p.OwnerLastName : '—'}</td>
                        <td>${p.Age ?? '—'}</td>
                        <td>${p.Species || p.Pet_Type || '—'}</td>
                        <td>${p.Current_Ailment ? p.Current_Ailment.slice(0, 40) : '<span style="color:var(--good);">None noted</span>'}</td>
                        <td>${p.CheckupCount}</td>
                        <td>${p.LastCheckup || '—'}</td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick='editPet(${JSON.stringify(p)})'>Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deletePet(${p.Pet_ID})">Delete</button>
                        </td>
                    </tr>
                    <tr class="pet-detail-row" id="detail-${p.Pet_ID}">
                        <td colspan="9" class="pet-detail-cell">
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                <div>
                                    <div class="field-label">Description</div>
                                    <div class="field-value">${p.Description || '—'}</div>
                                    <div class="field-label">Past Conditions</div>
                                    <div class="field-value">${p.Past_Conditions || '—'}</div>
                                    <div class="field-label">Current Medications</div>
                                    <div class="field-value">${p.Current_Medications || '—'}</div>
                                </div>
                                <div>
                                    <div class="field-label" style="display:flex; justify-content:space-between; align-items:center;">
                                        <span>Checkup History</span>
                                        <button class="btn btn-primary btn-sm" onclick="openCheckupForm(${p.Pet_ID})">+ Add Checkup</button>
                                    </div>
                                    <div id="checkups-${p.Pet_ID}" style="margin-top:8px;"><span class="hw-bone hw-bone-md hw-bone-shape" style="width:75%;display:inline-block;"></span></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `).join('');
            });
    }

    window.toggleDetail = function(petId) {
        const row = document.getElementById(`detail-${petId}`);
        const wasOpen = row.classList.contains('open');
        document.querySelectorAll('.pet-detail-row.open').forEach(r => r.classList.remove('open'));
        if (!wasOpen) {
            row.classList.add('open');
            loadCheckups(petId);
        }
    };

    function loadCheckups(petId) {
        const el = document.getElementById(`checkups-${petId}`);
        fetch(`manage_checkups.php?action=read&petId=${petId}`)
            .then(res => res.json())
            .then(data =>{
                if (!data.success || data.checkups.length === 0) {
                    el.innerHTML = `<div class="checkup-item" style="color:var(--text-muted);">No checkups recorded yet.</div>`;
                    return;
                }
                el.innerHTML = data.checkups.map(c =>`
                    <div class="checkup-item">
                        <strong>${c.Checkup_Date}</strong>${c.VetFirstName ? '— Dr. '+ c.VetFirstName + ''+ c.VetLastName : ''}
                        <button class="btn btn-danger btn-sm" style="float:right; padding:2px 8px;" onclick="deleteCheckup(${c.Checkup_ID}, ${petId})">×</button>
                        <div style="color:var(--text-muted); margin-top:2px;">${c.Notes || 'No notes.'}</div>
                    </div>
                `).join('');
            });
    }

    window.openCheckupForm = function(petId) {
        const date = prompt('Checkup date (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
        if (!date) return;
        const notes = prompt('Notes for this checkup:', '');
        fetch('manage_checkups.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ petId, date, notes })
        }).then(() =>{ loadCheckups(petId); loadPets(); });
    };

    window.deleteCheckup = function(checkupId, petId) {
        if (!confirm('Remove this checkup record?')) return;
        fetch('manage_checkups.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ checkupId })
        }).then(() =>{ loadCheckups(petId); loadPets(); });
    };

    window.openAddModal = function() {
        document.getElementById('petModalTitle').textContent = 'Add Pet';
        document.getElementById('petForm').reset();
        document.getElementById('petId').value = '';
        document.getElementById('petModal').classList.add('open');
    };

    window.editPet = function(p) {
        document.getElementById('petModalTitle').textContent = 'Edit Pet';
        document.getElementById('petId').value = p.Pet_ID;
        document.getElementById('ownerId').value = p.Owner_ID || '';
        document.getElementById('petName').value = p.Pet_Name || '';
        document.getElementById('petAge').value = p.Age || '';
        document.getElementById('petSpecies').value = p.Species || '';
        document.getElementById('petType').value = p.Pet_Type || '';
        document.getElementById('petDescription').value = p.Description || '';
        document.getElementById('petPastConditions').value = p.Past_Conditions || '';
        document.getElementById('petCurrentAilment').value = p.Current_Ailment || '';
        document.getElementById('petCurrentMedications').value = p.Current_Medications || '';
        document.getElementById('petModal').classList.add('open');
    };

    window.closeAddModal = function() {
        document.getElementById('petModal').classList.remove('open');
    };

    window.deletePet = function(petId) {
        if (!confirm('Delete this pet and all its checkup history? This cannot be undone.')) return;
        fetch('manage_pets.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ petId })
        }).then(() => loadPets());
    };

    document.getElementById('petForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('petId').value;
        const payload = {
            petId: id || undefined,
            ownerId: document.getElementById('ownerId').value,
            name: document.getElementById('petName').value,
            age: document.getElementById('petAge').value,
            species: document.getElementById('petSpecies').value,
            type: document.getElementById('petType').value || 'Unknown',
            description: document.getElementById('petDescription').value,
            pastConditions: document.getElementById('petPastConditions').value,
            currentAilment: document.getElementById('petCurrentAilment').value,
            currentMedications: document.getElementById('petCurrentMedications').value
        };
        const action = id ? 'update': 'create';
        fetch(`manage_pets.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() =>{ closeAddModal(); loadPets(); });
    });

    loadOwners().then(loadPets);
    </script>
</body>
</html>
