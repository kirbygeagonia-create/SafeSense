# SafeSense — Definitive WindSurf Kimi K2.5 Fix Prompt

> **Copy and paste this entire document into WindSurf with the SafeSense project open and Kimi K2.5 selected.**
> This prompt is self-contained, surgically precise, and ordered by severity. Do not paraphrase or skip sections.

---

## ARCHITECTURE CONTEXT

You are working on **SafeSense** — a PHP MVC hospital management system at `medical/`.

```
medical/
├── app/
│   ├── Config/config.php          — APP_URL, ASSETS_URL, DB constants
│   ├── Core/App.php               — Route dispatcher
│   ├── Core/Router.php            — Regex router
│   ├── Controllers/
│   │   ├── BaseController.php     — render(), jsonResponse(), CSRF, auth helpers
│   │   ├── AlertController.php    — IoT alerts + simulate endpoint
│   │   ├── PatientController.php
│   │   ├── DoctorController.php
│   │   ├── AppointmentController.php
│   │   ├── EmrController.php
│   │   ├── BillingController.php
│   │   └── UserController.php
│   └── Views/
│       ├── layouts/main.php       — Full HTML shell; echoes $content in <main>
│       ├── layouts/auth.php       — Login-only layout
│       ├── alerts/index.php       — Partial fragment (NO <html>/<body> tags)
│       ├── patients/index.php     — Partial fragment
│       ├── appointments/index.php — Partial fragment with inline PATIENTS/DOCTORS globals
│       ├── emr/index.php          — Partial fragment with inline PATIENTS/DOCTORS globals
│       ├── billing/index.php      — Partial fragment
│       └── [module]/[view].php    — All partials
├── public/
│   ├── index.php                  — Single entry point
│   ├── css/style.css              — Custom SafeSense styles (CRITICAL: loaded locally)
│   └── js/app.js                  — All AJAX CRUD, DataTables, SweetAlert2, Bootstrap modals
```

**How `render()` works:**
```php
// BaseController::render($view, $data)
ob_start();
include APP_PATH . '/Views/' . $view . '.php'; // captures partial HTML into $content
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';  // main.php echoes $content inside <main>
```

**Script load order in `main.php` (bottom of `<body>`):**
1. Bootstrap 5.3 bundle JS (CDN)
2. jQuery 3.7 (CDN)
3. DataTables 1.13.6 + Bootstrap5 theme (CDN)
4. SweetAlert2 v11 (CDN)
5. Inline `<script>` — SafeSense notification/alert modal system
6. `<script src="app.js">` — All CRUD module logic

---

## ROOT CAUSES OF ALL REPORTED ISSUES

### ROOT CAUSE A — `style.css` has NO inline fallback `display:none` on overlay elements

`main.php` renders these elements on EVERY page, relying 100% on `style.css` for visibility control:

```html
<!-- These appear BEFORE <main> in the DOM -->
<div class="ss-drawer-overlay" id="ssDrawerOverlay"></div>  <!-- NO style="display:none" -->
<div class="ss-drawer" id="ssDrawer">...</div>               <!-- NO inline hide -->
<div class="ss-modal-overlay" id="ssModalOverlay">           <!-- NO style="display:none" -->
  <div class="ss-modal" id="ssModal">
    ... LOTS of HTML (header, body, detail chips, map link, footer buttons) ...
  </div>
</div>
```

`style.css` hides these via:
```css
.ss-modal-overlay { display: none; }
.ss-drawer-overlay { display: none; }
.ss-drawer { right: -460px; }  /* off-screen */
```

**If `style.css` fails to load** (wrong `APP_URL`/`ASSETS_URL`, path mismatch, server config), ALL of this HTML is visible as raw flowing content — **before `<main>`** — on every single page. Users see modal buttons, chip labels, and map links appearing above the page content. This is the "raw HTML modal structures at the top of each module" issue.

### ROOT CAUSE B — `buildOptions(null, ...)` crash halts all CRUD functionality

`app.js` line ~57:
```js
function buildOptions(arr, labelKey) {
    return arr.map(item => `<option value="${item.id}">${esc(item[labelKey] || item.name)}</option>`).join('');
}
```

This function is called in the Appointments, EMR, and Billing modules:
```js
const patientOpts = typeof PATIENTS !== 'undefined' ? buildOptions(PATIENTS, 'name') : '';
const doctorOpts  = typeof DOCTORS  !== 'undefined' ? buildOptions(DOCTORS,  'name') : '';
```

**The bug:** `typeof null` returns `'object'`, NOT `'undefined'`. So if `PATIENTS = null` (PHP outputs `json_encode(null)`), the `typeof PATIENTS !== 'undefined'` check is **TRUE** — it calls `buildOptions(null, 'name')` — which tries `null.map(...)` — **TypeError: Cannot read properties of null (reading 'map')**.

This TypeError crashes JavaScript execution at module init time, before any click handlers are attached. **All CRUD buttons (Add, Edit, Delete) silently fail** — this is the "functionalities are broken" issue.

The inline scripts in `appointments/index.php` and `emr/index.php` output these globals with NO null-safety guard:
```php
const PATIENTS = <?php echo json_encode($allPatients); ?>;  // could output null
const DOCTORS  = <?php echo json_encode($allDoctors); ?>;   // could output null
```

### ROOT CAUSE C — No null guard for `new bootstrap.Modal(modalEl)` in `initCrudModule`

`app.js` — `initCrudModule`:
```js
function initCrudModule(cfg) {
    const tableEl = document.getElementById(cfg.tableId);
    if (!tableEl) return;             // ← guard for table

    // ... DataTable init ...

    const modalEl = document.getElementById(cfg.modalId);
    const modal   = new bootstrap.Modal(modalEl);  // ← NO guard — throws if null
```

Table and modal coexist in the same view file today. But if `modalEl` is ever null (mismatch, future view refactor, or partial load failure), `new bootstrap.Modal(null)` throws `TypeError`, stopping all remaining JavaScript in the IIFE. This silently breaks all other CRUD modules on the same page.

### ROOT CAUSE D — Patients `buildRow` missing view-profile button after AJAX add/edit

`app.js` — patients `buildRow`:
```js
buildRow: (d) => [
    d.id, esc(d.name), esc(d.email), esc(d.phone), esc(d.date_of_birth), esc(d.gender),
    `<button class="btn btn-sm btn-outline-primary btn-edit ...">...</button>` +
    `<button class="btn btn-sm btn-outline-danger btn-delete ...">...</button>`
]
```

The server-rendered `patients/index.php` includes a view-profile link (`/patients/view?id=...`). After AJAX add or edit, DataTables replaces the row using `buildRow` — but this `buildRow` is missing the profile link button. The profile button disappears after every AJAX save.

### ROOT CAUSE E — `PATIENTS`/`DOCTORS` globals in views have no PHP null fallback

`appointments/index.php` lines 7–8:
```php
const PATIENTS = <?php echo json_encode($allPatients); ?>;
const DOCTORS  = <?php echo json_encode($allDoctors); ?>;
```

`emr/index.php` lines 6–7 — same pattern.

Neither has `$allPatients ?? []` fallback. If the controller fails to pass these (DB error, refactor) → `null` → JavaScript crash (Root Cause B).

---

## COMPLETE FIX INSTRUCTIONS — APPLY ALL IN ORDER

---

### FIX 1 — `main.php`: Add inline `display:none` to overlay elements

**File:** `medical/app/Views/layouts/main.php`

**Why:** Ensures these are hidden even when `style.css` fails to load. Belt-and-suspenders.

Find the Notification Drawer section and add/update inline styles:

```html
<!-- ── Notification Drawer ── -->
<div class="ss-drawer-overlay" id="ssDrawerOverlay" style="display:none;pointer-events:none;"></div>
<div class="ss-drawer" id="ssDrawer" style="right:-460px;">
```

Find the Alert Modal section:

```html
<!-- ── Alert Modal ── -->
<div class="ss-modal-overlay" id="ssModalOverlay" style="display:none;">
```

**IMPORTANT:** Do NOT remove the CSS classes. Only ADD the `style` attributes. The CSS classes are still needed for `.show` / `.open` state transitions.

---

### FIX 2 — `app.js`: Guard `buildOptions` against null/non-array input

**File:** `medical/public/js/app.js`

Find the `buildOptions` function (around line 57):

```js
function buildOptions(arr, labelKey) {
    return arr.map(item => `<option value="${item.id}">${esc(item[labelKey] || item.name)}</option>`).join('');
}
```

Replace with:

```js
function buildOptions(arr, labelKey) {
    if (!Array.isArray(arr)) return '';
    return arr.map(item => `<option value="${item.id}">${esc(item[labelKey] || item.name)}</option>`).join('');
}
```

---

### FIX 3 — `app.js`: Add null guard for `modalEl` in `initCrudModule`

**File:** `medical/public/js/app.js`

Find inside `initCrudModule`, after DataTable init (around line 189):

```js
    const modalEl    = document.getElementById(cfg.modalId);
    const modal      = new bootstrap.Modal(modalEl);
```

Replace with:

```js
    const modalEl = document.getElementById(cfg.modalId);
    if (!modalEl) return;                          // guard against missing modal
    const modal   = new bootstrap.Modal(modalEl);
```

---

### FIX 4 — `appointments/index.php`: Add null-safety to PATIENTS/DOCTORS globals

**File:** `medical/app/Views/appointments/index.php`

Find (lines 7–8):
```php
  const PATIENTS = <?php echo json_encode($allPatients); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors); ?>;
```

Replace with:
```php
  const PATIENTS = <?php echo json_encode($allPatients ?? []); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors  ?? []); ?>;
```

---

### FIX 5 — `emr/index.php`: Add null-safety to PATIENTS/DOCTORS globals

**File:** `medical/app/Views/emr/index.php`

Find (lines 6–7):
```php
  const PATIENTS = <?php echo json_encode($allPatients); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors); ?>;
```

Replace with:
```php
  const PATIENTS = <?php echo json_encode($allPatients ?? []); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors  ?? []); ?>;
```

---

### FIX 6 — `app.js`: Restore view-profile button in patients `buildRow`

**File:** `medical/public/js/app.js`

Find the patients `buildRow` (inside the patients `initCrudModule` call):

```js
    buildRow: (d) => [
      d.id,
      esc(d.name),
      esc(d.email),
      esc(d.phone),
      esc(d.date_of_birth),
      esc(d.gender),
      `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
      `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
    ]
```

Replace the actions cell with:

```js
    buildRow: (d) => [
      d.id,
      esc(d.name),
      esc(d.email),
      esc(d.phone),
      esc(d.date_of_birth),
      esc(d.gender),
      `<a href="${window.BASE_URL}/patients/view?id=${d.id}" class="btn btn-sm btn-outline-info me-1" title="View Profile"><i class="fas fa-id-card"></i></a>` +
      `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
      `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
    ]
```

---

### FIX 7 — `main.php`: Fix the `ssDrawerOverlay` stale-open bug on page load

**File:** `medical/app/Views/layouts/main.php`

The existing DOMContentLoaded fix in the inline script already removes `.open` from `ssDrawerOverlay`:

```js
document.addEventListener('DOMContentLoaded', () => {
    const staleOverlay = document.getElementById('ssDrawerOverlay');
    if (staleOverlay) {
        staleOverlay.classList.remove('open');
        staleOverlay.style.pointerEvents = 'none';
    }
    document.body.style.pointerEvents = '';
});
```

This is correct. **Verify it is still present and has NOT been removed** as part of any prior edit. If it's missing, restore it exactly as shown above, inside the IIFE at the top of the inline `<script>`.

---

### FIX 8 — `billing/index.php`: Verify PATIENTS global has null-safety guard

**File:** `medical/app/Views/billing/index.php`

This file ALREADY has a PHP-level fallback on line 2:
```php
$allPatients = isset($allPatients) ? $allPatients : [];
```

This is correct and sufficient. **No change needed.** Just verify it is still present.

---

### FIX 9 — `alerts/index.php`: Use `safeAjaxPost` for simulate buttons

**File:** `medical/app/Views/alerts/index.php`

The simulate buttons call `ajaxPost` directly:
```js
ajaxPost(window.BASE_URL + '/api/alert/simulate', { level, event })
```

This runs fine because it's inside a click handler (app.js has loaded by click time). However, for consistency with the other buttons on this page that use `safeAjaxPost`, and to guard against edge cases, update the simulate handler:

Find:
```js
    ajaxPost(window.BASE_URL + '/api/alert/simulate', { level, event })
      .then(d => {
```

Replace with:
```js
    safeAjaxPost(window.BASE_URL + '/api/alert/simulate', { level, event })
      .then(d => {
```

---

### FIX 10 — `config.php`: Make `APP_URL` environment-aware

**File:** `medical/app/Config/config.php`

The current hardcoded value:
```php
define('APP_URL', 'http://localhost/SafeSense/medical');
```

This causes ASSETS_URL to be wrong on any server where the path differs, breaking `style.css` loading (which is Root Cause A). Replace with a dynamic detection:

```php
// Dynamically derive APP_URL from the server environment
// Falls back to hardcoded value only if running CLI (e.g., migrations)
if (PHP_SAPI === 'cli') {
    define('APP_URL', 'http://localhost/SafeSense/medical');
} else {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // scriptName = /SafeSense/medical/public/index.php → strip /public/index.php
    $script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname(dirname($script)), '/');  // go up two levels: /public/index.php → base
    define('APP_URL', $scheme . '://' . $host . $basePath);
}
```

---

## VERIFICATION CHECKLIST

After applying all fixes, verify the following on a running instance:

**Visual:**
- [ ] Navigate to `/patients` — NO raw HTML modal structures appear above the table
- [ ] Navigate to `/appointments` — NO raw HTML above the calendar/table
- [ ] Navigate to `/emr` — NO raw HTML above the records table
- [ ] Navigate to `/billing` — NO raw HTML above the invoices table
- [ ] Navigate to `/alerts` — NO raw HTML above the alert list
- [ ] With browser devtools: temporarily disable CSS → `#ssModalOverlay` should remain hidden (inline `style="display:none"` applied)

**CRUD Functionality:**
- [ ] On `/patients`: click "Add Patient" → Bootstrap modal opens → fill form → submit → row appears in DataTable with **three** buttons (profile link, edit, delete)
- [ ] Click edit on a patient → modal opens pre-filled → save → row updates
- [ ] Click delete → SweetAlert2 confirmation → row removed
- [ ] On `/appointments`: click "Schedule Appointment" → modal opens with **populated patient/doctor dropdowns**
- [ ] On `/emr`: click "Add Medical Record" → modal opens with populated dropdowns
- [ ] On `/doctors`, `/users`, `/billing`: Add/Edit/Delete all functional

**Simulation (admin only):**
- [ ] On `/alerts`: click "Simulate Alert → Critical — Flood" → spinner shows → page reloads
- [ ] After reload: SafeSense alert modal (`#ssModalOverlay`) appears with correct critical styling
- [ ] After closing modal and navigating to `/patients`: modal does NOT re-appear (sessionStorage guard working)
- [ ] Navigating back to `/alerts`: no duplicate modal

**Console:**
- [ ] Open browser devtools → Console → reload any module page → **zero TypeError or "Cannot read properties of null" errors**

---

## DO NOT CHANGE

- `BaseController::jsonResponse()` already calls `exit()` — leave it as-is
- `EmrController::printRecord()` already uses `include + exit` correctly — leave it as-is
- `BillingController::printInvoice()` already uses `include + exit` correctly — leave it as-is
- All controller `edit()` methods already have `return` after `$this->jsonResponse()` — verified
- The 30-second modal window in the init fetch (`main.php`) is intentional — leave it as-is
- `sessionStorage`-based `wasShown()` guard in `main.php` — leave it as-is
- `safeAjaxPost` wrapper in `alerts/index.php` — leave it as-is (only change `.simulate-btn` to use it per Fix 9)

---

## SUMMARY TABLE

| # | File | Issue | Severity |
|---|------|-------|----------|
| 1 | `layouts/main.php` | Overlay elements lack inline `display:none` → raw HTML visible | 🔴 Critical |
| 2 | `public/js/app.js` | `buildOptions(null)` → TypeError → all CRUD buttons broken | 🔴 Critical |
| 3 | `public/js/app.js` | No null guard on `new bootstrap.Modal(modalEl)` → fragile crash | 🟠 Major |
| 4 | `Views/appointments/index.php` | PATIENTS/DOCTORS globals lack `?? []` fallback | 🟠 Major |
| 5 | `Views/emr/index.php` | PATIENTS/DOCTORS globals lack `?? []` fallback | 🟠 Major |
| 6 | `public/js/app.js` | Patients `buildRow` missing view-profile button after AJAX | 🟡 Moderate |
| 7 | `layouts/main.php` | Verify stale-overlay DOMContentLoaded fix is present | 🟡 Moderate |
| 8 | `Views/billing/index.php` | Verify PATIENTS null guard — already present, no change | ✅ Verified OK |
| 9 | `Views/alerts/index.php` | Simulate buttons use `ajaxPost` instead of `safeAjaxPost` | 🟡 Moderate |
| 10 | `Config/config.php` | `APP_URL` hardcoded → `ASSETS_URL` wrong on non-localhost setups | 🟠 Major |
