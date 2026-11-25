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
    <title>Emergency Priority Triage · HealthWing Admin</title>
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
            <a href="room-management.php">Room Management</a>
            <a href="emergency-cases.php" class="current">Emergency Requests</a>
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
                <h1>Emergency Priority Triage & Conflict Preemption</h1>
                <p>Ranked automatically by Composite Priority Score (0–100) factoring clinical severity, wait time, and room/vet availability</p>
            </div>
            <button class="btn btn-primary" onclick="loadCases()">Re-Evaluate Priority Triage Queue</button>
        </div>
        <div class="case-grid" id="caseGrid"></div>
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
        const map = { Completed: 'badge-good', Pending: 'badge-warn', Cancelled: 'badge-bad', 'In Progress': 'badge-neutral'};
        const cls = map[status] || 'badge-neutral';
        return `<span class="badge ${cls}">${status || 'Pending'}</span>`;
    }

    function loadCases() {
        fetch('get_emergency.php')
            .then(res => res.json())
            .then(data =>{
                const grid = document.getElementById('caseGrid');
                if (!data.success || data.cases.length === 0) {
                    grid.innerHTML = `<div class="empty">No emergency requests right now.</div>`;
                    return;
                }
                grid.innerHTML = data.cases.map(c =>{
                    const isResolved = c.Status === 'Completed'|| c.Status === 'Cancelled';
                    const qBanner = (!isResolved && c.Queue_Position)
                        ? `<div style="font-size:13px;font-weight:800;color:${Number(c.Queue_Position) === 1 ? '#D8546F': '#1F3A3D'};margin-bottom:4px;">
                             ${Number(c.Queue_Position) === 1 ? 'Queue #1 — Treat First': 'Triage Queue #'+ c.Queue_Position}
                             · ${c.Severity_Level || 'Urgent'} (${Number(c.Priority_Score || 50).toFixed(1)}/100)
                           </div>`
                        : '';
                    return `
                    <div class="case-card" style="${isResolved ? 'opacity:0.55;': ''}">
                        ${c.Image_Path ? `<img src="../${c.Image_Path}" alt="Case photo" onerror="this.style.display='none'">`: ''}
                        ${qBanner}
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>#${c.Booking_ID} · ${c.Name || (c.OwnerFirstName ? c.OwnerFirstName + ''+ c.OwnerLastName : 'Guest')}</strong>
                            ${statusBadge(c.Status)}
                        </div>
                        <div class="meta">${c.Email || ''} ${c.Phone_Number ? '· '+ c.Phone_Number : ''}</div>
                        <div class="meta">Arrived: ${c.Created_At || ''}</div>
                        <div class="meta" style="color:var(--text);font-weight:600;">
                             ${c.Room_Name || 'Standby'} ·  ${c.VetFirst ? 'Dr. '+ c.VetFirst + ''+ c.VetLast : 'Unassigned'}
                        </div>
                        ${c.Cancel_Requested == 1 && !isResolved ? '<div class="meta" style="color:var(--bad); font-weight:700;">[Notice] Client requested cancellation</div>': ''}
                        <div class="details">${c.Additional_Details || 'No details provided.'}</div>
                        ${c.Triage_Rationale ? `<div class="details" style="font-size:11.5px;background:#EEF7F9;padding:8px;border-radius:6px;color:#1F3A3D;">${c.Triage_Rationale}</div>`: ''}
                        ${c.Cancel_Reason ? `<div class="details" style="color:var(--text-muted); font-style:italic;">Cancellation reason: ${c.Cancel_Reason}</div>`: ''}
                        <div class="actions">
                            <button class="btn btn-good btn-sm" onclick="setStatus(${c.Booking_ID}, 'In Progress')">In Progress</button>
                            <button class="btn btn-secondary btn-sm" onclick="setStatus(${c.Booking_ID}, 'Completed')">Mark Done</button>
                            <button class="btn btn-danger btn-sm" onclick="setStatus(${c.Booking_ID}, 'Cancelled')">Cancel</button>
                        </div>
                    </div>
                `;
                }).join('');
            });
    }

    window.setStatus = function(id, status) {
        let reason = '';
        if (status === 'Cancelled') {
            reason = prompt('A reason is required to cancel this request. This will be shown to the person who filed it:');
            if (reason === null) return;
            if (reason.trim() === '') {
                alert('A reason is required to cancel a request.');
                return;
            }
        }
        fetch('update_emergency_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({ id, status, reason })
        }).then(res => res.json()).then(data =>{
            if (!data.success) {
                alert(data.error || 'Could not update status.');
            }
            loadCases();
        });
    };

    loadCases();
    </script>
</body>
</html>
