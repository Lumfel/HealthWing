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
    <title>Manage Users · HealthWing Admin</title>
    <link rel="stylesheet" href="../bone-loader.css">
    <script src="../bone-loader.js"defer></script>
</head>
<body class="admin-body">
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
            <a href="ADMIN_VET_1.php">Overview</a>
            <a href="CRUD.php" class="current">Manage Users</a>
            <a href="appointment-report.php">Appointment Summary</a>
            <a href="Schedule-Management.php">Schedule Management</a>
            <a href="service-utilization.php">Service Utilization</a>
            <a href="client-activity.php">Client Activity</a>
            <a href="ratings-report.php">Ratings Report</a>
            <a href="room-management.php">Room Management</a>
            <a href="emergency-cases.php">Emergency Requests</a>
            <a href="pet-records.php">Pet Records</a>
            <a href="network-monitor.php">Network Monitor</a>
            <a href="client-history.php">Client History</a>
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
                <h1>Manage Users & Veterinarian Specialties</h1>
                <p>Create, approve, and manage Pet Owners and Veterinarians with full login credentials</p>
            </div>
        </div>

        <div class="panel" style="margin-bottom: 16px;">
            <form class="crud-form form-row" id="userForm" style="gap:10px;align-items:center;">
                <input type="text" id="first_name" name="first_name" placeholder="First Name"required />
                <input type="text" id="last_name" name="last_name" placeholder="Last Name"required />
                <input type="text" id="username" name="username" placeholder="Username"required />
                <input type="email" id="email" name="email" placeholder="Email"required />
                <input type="password" id="password" name="password" placeholder="Initial Password"required />
                <select id="role" name="role"required onchange="document.getElementById('specWrap').style.display = this.value === 'Veterinarian'? 'inline-block': 'none';">
                    <option value="Owner">Owner</option>
                    <option value="Veterinarian">Veterinarian</option>
                </select>
                <span id="specWrap" style="display:none;">
                    <select id="specialties" name="specialties">
                        <option value="General">Specialty: General</option>
                        <option value="Surgery">Specialty: Surgery</option>
                        <option value="Dental">Specialty: Dental</option>
                        <option value="Wellness">Specialty: Wellness & Vaccination</option>
                        <option value="Emergency">Specialty: Emergency & Critical Care</option>
                    </select>
                </span>
                <button class="btn btn-primary" type="submit">+ Add User</button>
            </form>
        </div>

        <div class="panel">
            <table class="data-table crud-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role & Specialty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
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

    document.addEventListener("DOMContentLoaded", function () {
        const tableBody = document.querySelector(".crud-table tbody");

        function roleBadge(role, spec) {
            const cls = role === 'Veterinarian'? 'badge-neutral': 'badge-good';
            const specText = (role === 'Veterinarian'&& spec) ? `· ${spec}`: '';
            return `<span class="badge ${cls}">${role}${specText}</span>`;
        }

        function statusBadge(status) {
            if (status === 'Pending') return '<span class="badge badge-warn">Pending Approval</span>';
            if (status === 'Rejected') return '<span class="badge badge-bad">Rejected</span>';
            return '<span class="badge badge-good">Approved</span>';
        }

        function fetchUsers() {
            fetch("adminphp.php?action=read")
                .then((response) => response.json())
                .then((users) =>{
                    tableBody.innerHTML = "";
                    if (!Array.isArray(users) || users.length === 0) {
                        tableBody.innerHTML = `<tr><td colspan="6" class="empty">No users yet.</td></tr>`;
                        return;
                    }
                    users.forEach((user) =>{
                        const row = document.createElement("tr");
                        const isPendingVet = user.role === 'Veterinarian'&& user.status === 'Pending';
                        row.innerHTML = `
                            <td><strong>${user.name}</strong></td>
                            <td>${user.username || '—'}</td>
                            <td>${user.email || '—'}</td>
                            <td>${roleBadge(user.role, user.specialties)}</td>
                            <td>${statusBadge(user.status)}</td>
                            <td>
                                ${isPendingVet ? `
                                    <button class="btn btn-good btn-sm" onclick="approveVet(${user.id})">Approve</button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectVet(${user.id})">Reject</button>
                                `: ''}
                                <button class="btn btn-secondary btn-sm" onclick="editUser(${user.id}, '${user.role}', '${(user.name || '').replace(/'/g, "\\'")}', '${(user.email || '').replace(/'/g, "\\'")}', '${(user.specialties || '').replace(/'/g, "\\'")}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id}, '${user.role}')">Delete</button>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                });
        }

        window.approveVet = function (id) {
            fetch("adminphp.php?action=approve_vet", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded"},
                body: `id=${id}`,
            }).then(() => fetchUsers());
        };

        window.rejectVet = function (id) {
            if (!confirm("Reject this veterinarian's signup request?")) return;
            fetch("adminphp.php?action=reject_vet", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded"},
                body: `id=${id}`,
            }).then(() => fetchUsers());
        };

        document.getElementById("userForm").addEventListener("submit", function (e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            fetch("adminphp.php?action=create", {
                method: "POST",
                body: formData,
            }).then(() =>{ fetchUsers(); e.target.reset(); document.getElementById('specWrap').style.display = 'none'; });
        });

        window.editUser = function (id, role, curName, curEmail, curSpec) {
            const name = prompt("Enter full name:", curName);
            if (!name) return;
            const email = prompt("Enter email:", curEmail);
            if (!email) return;
            let spec = curSpec || '';
            if (role === 'Veterinarian') {
                spec = prompt("Enter Veterinarian Specialty (General, Surgery, Dental, Wellness, Emergency):", curSpec || 'General') || 'General';
            }
            fetch("adminphp.php?action=update", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded"},
                body: `id=${id}&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&role=${role}&specialties=${encodeURIComponent(spec)}`,
            }).then(() => fetchUsers());
        };

        window.deleteUser = function (id, role) {
            if (confirm("Are you sure you want to delete this user?")) {
                fetch("adminphp.php?action=delete", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded"},
                    body: `id=${id}&role=${role}`,
                }).then(() => fetchUsers());
            }
        };

        fetchUsers();
    });
    </script>
</body>
</html>
