/**
 * HealthWing / VetSystem — Loading & CSRF Fetch Engine (assets/js/loader.js)
 */
(function (global) {
    function spinnerSvg(className = 'hw-bone-svg-spin', fill = '#4A9DBB') {
        return `<svg class="${className}" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="32" cy="32" r="24" stroke="#E4EEF0" stroke-width="6"/>
            <path d="M32 8 A24 24 0 0 1 56 32" stroke="${fill}" stroke-width="6" stroke-linecap="round"/>
        </svg>`;
    }

    function inlineSpinner(label = 'Loading...') {
        return `<span class="hw-bone-inline-loader">
            ${spinnerSvg('hw-bone-svg-spin', '#4A9DBB')}
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
                    ${spinnerSvg('hw-bone-svg-spin', '#4A9DBB')}
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

    /** Generates skeleton emergency case cards */
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
                    ${spinnerSvg('hw-bone-svg-spin', '#4A9DBB')}
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
                    ${spinnerSvg('hw-bone-svg-spin', '#4A9DBB')}
                    <div class="hw-bone-loader-title" id="hwBoneOverlayTitle">Loading...</div>
                    <div class="hw-bone-loader-sub" id="hwBoneOverlaySub">Please wait while clinic data loads</div>
                    <div class="hw-bone-progress-track">
                        <div class="hw-bone-progress-bar"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(ov);
        }
    }

    function showOverlay(title = 'Loading...', subtitle = 'Synchronizing clinic schedule and triage data') {
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

    // Intercept window.fetch to show the loading bar and inject CSRF token on state-changing requests
    let activeFetches = 0;
    const origFetch = global.fetch ? global.fetch.bind(global) : null;
    if (origFetch) {
        global.fetch = function (input, init) {
            const opts = Object.assign({}, init || {});
            const method = String(opts.method || (input && input.method) || 'GET').toUpperCase();
            if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                const csrfMeta = document.querySelector('meta[name="hw-csrf-token"]')
                    || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                if (csrfToken) {
                    if (opts.headers instanceof Headers) {
                        if (!opts.headers.has('X-CSRF-Token')) {
                            opts.headers.set('X-CSRF-Token', csrfToken);
                        }
                    } else {
                        opts.headers = Object.assign({}, opts.headers || {});
                        if (!opts.headers['X-CSRF-Token'] && !opts.headers['x-csrf-token']) {
                            opts.headers['X-CSRF-Token'] = csrfToken;
                        }
                    }
                }
            }
            activeFetches++;
            const topBar = document.getElementById('hwTopBoneBar');
            if (topBar) topBar.classList.add('active');
            return origFetch(input, opts).finally(() => {
                activeFetches = Math.max(0, activeFetches - 1);
                if (activeFetches === 0 && topBar) {
                    setTimeout(() => {
                        if (activeFetches === 0) topBar.classList.remove('active');
                    }, 180);
                }
            });
        };
    }

    // Automatically inject initial placeholder skeletons into empty tables/grids on DOMContentLoaded
    function autoPopulateInitialLoaders() {
        ensureOverlayDOM();

        // Hide initial page-load overlay smoothly after 320ms
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

        // Show loading overlay automatically when any non-AJAX form is submitted
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                if (!this.id || this.id === 'smartBookingForm') {
                    showOverlay('Loading...', 'Processing your request');
                }
            });
        });

        // Pre-fill empty data-tables with skeleton rows while their initial fetch is in flight
        document.querySelectorAll('table.data-table').forEach(tbl => {
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
        document.addEventListener('DOMContentLoaded', autoPopulateInitialLoaders);
    } else {
        autoPopulateInitialLoaders();
    }

    const loaderApi = {
        dogBoneSvg: spinnerSvg,
        spinnerSvg,
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
    global.HWBone = loaderApi;
    global.HWLoader = loaderApi;
})(window);
