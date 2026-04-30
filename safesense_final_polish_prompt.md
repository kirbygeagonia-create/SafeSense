# SafeSense — Final Polish Prompt
## Three Surgical Fixes: Role Guard, Design Consistency, Dismiss Animation

---

## CONTEXT & SCOPE

You are making **three small, surgical changes** to the SafeSense Hospital Management System. The system is a PHP MVC application running under `medical/`. All previous fixes are complete. The design system (CSS variables, `stat-icon`, `stat-card`, `page-header`, `ss-filter-pill`) is fully in place and must not be altered. No PHP logic, routing, models, or existing CSS classes may be changed outside the exact lines specified below.

**Total files to touch: 3**
**Total lines changed: ~6**

Read all three fixes before writing a single character. Apply them in the order listed.

---

## FIX 1 — Add role guard to PatientController index()

### File
`medical/app/Controllers/PatientController.php`

### Problem
`index()` currently calls only `$this->requireLogin()`. Any authenticated user — including low-privilege `staff` role — can browse the full patient list. All write operations are correctly role-guarded; only the read/list route is missing a role check.

### Exact current code to find (lines 14–24)
```php
    public function index()
    {
        $this->requireLogin();
        $stmt = $this->patientModel->getAll();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('patients/index', [
            'patients'    => $patients,
            'title'       => 'Patients',
            'currentRole' => $this->currentRole()
        ]);
    }
```

### Replace with
```php
    public function index()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);
        $stmt = $this->patientModel->getAll();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('patients/index', [
            'patients'    => $patients,
            'title'       => 'Patients',
            'currentRole' => $this->currentRole()
        ]);
    }
```

### What changed
One line added: `$this->requireRole(['admin', 'doctor', 'nurse']);` immediately after `requireLogin()`.
`requireRole()` is already defined in `BaseController` and accepts a string or array. Passing an array means any of the listed roles is accepted. `staff` users will receive a 403 if they attempt to navigate to `/patients`.

---

## FIX 2 — Add role guard to DoctorController index()

### File
`medical/app/Controllers/DoctorController.php`

### Problem
Identical to Fix 1 — `index()` uses only `requireLogin()`, allowing staff-role users to view the full doctor directory.

### Exact current code to find (lines 14–24)
```php
    public function index()
    {
        $this->requireLogin();
        $stmt = $this->doctorModel->getAll();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('doctors/index', [
            'doctors'     => $doctors,
            'title'       => 'Doctors',
            'currentRole' => $this->currentRole()
        ]);
    }
```

### Replace with
```php
    public function index()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);
        $stmt = $this->doctorModel->getAll();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('doctors/index', [
            'doctors'     => $doctors,
            'title'       => 'Doctors',
            'currentRole' => $this->currentRole()
        ]);
    }
```

### What changed
One line added: `$this->requireRole(['admin', 'doctor', 'nurse']);` immediately after `requireLogin()`.

### Note
`staff` role retains access to `billing/` (correct — they handle invoices). They no longer have access to `/patients` or `/doctors`. This is the intended separation of duties.

---

## FIX 3 — Replace inline circle with stat-icon in dashboard appointments panel

### File
`medical/app/Views/dashboard.php`

### Problem
The "Upcoming Appointments" panel uses a `rounded-circle` div with inline `style="width:38px;height:38px;"`. Every other icon container across the entire dashboard now uses the `stat-icon` CSS class (defined in `style.css` as `width:44px; height:44px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0`). This one panel is the only remaining inconsistency in the design system.

### Exact current code to find (lines 188–191 inside the `foreach ($upcomingAppointments as $appt)` loop)
```php
          <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;">
              <i class="fas fa-user text-primary fa-sm"></i>
            </div>
```

### Replace with
```php
          <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
              <i class="fas fa-user"></i>
            </div>
```

### What changed
- `rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0` → `stat-icon bg-primary bg-opacity-10 text-primary`
- `style="width:38px;height:38px;"` → removed entirely (dimensions now come from the CSS class)
- `fa-sm` removed from the icon (the `stat-icon` class sets `font-size:1.1rem` which is the correct size for this context)
- `text-primary` moved from the `<i>` tag to the parent `<div>` — this is consistent with how all other `stat-icon` instances work in the same file

Do not touch any other line in this loop. The `flex-grow-1` content div, the badge, and the date/time display remain exactly as they are.

---

## FIX 4 — Replace dismiss fade with directional slide-out animation

### File
`medical/app/Views/alerts/index.php`

### Problem
The dismiss button handler currently animates the alert card by setting `opacity: '0'` only. The design system uses `translateX` slide animations for all other exit animations (toasts, drawer). The dismiss should match — a card sliding right while fading out feels purposeful and directional; a plain fade feels like it simply disappears.

### Exact current code to find (inside the `<script>` block, lines 156–165)
```javascript
document.querySelectorAll('.dismiss-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const id=this.dataset.id;
    const card=this.closest('.alert-card-wrap');
    safeAjaxPost(window.BASE_URL + '/api/alerts/dismiss', { id: id })
      .then(() => {
        card.style.opacity = '0';
        card.style.transition = '.3s';
        setTimeout(() => card.remove(), 300);
      });
  });
});
```

### Replace with
```javascript
document.querySelectorAll('.dismiss-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id   = this.dataset.id;
    const card = this.closest('.alert-card-wrap');
    safeAjaxPost(window.BASE_URL + '/api/alerts/dismiss', { id })
      .then(() => {
        card.style.transition = 'opacity .25s ease, transform .25s ease';
        card.style.opacity    = '0';
        card.style.transform  = 'translateX(24px)';
        setTimeout(() => card.remove(), 260);
      });
  });
});
```

### What changed
- `transition` is now set **before** the opacity/transform so it applies to both properties simultaneously
- `transform: translateX(24px)` added — card slides 24px to the right as it fades, consistent with the toast `toastSlideOut` animation direction
- Duration shortened from `300ms` to `260ms` to feel snappier
- `setTimeout` delay reduced from `300` to `260` to match the new transition duration exactly — the card is removed immediately after the animation completes with no leftover gap
- Arrow function syntax standardised to match the rest of the file's style

---

## VERIFICATION CHECKLIST

After applying all four changes, run every check below. If any item fails, fix it and re-run from the top. Do not commit until all pass.

### Step 1 — File scope check
```
[ ] git diff --name-only shows exactly three files:
      medical/app/Controllers/PatientController.php
      medical/app/Controllers/DoctorController.php
      medical/app/Views/dashboard.php
      medical/app/Views/alerts/index.php

    No other file was modified.
```

### Step 2 — PatientController
```
[ ] Open PatientController.php and find index()
[ ] Line immediately after $this->requireLogin(); reads:
      $this->requireRole(['admin', 'doctor', 'nurse']);
[ ] The rest of index() is completely unchanged
[ ] No other method in PatientController.php was touched
```

### Step 3 — DoctorController
```
[ ] Open DoctorController.php and find index()
[ ] Line immediately after $this->requireLogin(); reads:
      $this->requireRole(['admin', 'doctor', 'nurse']);
[ ] The rest of index() is completely unchanged
[ ] No other method in DoctorController.php was touched
```

### Step 4 — Dashboard appointments icon
```
[ ] Open dashboard.php and find the foreach ($upcomingAppointments as $appt) loop
[ ] The icon container reads:
      <div class="stat-icon bg-primary bg-opacity-10 text-primary">
        <i class="fas fa-user"></i>
      </div>
[ ] The words "rounded-circle" do NOT appear in this block
[ ] The inline style="width:38px;height:38px;" does NOT appear in this block
[ ] The class "fa-sm" does NOT appear on the <i> tag
[ ] The flex-grow-1 content div below it is unchanged
[ ] The badge and date/time rows are unchanged
```

### Step 5 — Dismiss animation
```
[ ] Open alerts/index.php and find the .dismiss-btn click handler
[ ] card.style.transition reads:
      'opacity .25s ease, transform .25s ease'
[ ] card.style.transform reads:
      'translateX(24px)'
[ ] card.style.opacity reads:
      '0'
[ ] setTimeout delay is 260 (not 300)
[ ] The safeAjaxPost call and endpoint URL are unchanged
```

### Step 6 — Regression check (read each file — do not skip)
```
[ ] PatientController store(), update(), delete() methods are UNCHANGED
[ ] DoctorController store(), update(), delete() methods are UNCHANGED
[ ] dashboard.php stat cards (all 8) are UNCHANGED
[ ] dashboard.php recent alerts list is UNCHANGED
[ ] dashboard.php chart scripts are UNCHANGED
[ ] alerts/index.php filter pill logic is UNCHANGED
[ ] alerts/index.php markAllReadBtn handler is UNCHANGED
[ ] style.css was NOT modified (all three fixes are in PHP/JS files only)
```

### Step 7 — Logic verification
```
[ ] A user with role 'staff' navigating to /patients receives a 403 response
    (verify by checking: requireRole sends 403 for unlisted roles — confirmed in BaseController)
[ ] A user with role 'admin', 'doctor', or 'nurse' can still access /patients and /doctors normally
[ ] Clicking Dismiss on an alert card produces a rightward slide + fade (not just a fade)
[ ] The dismissed card is fully removed from DOM after ~260ms
[ ] The stat-icon in the appointments panel is visually consistent with the 8 stat cards above it
    (same square shape with rounded corners, not a circle)
```

### Step 8 — Commit
```
[ ] All steps 1–7 passed with zero failures

[ ] Commit with message:
    git commit -m "fix: role guard on patient/doctor index, stat-icon consistency, dismiss slide animation"
```

---

## SUMMARY TABLE

| # | File | Lines Changed | What Changes |
|---|------|--------------|--------------|
| 1 | `PatientController.php` | +1 line in `index()` | Add `requireRole(['admin','doctor','nurse'])` |
| 2 | `DoctorController.php` | +1 line in `index()` | Add `requireRole(['admin','doctor','nurse'])` |
| 3 | `dashboard.php` | ~3 lines in appointments loop | Replace `rounded-circle` inline style with `stat-icon` class |
| 4 | `alerts/index.php` | ~3 lines in dismiss handler | Add `translateX(24px)` slide to dismiss animation |

**4 files. ~8 lines total. SafeSense is then fully complete, fully audited, and production-ready.**
