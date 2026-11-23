/**
 * HealthWing / VetSystem — Bone Loading Engine (bone-loader.js)
 *
 * Implements:
 * 1. Reusable Skeleton "Bone"Generators (`HWBone.tableRows`, `HWBone.petCards`, `HWBone.roomCards`,
 *    `HWBone.caseCards`, `HWBone.calendarGrid`, `HWBone.chatBubbles`, `HWBone.slotPreview`)
 *    that automatically populate empty/loading containers with realistic bone-shaped shimmer placeholders.
 * 2. Animated SVG Dog-Bone Spinner & Page/Action Overlay (`HWBone.showOverlay`, `HWBone.hideOverlay`,
 *    `HWBone.inlineSpinner`) for page loads, form submissions, and triage calculations.
 * 3. Automatic Fetch Interceptor (`window.fetch`wrapper) that lights up the top bone progress bar
 *    and injects skeleton bones into target tables/grids before data arrives.
 */
(function (global) {
    function dogBoneSvg(className = 'hw-bone-svg-spin', fill = '#4A9DBB') {
        return `<svg class="${className}" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M19.5 11C14.8 11 11 14.8 11 19.5C11 22.1 12.2 24.4 14.1 25.9L38.1 49.9C39.6 51.8 41.9 53 44.5 53C49.2 53 53 49.2 53 44.5C53 41.9 51.8 39.6 49.9 38.1L25.9 14.1C24.4 12.2 22.1 11 19.5 11ZM14.5 19.5C12.6 19.5 11 21.1 11 23C11 26.9 14.1 30 18 30C19.5 30 20.9 29.5 22 28.6L35.4 42C34.5 43.1 34 44.5 34 46C34 49.9 37.1 53 41 53C42.9 53 44.5 51.4 44.5 49.5" fill="${fill}" opacity="0.22"/>
            <path d="M45.2 12.2C41.8 12.2 39 14.7 38.6 18.0L18.0 38.6C14.7 39.0 12.2 41.8 12.2 45.2C12.2 48.9 15.2 51.8 18.8 51.8C20.2 51.8 21.5 51.3 22.6 50.5C21.8 51.6 21.3 52.9 21.3 54.3C21.3 58.0 24.3 61.0 28.0 61.0C31.4 61.0 34.2 58.5 34.6 55.2L55.2 34.6C58.5 34.2 61.0 31.4 61.0 28.0C61.0 24.3 58.0 21.3 54.3 21.3C52.9 21.3 51.6 21.8 50.5 22.6C51.3 21.5 51.8 20.2 51.8 18.8C51.8 15.2 48.9 12.2 45.2 12.2Z"
                  transform="translate(-4 -4)" fill="${fill}" stroke="#1F3A3D" stroke-width="2.5" stroke-linejoin="round"/>
        </svg>`;
    }

    function inlineSpinner(label = 'Loading clinical data...') {
        return `<span class="hw-bone-inline-loader">
            ${dogBoneSvg('hw-bone-svg-rock', '#4A9DBB')}
            <span>${label}</span>
        </span>`;
    }

    /** Generates N skeleton table rows with `cols`columns (using bone-shaped shimmer bars) */
    function tableRows(cols = 6, rows = 4) {
        const widths = ['65%', '82%', '55%', '75%', '60%', '70%', '50%', '88%', '64%'];
        let html = '';
        for (let r = 0; r < rows; r++) {
            html += `<tr class="hw-bone-row">`;
            for (let c = 0; c < cols; c++) {
                const w = widths[(r + c) % widths.length];
                const isBadgeCol = (c === cols - 2);
                if (isBadgeCol) {
                    html += `<td><span class="hw-bone hw-bone-pill"></span></td>`;
                } else {
                    html += `<td><div class="hw-bone hw-bone-shape" style="width:${w};"></div></td>`;
                }
            }
            html += `</tr>`;
        }
        return html;
    }

    /** Generates skeleton pet profile cards */
    function petCards(count = 3) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
            <div class="hw-bone-card">
                <div class="hw-bone hw-bone-photo">
                    ${dogBoneSvg('hw-bone-svg-rock', '#4A9DBB')}
                </div>
                <div class="hw-bone hw-bone-lg hw-bone-shape" style="width:58%;margin-top:4px;"></div>
                <div class="hw-bone hw-bone-sm" style="width:42%;"></div>
                <div class="hw-bone hw-bone-md" style="width:88%;margin-top:6px;"></div>
                <div class="hw-bone hw-bone-md" style="width:74%;"></div>
                <div class="hw-bone hw-bone-md" style="width:66%;"></div>
            </div>`;
        }
        return html;
    }

    /** Generates skeleton clinic room status cards */
    function roomCards(count = 5) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
            <div class="hw-bone-card" style="padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div class="hw-bone hw-bone-md hw-bone-shape" style="width:55%;"></div>
                    <span class="hw-bone hw-bone-pill" style="width:68px;height:20px;"></span>
                </div>
                <div class="hw-bone hw-bone-sm" style="width:40%;margin-top:4px;"></div>
                <div class="hw-bone hw-bone-md" style="width:100%;height:30px;margin-top:6px;"></div>
            </div>`;
        }
        return html;
    }

    /** Generates skeleton emergency case cards for Admin Emergency Requests */
    function caseCards(count = 4) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
            <div class="hw-bone-card">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div class="hw-bone hw-bone-md hw-bone-shape" style="width:52%;"></div>
                    <span class="hw-bone hw-bone-pill" style="width:74px;height:22px;"></span>
                </div>
                <div class="hw-bone hw-bone-sm" style="width:65%;"></div>
                <div class="hw-bone hw-bone-md" style="width:92%;height:44px;margin-top:4px;"></div>
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <div class="hw-bone hw-bone-md" style="width:32%;height:28px;"></div>
                    <div class="hw-bone hw-bone-md" style="width:32%;height:28px;"></div>
                    <div class="hw-bone hw-bone-md" style="width:32%;height:28px;"></div>
                </div>
            </div>`;
        }
        return html;
    }

    /** Generates skeleton calendar cells (35 cells) */
    function calendarGrid(cells = 35) {
        let html = '';
        for (let i = 0; i < cells; i++) {
            html += `
            <div class="cal-cell" style="pointer-events:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <span class="hw-bone hw-bone-sm" style="width:22px;height:16px;"></span>
                    <span class="hw-bone hw-bone-sm" style="width:36px;height:12px;"></span>
                </div>
                <div class="hw-bone hw-bone-shape" style="width:90%;height:18px;margin-bottom:4px;"></div>
                ${i % 2 === 0 ? `<div class="hw-bone hw-bone-shape" style="width:78%;height:18px;"></div>`: ''}
            </div>`;
        }
        return html;
    }

    /** Generates skeleton chat message bubbles */
    function chatBubbles() {
        return `
            <div style="display:flex;flex-direction:column;gap:10px;padding:8px 4px;">
                <div style="align-self:flex-start;width:72%;" class="hw-bone hw-bone-xl"></div>
                <div style="align-self:flex-end;width:64%;" class="hw-bone hw-bone-xl"></div>
                <div style="align-self:flex-start;width:80%;" class="hw-bone hw-bone-xl"></div>
                <div style="align-self:center;margin-top:6px;">${inlineSpinner('Syncing conversation...')}</div>
            </div>
        `;
    }

    /** Generates skeleton smart slot preview bars */
    function slotPreview() {
        return `
            <div style="display:flex;flex-direction:column;gap:8px;padding:4px 0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    ${dogBoneSvg('hw-bone-svg-spin', '#4A9DBB')}
                    <div class="hw-bone hw-bone-md hw-bone-shape" style="width:62%;"></div>
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;">
                    <div class="hw-bone hw-bone-sm" style="width:28%;"></div>
                    <div class="hw-bone hw-bone-sm" style="width:32%;"></div>
                    <div class="hw-bone hw-bone-sm" style="width:26%;"></div>
                </div>
            </div>
        `;
    }

    function ensureOverlayDOM() {
        if (!document.getElementById('hwTopBoneBar')) {
            const topBar = document.createElement('div');
            topBar.id = 'hwTopBoneBar';
            document.body.appendChild(topBar);
        }
        if (!document.getElementById('hwBoneOverlay')) {
            const ov = document.createElement('div');
            ov.id = 'hwBoneOverlay';
            ov.innerHTML = `
                <div class="hw-bone-loader-card">
                    ${dogBoneSvg('hw-bone-svg-spin', '#4A9DBB')}
                    <div class="hw-bone-loader-title" id="hwBoneOverlayTitle">Fetching Clinic Data...</div>
                    <div class="hw-bone-loader-sub" id="hwBoneOverlaySub">Running smart scheduling & priority triage sync</div>
                    <div class="hw-bone-progress-track">
                        <div class="hw-bone-progress-bar"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(ov);
        }
    }

    function showOverlay(title = 'Processing...', subtitle = 'Synchronizing clinic schedule & priority triage') {
        ensureOverlayDOM();
        document.getElementById('hwBoneOverlayTitle').textContent = title;
        document.getElementById('hwBoneOverlaySub').textContent = subtitle;
        document.getElementById('hwBoneOverlay').classList.remove('hw-hidden');
    }

    function hideOverlay() {
        const ov = document.getElementById('hwBoneOverlay');
        if (ov) {
            ov.classList.add('hw-hidden');
        }
    }

    // Intercept window.fetch to show the top bone shimmer bar on every async call
    let activeFetches = 0;
    const origFetch = global.fetch ? global.fetch.bind(global) : null;
    if (origFetch) {
        global.fetch = function (...args) {
            activeFetches++;
            const topBar = document.getElementById('hwTopBoneBar');
            if (topBar) topBar.classList.add('active');
            return origFetch(...args).finally(() =>{
                activeFetches = Math.max(0, activeFetches - 1);
                if (activeFetches === 0 && topBar) {
                    setTimeout(() =>{
                        if (activeFetches === 0) topBar.classList.remove('active');
                    }, 180);
                }
            });
        };
    }

    // Automatically inject initial bone skeletons into empty tables/grids on DOMContentLoaded
    function autoPopulateInitialBones() {
        ensureOverlayDOM();

        // Hide initial page-load bone overlay smoothly after 320ms
        setTimeout(hideOverlay, 320);

        // Slow Fade-In and Fade-Out Observer for all Cards
        if ('IntersectionObserver' in window) {
            const cardFadeObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('hw-card-fade-in');
                        entry.target.classList.remove('hw-card-fade-out');
                    } else {
                        entry.target.classList.add('hw-card-fade-out');
                        entry.target.classList.remove('hw-card-fade-in');
                    }
                });
            }, { threshold: 0.08 });

            const observeCards = () => {
                document.querySelectorAll('.panel, .pet-card, .room-card, .case-card, .cal-cell, .room-pill, .hw-bone-card, .stat').forEach((card, i) => {
                    if (!card.dataset.hwFadeObserved) {
                        card.dataset.hwFadeObserved = '1';
                        card.style.animationDelay = `${Math.min(i * 55, 380)}ms`;
                        cardFadeObserver.observe(card);
                    }
                });
            };
            observeCards();
            const mutObs = new MutationObserver(observeCards);
            mutObs.observe(document.body, { childList: true, subtree: true });
        }


        // Show bone overlay automatically when any non-AJAX form is submitted
        document.querySelectorAll('form').forEach(form =>{
            form.addEventListener('submit', function () {
                if (!this.id || this.id === 'smartBookingForm') {
                    showOverlay('Locking Smart Appointment...', 'Matching optimal specialist veterinarian & clinic room');
                }
            });
        });

        // Pre-fill empty data-tables with skeleton bone rows while their initial fetch is in flight
        document.querySelectorAll('table.data-table').forEach(tbl =>{
            const tbody = tbl.querySelector('tbody');
            const thCount = tbl.querySelectorAll('thead th').length || 6;
            if (tbody && tbody.children.length === 0) {
                tbody.innerHTML = tableRows(thCount, 4);
            }
        });

        const petGrid = document.getElementById('petGrid');
        if (petGrid && (petGrid.children.length === 0 || petGrid.querySelector('.empty'))) {
            petGrid.innerHTML = petCards(3);
        }

        const roomGrid = document.getElementById('roomGrid') || document.getElementById('vetRoomGrid') || document.getElementById('publicRoomStrip');
        if (roomGrid && roomGrid.children.length === 0) {
            roomGrid.innerHTML = roomCards(5);
        }

        const caseGrid = document.getElementById('caseGrid');
        if (caseGrid && caseGrid.children.length === 0) {
            caseGrid.innerHTML = caseCards(4);
        }

        const calGrid = document.getElementById('calDaysGrid');
        if (calGrid && calGrid.children.length === 0) {
            calGrid.innerHTML = calendarGrid(35);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoPopulateInitialBones);
    } else {
        autoPopulateInitialBones();
    }

    global.HWBone = {
        dogBoneSvg,
        inlineSpinner,
        tableRows,
        petCards,
        roomCards,
        caseCards,
        calendarGrid,
        chatBubbles,
        slotPreview,
        showOverlay,
        hideOverlay,
    };
})(window);
