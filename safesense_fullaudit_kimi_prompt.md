# SafeSense — Full Audit, Bug Fixes & UI Overhaul
## Prompt for Windsurf (Kimi K2.5)

---

## CONTEXT

You are working on **SafeSense**, a PHP hospital management system with IoT flood/hazard alert monitoring. The system is an MVC app at `medical/` with:
- `app/Controllers/` — PHP controllers
- `app/Views/` — PHP view files
- `app/Models/` — PDO-based models
- `public/css/style.css` — the single design system CSS file
- `public/js/app.js` — global JavaScript
- `app/Views/layouts/main.php` — the main layout (navbar, footer, JS)

The system recently had several modules added (Reports, Audit Log, Global Search, Document Upload, Patient Profile, Print Invoice, Appointment Calendar, Role-aware Dashboard). Many were added quickly and have bugs, incomplete wiring, inline CSS, and a critically broken navbar.

---

## PART 1 — BUG FIXES (do these first, in order)

### BUG 1 — CSRF Token Field Name Mismatch (CRITICAL — breaks Document Upload entirely)

**File:** `app/Views/documents/index.php`

**Problem:** The document upload form and delete form both submit `name="csrf_token"`, but `BaseController::validateCsrf()` only reads `$_POST['_csrf_token']` (with underscore prefix). Every document upload and delete silently fails with "Invalid request. Please try again."

**Fix:** In `app/Views/documents/index.php`, change both hidden input field names from `csrf_token` to `_csrf_token`:

```php
// Line 27 — upload form
<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

// Line 91 — delete form  
<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
```

Also audit **every other view file** for the same mismatch. Check:
- `app/Views/billing/index.php`
- `app/Views/patients/index.php`  
- `app/Views/appointments/index.php`
- `app/Views/emr/index.php`
- `app/Views/doctors/index.php`
- `app/Views/users/index.php`

Any form that calls a controller method using `$this->validateCsrf()` must use `name="_csrf_token"`. Fix all occurrences found.

---

### BUG 2 — Home Page Shows Inaccessible Buttons to Staff Role

**File:** `app/Views/home.php`

**Problem:** The hero grid already correctly wraps the Patients and Doctors cards in a role check — but a secondary grid of action cards further down (if it exists after future edits) may expose these links again. Additionally, the current `home.php` wraps the Patients/Doctors section in `if (in_array(...['admin','doctor','nurse']))` — this is correct. Verify it is still intact and not accidentally removed.

**Current correct state to preserve:**
```php
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
  <!-- Patients card -->
  <!-- Doctors card -->
<?php endif; ?>
```

If this wrapping is missing or broken, restore it. The "View Patients" and "View Doctors" buttons must NOT be visible to `staff` or `nurse`-only users who would get a 403 on click.

---

### BUG 3 — Billing Nav Link Shown to Wrong Role

**File:** `app/Views/layouts/main.php` (line ~78)

**Problem:** The Billing nav link is gated to `['admin', 'staff']`. But in `BillingController`, the role guard uses `requireRole(['admin', 'doctor', 'nurse', 'staff'])` — meaning doctors and nurses can access billing but the nav link doesn't show for them, forcing them to navigate directly. 

**Fix:** In `main.php`, change the Billing nav item role check to match the controller:
```php
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse', 'staff'])): ?>
  <li class="nav-item">
    <a class="nav-link ..." href="...">Billing</a>
  </li>
<?php endif; ?>
```

---

### BUG 4 — Document Upload Path Outside Web Root

**File:** `app/Controllers/DocumentController.php` (line ~76)

**Problem:** Uploaded files are stored at `__DIR__ . '/../../../uploads/documents/'` which resolves to a path outside the `public/` folder. Files cannot be served to the browser without a dedicated download route.

**Fix Option A (add a download route — recommended):** Add a GET route `/patients/documents/download` in `App.php` that reads the file from the upload path, sets appropriate headers (`Content-Disposition`, `Content-Type`), and streams it. The view's "download" button should link to this route with `?id={doc_id}`.

**Fix Option B (move storage inside public):** Change upload path to `public/uploads/documents/` and serve files directly. Less secure (files are public) but simpler for a demo.

For the demo, implement **Fix Option B**: change the upload directory in `DocumentController.php`:
```php
$uploadDir = dirname(__DIR__, 3) . '/public/uploads/documents/';
```
And update the documents view to link files as:
```php
<a href="<?php echo url('/uploads/documents/' . $doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
  <i class="fas fa-download me-1"></i>View
</a>
```

---

### BUG 5 — Search Page Has No Nav Highlight in Documents Module

**File:** `app/Views/layouts/main.php`

**Problem:** The `$navPage` detection splits on `/` and uses the first segment. The `documents` route is `/patients/documents`, so `$navPage` would be `patients` not `documents`. The Documents module has no nav link at all — it's only accessible from the patient profile page. This is correct behavior, but the nav shows no active state when on `/patients/documents`. This is minor — no fix needed, but add a comment in `main.php` noting this is intentional.

---

### BUG 6 — Migration Files Hardcode DB Credentials

**Files:** All 7 files in `database/` directory

**Problem:** Each migration file contains `$host='localhost'; $password='';` hardcoded instead of reading from `.env`. While low-risk (run-once scripts), it causes issues if the DB password is ever set.

**Fix:** At the top of each migration file, after the `<?php` tag, add:
```php
// Load .env values if available
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}
$host   = $_ENV['DB_HOST']     ?? 'localhost';
$dbname = $_ENV['DB_NAME']     ?? 'safesense';
$user   = $_ENV['DB_USER']     ?? 'root';
$pass   = $_ENV['DB_PASSWORD'] ?? '';
```

---

## PART 2 — NAVBAR OVERHAUL (most visible problem)

### Current Problem

The navbar currently has **11 nav links** in the left `me-auto` group plus a user pill and logout button on the right. At standard screen widths (1280px–1440px), the navbar overflows — the logout button is pushed off-screen and the links wrap or disappear. This is the most obvious UX failure.

**Nav items for admin role (maximum):**
1. Dashboard
2. Patients
3. Doctors
4. Appointments
5. Medical Records
6. Billing
7. Users
8. SafeSense Alerts (+ live dot)
9. Reports
10. Audit Log
11. Search

That is 11 items plus the user pill and logout — far too many for a single navbar row.

### Solution — Grouped Dropdown Navbar

Collapse related items into Bootstrap dropdowns to reduce the top-level count to 5–6 items maximum. Here is the exact grouping to implement:

**Group 1: Patients** (dropdown)
- Patients List → `/patients`
- Doctors List → `/doctors`
- Patient Documents → `/patients/documents` *(only visible to admin/doctor/nurse)*

**Group 2: Clinical** (dropdown)
- Appointments → `/appointments`
- Medical Records (EMR) → `/emr`

**Group 3: Billing** (standalone link, role-gated to admin/doctor/nurse/staff)

**Group 4: SafeSense Alerts** (standalone link with live dot — keep prominent)

**Group 5: Admin** (dropdown, admin-only)
- Reports → `/reports`
- Audit Log → `/audit`
- User Management → `/users`

**Right side (keep as-is):**
- Bell icon with badge
- User pill
- Search icon linking to `/search` *(move search here as an icon, not a full nav link)*
- Logout button

### Implementation in `app/Views/layouts/main.php`

Replace the entire `<ul class="navbar-nav me-auto gap-1">` block with:

```php
<ul class="navbar-nav me-auto gap-1">

  <!-- Dashboard -->
  <li class="nav-item">
    <a class="nav-link <?php echo $navPage==='dashboard'?'active':''; ?>" href="<?php echo url('/dashboard'); ?>">
      <i class="fas fa-tachometer-alt me-1"></i>Dashboard
    </a>
  </li>

  <!-- People dropdown (admin/doctor/nurse) -->
  <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin','doctor','nurse'])): ?>
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle <?php echo in_array($navPage,['patients','doctors','documents'])?'active':''; ?>"
       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-users me-1"></i>People
    </a>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item <?php echo $navPage==='patients'?'active':''; ?>" href="<?php echo url('/patients'); ?>">
        <i class="fas fa-user-injured me-2 text-primary"></i>Patients
      </a></li>
      <li><a class="dropdown-item <?php echo $navPage==='doctors'?'active':''; ?>" href="<?php echo url('/doctors'); ?>">
        <i class="fas fa-user-md me-2 text-success"></i>Doctors
      </a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="<?php echo url('/patients/documents'); ?>">
        <i class="fas fa-file-upload me-2 text-info"></i>Documents
      </a></li>
    </ul>
  </li>
  <?php endif; ?>

  <!-- Clinical dropdown -->
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle <?php echo in_array($navPage,['appointments','emr'])?'active':''; ?>"
       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-stethoscope me-1"></i>Clinical
    </a>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item <?php echo $navPage==='appointments'?'active':''; ?>" href="<?php echo url('/appointments'); ?>">
        <i class="fas fa-calendar-check me-2 text-primary"></i>Appointments
      </a></li>
      <li><a class="dropdown-item <?php echo $navPage==='emr'?'active':''; ?>" href="<?php echo url('/emr'); ?>">
        <i class="fas fa-file-medical me-2 text-info"></i>Medical Records
      </a></li>
    </ul>
  </li>

  <!-- Billing (role-gated) -->
  <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin','doctor','nurse','staff'])): ?>
  <li class="nav-item">
    <a class="nav-link <?php echo $navPage==='billing'?'active':''; ?>" href="<?php echo url('/billing'); ?>">
      <i class="fas fa-file-invoice-dollar me-1"></i>Billing
    </a>
  </li>
  <?php endif; ?>

  <!-- SafeSense Alerts — always visible, prominent -->
  <li class="nav-item">
    <a class="nav-link d-flex align-items-center gap-1 <?php echo $navPage==='alerts'?'active':''; ?>" href="<?php echo url('/alerts'); ?>">
      <i class="fas fa-satellite-dish me-1"></i>Alerts<span class="ss-live-dot ss-live-dot--sm ms-1"></span>
    </a>
  </li>

  <!-- Admin dropdown (admin only) -->
  <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle <?php echo in_array($navPage,['reports','audit','users'])?'active':''; ?>"
       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-cog me-1"></i>Admin
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item <?php echo $navPage==='reports'?'active':''; ?>" href="<?php echo url('/reports'); ?>">
        <i class="fas fa-chart-bar me-2 text-primary"></i>Reports & Analytics
      </a></li>
      <li><a class="dropdown-item <?php echo $navPage==='audit'?'active':''; ?>" href="<?php echo url('/audit'); ?>">
        <i class="fas fa-clipboard-list me-2 text-warning"></i>Audit Log
      </a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item <?php echo $navPage==='users'?'active':''; ?>" href="<?php echo url('/users'); ?>">
        <i class="fas fa-users-cog me-2 text-danger"></i>User Management
      </a></li>
    </ul>
  </li>
  <?php endif; ?>

  <!-- Reports only (non-admin doctor/nurse) -->
  <?php if (in_array($_SESSION['user']['role'] ?? '', ['doctor','nurse'])): ?>
  <li class="nav-item">
    <a class="nav-link <?php echo $navPage==='reports'?'active':''; ?>" href="<?php echo url('/reports'); ?>">
      <i class="fas fa-chart-bar me-1"></i>Reports
    </a>
  </li>
  <?php endif; ?>

</ul>
```

Replace the right-side `<ul class="navbar-nav align-items-center gap-2">` block with:

```php
<ul class="navbar-nav align-items-center gap-2">
  <?php if (isset($_SESSION['user'])): ?>

  <!-- Search icon button -->
  <li class="nav-item">
    <a class="nav-link ss-icon-btn <?php echo $navPage==='search'?'active':''; ?>" href="<?php echo url('/search'); ?>" title="Global Search">
      <i class="fas fa-search"></i>
    </a>
  </li>

  <!-- Alert bell -->
  <li class="nav-item">
    <div class="ss-bell-wrap" id="ssBellBtn" title="Open SafeSense Alerts">
      <i class="fas fa-bell"></i>
      <span class="ss-badge" id="ssBadge" data-count="0">0</span>
    </div>
  </li>

  <!-- User pill -->
  <li class="nav-item">
    <div class="nav-user-pill">
      <i class="fas fa-user-circle"></i>
      <span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
      <small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
    </div>
  </li>

  <!-- Logout -->
  <li class="nav-item">
    <form method="post" action="<?php echo url('/logout'); ?>" class="d-inline">
      <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit" class="btn btn-outline-light btn-sm">
        <i class="fas fa-sign-out-alt me-1"></i>Logout
      </button>
    </form>
  </li>

  <?php endif; ?>
</ul>
```

Also add these CSS rules to `public/css/style.css` for the search icon button and dropdown menu styling:

```css
/* Search icon nav button */
.ss-icon-btn {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--r-md);
  padding: 0 !important;
}

/* Dropdown menus in navbar */
.navbar .dropdown-menu {
  background: var(--ss-surface);
  border: 1px solid var(--ss-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-md);
  padding: .4rem;
  min-width: 200px;
  margin-top: .5rem;
}
.navbar .dropdown-item {
  border-radius: var(--r-sm);
  font-size: .85rem;
  font-weight: 500;
  color: var(--ss-text);
  padding: .55rem .85rem;
  display: flex;
  align-items: center;
  transition: background var(--t-fast);
}
.navbar .dropdown-item:hover,
.navbar .dropdown-item:focus {
  background: var(--ss-primary-light);
  color: var(--ss-primary);
}
.navbar .dropdown-item.active {
  background: var(--ss-primary);
  color: #fff;
}
.navbar .dropdown-item.active i { color: #fff !important; }
.navbar .dropdown-divider { border-color: var(--ss-border); margin: .35rem .5rem; }

/* Make dropdown toggle arrow smaller */
.navbar .dropdown-toggle::after {
  margin-left: .3em;
  vertical-align: .2em;
}
```

---

## PART 3 — INLINE CSS CLEANUP

The following views contain inline `style=""` attributes that should be moved into `public/css/style.css` as proper utility classes. Go through each file and extract inline styles into the CSS file, then replace with class names.

**Priority files (most inline styles):**

### `app/Views/home.php`
Extract these to CSS:
```css
/* Home page hero */
.ss-home-hero {
  background: linear-gradient(135deg, var(--ss-primary-dark) 0%, var(--ss-primary) 100%);
  border-radius: var(--r-xl);
  padding: 3rem;
  color: #fff;
  margin-bottom: 1.5rem;
}
.ss-home-hero h1 { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; margin: 0; }
.ss-home-hero-icon { color: #f87171; }
.ss-home-subtitle { opacity: .85; font-size: .88rem; }
.ss-home-lead { opacity: .85; max-width: 560px; font-size: .95rem; line-height: 1.65; }

/* Home cards with color top borders */
.ss-card-top-primary { border-top: 3px solid var(--ss-primary) !important; }
.ss-card-top-success { border-top: 3px solid #16a34a !important; }
.ss-card-top-info    { border-top: 3px solid #0891b2 !important; }
.ss-card-top-warning { border-top: 3px solid #d97706 !important; }
```

Replace `style="background: linear-gradient(...)..."` in home.php with `class="ss-home-hero"`, and `style="border-top: 3px solid var(--ss-primary)"` with `class="card h-100 ss-card-top-primary"`, etc.

### `app/Views/patients/view.php`
Extract:
```css
/* Patient profile page */
.ss-profile-header h1 { margin: 0; }
.ss-stat-value-sm { font-size: 1.2rem; }
.ss-stat-value-success { font-size: 1.2rem; color: #15803d; }
.ss-stat-value-danger  { font-size: 1.2rem; color: #b91c1c; }
.ss-table-date { white-space: nowrap; }
.ss-table-meta { white-space: nowrap; font-size: .8rem; color: #64748b; }
.ss-mono-sm   { font-family: var(--font-mono, monospace); font-size: .8rem; }
```

### `app/Views/dashboard.php`
Extract:
```css
/* Dashboard role widgets */
.ss-dash-critical-card { border-left: 4px solid var(--ss-critical) !important; }
.ss-dash-primary-card  { border-left: 4px solid var(--ss-primary)  !important; }
.ss-dash-alert-header  { background: #fef2f2; border-color: #fecaca; }
.ss-dash-alert-msg     { font-size: .875rem; font-weight: 500; }
.ss-dash-alert-meta    { font-size: .78rem; }
.ss-chart-hidden       { display: none; }
```

### `app/Views/search/index.php`
The `style="max-width: 700px;"` on the search input group should become a CSS class:
```css
.ss-search-form { max-width: 700px; }
```

### General rule for all remaining files
Any `style="font-size: .Xrem"` → use Bootstrap's `small`, `fs-6`, or add specific utility to style.css.
Any `style="color: #xxxxxx"` → use existing CSS variables or add a utility class.
Any `style="display:none"` that is toggled by JS → keep as-is (JS-controlled visibility).
Any `style="border-radius: 99px"` → use Bootstrap's `rounded-pill`.

---

## PART 4 — FUNCTIONAL COMPLETENESS FIXES

### 4A — Documents Module: No Nav Entry

The Document Upload module is only accessible from the Patient Profile page via a button. Users have no way to know it exists from the navbar. The navbar fix in Part 2 already adds it under the "People" dropdown as "Documents" → `/patients/documents`. This is already included in the navbar code above — ensure it is implemented.

### 4B — Appointment Calendar: Verify FullCalendar Wiring

**File:** `app/Views/appointments/index.php`

Check that:
1. The tab toggle between "Table View" and "Calendar View" works correctly
2. The `/api/appointments/events` fetch URL uses `window.BASE_URL` correctly  
3. The calendar initializes only when the calendar tab is active (lazy init)
4. Events show the correct patient name and doctor name, not just IDs

If the calendar tab is missing or the JS is broken, inspect and fix. The `AppointmentController::calendarEvents()` method should return JSON formatted as:
```json
[
  {
    "id": 1,
    "title": "Patient Name — Dr. Doctor Name",
    "start": "2025-06-15T10:00:00",
    "end": "2025-06-15T10:30:00",
    "color": "#1d4ed8",
    "status": "scheduled"
  }
]
```

### 4C — Reports Page: Verify Chart.js Rendering

**File:** `app/Views/reports/index.php`

Check that:
1. `Chart.js` is being called after the DOM is ready (wrap in `DOMContentLoaded`)
2. Canvas elements have proper IDs and are not hidden
3. The GROUP BY SQL queries in `ReportsController` are correct and return data

If charts are blank on first load, add a `DOMContentLoaded` wrapper around all Chart.js instantiation code.

### 4D — Audit Log: Verify `logAction()` is Hooked

**File:** `app/Controllers/BaseController.php`

Confirm `logAction()` method exists and is called in:
- `PatientController::store()`, `update()`, `delete()`
- `DoctorController::store()`, `update()`, `delete()`
- `AppointmentController::store()`, `update()`, `delete()`
- `BillingController::store()`, `update()`, `delete()`
- `EmrController::store()`, `update()`, `delete()`

If any controller's store/update/delete method is missing a `$this->logAction(...)` call, add it.

---

## PART 5 — UX POLISH

### 5A — Empty State Pages

Every list page that can show zero records should have a proper empty state. Check:
- `/search` with no query → shows "Enter a search term" message ✓ (already done)
- `/patients/documents` with no patient → shows info alert ✓
- `/audit` with no logs → add empty state if missing
- `/reports` with no data → charts should show "No data available" text in the canvas

For any missing empty state, add:
```php
<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted">
  <i class="fas fa-inbox fa-3x mb-3 opacity-25 d-block"></i>
  <p class="mb-0">No records found.</p>
</div>
<?php endif; ?>
```

### 5B — Print Invoice: Back Button

**File:** `app/Views/billing/print.php`

The print view uses `include + exit` to bypass the layout, so there's no navbar. Add a print-only back navigation hint:
```html
<div class="no-print" style="margin-bottom: 1.5rem;">
  <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Back to Billing
  </a>
  <button onclick="window.print()" class="btn btn-primary btn-sm ms-2">
    <i class="fas fa-print me-1"></i>Print Invoice
  </button>
</div>
```
Ensure `@media print { .no-print { display: none !important; } }` is in the print stylesheet.

### 5C — Flash Messages: Move Inside `<main>`

**File:** `app/Views/layouts/main.php`

Flash messages (success/error toasts) are handled by `app.js` via the `#ssFlashData` div. Verify the flash data div is rendered before `</body>` and that `app.js` picks it up. The flash display should use the existing `ss-toast` system, not Bootstrap alerts inline in content.

If any controller is rendering flash messages via Bootstrap alert inside a view directly, migrate those to the `$_SESSION['flash_success']`/`$_SESSION['flash_error']` pattern so they use the toast system.

---

## PART 6 — FINAL CHECKLIST

After implementing all of the above, verify:

- [ ] Document upload works end-to-end (CSRF fix applied)
- [ ] Document delete works end-to-end (CSRF fix applied)
- [ ] Navbar fits on screen at 1280px width with no overflow (dropdown approach)
- [ ] Logout button is always visible
- [ ] Search is accessible via icon in navbar right-side
- [ ] Admin dropdown contains Reports, Audit Log, Users
- [ ] People dropdown contains Patients, Doctors, Documents (for admin/doctor/nurse)
- [ ] Clinical dropdown contains Appointments, EMR
- [ ] Billing nav link visible to admin/doctor/nurse/staff
- [ ] No inline `style=""` attributes remain in: home.php, patients/view.php, dashboard.php, search/index.php
- [ ] All forms using `validateCsrf()` submit `_csrf_token` not `csrf_token`
- [ ] Migration files read from `.env` instead of hardcoded credentials
- [ ] Reports page charts render on first load
- [ ] Audit log receives entries when patients/doctors/appointments/billing are created or modified
- [ ] FullCalendar tab renders on appointments page
- [ ] Patient profile page (`/patients/view?id=X`) loads without errors
- [ ] Print invoice page opens and prints correctly
- [ ] Mobile hamburger menu still works (Bootstrap collapse behavior intact)

---

## STYLE NOTES FOR KIMI

- Do not change the design system color variables in `style.css` — the `--ss-primary`, `--ss-critical`, `--ss-warning` palette is intentional and complete.
- Do not switch from Bootstrap 5.3 to any other CSS framework.
- Do not introduce any new npm dependencies or build steps — this is a plain PHP/HTML/CSS/JS project with CDN scripts only.
- Preserve all existing `ss-*` class names and the `ss-live-dot`, `ss-badge`, `ss-bell-wrap`, drawer, modal, and toast systems — they are working and must not be broken.
- The navbar overhaul uses Bootstrap's built-in `.dropdown`, `.dropdown-menu`, `.dropdown-item` classes — no custom JS needed.
- When adding CSS classes to `style.css`, place them in a clearly labelled section at the bottom (e.g., `/* ═══ Utility additions ═══ */`).
- PHP files use 4-space indentation. Keep consistent.
- All user-facing strings use `htmlspecialchars()` — do not remove XSS protection.

---

*This prompt was generated from a live audit of the SafeSense GitHub repository (`kirbygeagonia-create/SafeSense`). All line numbers and file paths were verified against the actual codebase at time of writing.*
