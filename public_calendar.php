<?php
/**
 * HealthWing / VetSystem — Privacy-Safe Public Clinic Calendar (public_calendar.php)
 *
 * Displays all booked schedules and active emergency clinic blocks across Monthly, Weekly,
 * and Daily views plus a live Room Occupancy strip.
 * Strictly omits all sensitive data (no owner names, emails, phone numbers, pet names, or medical notes).
 */
require_once __DIR__ . '/includes/security_crypto.php';
hw_start_secure_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content=" width=device-width, initial-scale=1.0"/>
    <title>Public Clinic Schedule & Availability | HealthWing</title>
    <link href="api/asset_proxy.php?f=vendor/tailwind/tailwind.min.css" rel="stylesheet"/>
    <style>
        body { background: #F4F9FA; color: #16262A; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .cal-container { max-width: 1260px; margin: 28px auto 60px; padding: 0 20px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .cal-day-header { text-align: center; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #62787B; padding: 8px 0; }
        .cal-cell {
            background: #FFFFFF;
            border: 1px solid #E3EBEC;
            border-radius: 10px;
            min-height: 118px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .cal-cell:hover { border-color: #4A9DBB; box-shadow: 0 4px 14px rgba(74,157,187,0.12); }
        .cal-cell.today { border: 2px solid #4A9DBB; background: #F8FCFD; }
        .cal-cell.other-month { opacity: 0.42; background: #F1F5F6; }
        .slot-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 7px;
            border-radius: 6px;
            line-height: 1.25;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .slot-booked { background: #E7F3F7; color: #1F5B70; border-left: 3px solid #4A9DBB; }
        .slot-emergency { background: #FBE7EC; color: #9E2A42; border-left: 3px solid #D8546F; }
        .slot-completed { background: #E7F5EC; color: #246A46; border-left: 3px solid #4CAF7D; }
        .room-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1px solid #E3EBEC;
            border-radius: 10px;
            padding: 12px 15px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="cal-container">
    <!-- Top Banner -->
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:14px;padding:22px 24px;margin-bottom:20px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;">
        <div>
            <div style="display:inline-flex;align-items:center;gap:6px;background:#E7F5EC;color:#246A46;font-size:11.5px;font-weight:700;padding:4px 10px;border-radius:999px;margin-bottom:6px;">
                 Privacy-Protected Public Schedule (Zero Personal Data)
            </div>
            <h1 style="font-size:24px;font-weight:800;margin:0 0 4px 0;">Clinic Public Calendar & Room Availability</h1>
            <p style="color:#62787B;font-size:13.5px;margin:0;">
                View all booked dates, times, and clinic room blocks in real time. Personal details, pet names, and clinical records are strictly hidden.
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div id="vetScheduleTabs" style="display:inline-flex;background:#F1F6F7;padding:4px;border-radius:10px;border:1px solid #D5E3E6;gap:4px;">
                <button type="button" id="tabCalAll" class="btn btn-primary btn-sm" onclick="switchCalendarScope('all')">All Clinic Schedule</button>
                <button type="button" id="tabCalYours" class="btn btn-secondary btn-sm" onclick="switchCalendarScope('yours')">Your Schedule</button>
            </div>
            <a href="appointment_schedule.php" class="btn btn-primary" style="text-decoration:none;padding:10px 18px;font-size:13.5px;">
                + Book Open Time Slot
            </a>
        </div>
    </div>

    <!-- Live Clinic Room Availability Strip -->
    <div style="margin-bottom:22px;">
        <h3 style="font-size:13.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.04em;color:#62787B;margin:0 0 10px 0;">
            Live Clinic Room Status
        </h3>
        <div id="publicRoomStrip" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;"></div>
    </div>

    <!-- Calendar Controls & View Switcher -->
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:14px;padding:18px 20px;margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="btn btn-secondary btn-sm" onclick="shiftMonth(-1)">← Prev</button>
                <h2 id="calMonthTitle" style="font-size:18px;font-weight:800;margin:0 10px;min-width:170px;text-align:center;"></h2>
                <button class="btn btn-secondary btn-sm" onclick="shiftMonth(1)">Next →</button>
                <button class="btn btn-secondary btn-sm" onclick="goToday()">Today</button>
            </div>

            <div style="display:flex;gap:12px;align-items:center;font-size:12px;">
                <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:#4A9DBB;display:inline-block;"></span>Booked Slot</span>
                <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:#D8546F;display:inline-block;"></span>Emergency Priority Block</span>
                <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:#4CAF7D;display:inline-block;"></span>Completed</span>
            </div>
        </div>

        <div class="cal-grid">
            <div class="cal-day-header">Sun</div>
            <div class="cal-day-header">Mon</div>
            <div class="cal-day-header">Tue</div>
            <div class="cal-day-header">Wed</div>
            <div class="cal-day-header">Thu</div>
            <div class="cal-day-header">Fri</div>
            <div class="cal-day-header">Sat</div>
        </div>
        <div class="cal-grid" id="calDaysGrid"></div>
    </div>

    <!-- Selected Date / Upcoming Booked Time Slots List (Date & Time Only) -->
    <div style="background:#FFFFFF;border:1px solid #E3EBEC;border-radius:14px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <div>
                <h3 id="selectedDayTitle" style="font-size:16px;font-weight:800;margin:0;">Booked Schedule Feed (Date & Time Only)</h3>
                <p style="font-size:12.5px;color:#62787B;margin:2px 0 0 0;">Click any date cell in the calendar above to filter slots for that day, or click an open window to book.</p>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="clearDayFilter()">Show All Upcoming Booked Slots</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" id="publicScheduleTable">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Booked Time Window</th>
                        <th>Duration</th>
                        <th>Room</th>
                        <th>Slot Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let allSlots = [];
let viewYear = new Date().getFullYear();
let viewMonth = new Date().getMonth();
let selectedDateFilter = null;
let calendarScope = "all"; // "all"| "yours"
let currentVetId = null;
let currentVetName = null;

function loadPublicCalendar() {
    fetch('api/public_calendar_api.php')
        .then(r => r.json())
        .then(data =>{
            if (!data.success) return;
            allSlots = data.booked_slots || [];
            currentVetId = data.current_vet_id;
            currentVetName = data.current_vet_name;
            renderRoomStrip(data.rooms || []);
            renderMonthGrid();
            renderScheduleTable();
        });
}

function renderRoomStrip(rooms) {
    const strip = document.getElementById('publicRoomStrip');
    if (!rooms.length) {
        strip.innerHTML = `<div class="empty">No rooms configured.</div>`;
        return;
    }
    strip.innerHTML = rooms.map(r =>{
        const badgeCls = r.status === 'idle'? 'badge-good': (r.status === 'ongoing'? 'badge-bad': 'badge-warn');
        const label = r.status === 'idle'? 'Available': (r.status === 'ongoing'? 'In Session': 'Booked');
        return `
            <div class="room-pill">
                <div>
                    <div style="font-weight:700;font-size:13.5px;">${r.room_name}</div>
                    <div style="font-size:11.5px;color:#62787B;">${r.room_type} Room</div>
                </div>
                <span class="badge ${badgeCls}">${label}</span>
            </div>
        `;
    }).join('');
}

function renderMonthGrid() {
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('calMonthTitle').textContent = `${monthNames[viewMonth]} ${viewYear}`;

    const firstDay = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const todayStr = new Date().toISOString().slice(0, 10);

    const slotsByDate = {};
    const activeSlots = calendarScope === "yours"? allSlots.filter(s => s.is_my_schedule) : allSlots;
    activeSlots.forEach(s =>{
        if (!slotsByDate[s.date]) slotsByDate[s.date] = [];
        slotsByDate[s.date].push(s);
    });

    const cells = [];
    for (let i = 0; i < firstDay; i++) {
        cells.push(`<div class="cal-cell other-month"></div>`);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const daySlots = slotsByDate[dateStr] || [];
        const isToday = dateStr === todayStr;
        const isSelected = selectedDateFilter === dateStr;

        const pillsHtml = daySlots.slice(0, 4).map(s =>{
            const cls = s.category.includes('Emergency') ? 'slot-emergency'
                : (s.status_label === 'Completed'? 'slot-completed': 'slot-booked');
            return `<div class="slot-pill ${cls}" title="${s.time_label} (${s.room_label})">
                        <span>${s.start_hm}–${s.end_hm}</span>
                        <span style="font-size:10px;opacity:0.85;">${s.status_label === 'Emergency Priority Block'? '': ''}</span>
                    </div>`;
        }).join('');

        const moreHtml = daySlots.length >4
            ? `<div style="font-size:10.5px;color:#62787B;font-weight:700;text-align:center;">+${daySlots.length - 4} more booked</div>`
            : '';

        cells.push(`
            <div class="cal-cell ${isToday ? 'today': ''}"
                 style="${isSelected ? 'outline:2px solid #1F3A3D;background:#EEF7F9;': ''}cursor:pointer;"
                 onclick="selectDay('${dateStr}')">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                    <span style="font-weight:800;font-size:13px;color:${isToday ? '#4A9DBB': '#16262A'};">${d}</span>
                    <a href="appointment_schedule.php?date=${dateStr}" onclick="event.stopPropagation();"
                       style="font-size:10.5px;color:#4A9DBB;text-decoration:none;font-weight:700;">+ Book</a>
                </div>
                ${pillsHtml}
                ${moreHtml}
            </div>
        `);
    }

    document.getElementById('calDaysGrid').innerHTML = cells.join('');
}

function renderScheduleTable() {
    const tbody = document.querySelector('#publicScheduleTable tbody');
    let filtered = calendarScope === "yours"? allSlots.filter(s => s.is_my_schedule) : allSlots;
    if (selectedDateFilter) {
        filtered = allSlots.filter(s => s.date === selectedDateFilter);
        document.getElementById('selectedDayTitle').textContent = `Booked Slots for ${selectedDateFilter} (Privacy-Safe View)`;
    } else {
        document.getElementById('selectedDayTitle').textContent = `All Booked Clinic Slots (Date & Time Only)`;
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty">No booked schedules on this date — all clinic operating hours are open! <a href="appointment_schedule.php${selectedDateFilter ? '?date='+ selectedDateFilter : ''}" style="color:#4A9DBB;font-weight:700;">Book a slot now →</a></td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(s =>{
        const badgeCls = s.category.includes('Emergency') ? 'badge-bad'
            : (s.status_label === 'Completed'? 'badge-good': 'badge-neutral');
        return `
            <tr>
                <td><strong>${s.slot_id}</strong></td>
                <td>${s.date}</td>
                <td><strong>${s.time_label}</strong></td>
                <td>${s.duration_minutes} mins</td>
                <td>${s.room_label}</td>
                <td><span class="badge ${badgeCls}">${s.status_label}</span></td>
            </tr>
        `;
    }).join('');
}

window.shiftMonth = function(delta) {
    viewMonth += delta;
    if (viewMonth < 0) { viewMonth = 11; viewYear--; }
    if (viewMonth >11) { viewMonth = 0; viewYear++; }
    renderMonthGrid();
};

window.goToday = function() {
    const now = new Date();
    viewYear = now.getFullYear();
    viewMonth = now.getMonth();
    selectedDateFilter = null;
    renderMonthGrid();
    renderScheduleTable();
};

window.selectDay = function(dateStr) {
    selectedDateFilter = dateStr;
    renderMonthGrid();
    renderScheduleTable();
};

window.clearDayFilter = function() {
    selectedDateFilter = null;
    renderMonthGrid();
    renderScheduleTable();
};

window.switchCalendarScope = function(scope) {
    calendarScope = scope;
    const bAll = document.getElementById('tabCalAll');
    const bYours = document.getElementById('tabCalYours');
    if (bAll && bYours) {
        bAll.className = scope === 'all'? 'btn btn-primary btn-sm': 'btn btn-secondary btn-sm';
        bYours.className = scope === 'yours'? 'btn btn-primary btn-sm': 'btn btn-secondary btn-sm';
    }
    renderMonthGrid();
    renderScheduleTable();
};

loadPublicCalendar();
setInterval(loadPublicCalendar, 15000);
</script>
</body>
</html>
