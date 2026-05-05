# SafeSense Hospital Management — Full System Repair & Feature Expansion Prompt
### For: Windsurf with Kimi K2.5
### Project path: `medical/` (all paths are relative to this root unless stated)
### Phases 1–3 Complete — This prompt covers: Modal Bug Fixes, Incomplete Functionality Repairs, and Next-Phase Feature Additions

---

## HOW YOU MUST OPERATE — READ FIRST

You are a Senior Full-Stack PHP/JS Engineer. For every task:

```
LOOP for each task:
  1. READ     → Open and fully read the file(s) listed before touching anything
  2. DIAGNOSE → Confirm the problem is present exactly as described
  3. IMPLEMENT → Apply the fix precisely — no unrelated refactoring
  4. SAVE     → Write all modified files to disk
  5. VERIFY   → Re-read the changed file and grep-confirm the result
  6. REPORT   → Print ✅ DONE: [Task ID] or ❌ FAILED: [Task ID] — [exact reason]

After ALL tasks:
  7. Run FINAL VERIFICATION CHECKLIST
  8. Print FINAL REPORT TABLE
  9. Retry every ❌ until the table shows all ✅
```

**Hard rules:**
- Never skip a task or assume a change is saved without re-reading the file
- Never refactor code outside the specified scope
- Never remove existing functionality to make a task easier
- If a file path does not exist, report it as ❌ FAILED with `[file not found]` and move on

---

## SECTION 1 — CRITICAL: MODAL INTERACTION BUG (All Modules Affected)

The root cause is a **z-index conflict between the custom alert drawer overlay and Bootstrap's modal system**, combined with a **persistent overlay state when alerts fire during CRUD operations**. There are two separate sub-bugs:

**Bug 1A — `ss-drawer-overlay` blocks Bootstrap modals**
The `.ss-drawer-overlay` element uses `z-index: 1050`, which is the **same z-index Bootstrap uses for its modal backdrop**. If the drawer closes but a stale `.open` class lingers (e.g., on browser back/forward, or if the overlay click handler fires before Bootstrap finishes), the invisible overlay sits over the entire page — including all CRUD modal forms — blocking all clicks and input interaction.

**Bug 1B — `ss-modal-overlay` (alert modal) covers CRUD modals**
The `.ss-modal-overlay` alert overlay uses `z-index: 3000`, which is above Bootstrap's modal stack (1050/1055). If a critical/danger alert fires while a CRUD modal (Add Patient, Edit Doctor, etc.) is already open, the alert overlay renders on top and does not release click events back to the CRUD modal. After the alert is dismissed, the CRUD modal remains visible but `pointer-events` are still blocked by a residual backdrop.

**Bug 1C — Bootstrap modal `tabindex="-1"` + missing `enforce` on backdrop**
Several modals are missing `data-bs-backdrop="static"` in high-input forms (EMR, Billing), meaning accidental backdrop clicks dismiss partially filled forms with no warning.

---

### TASK-1A — Fix Drawer Overlay Z-Index Collision

**File:** `public/css/style.css`

**Find:**
```css
.ss-drawer-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.5);
  z-index: 1050;
  backdrop-filter: blur(4px);
}
.ss-drawer-overlay.open { display: block; }
```

**Replace with:**
```css
.ss-drawer-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.5);
  z-index: 1045; /* FIXED: Must be below Bootstrap backdrop (1050) so CRUD modals stay interactive */
  backdrop-filter: blur(4px);
  pointer-events: none; /* Default: non-blocking */
}
.ss-drawer-overlay.open {
  display: block;
  pointer-events: all; /* Only capture clicks when truly open */
}
```

**Find the drawer panel z-index:**
```css
.ss-drawer {
  ...
  z-index: 1051;
```
**Replace that line with:**
```css
  z-index: 1046; /* FIXED: Stay above its overlay but below Bootstrap modal */
```

**Verify:** `grep -n "z-index: 1045" public/css/style.css` → 1 result inside `.ss-drawer-overlay`

---

### TASK-1B — Ensure Alert Modal Releases CRUD Modals Cleanly

**File:** `app/Views/layouts/main.php`

**Find the `closeModal` function** (inside the `<script>` block near the alert polling code). It currently reads:
```javascript
function closeModal(){
  modalOverlay.classList.remove('show');
  modalOpen=false;
  if(modalQueue.length) setTimeout(()=>openModal(modalQueue.shift()),350);
}
```

**Replace with:**
```javascript
function closeModal(){
  modalOverlay.classList.remove('show');
  /* FIXED: Re-enable pointer-events on the body after alert modal closes,
     in case a Bootstrap CRUD modal was open behind it. */
  document.body.style.pointerEvents = '';
  modalOpen=false;
  if(modalQueue.length) setTimeout(()=>openModal(modalQueue.shift()),350);
}
```

**Find the `openModal` / `showModal` function** where it adds `show` to the overlay. It will look like:
```javascript
modalOverlay.classList.add('show');
```

**Immediately before that line, add:**
```javascript
/* FIXED: Suppress body interactions while alert modal is on top */
document.body.style.pointerEvents = 'none';
modalOverlay.style.pointerEvents = 'all';
```

**Verify:** `grep -n "pointerEvents" app/Views/layouts/main.php` → at least 2 results

---

### TASK-1C — Guard Overlay `open` State on Page Navigation

**File:** `app/Views/layouts/main.php`

Inside the same `<script>` block, **at the very top of the IIFE** (immediately after `'use strict';`), add:

```javascript
/* FIXED: On every page load, ensure the drawer overlay is not stuck open
   (caused by browser back/forward navigation not triggering closeDrawer). */
document.addEventListener('DOMContentLoaded', () => {
  const staleOverlay = document.getElementById('ssDrawerOverlay');
  if (staleOverlay) {
    staleOverlay.classList.remove('open');
    staleOverlay.style.pointerEvents = 'none';
  }
  document.body.style.pointerEvents = '';
});
```

**Verify:** `grep -n "staleOverlay" app/Views/layouts/main.php` → 1 result

---

### TASK-1D — Add `data-bs-backdrop="static"` to Long-Form Modals

Accidental backdrop clicks silently lose form data in EMR and Billing modals.

**File:** `app/Views/emr/index.php`

**Find:**
```html
<div class="modal fade" id="emrModal" tabindex="-1" aria-hidden="true">
```
**Replace with:**
```html
<div class="modal fade" id="emrModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
```

**File:** `app/Views/billing/index.php`

**Find:**
```html
<div class="modal fade" id="billingModal" tabindex="-1" aria-hidden="true">
```
**Replace with:**
```html
<div class="modal fade" id="billingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
```

**Verify:**
- `grep -n "data-bs-backdrop" app/Views/emr/index.php` → 1 result
- `grep -n "data-bs-backdrop" app/Views/billing/index.php` → 1 result

---

## SECTION 2 — INCOMPLETE OR BROKEN FUNCTIONALITY REPAIRS

---

### TASK-2A — Fix `created_at` Missing from Patient AJAX Response

**Problem:** The Users module's `buildRow` in `public/js/app.js` renders `d.created_at.slice(0,10)`. But `PatientController::store()` and `update()` do not return `created_at` in the AJAX JSON response. Newly added patients show `undefined` in the date column until page refresh.

**File:** `app/Controllers/PatientController.php`

**In the `store()` method**, find the `jsonResponse` data array:
```php
'data' => [
    'id'            => $this->patientModel->id,
    'name'          => $this->patientModel->name,
    'email'         => $this->patientModel->email,
    'phone'         => $this->patientModel->phone,
    'address'       => $this->patientModel->address,
    'date_of_birth' => $this->patientModel->date_of_birth,
    'gender'        => $this->patientModel->gender
]
```
**Add `created_at` to it:**
```php
'data' => [
    'id'            => $this->patientModel->id,
    'name'          => $this->patientModel->name,
    'email'         => $this->patientModel->email,
    'phone'         => $this->patientModel->phone,
    'address'       => $this->patientModel->address,
    'date_of_birth' => $this->patientModel->date_of_birth,
    'gender'        => $this->patientModel->gender,
    'created_at'    => date('Y-m-d H:i:s')  // FIXED: include for buildRow
]
```

Apply the **same addition** to the `update()` method's `jsonResponse` data array.

**Verify:** `grep -n "'created_at'" app/Controllers/PatientController.php` → at least 2 results

---

### TASK-2B — Fix Appointments Module: `PATIENTS` and `DOCTORS` JS Variables Not Always Defined

**Problem:** `public/js/app.js` uses `typeof PATIENTS !== 'undefined'` guards, but the `PATIENTS` and `DOCTORS` global JS variables are only injected by the Appointments view. If JavaScript loads before the view's inline `<script>` that defines them, or if the page is the wrong view, `buildRow` silently outputs `—` for all patient/doctor names in the Appointments and EMR tables.

**File:** `app/Views/appointments/index.php`

**Find the inline script** that defines `PATIENTS` and `DOCTORS` (it will look like):
```php
<script>
const PATIENTS = <?php echo json_encode($patients); ?>;
const DOCTORS  = <?php echo json_encode($doctors); ?>;
</script>
```

If those variables are defined inside a `<script>` tag **after** `<script src=".../app.js">`, move the entire inline `<script>` block to **above** the `app.js` `<script>` tag. If the variables are not defined at all, add them.

**Also ensure the same is done in `app/Views/emr/index.php`** — the EMR module also uses `PATIENTS` and `DOCTORS`.

**Verify:**
- In `appointments/index.php`: The `const PATIENTS` script block appears before the `app.js` `<script src>` tag
- In `emr/index.php`: The same ordering is correct

---

### TASK-2C — Fix Billing Module Role Mismatch (Store/Update Blocks Nurses)

**Problem:** `BillingController::store()` and `update()` require role `['admin','staff']`. But `PatientController` (which nurses use daily) grants nurses full access. Nurses who schedule appointments cannot create invoices. The `index()` method correctly includes `nurse`, but CRUD methods do not.

**File:** `app/Controllers/BillingController.php`

**Find in `store()`:**
```php
$this->requireRole(['admin','staff']);
```
**Replace with:**
```php
$this->requireRole(['admin','nurse','staff']);
```

**Find in `update()`:**
```php
$this->requireRole(['admin','staff']);
```
**Replace with:**
```php
$this->requireRole(['admin','nurse','staff']);
```

**Verify:** `grep -n "requireRole" app/Controllers/BillingController.php` → the store and update lines now include `'nurse'`

---

### TASK-2D — Fix Document Download Route Missing

**Problem:** `DocumentController` has `upload()` and `delete()` methods, but there is no `download()` method or route. The documents index view likely shows a "Download" button that links to `/patients/documents/download?id=X`, which returns a 404.

**File:** `app/Controllers/DocumentController.php`

**Add this method** after the `delete()` method:
```php
public function download()
{
    $this->requireLogin();
    $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);

    $database = new Database();
    $db       = $database->getConnection();

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $_SESSION['flash_error'] = 'Invalid document ID.';
        $this->redirect('/patients/documents');
        return;
    }

    $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        $_SESSION['flash_error'] = 'Document not found.';
        $this->redirect('/patients/documents');
        return;
    }

    // File is stored relative to public/uploads/documents/
    $filePath = dirname(__DIR__, 3) . '/public/uploads/documents/' . basename($doc['file_path']);

    if (!file_exists($filePath)) {
        $_SESSION['flash_error'] = 'File no longer exists on disk.';
        $this->redirect('/patients/documents?patient_id=' . $doc['patient_id']);
        return;
    }

    $this->logAction('read', 'document', $id, 'Document downloaded: ' . $doc['file_name']);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Cache-Control: must-revalidate');
    readfile($filePath);
    exit;
}
```

**File:** `app/Core/Router.php`

Find where document routes are registered and add:
```php
'/patients/documents/download' => ['DocumentController', 'download'],
```
(Follow the exact routing pattern already used for `/patients/documents` and `/patients/documents/upload`.)

**Verify:**
- `grep -n "download" app/Controllers/DocumentController.php` → method present
- `grep -n "documents/download" app/Core/Router.php` → route present

---

### TASK-2E — Fix SearchController Returns No Results for Partial Names

**File:** `app/Controllers/SearchController.php`

**Read the file first.** The current search query likely uses `LIKE :q` with `$q = '%' . $term . '%'` — but check whether `$term` is being trimmed and whether the LIKE wildcards are actually appended. A common bug is building the PDO param as `':q' => $term` without the `%` wildcards.

**Find the pattern** (may vary):
```php
$q = $request->get('q') ?? '';
// OR
$q = $_GET['q'] ?? '';
```

**Ensure the search term is properly sanitized and wildcarded:**
```php
$term = trim($_GET['q'] ?? '');
if (strlen($term) < 2) {
    $this->jsonResponse(['results' => [], 'query' => $term]);
    return;
}
$like = '%' . $term . '%';
```

**Ensure every PDO execute/bindValue for the search uses `$like`, not `$term`.**

**Also add**: a minimum 2-character guard (already shown above) so single-character searches don't produce huge unfiltered result sets.

**Verify:** `grep -n "like\|LIKE\|'%'" app/Controllers/SearchController.php` → the wildcard `'%'` is used in binding

---

### TASK-2F — Audit Log `view()` Action Not Being Logged

**Problem:** Patient view (`PatientController::view()`), EMR records view, and Billing print (`BillingController::print()`) never call `$this->logAction(...)`. The audit log is therefore incomplete — reads of sensitive records are not recorded, which is a compliance gap for a medical system.

**File:** `app/Controllers/PatientController.php`

In the `view()` method, **after** the `$this->render(...)` call fails — actually, add it **before** the render, once you've confirmed the patient exists:

```php
// After: $found = $this->patientModel->getById($id); and the not-found check
$this->logAction('read', 'patient', $id, 'Patient profile viewed: ' . $this->patientModel->name);
```

**File:** `app/Controllers/BillingController.php`

Find the `print()` or `printInvoice()` method. After confirming the billing record exists, add:
```php
$this->logAction('read', 'billing', $id, 'Invoice printed: ' . ($record->invoice_number ?? $id));
```

**Verify:**
- `grep -n "logAction.*read.*patient" app/Controllers/PatientController.php` → 1 result
- `grep -n "logAction.*read.*billing" app/Controllers/BillingController.php` → 1 result

---

## SECTION 3 — NEW FEATURES AND MODULES TO ADD NEXT

These are the recommended next-phase additions. Implement them in the order listed.

---

### TASK-3A — Add Patient Vitals Trend Chart to Patient Profile View

**Why:** The patient profile (`patients/view.php`) shows EMR records in a table, but there is no visual trend of vitals (blood pressure, temperature, heart rate, weight) over time. Clinicians need to see trends at a glance.

**Files to create/modify:**
- `app/Views/patients/view.php` — add a Chart.js card below the EMR table
- No new controller or model needed — the data already comes from `$emrRecords`

**In `app/Views/patients/view.php`**, after the EMR records table, add:

```html
<!-- Vitals Trend Chart -->
<?php if (!empty($emrRecords)): ?>
<div class="card mt-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="fas fa-chart-line text-primary"></i>
    <strong>Vitals Trend</strong>
    <small class="text-muted ms-auto">Last <?php echo count($emrRecords); ?> visits</small>
  </div>
  <div class="card-body">
    <canvas id="vitalsChart" height="110"></canvas>
  </div>
</div>
<script>
(function(){
  const records = <?php echo json_encode(array_reverse($emrRecords)); ?>;
  const labels  = records.map(r => r.visit_date ? r.visit_date.slice(0,10) : '—');
  const bp      = records.map(r => {
    // Parse systolic from "120/80" format
    const m = (r.blood_pressure || '').match(/^(\d+)/);
    return m ? parseInt(m[1]) : null;
  });
  const temp = records.map(r => parseFloat(r.temperature) || null);
  const hr   = records.map(r => parseInt(r.heart_rate)    || null);

  new Chart(document.getElementById('vitalsChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'BP Systolic (mmHg)', data: bp,   borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.08)',   tension: .4, spanGaps: true },
        { label: 'Temperature (°C)',   data: temp, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.08)',  tension: .4, spanGaps: true },
        { label: 'Heart Rate (bpm)',   data: hr,   borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)',   tension: .4, spanGaps: true },
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: false } }
    }
  });
})();
</script>
<?php endif; ?>
```

**Ensure Chart.js is loaded in the layout.** Check `app/Views/layouts/main.php` — if Chart.js is not already included, add to the `<head>`:
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
```

**Verify:** `grep -n "vitalsChart" app/Views/patients/view.php` → 1 result

---

### TASK-3B — Add Appointment Calendar View (FullCalendar Integration)

**Why:** FullCalendar is already loaded in `main.php` but unused. The appointments module only shows a DataTable. Doctors and nurses need a visual calendar view of scheduled appointments.

**Files:**
- `app/Views/appointments/index.php` — add calendar toggle and calendar div
- `app/Controllers/AppointmentController.php` — add `calendarFeed()` JSON endpoint
- `app/Core/Router.php` — register `/appointments/calendar-feed`

**In `AppointmentController.php`**, add:
```php
public function calendarFeed()
{
    $this->requireLogin();
    $database = new Database();
    $db       = $database->getConnection();

    $stmt = $db->query(
        "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
                p.name AS patient_name, d.name AS doctor_name
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN doctors  d ON a.doctor_id  = d.id
         ORDER BY a.appointment_date DESC
         LIMIT 500"
    );
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events = [];

    $statusColors = [
        'pending'   => '#f59e0b',
        'confirmed' => '#22c55e',
        'completed' => '#6b7280',
        'cancelled' => '#dc2626',
    ];

    foreach ($rows as $row) {
        $start = $row['appointment_date'];
        if (!empty($row['appointment_time'])) {
            $start .= 'T' . $row['appointment_time'];
        }
        $events[] = [
            'id'       => $row['id'],
            'title'    => $row['patient_name'] . ' → Dr. ' . $row['doctor_name'],
            'start'    => $start,
            'color'    => $statusColors[$row['status']] ?? '#2563eb',
            'extendedProps' => [
                'status' => $row['status'],
                'reason' => $row['reason'],
            ],
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($events);
    exit;
}
```

**In `Router.php`**, add:
```php
'/appointments/calendar-feed' => ['AppointmentController', 'calendarFeed'],
```

**In `app/Views/appointments/index.php`**, add a toggle button near the page header:
```html
<button id="toggleCalendarBtn" class="btn btn-outline-primary btn-sm ms-2">
  <i class="fas fa-calendar-alt me-1"></i>Calendar View
</button>
```

And add the calendar container after the DataTable card:
```html
<div id="appointmentCalendarWrap" class="card mt-4" style="display:none;">
  <div class="card-body">
    <div id="appointmentCalendar"></div>
  </div>
</div>
<script>
document.getElementById('toggleCalendarBtn').addEventListener('click', function() {
  const wrap = document.getElementById('appointmentCalendarWrap');
  const isHidden = wrap.style.display === 'none';
  wrap.style.display = isHidden ? '' : 'none';
  this.innerHTML = isHidden
    ? '<i class="fas fa-table me-1"></i>Table View'
    : '<i class="fas fa-calendar-alt me-1"></i>Calendar View';

  if (isHidden && !wrap.dataset.calInit) {
    wrap.dataset.calInit = '1';
    const cal = new FullCalendar.Calendar(document.getElementById('appointmentCalendar'), {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
      events: window.BASE_URL + '/appointments/calendar-feed',
      eventClick: function(info) {
        Swal.fire({
          title: info.event.title,
          html: `<b>Status:</b> ${info.event.extendedProps.status}<br><b>Reason:</b> ${info.event.extendedProps.reason || '—'}`,
          icon: 'info'
        });
      }
    });
    cal.render();
  }
});
</script>
```

**Verify:**
- `grep -n "calendarFeed" app/Controllers/AppointmentController.php` → 1 result
- `grep -n "calendar-feed" app/Core/Router.php` → 1 result
- `grep -n "appointmentCalendar" app/Views/appointments/index.php` → 1 result

---

### TASK-3C — Add Patient Age Calculator + BMI Display to Patient Profile

**Why:** Doctors must manually calculate age and BMI from raw data. These should be computed and shown in the patient profile view automatically.

**File:** `app/Views/patients/view.php`

Find the patient info display section (where `date_of_birth` and other fields are rendered). Add a computed age badge next to the birth date:

```php
<?php
// Age calculation
$age = '—';
if (!empty($patient->date_of_birth)) {
    try {
        $dob  = new DateTime($patient->date_of_birth);
        $now  = new DateTime();
        $diff = $now->diff($dob);
        $age  = $diff->y . ' yrs ' . $diff->m . ' mo';
    } catch (Exception $e) {}
}
?>
```

Then in the rendered HTML, next to the birth date value:
```html
<span class="badge bg-info text-dark ms-2"><?php echo htmlspecialchars($age); ?></span>
```

For BMI: find the most recent EMR record that has a weight and height (if height is stored), or simply display the most recent weight with a note. If height is not in the EMR schema, add a helper note:
```php
<?php
$latestWeight = null;
foreach ($emrRecords as $rec) {
    if (!empty($rec['weight'])) { $latestWeight = $rec['weight']; break; }
}
?>
<?php if ($latestWeight): ?>
<div class="badge bg-secondary ms-1">Last Weight: <?php echo htmlspecialchars($latestWeight); ?> kg</div>
<?php endif; ?>
```

**Verify:** `grep -n "diff->y" app/Views/patients/view.php` → 1 result

---

### TASK-3D — Add Notification/Reminder System for Upcoming Appointments

**Why:** There is no proactive reminder mechanism. Doctors and staff have no way to see appointments happening today or within the next 24 hours without browsing the full appointments table.

**Files to create/modify:**
- `app/Controllers/AppointmentController.php` — add `upcomingToday()` endpoint
- `app/Views/layouts/main.php` — add a "Today's Appointments" badge in the navbar
- `app/Core/Router.php` — register route

**In `AppointmentController.php`**, add:
```php
public function upcomingToday()
{
    $this->requireLogin();
    $database = new Database();
    $db       = $database->getConnection();

    $stmt = $db->prepare(
        "SELECT a.id, a.appointment_time, a.status, a.reason,
                p.name AS patient_name, d.name AS doctor_name
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN doctors  d ON a.doctor_id  = d.id
         WHERE a.appointment_date = CURDATE()
           AND a.status IN ('pending','confirmed')
         ORDER BY a.appointment_time ASC
         LIMIT 20"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($this->isAjax()) {
        $this->jsonResponse(['success' => true, 'count' => count($rows), 'appointments' => $rows]);
        return;
    }
    $this->jsonResponse(['success' => false, 'message' => 'AJAX only'], 400);
}
```

**In `Router.php`**:
```php
'/appointments/today' => ['AppointmentController', 'upcomingToday'],
```

**In `app/Views/layouts/main.php`**, inside the `<ul class="navbar-nav ...">` (the right-side nav area, near the bell icon), add a today-appointments badge:
```html
<li class="nav-item me-1" id="todayApptWrap" style="display:none;">
  <a class="nav-link position-relative" href="<?php echo url('/appointments'); ?>" title="Today's Appointments">
    <i class="fas fa-calendar-check"></i>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" id="todayApptBadge" style="font-size:.65rem;">0</span>
  </a>
</li>
```

And in the inline polling script, add a one-time fetch on load:
```javascript
// Today's appointments count badge
fetch(window.BASE_URL + '/appointments/today', {
  headers: { 'X-Requested-With': 'XMLHttpRequest' }
})
.then(r => r.json())
.then(d => {
  if (d.success && d.count > 0) {
    document.getElementById('todayApptBadge').textContent = d.count;
    document.getElementById('todayApptWrap').style.display = '';
  }
})
.catch(() => {});
```

**Verify:**
- `grep -n "upcomingToday" app/Controllers/AppointmentController.php` → 1 result
- `grep -n "appointments/today" app/Core/Router.php` → 1 result
- `grep -n "todayApptBadge" app/Views/layouts/main.php` → 1 result

---

### TASK-3E — Add Prescription Print Page for EMR Records

**Why:** Doctors need to print prescriptions directly from the EMR module. The billing module already has a `print.php` view — the EMR module needs the same.

**File to create:** `app/Views/emr/print.php`

**Create this file:**
```html
<?php
// Minimal printable prescription view — no main layout
if (!isset($_SESSION['user'])) { header('Location: ' . url('/login')); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Prescription — <?php echo htmlspecialchars($patient->name ?? ''); ?></title>
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 2cm; color: #111; font-size: 13pt; }
  .rx-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #2563eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
  .rx-logo { font-weight: 700; font-size: 1.4rem; color: #2563eb; }
  .rx-subtitle { color: #6b7280; font-size: .85rem; }
  .rx-body { margin-top: 1.5rem; }
  .rx-symbol { font-size: 2.5rem; font-weight: 700; color: #2563eb; float: left; margin-right: .5rem; line-height: 1; }
  .rx-prescription { font-size: 1.05rem; line-height: 1.7; white-space: pre-wrap; }
  .rx-footer { margin-top: 3rem; border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; }
  .sig-line { border-top: 1px solid #111; width: 200px; margin-top: 2rem; text-align: center; font-size: .85rem; color: #6b7280; }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:1rem;">
  <button onclick="window.print()" style="padding:.5rem 1.5rem; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:1rem;">
    🖨️ Print Prescription
  </button>
  <button onclick="history.back()" style="padding:.5rem 1.5rem; margin-left:.75rem; background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; cursor:pointer; font-size:1rem;">
    ← Back
  </button>
</div>

<div class="rx-header">
  <div>
    <div class="rx-logo">🏥 <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'SafeSense Hospital'); ?></div>
    <div class="rx-subtitle">Medical Prescription</div>
  </div>
  <div style="text-align:right; font-size:.9rem; color:#374151;">
    <strong>Date:</strong> <?php echo htmlspecialchars($record->visit_date ?? date('Y-m-d')); ?><br>
    <strong>Dr.:</strong> <?php echo htmlspecialchars($doctor->name ?? '—'); ?><br>
    <strong>License:</strong> <?php echo htmlspecialchars($doctor->license_number ?? '—'); ?>
  </div>
</div>

<div style="margin-bottom:1.2rem;">
  <strong>Patient:</strong> <?php echo htmlspecialchars($patient->name ?? '—'); ?> &nbsp;|&nbsp;
  <strong>Age:</strong> <?php
    $age = '—';
    if (!empty($patient->date_of_birth)) {
        try { $age = (new DateTime())->diff(new DateTime($patient->date_of_birth))->y . ' yrs'; } catch(Exception $e) {}
    }
    echo $age;
  ?> &nbsp;|&nbsp;
  <strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($patient->gender ?? '—')); ?>
</div>

<hr style="border-color:#e5e7eb;">
<div class="rx-body">
  <div class="rx-symbol">℞</div>
  <div class="rx-prescription"><?php echo nl2br(htmlspecialchars($record->prescription ?? 'No prescription recorded.')); ?></div>
  <div style="clear:both;"></div>
</div>

<?php if (!empty($record->notes)): ?>
<div style="margin-top:1.5rem; padding:1rem; background:#f8fafc; border-left:3px solid #2563eb; border-radius:4px;">
  <strong>Clinical Notes:</strong><br>
  <span style="white-space:pre-wrap;"><?php echo htmlspecialchars($record->notes); ?></span>
</div>
<?php endif; ?>

<div class="rx-footer">
  <div>
    <div class="sig-line">Physician's Signature</div>
  </div>
  <div style="font-size:.8rem; color:#9ca3af;">
    Printed: <?php echo date('F j, Y g:i A'); ?>
  </div>
</div>
</body>
</html>
```

**File:** `app/Controllers/EmrController.php`

Add a `printRecord()` method:
```php
public function printRecord()
{
    $this->requireLogin();
    $this->requireRole(['admin','doctor','nurse']);

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { $this->redirect('/emr'); return; }

    $database = new Database();
    $db       = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM emr_records WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $_SESSION['flash_error'] = 'EMR record not found.'; $this->redirect('/emr'); return; }

    // Load patient and doctor
    $patientModel = new Patient($db);
    $patientModel->getById($row['patient_id']);

    $doctorModel = new Doctor($db);
    $doctorModel->getById($row['doctor_id']);

    // Use a minimal view (no main layout)
    extract(['record' => (object)$row, 'patient' => $patientModel, 'doctor' => $doctorModel]);
    include dirname(__DIR__) . '/Views/emr/print.php';
    exit;
}
```

**In `Router.php`**, add:
```php
'/emr/print' => ['EmrController', 'printRecord'],
```

**In `app/Views/emr/index.php`**, add a print button to each row's action buttons (in the PHP row render, alongside Edit/Delete):
```php
<a href="<?php echo url('/emr/print?id=' . $rec['id']); ?>" target="_blank"
   class="btn btn-sm btn-outline-secondary" title="Print Prescription">
  <i class="fas fa-print"></i>
</a>
```

**Verify:**
- `grep -n "printRecord" app/Controllers/EmrController.php` → 1 result
- `grep -n "emr/print" app/Core/Router.php` → 1 result
- File `app/Views/emr/print.php` exists

---

### TASK-3F — Add Dashboard "Today's Summary" Stats Cards

**Why:** The current dashboard only shows static counts. There are no "today" metrics — appointments today, new patients today, unread alerts today. This makes the dashboard feel stale and unreactive.

**File:** `app/Controllers/DashboardController.php`

Read the existing `index()` method. Add these queries alongside the existing ones:

```php
// Today's summary metrics
$todayStats = [];
try {
    $todayStats['appointments_today']  = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    $todayStats['new_patients_today']  = $db->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $todayStats['alerts_today']        = $db->query("SELECT COUNT(*) FROM safesense_alerts WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $todayStats['unread_alerts']       = $db->query("SELECT COUNT(*) FROM safesense_alerts WHERE is_read = 0")->fetchColumn();
    $todayStats['unpaid_invoices']     = $db->query("SELECT COUNT(*) FROM billing WHERE payment_status = 'unpaid'")->fetchColumn();
} catch (Exception $e) {
    $todayStats = array_fill_keys(['appointments_today','new_patients_today','alerts_today','unread_alerts','unpaid_invoices'], 0);
}
```

Pass `'todayStats' => $todayStats` to the view render call.

**File:** `app/Views/dashboard.php`

After the existing stat cards row, add a "Today's Activity" section:
```html
<div class="row g-3 mb-4">
  <div class="col-12">
    <h6 class="text-muted fw-semibold mb-2"><i class="fas fa-clock me-1"></i>Today's Activity</h6>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="stat-card border-start border-success border-3">
      <div class="text-muted small">Appointments Today</div>
      <div class="fs-4 fw-bold text-success"><?php echo (int)($todayStats['appointments_today'] ?? 0); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="stat-card border-start border-primary border-3">
      <div class="text-muted small">New Patients</div>
      <div class="fs-4 fw-bold text-primary"><?php echo (int)($todayStats['new_patients_today'] ?? 0); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="stat-card border-start border-danger border-3">
      <div class="text-muted small">IoT Alerts Today</div>
      <div class="fs-4 fw-bold text-danger"><?php echo (int)($todayStats['alerts_today'] ?? 0); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="stat-card border-start border-warning border-3">
      <div class="text-muted small">Unpaid Invoices</div>
      <div class="fs-4 fw-bold text-warning"><?php echo (int)($todayStats['unpaid_invoices'] ?? 0); ?></div>
    </div>
  </div>
</div>
```

**Verify:** `grep -n "todayStats" app/Controllers/DashboardController.php` → at least 1 result

---

### TASK-3G — Add Role-Based Access to Reports (Doctor-Only Financials)

**Why:** The Reports page shows revenue and financial data to all roles including `nurse`. Financial data should be gated to `admin` only, while clinical data (patient trends, appointment stats, alert trends) remains visible to `doctor` and `nurse`.

**File:** `app/Views/reports/index.php`

Wrap the revenue chart and revenue table sections with a PHP role check:

```php
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin'])): ?>
  <!-- Revenue by Month Chart -->
  <!-- ... existing revenue chart code ... -->
<?php endif; ?>
```

Wrap the "Summary Totals" revenue line items similarly:
```php
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin'])): ?>
  <div>Revenue: ₱<?php echo number_format($totals['revenue'] ?? 0, 2); ?></div>
  <div>Collected: ₱<?php echo number_format($totals['collected'] ?? 0, 2); ?></div>
<?php endif; ?>
```

**Verify:** `grep -n "admin.*revenue\|revenue.*admin" app/Views/reports/index.php` → role-gated revenue section present

---

## SECTION 4 — ADDITIONAL RECOMMENDED FEATURES (Future Sprints)

These are not implemented in this prompt — they are documented here for the next planning session:

| Feature | Priority | Complexity | Notes |
|---|---|---|---|
| **SMS/Email Appointment Reminders** | High | Medium | Integrate with Twilio or Mailjet. Fire 24h before appointment via a cron job or a `/appointments/send-reminders` endpoint |
| **Patient Portal (Read-Only Login)** | High | High | Separate login role `patient` — can view own appointments, EMR summary, and billing. No admin access. |
| **Inventory / Supplies Module** | Medium | Medium | Track medical supplies, quantities, and low-stock alerts. New `inventory` table. |
| **Inter-Doctor Referral Notes** | Medium | Medium | Add a `referrals` table — doctor A can refer patient to doctor B with notes attached to the EMR |
| **Telemedicine Link Field on Appointments** | Low | Low | Add a `meeting_url` field to appointments for virtual consultation links (Google Meet / Zoom URL). Show a "Join" button in the calendar event popup. |
| **HIPAA / Data Export Compliance** | High | High | Add a "Export My Data" endpoint that generates a zip of all patient records (PDF) for portability. Requires PDF library (mPDF or TCPDF). |
| **Two-Factor Authentication (2FA)** | High | Medium | TOTP via `google2fa` PHP library. Add `two_fa_secret` to users table. Required for admin role. |
| **Dark Mode Toggle** | Low | Low | CSS variables are already set up. Add a `data-theme="dark"` toggle on `<body>`, persist in `localStorage`. |

---

## FINAL VERIFICATION CHECKLIST

Run each command from the `medical/` root and confirm the expected result:

```
SECTION 1 — MODAL BUG FIXES
[ ] TASK-1A: grep -n "z-index: 1045" public/css/style.css → 1 result in .ss-drawer-overlay
[ ] TASK-1A: grep -n "z-index: 1046" public/css/style.css → 1 result in .ss-drawer
[ ] TASK-1B: grep -n "pointerEvents" app/Views/layouts/main.php → at least 2 results
[ ] TASK-1C: grep -n "staleOverlay" app/Views/layouts/main.php → 1 result
[ ] TASK-1D: grep -n "data-bs-backdrop" app/Views/emr/index.php → 1 result
[ ] TASK-1D: grep -n "data-bs-backdrop" app/Views/billing/index.php → 1 result

SECTION 2 — INCOMPLETE FUNCTIONALITY
[ ] TASK-2A: grep -n "'created_at'" app/Controllers/PatientController.php → 2+ results (in store and update)
[ ] TASK-2B: In appointments/index.php, the const PATIENTS script block is above the app.js <script src> tag
[ ] TASK-2B: In emr/index.php, the same ordering is correct
[ ] TASK-2C: grep -n "'nurse'" app/Controllers/BillingController.php → appears in store() and update()
[ ] TASK-2D: grep -n "public function download" app/Controllers/DocumentController.php → 1 result
[ ] TASK-2D: grep -n "documents/download" app/Core/Router.php → 1 result
[ ] TASK-2E: grep -n "'%'" app/Controllers/SearchController.php → LIKE wildcards present
[ ] TASK-2F: grep -n "logAction.*read.*patient" app/Controllers/PatientController.php → 1 result
[ ] TASK-2F: grep -n "logAction.*read.*billing" app/Controllers/BillingController.php → 1 result

SECTION 3 — NEW FEATURES
[ ] TASK-3A: grep -n "vitalsChart" app/Views/patients/view.php → 1 result
[ ] TASK-3B: grep -n "calendarFeed" app/Controllers/AppointmentController.php → 1 result
[ ] TASK-3B: grep -n "calendar-feed" app/Core/Router.php → 1 result
[ ] TASK-3C: grep -n "diff->y" app/Views/patients/view.php → 1 result
[ ] TASK-3D: grep -n "upcomingToday" app/Controllers/AppointmentController.php → 1 result
[ ] TASK-3D: grep -n "todayApptBadge" app/Views/layouts/main.php → 1 result
[ ] TASK-3E: grep -n "printRecord" app/Controllers/EmrController.php → 1 result
[ ] TASK-3E: ls app/Views/emr/print.php → file exists
[ ] TASK-3F: grep -n "todayStats" app/Controllers/DashboardController.php → 1 result
[ ] TASK-3G: grep -n "admin.*revenue\|revenue.*admin" app/Views/reports/index.php → 1 result (role gate)
```

---

## FINAL REPORT FORMAT

```
╔══════════════════════════════════════════════════════════════════════════╗
║           SAFESENSE — SYSTEM REPAIR & FEATURE EXPANSION REPORT          ║
╠══════════════════════════════════════════════════════════════════════════╣
║  TASK-1A  Drawer Overlay Z-Index Fixed (1050→1045)        ✅ / ❌       ║
║  TASK-1B  Alert Modal Releases CRUD Modals Cleanly        ✅ / ❌       ║
║  TASK-1C  Stale Overlay Guard on Page Navigation          ✅ / ❌       ║
║  TASK-1D  Static Backdrop on EMR & Billing Modals         ✅ / ❌       ║
║  TASK-2A  Patient AJAX Response Includes created_at       ✅ / ❌       ║
║  TASK-2B  PATIENTS/DOCTORS JS Vars Load Before app.js     ✅ / ❌       ║
║  TASK-2C  Billing Store/Update Allow Nurse Role           ✅ / ❌       ║
║  TASK-2D  Document Download Route & Method Added          ✅ / ❌       ║
║  TASK-2E  Search Uses Proper LIKE Wildcards               ✅ / ❌       ║
║  TASK-2F  Audit Log Captures Read Events                  ✅ / ❌       ║
║  TASK-3A  Vitals Trend Chart on Patient Profile           ✅ / ❌       ║
║  TASK-3B  Appointment Calendar View (FullCalendar)        ✅ / ❌       ║
║  TASK-3C  Patient Age + Last Weight on Profile            ✅ / ❌       ║
║  TASK-3D  Today's Appointments Navbar Badge               ✅ / ❌       ║
║  TASK-3E  Prescription Print View for EMR Records         ✅ / ❌       ║
║  TASK-3F  Dashboard Today's Activity Stats Cards          ✅ / ❌       ║
║  TASK-3G  Role-Gated Revenue in Reports                   ✅ / ❌       ║
╠══════════════════════════════════════════════════════════════════════════╣
║  TOTAL:  ___ / 17 PASSED                                                ║
║  STATUS: [ ALL CLEAR ✅ ] or [ NEEDS RETRY ❌ ]                          ║
╚══════════════════════════════════════════════════════════════════════════╝
```

If any ❌ appears — do NOT stop. Return to that task, re-read the file, re-apply the fix, re-run the verify grep, and update this table. Only stop when all 17 show ✅.
