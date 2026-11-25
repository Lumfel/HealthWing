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
    <title>Overview · HealthWing Admin</title>
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
            <a href="ADMIN_VET_1.php" class="current">
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
                <h1>Clinic Overview</h1>
                <p>Appointment activity across the last 6 months</p>
            </div>
        </div>

        <div class="stats" id="statStrip">
            <div class="stat">
                <div class="label">Total appointments today</div>
                <div class="value" id="statTotalToday">–</div>
            </div>
            <div class="stat good">
                <div class="label">Completed today</div>
                <div class="value" id="statCompletedToday">–</div>
            </div>
            <div class="stat warn">
                <div class="label">Pending today</div>
                <div class="value" id="statPendingToday">–</div>
            </div>
            <div class="stat bad">
                <div class="label">Emergency (30 days)</div>
                <div class="value" id="statEmergency">–</div>
            </div>
        </div>

        <div class="chart-container">
            <div class="mainconbargraph">
                <h3>Appointment Status Summary</h3>
                <div class="chart-wrap"><canvas id="statusSummaryChart"></canvas></div>
            </div>
            <div class="mainconbargraph">
                <h3>Monthly Appointments</h3>
                <div class="chart-wrap"><canvas id="appointmentsChart"></canvas></div>
            </div>
            <div class="mainconbargraph">
                <h3>Service Distribution</h3>
                <div class="chart-wrap"><canvas id="servicesChart"></canvas></div>
            </div>
            <div class="mainconbargraph">
                <h3>Client Activity Trends</h3>
                <div class="chart-wrap"><canvas id="clientActivityChart"></canvas></div>
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
    </script>

    <script src="../api/asset_proxy.php?f=vendor/chartjs/chart.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let appointmentsChart = null, servicesChart = null, statusSummaryChart = null, clientActivityChart = null;

        Chart.defaults.font.family = "'Manrope', sans-serif";
        Chart.defaults.color = '#62787B';
        Chart.defaults.borderColor = '#E3EBEC';

        function createMonthlyAppointmentsChart(data) {
            const ctx = document.getElementById('appointmentsChart');
            if (!ctx) return;
            if (appointmentsChart) appointmentsChart.destroy();
            const months = data.map(item =>{ const [y, m] = item.month.split('-'); return new Date(y, m - 1).toLocaleString('default', { month: 'short', year: '2-digit'}); });
            appointmentsChart = new Chart(ctx, {
                type: 'line',
                data: { labels: months, datasets: [{ label: 'Total Appointments', data: data.map(i => i.total_appointments), borderColor: '#4A9DBB', backgroundColor: 'rgba(74,157,187,0.12)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3, pointBackgroundColor: '#4A9DBB'}] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF3F3'} }, x: { grid: { display: false } } } }
            });
        }

        function createServicesChart(data) {
            const ctx = document.getElementById('servicesChart');
            if (!ctx) return;
            if (servicesChart) servicesChart.destroy();
            servicesChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: data.map(i => i.Services), datasets: [{ data: data.map(i => i.count), backgroundColor: ['#4A9DBB', '#4CAF7D', '#E3A857', '#D8546F', '#8C7BC7', '#5FB8C9'], borderColor: '#fff', borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } } }
            });
        }

        function createStatusSummaryChart(data) {
            const ctx = document.getElementById('statusSummaryChart');
            if (!ctx) return;
            if (statusSummaryChart) statusSummaryChart.destroy();
            statusSummaryChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.slice(-6).map(item =>{ const [y, m] = item.month.split('-'); return new Date(y, m - 1).toLocaleString('default', { month: 'short', year: '2-digit'}); }),
                    datasets: [
                        { label: 'Completed', data: data.slice(-6).map(i => i.completed), backgroundColor: '#4CAF7D', borderRadius: 3 },
                        { label: 'Pending', data: data.slice(-6).map(i => i.pending), backgroundColor: '#E3A857', borderRadius: 3 },
                        { label: 'Cancelled', data: data.slice(-6).map(i => i.cancelled), backgroundColor: '#D8546F', borderRadius: 3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: '#EEF3F3'} } } }
            });
        }

        function createClientActivityChart(data) {
            const ctx = document.getElementById('clientActivityChart');
            if (!ctx) return;
            if (clientActivityChart) clientActivityChart.destroy();
            clientActivityChart = new Chart(ctx, {
                type: 'line',
                data: { labels: data.slice(-6).map(item =>{ const [y, m] = item.month.split('-'); return new Date(y, m - 1).toLocaleString('default', { month: 'short', year: '2-digit'}); }), datasets: [{ label: 'Client Activity', data: data.slice(-6).map(i => i.total_appointments), borderColor: '#8C7BC7', backgroundColor: 'rgba(140,123,199,0.12)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3, pointBackgroundColor: '#8C7BC7'}] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF3F3'} }, x: { grid: { display: false } } } }
            });
        }

        async function fetchDashboardData() {
            try {
                const response = await fetch('get_dashboard_data.php');
                const data = await response.json();
                if (data.success) {
                    if (Array.isArray(data.monthly_appointments)) {
                        createMonthlyAppointmentsChart(data.monthly_appointments);
                        createStatusSummaryChart(data.monthly_appointments);
                        createClientActivityChart(data.monthly_appointments);
                    }
                    if (Array.isArray(data.service_distribution) && data.service_distribution.length >0) {
                        createServicesChart(data.service_distribution);
                    }
                    if (data.today_stats) {
                        document.getElementById('statTotalToday').textContent = data.today_stats.total_today ?? 0;
                        document.getElementById('statCompletedToday').textContent = data.today_stats.completed_today ?? 0;
                        document.getElementById('statPendingToday').textContent = data.today_stats.pending_today ?? 0;
                    }
                    if (data.emergency_stats) {
                        document.getElementById('statEmergency').textContent = data.emergency_stats.total_emergency ?? 0;
                    }
                } else {
                    console.error('Error in dashboard data:', data.error || 'Unknown error');
                }
            } catch (error) { console.error('Error fetching dashboard data:', error); }
        }

        fetchDashboardData();
        setInterval(fetchDashboardData, 5 * 60 * 1000);
    });
    </script>
</body>
</html>
