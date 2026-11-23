<?php
$ADMIN_GUARD_IS_API = false;
require_once __DIR__ . '/auth_guard.php';
$admin_display_name = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content=" width=device-width, initial-scale=1.0">
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../api/asset_proxy.php?f=admin/dashboard.css">
    <title>Smart Schedule Management · HealthWing Admin</title>
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
            <a href="ADMIN_VET_1.php">Overview</a>
            <a href="CRUD.php">Manage Users</a>
            <a href="appointment-report.php">Appointment Summary</a>
            <a href="Schedule-Management.php" class="current">Schedule Management</a>
            <a href="service-utilization.php">Service Utilization</a>
            <a href="client-activity.php">Client Activity</a>
            <a href="ratings-report.php">Ratings Report</a>
            <a href="room-management.php">Room Management</a>
            <a href="emergency-cases.php">Emergency Requests</a>
            <a href="pet-records.php">Pet Records</a>
            <a href="network-monitor.php">Network Monitor</a>
            <a href="client-history.php">Client History</a>
            <a href="../receipts.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                Receipts
            </a>
        </nav>

        <div class="exit-row">
            <div style="font-size:11.5px; color:#9FBEC0; padding: 0 4px 8px 4px;">Signed in as <strong style="color:#E4EDEC;"><?php echo $admin_display_name; ?></strong></div>
            <button class="exit-btn" onclick="window.location.href='../public_calendar.php'">Public Calendar</button>
            <button class="exit-btn" style="margin-top:6px;" onclick="window.location.href='../index.php'">View Site</button>
            <button class="exit-btn" style="margin-top:6px;" onclick="window.location.href='admin_logout.php'">Log Out</button>
        </div>
    </aside>

    <div class="main" id="mainArea">
        <div class="page-head">
            <div>
                <h1>Smart Schedule Management</h1>
                <p>Automated conflict-free veterinarian & clinic room assignment</p>
            </div>
            <button class="btn btn-primary" onclick="runAutoAssignAll()">Auto-Assign All Unassigned Slots</button>
        </div>

        <div class="panel" style="margin-bottom:16px;">
            <h3 id="formTitle" style="margin-top:0; font-size:14px;">Add / Edit Scheduled Appointment</h3>
            <form id="scheduleForm" class="form-row" style="gap:10px;align-items:center;">
                <input type="hidden" id="scheduleId">
                <select id="ownerId"required style="min-width:170px;">
                    <option value="">Select Owner...</option>
                </select>
                <select id="petId" style="min-width:150px;">
                    <option value="">Select Pet...</option>
                </select>
                <select id="services"required style="min-width:155px;">
                    <option value="Wellness Care">Wellness Care (30m)</option>
                    <option value="Vaccination">Vaccination (30m)</option>
                    <option value="Parasite Control">Parasite Control (30m)</option>
                    <option value="Dental">Dental (60m)</option>
                    <option value="Grooming">Grooming (60m)</option>
                    <option value="Surgery">Surgery (90m)</option>
                </select>
                <input type="datetime-local" id="appointmentTime"required>
                <select id="vetId" style="min-width:165px;">
                    <option value="">Auto-Assign Vet</option>
                </select>
                <select id="roomId" style="min-width:165px;">
                    <option value="">Auto-Assign Room</option>
                </select>
                <select id="statusSelect" style="min-width:120px;">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <button class="btn btn-primary" type="submit">Save Slot</button>
                <button class="btn btn-secondary" type="button" onclick="resetForm()">Clear</button>
            </form>
        </div>

        <div class="panel">
            <div style="overflow-x:auto;">
                <table id="scheduleTable" class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Owner</th>
                            <th>Pet</th>
                            <th>Service & Duration</th>
                            <th>Appointment Slot</th>
                            <th>Assigned Vet</th>
                            <th>Assigned Room</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
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

    let resourcesCache = { owners: [], pets: [], vets: [], rooms: [] };

    function statusBadge(status) {
        const map = { Completed: 'badge-good', Pending: 'badge-warn', Cancelled: 'badge-bad', 'In Progress': 'badge-neutral'};
        const cls = map[status] || 'badge-neutral';
        return `<span class="badge ${cls}">${status || 'Pending'}</span>`;
    }

    function loadResources() {
        return fetch('../api/scheduler.php?action=resources')
            .then(r => r.json())
            .then(data =>{
                if (!data.success) return;
                resourcesCache = data;

                const ownerSel = document.getElementById('ownerId');
                ownerSel.innerHTML = '<option value="">Select Owner...</option>'+
                    data.owners.map(o =>`<option value="${o.Owner_ID}">${o.First_Name} ${o.Last_Name} (#${o.Owner_ID})</option>`).join('');

                const vetSel = document.getElementById('vetId');
                vetSel.innerHTML = '<option value="">Auto-Assign Vet</option>'+
                    data.vets.map(v =>`<option value="${v.Veterinarian_ID}">Dr. ${v.First_Name} ${v.Last_Name} (${v.Specialties || 'General'})</option>`).join('');

                const roomSel = document.getElementById('roomId');
                roomSel.innerHTML = '<option value="">Auto-Assign Room</option>'+
                    data.rooms.map(rm =>`<option value="${rm.Room_ID}">${rm.Room_Name || 'Room #'+ rm.Room_ID} (${rm.Room_Type || 'General'})</option>`).join('');

                populatePetsDropdown();
            });
    }

    function populatePetsDropdown(filterOwnerId) {
        const petSel = document.getElementById('petId');
        const list = filterOwnerId
            ? resourcesCache.pets.filter(p => String(p.Owner_ID) === String(filterOwnerId))
            : resourcesCache.pets;
        petSel.innerHTML = '<option value="">Select Pet...</option>'+
            list.map(p =>`<option value="${p.Pet_ID}">${p.Pet_Name} (${p.Species || p.Pet_Type || 'Pet'})</option>`).join('');
    }

    document.getElementById('ownerId').addEventListener('change', function () {
        populatePetsDropdown(this.value);
    });

    function loadSchedules() {
        fetch('manage_schedule.php?action=read')
            .then(res => res.json())
            .then(data =>{
                const tbody = document.querySelector('#scheduleTable tbody');
                if (!data.success || data.schedules.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" class="empty">No appointments yet.</td></tr>`;
                    return;
                }
                tbody.innerHTML = data.schedules.map(s =>`
                    <tr>
                        <td><strong>#${s.Schedule_ID}</strong></td>
                        <td>${s.OwnerFirstName ? s.OwnerFirstName + ''+ s.OwnerLastName : 'ID '+ (s.Owner_ID ?? '—')}</td>
                        <td>${s.Pet_Name || '—'}</td>
                        <td>${s.Services} <span style="font-size:11px;color:var(--text-muted);">(${s.Duration_Minutes || 30}m)</span></td>
                        <td>
                            <div>${s.Appointment_Time}</div>
                            ${s.Preempted_By_Emergency_ID ? `<span class="badge badge-warn" style="font-size:10px;">Preempted by Case #${s.Preempted_By_Emergency_ID}</span>`: ''}
                        </td>
                        <td>${s.VetFirstName ? 'Dr. '+ s.VetFirstName + ''+ s.VetLastName : '<span style="color:var(--warn);">Unassigned</span>'}</td>
                        <td>${s.Room_Name || '<span style="color:var(--warn);">Unassigned</span>'}</td>
                        <td>${statusBadge(s.Status)}</td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick='editSchedule(${JSON.stringify(s)})'>Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteSchedule(${s.Schedule_ID})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    window.runAutoAssignAll = function () {
        fetch('../api/scheduler.php?action=auto_assign_all', { method: 'POST'})
            .then(r => r.json())
            .then(data =>{
                if (data.success) {
                    alert(`Auto-assigned ${data.updated_count} appointment(s) to optimal Veterinarians & Rooms.`);
                    loadSchedules();
                }
            });
    };

    document.getElementById('scheduleForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const id = document.getElementById('scheduleId').value;
        const vetVal = document.getElementById('vetId').value;
        const roomVal = document.getElementById('roomId').value;
        const payload = {
            scheduleId: id || undefined,
            ownerId: document.getElementById('ownerId').value,
            petId: document.getElementById('petId').value,
            vetId: vetVal || undefined,
            roomId: roomVal || undefined,
            services: document.getElementById('services').value,
            appointmentTime: document.getElementById('appointmentTime').value,
            status: document.getElementById('statusSelect').value,
            autoAssign: (!vetVal || !roomVal)
        };
        const action = id ? 'update': 'create';
        fetch(`manage_schedule.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() =>{ loadSchedules(); resetForm(); });
    });

    window.editSchedule = function (s) {
        document.getElementById('formTitle').textContent = 'Edit Appointment #'+ s.Schedule_ID;
        document.getElementById('scheduleId').value = s.Schedule_ID;
        document.getElementById('ownerId').value = s.Owner_ID || '';
        populatePetsDropdown(s.Owner_ID);
        document.getElementById('petId').value = s.Pet_ID || '';
        document.getElementById('vetId').value = s.Veterinarian_ID || '';
        document.getElementById('roomId').value = s.Room_ID || '';
        document.getElementById('services').value = s.Services || 'Wellness Care';
        document.getElementById('statusSelect').value = s.Status || 'Pending';
        document.getElementById('appointmentTime').value = (s.Appointment_Time || '').replace('', 'T').slice(0, 16);
    };

    window.deleteSchedule = function (id) {
        if (!confirm('Delete this appointment?')) return;
        fetch('manage_schedule.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ scheduleId: id })
        }).then(() => loadSchedules());
    };

    window.resetForm = function () {
        document.getElementById('scheduleForm').reset();
        document.getElementById('formTitle').textContent = 'Add / Edit Scheduled Appointment';
        document.getElementById('scheduleId').value = '';
        populatePetsDropdown();
    };

    loadResources().then(loadSchedules);
    </script>
</body>
</html>
