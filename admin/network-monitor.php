<?php
$ADMIN_GUARD_IS_API = false;
require_once __DIR__ . '/auth_guard.php';
$admin_display_name = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/admin/">
    <meta charset="UTF-8">
    <meta name="viewport" content=" width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="dashboard.css">
    <title>Network Monitor · HealthWing Admin</title>
    <style>
        .alert-toast {
            position: fixed; top: 20px; right: 20px; z-index: 300;
            background: var(--bad); color: #fff; padding: 12px 18px;
            border-radius: 10px; font-size: 13px; font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            display: none; max-width: 320px;
        }
        .alert-toast.show { display: block; animation: slideIn 0.25s ease; }
        @keyframes slideIn { from { transform: translateX(30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .live-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%;
            background: var(--good); margin-right: 6px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.35; } }
        .ip-tag { font-family: monospace; font-size: 12.5px; background: var(--bg); padding: 2px 7px; border-radius: 5px; }
    </style>
    <link rel="stylesheet" href="../bone-loader.css">
    <script src="../bone-loader.js"defer></script>
</head>
<body class="admin-body">
    <div class="alert-toast" id="alertToast"></div>
    <button id="toggleDashboard1"></button>

    <aside class="side" id="dashboard">
        <div class="brand">
            <img src="../images/logo.png" width="26" height="26" alt="HealthWing logo" style="border-radius: 6px;"/>
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
            <a href="pet-records.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4.5 20.5c0-3 2.5-5 5-5h5c2.5 0 5 2 5 5"/><circle cx="7" cy="6.5" r="2"/><circle cx="12" cy="4.5" r="2"/><circle cx="17" cy="6.5" r="2"/><circle cx="12" cy="10.5" r="2.2"/></svg>
                Pet Records
            </a>
            <a href="network-monitor.php" class="current">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="2"/><path d="M4.5 19.5c0-1.5 3-2.5 7.5-2.5s7.5 1 7.5 2.5"/><path d="M6 12a6 6 0 0112 0M3.5 8.5a10 10 0 0117 0"/></svg>
                Network Monitor
            </a>
            <a href="client-history.php">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="8.5"/></svg>
                Client History
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
                <h1><span class="live-dot"></span>Network Monitor</h1>
                <p>Live view of login, signup, and booking traffic — refreshes every 10 seconds</p>
            </div>
        </div>

        <div class="stats" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat">
                <div class="label">Total Requests (last hour)</div>
                <div class="value" id="statTotal">–</div>
            </div>
            <div class="stat bad">
                <div class="label">Blocked / Rate-Limited (last hour)</div>
                <div class="value" id="statBlocked">–</div>
            </div>
            <div class="stat warn">
                <div class="label">Most Active Endpoint</div>
                <div class="value" id="statTopAction" style="font-size:18px;">–</div>
            </div>
        </div>

        <div class="panel">
            <h3 style="margin-top:0; font-size:14px;">Recent Activity</h3>
            <div style="overflow-x:auto; max-height: 480px; overflow-y: auto;">
                <table class="data-table" id="trafficTable">
                    <thead>
                        <tr><th>Time</th><th>IP Address</th><th>Endpoint</th><th>Status</th></tr>
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

    let allEvents = [];
    let highestSeenId = 0;
    let audioCtx = null;

    function beep() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.frequency.value = 720;
            gain.gain.value = 0.06;
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.15);
        } catch (e) { /* audio not available, ignore */ }
    }

    function showToast(message) {
        const toast = document.getElementById('alertToast');
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 5000);
    }

    function statusBadge(status) {
        return status === 'blocked'
            ? '<span class="badge badge-bad">Blocked</span>'
            : '<span class="badge badge-good">Allowed</span>';
    }

    function renderTable() {
        const tbody = document.querySelector('#trafficTable tbody');
        if (allEvents.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="empty">No traffic recorded yet. Try logging in or submitting a form.</td></tr>`;
            return;
        }
        tbody.innerHTML = allEvents.slice(0, 100).map(e =>`
            <tr style="${e.Status === 'blocked'? 'background: #FBE7EC;': ''}">
                <td>${e.Created_At}</td>
                <td><span class="ip-tag">${e.IP_Address}</span></td>
                <td>${e.Action}</td>
                <td>${statusBadge(e.Status)}</td>
            </tr>
        `).join('');
    }

    function poll() {
        fetch(`get_traffic_log.php?sinceId=${highestSeenId}`)
            .then(res => res.json())
            .then(data =>{
                if (!data.success) return;

                const newBlocked = data.events.filter(e => e.Status === 'blocked'&& e.Log_ID >highestSeenId);
                if (highestSeenId >0 && newBlocked.length >0) {
                    newBlocked.forEach(e =>{
                        showToast(`[Notice] Rate limit triggered on "${e.Action}"from IP ${e.IP_Address}`);
                    });
                    beep();
                }

                if (data.events.length >0) {
                    highestSeenId = Math.max(highestSeenId, ...data.events.map(e => e.Log_ID));
                    allEvents = [...data.events, ...allEvents];
                }

                renderTable();

                document.getElementById('statTotal').textContent = data.total_last_hour;
                document.getElementById('statBlocked').textContent = data.blocked_last_hour;

                const allowedSummary = data.summary.filter(s => s.Status === 'allowed').sort((a, b) => b.total - a.total);
                document.getElementById('statTopAction').textContent = allowedSummary.length >0 ? allowedSummary[0].Action : '—';
            });
    }

    poll();
    setInterval(poll, 10000);
    </script>
</body>
</html>
