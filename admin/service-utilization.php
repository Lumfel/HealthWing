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
    <title>Service Utilization · HealthWing Admin</title>
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
            <a href="service-utilization.php" class="current">
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
                <h1>Service Utilization</h1>
                <p>How each service performs across bookings and ratings</p>
            </div>
        </div>

        <div class="stats" id="statsCards">
            <div class="stat">
                <div class="label">Total Appointments</div>
                <div class="value" id="totalAppointments">0</div>
            </div>
            <div class="stat good">
                <div class="label">Most Popular Service</div>
                <div class="value" id="popularService" style="font-size:18px;">-</div>
            </div>
            <div class="stat">
                <div class="label">Average Completion Rate</div>
                <div class="value" id="completionRate">0%</div>
            </div>
        </div>

        <div class="chart-container" style="margin-bottom: 16px;">
            <div class="mainconbargraph">
                <h3>Service Distribution</h3>
                <div class="chart-wrap"><canvas id="serviceDistributionChart"></canvas></div>
            </div>
            <div class="mainconbargraph">
                <h3>Service Ratings</h3>
                <div class="chart-wrap"><canvas id="serviceRatingsChart"></canvas></div>
            </div>
        </div>

        <div class="panel">
            <h3 style="margin-top:0; font-size:14px;">Detailed Service Report</h3>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Service</th><th>Month</th><th>Total</th><th>Completed</th>
                            <th>Pending</th><th>Cancelled</th><th>Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody id="reportTable"></tbody>
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
    </script>

    <script src="../api/asset_proxy.php?f=vendor/chartjs/chart.min.js"></script>
    <script>
    Chart.defaults.font.family = "'Manrope', sans-serif";
    Chart.defaults.color = '#62787B';

    async function fetchServiceData() {
        try {
            const response = await fetch('get_services_utilization.php');
            const data = await response.json();
            if (data.success) renderData(data);
            else console.error('Error fetching data:', data.error);
        } catch (error) { console.error('Error:', error); }
    }

    function renderData(data) {
        renderStatistics(data);
        renderCharts(data);
        renderTable(data);
    }

    function renderStatistics(data) {
        if (!data.services.length) {
            document.getElementById('totalAppointments').textContent = '0';
            document.getElementById('popularService').textContent = '—';
            document.getElementById('completionRate').textContent = '0%';
            return;
        }
        let totalAppointments = 0;
        const serviceCount = {};
        data.services.forEach(service =>{
            totalAppointments += parseInt(service.total_appointments);
            serviceCount[service.Services] = (serviceCount[service.Services] || 0) + parseInt(service.total_appointments);
        });
        const mostPopularService = Object.entries(serviceCount).sort((a, b) => b[1] - a[1])[0][0];
        const completedAppointments = data.services.reduce((sum, service) => sum + parseInt(service.completed_appointments), 0);
        const completionRate = totalAppointments ? Math.round((completedAppointments / totalAppointments) * 100) : 0;

        document.getElementById('totalAppointments').textContent = totalAppointments;
        document.getElementById('popularService').textContent = mostPopularService;
        document.getElementById('completionRate').textContent = `${completionRate}%`;
    }

    function renderCharts(data) {
        renderServiceDistributionChart(data.services);
        renderServiceRatingsChart(data.ratings);
    }

    function renderServiceDistributionChart(services) {
        const serviceCount = {};
        services.forEach(service =>{
            serviceCount[service.Services] = (serviceCount[service.Services] || 0) + parseInt(service.total_appointments);
        });
        const ctx = document.getElementById('serviceDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(serviceCount),
                datasets: [{
                    data: Object.values(serviceCount),
                    backgroundColor: ['#4A9DBB', '#4CAF7D', '#E3A857', '#D8546F', '#8C7BC7', '#5FB8C9'],
                    borderColor: '#fff', borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
        });
    }

    function renderServiceRatingsChart(ratings) {
        const services = Object.keys(ratings);
        const averageRatings = services.map(service => ratings[service].average_rating || 0);
        const ctx = document.getElementById('serviceRatingsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: { labels: services, datasets: [{ label: 'Average Rating', data: averageRatings, backgroundColor: '#4A9DBB', borderRadius: 3 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 5, grid: { color: '#EEF3F3'} }, x: { grid: { display: false } } }
            }
        });
    }

    function renderTable(data) {
        const tableBody = document.getElementById('reportTable');
        if (!data.services.length) {
            tableBody.innerHTML = `<tr><td colspan="7" class="empty">No service data yet.</td></tr>`;
            return;
        }
        tableBody.innerHTML = data.services.map(service =>`
            <tr>
                <td>${service.Services}</td>
                <td>${service.month}</td>
                <td>${service.total_appointments}</td>
                <td>${service.completed_appointments}</td>
                <td>${service.pending_appointments}</td>
                <td>${service.cancelled_appointments}</td>
                <td>${data.ratings[service.Services]?.average_rating ?? 'N/A'}</td>
            </tr>
        `).join('');
    }

    fetchServiceData();
    </script>
</body>
</html>
