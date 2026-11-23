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
    <title>Room Management · HealthWing Admin</title>
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
            <a href="Schedule-Management.php">Schedule Management</a>
            <a href="service-utilization.php">Service Utilization</a>
            <a href="client-activity.php">Client Activity</a>
            <a href="ratings-report.php">Ratings Report</a>
            <a href="room-management.php" class="current">Room Management</a>
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
                <h1>Clinic Room & Trauma Bay Management</h1>
                <p>Specialized clinic rooms factored into the Automated Scheduler & Emergency Priority Triage Engine</p>
            </div>
        </div>

        <div class="panel">
            <form class="form-row" id="roomForm">
                <input type="text" placeholder="Room name (e.g. Surgery Suite B)" id="roomName"required>
                <select id="roomType">
                    <option value="General">Type: General Exam</option>
                    <option value="Emergency">Type: Emergency Trauma Bay</option>
                    <option value="Surgery">Type: Surgery Suite</option>
                    <option value="Dental">Type: Dental & Procedure</option>
                    <option value="Grooming">Type: Grooming</option>
                </select>
                <select id="roomStatus">
                    <option value="idle">Status: Idle (Available)</option>
                    <option value="ongoing">Status: Ongoing (Occupied)</option>
                    <option value="booked">Status: Booked</option>
                </select>
                <button class="btn btn-primary" type="submit">+ Add Clinic Room</button>
            </form>
            <div class="room-grid" id="roomGrid"></div>
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

    function statusBadge(status) {
        const map = { ongoing: 'badge-warn', booked: 'badge-bad', idle: 'badge-good'};
        const cls = map[status] || 'badge-neutral';
        return `<span class="badge ${cls}">${status}</span>`;
    }

    function loadRooms() {
        fetch('manage_rooms.php?action=read')
            .then(res => res.json())
            .then(data =>{
                const grid = document.getElementById('roomGrid');
                if (!data.success || data.rooms.length === 0) {
                    grid.innerHTML = `<div class="empty">No rooms added yet.</div>`;
                    return;
                }
                grid.innerHTML = data.rooms.map(r =>`
                    <div class="room-card">
                        <div class="room-name">${r.Room_Name || 'Room '+ r.Room_ID}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">Specialty Type: <strong>${r.Room_Type || 'General'}</strong></div>
                        <div style="margin-bottom:8px;">${statusBadge(r.Status)}</div>
                        ${r.Current_Emergency_ID ? `<div style="font-size:11.5px;color:var(--bad);font-weight:700;margin-bottom:6px;">Occupied by Emergency #${r.Current_Emergency_ID}</div>`: ''}
                        <select onchange="updateRoomStatus(${r.Room_ID}, this.value)" style="width:100%; padding:6px; border-radius:6px; border:1px solid var(--border); margin-bottom:8px;">
                            <option value="idle"${r.Status === 'idle'? 'selected': ''}>Idle</option>
                            <option value="ongoing"${r.Status === 'ongoing'? 'selected': ''}>Ongoing</option>
                            <option value="booked"${r.Status === 'booked'? 'selected': ''}>Booked</option>
                        </select>
                        <button class="btn btn-danger btn-sm" style="width:100%;" onclick="deleteRoom(${r.Room_ID})">Remove</button>
                    </div>
                `).join('');
            });
    }

    document.getElementById('roomForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const name = document.getElementById('roomName').value.trim();
        const roomType = document.getElementById('roomType').value;
        const status = document.getElementById('roomStatus').value;
        fetch('manage_rooms.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ name, roomType, status })
        }).then(() =>{ e.target.reset(); loadRooms(); });
    });

    window.updateRoomStatus = function(id, status) {
        fetch('manage_rooms.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ id, status })
        }).then(() => loadRooms());
    };

    window.deleteRoom = function(id) {
        if (!confirm('Remove this room?')) return;
        fetch('manage_rooms.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        }).then(() => loadRooms());
    };

    loadRooms();
    </script>
</body>
</html>
