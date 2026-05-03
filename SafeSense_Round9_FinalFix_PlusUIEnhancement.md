# SafeSense — Round 9: Final Bug Fixes + Full UI/UX Enhancement
### For Windsurf Kimi K2.5

> This prompt has two parts. **Part A** is the final two-issue surgical fix (home.php borders + ROLE_STAFF). **Part B** is a comprehensive UI/UX upgrade — skeleton loading screens, shimmer effects, micro-animations, and polish — all translated into pure CSS and vanilla JS (no React, no npm) for the existing PHP/Bootstrap stack. All inspiration drawn from boneyard-js, animata, and turbostarter/loading-ui.

---

# PART A — FINAL TWO-ISSUE FIX

## Fix 1 — `home.php`: Bootstrap Border Conflict on Feature Cards

**File:** `medical/app/Views/home.php` — lines 17, 26, 35

**Problem:** `border-primary border-top border-3` uses Bootstrap's `.border-primary` which sets `--bs-border-color` on ALL 4 sides with `!important`, coloring all edges of the card — not just the top as intended. Same root cause as the stat card issue fixed in Round 5.

**Find and replace the three card opening divs:**

```html
<!-- Patients card BEFORE -->
<div class="card h-100 border-primary border-top border-3">
<!-- AFTER -->
<div class="card h-100" style="border-top: 3px solid var(--ss-primary);">

<!-- Doctors card BEFORE -->
<div class="card h-100 border-success border-top border-3">
<!-- AFTER -->
<div class="card h-100" style="border-top: 3px solid #16a34a;">

<!-- Appointments card BEFORE -->
<div class="card h-100 border-info border-top border-3">
<!-- AFTER -->
<div class="card h-100" style="border-top: 3px solid #0891b2;">
```

## Fix 2 — `config.php`: `ROLE_STAFF` Constant Missing

**File:** `medical/app/Config/config.php` — after line 45 (after `ROLE_NURSE`)

```php
// BEFORE (lines 43–45):
define('ROLE_ADMIN',   'admin');
define('ROLE_DOCTOR',  'doctor');
define('ROLE_NURSE',   'nurse');

// AFTER:
define('ROLE_ADMIN',   'admin');
define('ROLE_DOCTOR',  'doctor');
define('ROLE_NURSE',   'nurse');
define('ROLE_STAFF',   'staff');
```

**Files that use `Part A` only:** `home.php`, `config.php`. No other files.

---

# PART B — COMPREHENSIVE UI/UX ENHANCEMENT

> **Stack constraint:** This is PHP + Bootstrap 5.3 + vanilla JS. No React, no npm, no build step. All patterns from boneyard-js, animata, and turbostarter/loading-ui must be translated to pure CSS keyframes and vanilla JS DOM manipulation.

---

## B1 — Skeleton Loading System (Inspired by Boneyard + Loading-UI)

**File:** `medical/public/css/style.css` — add a new "Skeleton Loading" section

The boneyard library's core insight: skeleton shapes match the real layout exactly. The loading-ui skeleton uses a simple opacity-pulse keyframe. We combine both: shape-accurate skeletons + a shimmer sweep animation.

```css
/* ════════════════════════════════════════════
   SKELETON LOADING SYSTEM
   Inspired by boneyard-js layout accuracy +
   turbostarter/loading-ui shimmer animation
════════════════════════════════════════════ */

/* ── Base shimmer keyframe (from loading-ui skeleton.tsx adapted to CSS) ── */
@keyframes ss-shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}
@keyframes ss-skel-pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: .45; }
}

/* ── Single skeleton bone ── */
.ss-skel {
  display: block;
  border-radius: var(--r-sm);
  background: linear-gradient(
    90deg,
    #e8edf3 25%,
    #f4f6f9 50%,
    #e8edf3 75%
  );
  background-size: 800px 100%;
  animation: ss-shimmer 1.6s ease-in-out infinite;
}
.ss-skel.rounded-full { border-radius: 9999px; }
.ss-skel.rounded      { border-radius: var(--r-md); }
.ss-skel.rounded-lg   { border-radius: var(--r-lg); }

/* ── Skeleton stat card (mirrors real .stat-card layout) ── */
/* Boneyard dashboard-stats.bones.json layout: card + label + value + subtext */
.ss-skel-stat-card {
  border: 1px solid var(--ss-border);
  border-radius: var(--r-lg);
  background: var(--ss-surface);
  padding: 1.25rem 1.5rem;
  height: 100%;
  overflow: hidden;
}
.ss-skel-stat-card .skel-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.ss-skel-stat-card .skel-label  { height: 11px; width: 55%; margin-bottom: .55rem; }
.ss-skel-stat-card .skel-value  { height: 28px; width: 40%; margin-bottom: .3rem; }
.ss-skel-stat-card .skel-sub    { height: 9px;  width: 30%; }
.ss-skel-stat-card .skel-icon   {
  width: 44px;
  height: 44px;
  border-radius: var(--r-md);
  flex-shrink: 0;
}

/* ── Skeleton table row (mirrors real tbody td layout) ── */
/* Boneyard user-table.bones.json: 6 full-width rows, 28px each */
.ss-skel-table-row {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .9rem 1rem;
  border-bottom: 1px solid var(--ss-border);
}
.ss-skel-table-row .skel-cell-sm  { height: 12px; width: 8%; }
.ss-skel-table-row .skel-cell-md  { height: 12px; width: 20%; }
.ss-skel-table-row .skel-cell-lg  { height: 12px; flex: 1; }
.ss-skel-table-row .skel-cell-btn { height: 28px; width: 60px; border-radius: var(--r-sm); }

/* ── Skeleton alert card (mirrors real ss-alert-card layout) ── */
.ss-skel-alert {
  border: 1px solid var(--ss-border);
  border-left: 4px solid var(--ss-border-strong);
  border-radius: var(--r-lg);
  padding: 1rem 1.25rem;
  background: var(--ss-surface);
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}
.ss-skel-alert .skel-icon-bubble { width: 44px; height: 44px; border-radius: var(--r-md); flex-shrink: 0; }
.ss-skel-alert .skel-badge       { height: 20px; width: 80px; border-radius: 99px; margin-bottom: .6rem; }
.ss-skel-alert .skel-msg-1       { height: 13px; width: 90%; margin-bottom: .4rem; }
.ss-skel-alert .skel-msg-2       { height: 13px; width: 65%; margin-bottom: .7rem; }
.ss-skel-alert .skel-meta        { height: 10px; width: 50%; }

/* ── Skeleton notification item (mirrors ss-notif in drawer) ── */
/* Boneyard activity.bones.json layout: dot + two text lines per item */
.ss-skel-notif {
  border-radius: var(--r-md);
  padding: .85rem .95rem;
  margin-bottom: 5px;
  border-left: 3.5px solid var(--ss-border-strong);
  background: var(--ss-surface);
}
.ss-skel-notif .skel-notif-level { height: 10px; width: 55px; margin-bottom: .45rem; border-radius: 99px; }
.ss-skel-notif .skel-notif-msg-1 { height: 12px; width: 90%; margin-bottom: .3rem; }
.ss-skel-notif .skel-notif-msg-2 { height: 12px; width: 65%; margin-bottom: .55rem; }
.ss-skel-notif .skel-notif-meta  { height: 9px; width: 80%; }

/* ── Skeleton chart placeholder ── */
.ss-skel-chart {
  width: 100%;
  height: 200px;
  border-radius: var(--r-md);
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 1rem;
}
.ss-skel-chart-bar {
  flex: 1;
  border-radius: var(--r-sm) var(--r-sm) 0 0;
}
/* Staggered animation delays on chart bars */
.ss-skel-chart-bar:nth-child(1) { height: 60%; animation-delay: 0s; }
.ss-skel-chart-bar:nth-child(2) { height: 80%; animation-delay: .1s; }
.ss-skel-chart-bar:nth-child(3) { height: 45%; animation-delay: .2s; }
.ss-skel-chart-bar:nth-child(4) { height: 90%; animation-delay: .3s; }
.ss-skel-chart-bar:nth-child(5) { height: 55%; animation-delay: .4s; }
.ss-skel-chart-bar:nth-child(6) { height: 70%; animation-delay: .5s; }
.ss-skel-chart-bar:nth-child(7) { height: 85%; animation-delay: .6s; }
.ss-skel-chart-bar:nth-child(8) { height: 40%; animation-delay: .7s; }

/* ── Skeleton visibility toggle (JS adds/removes .ss-loaded) ── */
.ss-skeleton-wrap { transition: opacity var(--t-base); }
.ss-skeleton-wrap.ss-loaded { display: none !important; }
.ss-content-wrap  { opacity: 0; transition: opacity var(--t-slow); }
.ss-content-wrap.ss-loaded  { opacity: 1; }
```

---

## B2 — Skeleton HTML for Dashboard Stat Cards

**File:** `medical/app/Views/dashboard.php`

Wrap each existing stat row with a skeleton that shows while the page loads and disappears once JS confirms the DOM is ready. Place the skeleton HTML **immediately before** each `.row.g-3` that contains stat cards:

```html
<!-- ── Stat Cards Skeleton (shown for ~400ms on load, then hidden) ── -->
<div class="ss-skeleton-wrap" id="skelStats">
  <div class="row g-3 mb-4">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="col-sm-6 col-lg-3">
      <div class="ss-skel-stat-card">
        <div class="skel-row">
          <div>
            <div class="ss-skel skel-label"></div>
            <div class="ss-skel skel-value"></div>
            <div class="ss-skel skel-sub"></div>
          </div>
          <div class="ss-skel skel-icon"></div>
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<!-- ── Real stat cards (initially hidden, revealed after skeleton) ── -->
<div class="ss-content-wrap" id="realStats">
  <!-- existing row g-3 mb-4 stat cards here — UNCHANGED -->
```

Close the `ss-content-wrap` div after the billing row too:
```html
  <!-- end of billing row g-3 mb-4 -->
</div><!-- end #realStats -->
```

Add a matching skeleton for the billing stats row (same pattern, 4 cards).

---

## B3 — Skeleton HTML for DataTables

**File:** `medical/public/js/app.js` — inside `initCrudModule()`, in the `initComplete` callback of DataTables

After the DataTable initialises, it hides its own initial loading state. We need to show a skeleton while DataTables initialises. Add before `const dt = new DataTable(...)`:

```js
// Show skeleton before DataTable init
const tableWrapper = tableEl.parentElement;
const skelId = 'skel-' + cfg.tableId;
const skelHtml = `
  <div id="${skelId}" class="ss-skeleton-wrap">
    ${Array.from({length: 6}).map(() => `
      <div class="ss-skel-table-row">
        <div class="ss-skel skel-cell-sm"></div>
        <div class="ss-skel skel-cell-md"></div>
        <div class="ss-skel skel-cell-lg"></div>
        <div class="ss-skel skel-cell-lg"></div>
        <div class="ss-skel skel-cell-btn"></div>
      </div>
    `).join('')}
  </div>`;
tableWrapper.insertAdjacentHTML('beforebegin', skelHtml);
tableEl.style.opacity = '0';
```

Then in `initComplete` (already there), add:
```js
initComplete: function() {
  // Hide skeleton, reveal table
  const skel = document.getElementById(skelId);
  if (skel) skel.classList.add('ss-loaded');
  tableEl.style.transition = 'opacity 0.3s ease';
  tableEl.style.opacity = '1';

  // existing length selector fix code...
  const lengthSel = tableEl.closest('.dataTables_wrapper') ...
}
```

---

## B4 — Skeleton for Notification Drawer

**File:** `medical/app/Views/layouts/main.php` — inside `ssDrawerBody`, replace the `ssNoAlerts` paragraph with a skeleton + no-alerts state:

```html
<div class="ss-drawer-body" id="ssDrawerBody">
  <!-- Skeleton shown while init fetch runs -->
  <div id="ssDrawerSkel" class="ss-skeleton-wrap p-2">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="ss-skel-notif mb-1">
      <div class="ss-skel skel-notif-level"></div>
      <div class="ss-skel skel-notif-msg-1"></div>
      <div class="ss-skel skel-notif-msg-2"></div>
      <div class="ss-skel skel-notif-meta"></div>
    </div>
    <?php endfor; ?>
  </div>
  <p class="text-muted text-center py-4" id="ssNoAlerts" style="display:none;">
    <i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>No new alerts — all clear.
  </p>
</div>
```

**In the IIFE** (`main.php` JS), update the init `.then()` to hide the skeleton after the fetch completes:
```js
.then(d => {
  // Hide drawer skeleton once data arrives
  const drawerSkel = document.getElementById('ssDrawerSkel');
  if (drawerSkel) drawerSkel.classList.add('ss-loaded');
  if (!d.alerts || d.alerts.length === 0) {
    noAlerts.style.display = '';
  }
  // rest of existing init code...
```

---

## B5 — Shimmer on Page Headings (Inspired by loading-ui `text-shimmer.tsx`)

The text-shimmer component sweeps a bright gradient across text. We translate this to a CSS-only version for page headings and the dashboard title.

**Add to `style.css`:**
```css
/* ════════════════════════════════════════════
   TEXT SHIMMER (inspired by turbostarter/loading-ui text-shimmer.tsx)
   Translated to pure CSS — no React/motion needed
════════════════════════════════════════════ */
@keyframes ss-text-shimmer {
  0%   { background-position: 200% center; }
  100% { background-position: -200% center; }
}

.ss-shimmer-text {
  background: linear-gradient(
    90deg,
    var(--ss-text) 20%,
    var(--ss-primary) 40%,
    #60a5fa 50%,
    var(--ss-primary) 60%,
    var(--ss-text) 80%
  );
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: ss-text-shimmer 4s linear infinite;
}

/* Applied only to the icon in page headings — subtle color shift */
.page-header h1 i {
  animation: ss-text-shimmer 4s linear infinite;
  background: linear-gradient(90deg, var(--ss-primary) 0%, #60a5fa 50%, var(--ss-primary) 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

**Apply in dashboard.php** — update the Dashboard h1:
```html
<!-- BEFORE -->
<h1><i class="fas fa-tachometer-alt text-primary"></i>Dashboard</h1>

<!-- AFTER -->
<h1><i class="fas fa-tachometer-alt"></i><span class="ss-shimmer-text">Dashboard</span></h1>
```

The icon shimmer applies globally via `.page-header h1 i` CSS rule — no HTML changes needed on other pages.

---

## B6 — Ripple Ring on Live Dot (Inspired by loading-ui `ripple.tsx`)

The ripple component expands concentric rings. We replicate this on the `.ss-live-dot` using CSS pseudo-elements.

**Replace the existing `.ss-live-dot` CSS block in `style.css`:**
```css
/* ── Live Dot — with ripple rings (loading-ui ripple.tsx pattern) ── */
.ss-live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  flex-shrink: 0;
  position: relative;
  animation: ss-pulse 2s infinite;
}
/* Ripple rings using box-shadow animation */
@keyframes ss-live-ripple {
  0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.6), 0 0 0 0 rgba(34,197,94,.3); }
  70%  { box-shadow: 0 0 0 6px rgba(34,197,94,0), 0 0 0 12px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0),  0 0 0 0 rgba(34,197,94,0); }
}
.ss-live-dot {
  animation: ss-live-ripple 2s ease-out infinite;
}
.ss-live-dot--sm {
  width: 6px;
  height: 6px;
}
/* Keep the ss-pulse keyframe for the bell badge, rename to avoid conflict */
@keyframes ss-dot-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
  70%  { box-shadow: 0 0 0 5px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}
```

---

## B7 — Top Progress Bar for AJAX Calls (Inspired by animata progress components)

A thin colored bar sweeps across the top of the screen during AJAX fetch calls. This is a global enhancement — it automatically activates whenever `ajaxPost` or `poll` runs.

**Add to `style.css`:**
```css
/* ════════════════════════════════════════════
   TOP PROGRESS BAR (animata progress pattern)
════════════════════════════════════════════ */
#ssProgressBar {
  position: fixed;
  top: 0;
  left: 0;
  width: 0%;
  height: 3px;
  background: linear-gradient(90deg, var(--ss-primary) 0%, #60a5fa 50%, var(--ss-primary) 100%);
  background-size: 200% 100%;
  z-index: 9999;
  transition: width .3s ease, opacity .4s ease;
  opacity: 0;
  pointer-events: none;
  animation: ss-progress-shimmer 1.5s linear infinite;
}
#ssProgressBar.active {
  opacity: 1;
  animation: ss-progress-shimmer 1.5s linear infinite;
}
@keyframes ss-progress-shimmer {
  0%   { background-position: 200% center; }
  100% { background-position: -200% center; }
}
```

**Add to `medical/app/Views/layouts/main.php`** — right after `<body>`:
```html
<body>
<div id="ssProgressBar"></div>
```

**Add to `medical/public/js/app.js`** — update `ajaxPost` to trigger the bar:
```js
// Progress bar helpers
const progBar = document.getElementById('ssProgressBar');
function progStart() {
  if (!progBar) return;
  progBar.style.width = '0%';
  progBar.classList.add('active');
  // Simulate incremental progress
  let w = 0;
  progBar._timer = setInterval(() => {
    w = Math.min(w + Math.random() * 15, 85);
    progBar.style.width = w + '%';
  }, 200);
}
function progDone() {
  if (!progBar) return;
  clearInterval(progBar._timer);
  progBar.style.width = '100%';
  setTimeout(() => { progBar.classList.remove('active'); progBar.style.width = '0%'; }, 400);
}

// Wrap existing ajaxPost to trigger progress bar
const _origAjaxPost = ajaxPost;
function ajaxPost(url, formData) {
  progStart();
  return _origAjaxPost(url, formData).finally(progDone);
}
window.ajaxPost = ajaxPost;
```

---

## B8 — Card Hover Micro-Animations (Inspired by animata card patterns)

**Add to `style.css`:**
```css
/* ════════════════════════════════════════════
   CARD MICRO-ANIMATIONS (animata card patterns)
════════════════════════════════════════════ */

/* Stat card — lift + left accent color brightens on hover */
.stat-card {
  transition: box-shadow var(--t-base), transform var(--t-base), border-left-color var(--t-base);
}
.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-3px);
}
/* Icon scales on stat card hover */
.stat-card:hover .stat-icon {
  transform: scale(1.08);
  transition: transform var(--t-spring);
}
.stat-icon {
  transition: transform var(--t-base);
}

/* Alert card — slide right on hover (animata list hover pattern) */
.ss-alert-card {
  transition: box-shadow var(--t-base), transform var(--t-base);
}
.ss-alert-card:hover {
  transform: translateX(4px) translateY(-1px);
  box-shadow: var(--shadow-md) !important;
}

/* Dashboard alert row — highlight bar slides in from left */
.ss-dash-alert-row {
  transition: background var(--t-fast), padding-left var(--t-base);
}
.ss-dash-alert-row:hover {
  padding-left: 1.4rem;
  background: var(--ss-primary-light);
}

/* Nav link — underline slide-in (animata text underline pattern) */
.navbar .nav-link {
  position: relative;
}
.navbar .nav-link::after {
  content: '';
  position: absolute;
  bottom: 2px;
  left: .75rem;
  right: .75rem;
  height: 2px;
  background: rgba(255,255,255,.6);
  border-radius: 99px;
  transform: scaleX(0);
  transform-origin: left;
  transition: transform var(--t-base);
}
.navbar .nav-link:hover::after,
.navbar .nav-link.active::after { transform: scaleX(1); }

/* Button — ripple effect (animata button pattern) */
.btn { overflow: hidden; position: relative; }
.btn::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,.15);
  border-radius: inherit;
  transform: scale(0);
  opacity: 0;
  transition: transform .4s ease, opacity .4s ease;
}
.btn:active::after {
  transform: scale(2);
  opacity: 0;
  transition: 0s;
}
```

---

## B9 — Smooth Page Reveal Animation

**Add to `style.css`:**
```css
/* ════════════════════════════════════════════
   PAGE REVEAL (animata container fade pattern)
════════════════════════════════════════════ */
@keyframes ss-page-in {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
main.container {
  animation: ss-page-in .35s ease both;
}

/* Page header reveal — slight delay after main */
.page-header {
  animation: ss-page-in .4s .05s ease both;
}

/* Cards stagger in — first 4 direct children of a .row.g-3 */
.row.g-3 > .col-sm-6:nth-child(1),
.row.g-3 > .col-lg-3:nth-child(1) { animation: ss-page-in .4s .08s ease both; }
.row.g-3 > .col-sm-6:nth-child(2),
.row.g-3 > .col-lg-3:nth-child(2) { animation: ss-page-in .4s .14s ease both; }
.row.g-3 > .col-sm-6:nth-child(3),
.row.g-3 > .col-lg-3:nth-child(3) { animation: ss-page-in .4s .20s ease both; }
.row.g-3 > .col-sm-6:nth-child(4),
.row.g-3 > .col-lg-3:nth-child(4) { animation: ss-page-in .4s .26s ease both; }
```

---

## B10 — Dashboard Skeleton: JS-Controlled Show/Hide

**Add to `medical/app/Views/dashboard.php`** — at the bottom of the page's `<script>` block (after the chart code):

```js
// Show skeleton → reveal real content after a brief moment (or after chart loads)
(function() {
  const skelStats  = document.getElementById('skelStats');
  const realStats  = document.getElementById('realStats');
  const skelBilling = document.getElementById('skelBilling');
  const realBilling = document.getElementById('realBilling');

  // Real content was hidden at start; reveal after 350ms (feels natural, hides flash)
  setTimeout(() => {
    if (skelStats)   skelStats.classList.add('ss-loaded');
    if (realStats)   realStats.classList.add('ss-loaded');
    if (skelBilling) skelBilling.classList.add('ss-loaded');
    if (realBilling) realBilling.classList.add('ss-loaded');
  }, 350);
})();
```

Also add corresponding skeleton for the billing row above the billing `.row.g-3.mb-4`:

```html
<!-- Billing Stats Skeleton -->
<div class="ss-skeleton-wrap" id="skelBilling">
  <div class="row g-3 mb-4">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="col-sm-6 col-lg-3">
      <div class="ss-skel-stat-card">
        <div class="skel-row">
          <div>
            <div class="ss-skel skel-label"></div>
            <div class="ss-skel skel-value"></div>
            <div class="ss-skel skel-sub"></div>
          </div>
          <div class="ss-skel skel-icon"></div>
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<div class="ss-content-wrap" id="realBilling">
  <!-- existing billing row g-3 mb-4 — UNCHANGED -->
  ...
</div>
```

---

## B11 — Chart Area Skeleton

**File:** `medical/app/Views/dashboard.php` — the chart card bodies

Replace each `<canvas>` container with a skeleton + canvas structure:

```html
<!-- Alerts chart card body -->
<div class="card-body d-flex align-items-center justify-content-center" id="alertsChartWrap">
  <!-- Skeleton chart bars (shown while AJAX loads) -->
  <div id="skelAlertsChart" class="ss-skel-chart ss-skeleton-wrap w-100">
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
    <div class="ss-skel ss-skel-chart-bar"></div>
  </div>
  <canvas id="alertsChart" height="200" style="display:none;"></canvas>
</div>
```

In the existing chart JS `.then(data => { ... })` block, after the Chart is created, add:

```js
// Alerts chart
const skelAlertsChart = document.getElementById('skelAlertsChart');
if (skelAlertsChart) skelAlertsChart.classList.add('ss-loaded');
document.getElementById('alertsChart').style.display = '';

// Appointments chart (same pattern)
const skelApptChart = document.getElementById('skelApptChart');
if (skelApptChart) skelApptChart.classList.add('ss-loaded');
document.getElementById('appointmentsChart').style.display = '';
```

Apply the same skeleton markup to the appointments chart card body (same pattern, 8 bars).

---

## B12 — Enhanced Auth Page Animation (Inspired by animata hero patterns)

**File:** `medical/app/Views/layouts/auth.php` + `style.css`

```css
/* Auth card reveal — slides up from below with spring */
@keyframes ss-auth-in {
  from { opacity: 0; transform: translateY(24px) scale(.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.auth-card {
  animation: ss-auth-in .55s var(--t-spring) both;
}

/* Auth logo pulse (animata icon pulse pattern) */
@keyframes ss-logo-pulse {
  0%, 100% { filter: drop-shadow(0 0 0 rgba(220,38,38,0)); }
  50%       { filter: drop-shadow(0 0 12px rgba(220,38,38,.45)); }
}
.auth-logo {
  animation: ss-logo-pulse 2.5s ease-in-out infinite;
}

/* Auth input focus — gentle glow expands */
.auth-card .form-control {
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}
.auth-card .form-control:focus {
  transform: none; /* override base .form-control focus if any */
  box-shadow: 0 0 0 3px rgba(59,130,246,.28), 0 1px 6px rgba(15,23,42,.15);
}
```

---

## 📋 COMPLETE FILE TOUCH LIST

| File | Changes |
|------|---------|
| `medical/app/Config/config.php` | Add `ROLE_STAFF` constant |
| `medical/app/Views/home.php` | Fix 3 card border classes → inline style |
| `medical/public/css/style.css` | Add: skeleton system, text shimmer, ripple live dot, progress bar, card micro-animations, page reveal, auth animations |
| `medical/public/js/app.js` | Add: `progStart/progDone`, wrap `ajaxPost` with progress bar, skeleton show/hide in `initCrudModule` |
| `medical/app/Views/layouts/main.php` | Add `#ssProgressBar` div; skeleton markup in drawer body; update init `.then()` to hide drawer skeleton |
| `medical/app/Views/dashboard.php` | Add stat card skeletons + billing skeletons + chart bar skeletons; add JS reveal timeout |

---

## ⚠️ CONSTRAINTS

1. No npm, no React, no build step — all CSS/JS must be pure vanilla.
2. Do NOT remove any existing CSS rules — only add new sections.
3. Skeletons must be visually accurate to the real content they replace (matching height, width proportions, border-radius, and layout direction).
4. The `ss-skeleton-wrap.ss-loaded { display:none }` approach is deliberate — it fully removes skeletons from layout flow after reveal, preventing double-spacing.
5. The `@keyframes ss-text-shimmer` on `.page-header h1 i` is subtle — a slow 4s loop, not a rapid flash. Adjust `animation-duration` down to `3s` if you want it more visible.
6. The progress bar wraps `ajaxPost` only — the `poll()` fetch deliberately does NOT trigger the progress bar (it runs silently every 5 seconds and would create constant visual noise).
7. The drawer skeleton uses PHP to render the initial 4 skeleton items server-side — this is correct and intentional so the skeleton appears instantly without waiting for JS.
