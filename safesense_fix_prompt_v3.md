# SafeSense — Final Fix Pass (Round 3)
## Self-Verifying Iterative Prompt

You are fixing the final remaining issues in the **SafeSense Hospital Management System** PHP repo at `medical/`. Read every fix carefully, apply it, then run the **verification checklist at the bottom** to confirm. If any check fails, fix it and re-run the checklist. Do not stop until every single checkbox passes. Do not skip any fix, even the low-priority ones — all must be completed.

---

## THE ISSUES TO FIX (7 total)

---

### FIX 1 — Untrack `.env` from git (Critical security)

**Problem:** `medical/.env` was committed before `.gitignore` existed. It is still tracked by git and the real API key (`c3afe5b8d9e47f21a6b0c8d4e5f3a9b2`) is visible to anyone who clones the repo. Adding `.gitignore` alone does not untrack already-committed files.

**Fix — run these shell commands from the repo root:**
```bash
git rm --cached medical/.env
git commit -m "security: untrack .env from version control"
```

After doing this, **rotate the API key**: generate a new one and update `medical/.env`:
```bash
# Generate new key (run this, copy the output)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```
Replace the value in `medical/.env`:
```
SAFESENSE_API_KEY=<paste-new-key-here>
```

> The `.gitignore` files already exist and are correct — no changes needed there.

---

### FIX 2 — CSRF protection on the logout form (Security)

**Problem:** The logout `<form>` in `medical/app/Views/layouts/main.php` (around line 73) posts to `/logout` with no CSRF token. `AuthController::logout()` does not call `$this->validateCsrf()`. Any external page can silently force users to log out.

**Fix — Part A:** In `medical/app/Views/layouts/main.php`, find the logout form:
```php
<form method="post" action="<?php echo url('/logout'); ?>" class="d-inline">
    <button type="submit" class="btn btn-outline-light btn-sm">
```
Add a hidden CSRF field as the first child of the form:
```php
<form method="post" action="<?php echo url('/logout'); ?>" class="d-inline">
    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" class="btn btn-outline-light btn-sm">
```

**Fix — Part B:** In `medical/app/Controllers/AuthController.php`, add `validateCsrf()` as the very first line of `logout()`:
```php
public function logout() {
    $this->validateCsrf();   // ADD THIS LINE

    // Save flash message before destroying session
    $flashMessage = 'You have been logged out successfully.';
    $_SESSION = [];
    session_destroy();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash_success'] = $flashMessage;
    $this->redirect('/login');
}
```

---

### FIX 3 — Add `session_regenerate_id()` to logout (Security)

**Problem:** After `session_destroy()` + `session_start()`, the old session cookie is not explicitly invalidated. `session_regenerate_id(true)` forces a new session ID and deletes the old session file, closing any session-fixation window.

**Fix:** In `medical/app/Controllers/AuthController.php`, update the `logout()` body so it reads exactly:
```php
public function logout() {
    $this->validateCsrf();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);

    $_SESSION['flash_success'] = 'You have been logged out successfully.';
    $this->redirect('/login');
}
```

---

### FIX 4 — Update Arduino API key to match `.env` (Functional)

**Problem:** `arduino/SafeSense_IoT.ino` still has `const char* API_KEY = "SAFESENSE_SECRET_KEY"`. The server's `.env` holds a different real key. The Arduino will receive a 401 Unauthorized on every POST until the keys match.

**Fix:** In `arduino/SafeSense_IoT.ino`, find line 51:
```cpp
const char* API_KEY       = "SAFESENSE_SECRET_KEY";   // must match .env SAFESENSE_API_KEY
```
Replace with the **new rotated key** from Fix 1 (whatever value you generated):
```cpp
const char* API_KEY       = "<paste-same-key-as-.env-SAFESENSE_API_KEY>";   // must match .env SAFESENSE_API_KEY
```
The value here must be byte-for-byte identical to `SAFESENSE_API_KEY` in `medical/.env`.

---

### FIX 5 — Remove redundant `session_start()` calls in controllers (Code quality)

**Problem:** `public/index.php` starts the session once at the top. Two controllers defensively call `session_start()` again inside their own methods, which is redundant and can cause unexpected behaviour if called outside the normal request flow.

**Fix — Part A:** In `medical/app/Controllers/AlertController.php`, find the `requireAuth()` method (around line 190):
```php
protected function requireAuth() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();   // DELETE THIS LINE
    if (!isset($_SESSION['user'])) {
```
Delete the `session_start()` line. The method should start with `if (!isset($_SESSION['user'])) {`.

**Fix — Part B:** In `medical/app/Controllers/AuthController.php`, find the `logout()` method. After Fix 3 is applied, the `session_start()` call inside logout is intentional (re-opening after destroy) — leave that one. However, if there is a standalone defensive `session_start()` anywhere else in the file outside of `logout()`, remove it.

---

### FIX 6 — Create missing `errors/500.php` view (Functional)

**Problem:** `ErrorController::error()` references `errors/500.php` which does not exist. A server error would show "View not found" instead of a proper 500 page. The existing `errors/404.php` is the style reference.

**Fix:** Create the file `medical/app/Views/errors/500.php` with this content, matching the style of `404.php`:
```php
<div class='container'>
    <div class='row justify-content-center'>
        <div class='col-md-6 text-center'>
            <div class='alert alert-danger'>
                <h4>500 - Internal Server Error</h4>
                <p>Something went wrong on our end. Please try again or contact support.</p>
                <a href='<?php echo url("/dashboard"); ?>' class='btn btn-primary'>Go to Dashboard</a>
            </div>
        </div>
    </div>
</div>
```

---

### FIX 7 — Create missing edit views for all CRUD modules (Functional)

**Problem:** Five edit views are missing. Normally edits go through AJAX modals, so most users never hit these routes directly. But navigating to `/patients/edit?id=1` in a browser shows "View not found: patients/edit". These views must exist as a safe fallback.

Create all five files below. Each one is a simple redirect-to-index fallback page — the AJAX modal is the primary UX, but these prevent blank crashes.

---

**`medical/app/Views/patients/edit.php`**
```php
<?php
// Fallback: patient edit is handled via AJAX modal on the patients page.
// This view only renders for direct (non-AJAX) browser navigation.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Patient</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/patients/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($patient->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($patient->name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($patient->email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($patient->phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?php echo htmlspecialchars($patient->address ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?php echo htmlspecialchars($patient->date_of_birth ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="male"   <?php echo ($patient->gender ?? '') === 'male'   ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($patient->gender ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other"  <?php echo ($patient->gender ?? '') === 'other'  ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <a href="<?php echo url('/patients'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Patient</button>
            </form>
        </div>
    </div>
</div>
```

---

**`medical/app/Views/doctors/edit.php`**
```php
<?php
// Fallback: doctor edit is handled via AJAX modal on the doctors page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Doctor</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/doctors/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($doctor->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($doctor->name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($doctor->email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($doctor->phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Specialization</label>
                    <input type="text" name="specialization" class="form-control" required value="<?php echo htmlspecialchars($doctor->specialization ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">License Number</label>
                    <input type="text" name="license_number" class="form-control" required value="<?php echo htmlspecialchars($doctor->license_number ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <a href="<?php echo url('/doctors'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Doctor</button>
            </form>
        </div>
    </div>
</div>
```

---

**`medical/app/Views/appointments/edit.php`**
```php
<?php
// Fallback: appointment edit is handled via AJAX modal on the appointments page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Appointment</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/appointments/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($appointment->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select" required>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($appointment->patient_id ?? '') == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" class="form-select" required>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo ($appointment->doctor_id ?? '') == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="appointment_date" class="form-control" required value="<?php echo htmlspecialchars($appointment->appointment_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Time</label>
                    <input type="time" name="appointment_time" class="form-control" required value="<?php echo htmlspecialchars($appointment->appointment_time ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($appointment->status ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control"><?php echo htmlspecialchars($appointment->reason ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <a href="<?php echo url('/appointments'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Appointment</button>
            </form>
        </div>
    </div>
</div>
```

---

**`medical/app/Views/emr/edit.php`**
```php
<?php
// Fallback: EMR edit is handled via AJAX modal on the medical records page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Medical Record</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/emr/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient ID</label>
                        <input type="number" name="patient_id" class="form-control" required value="<?php echo htmlspecialchars($record->patient_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Doctor ID</label>
                        <input type="number" name="doctor_id" class="form-control" required value="<?php echo htmlspecialchars($record->doctor_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Visit Date</label>
                    <input type="date" name="visit_date" class="form-control" required value="<?php echo htmlspecialchars($record->visit_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Chief Complaint</label>
                    <textarea name="chief_complaint" class="form-control" required><?php echo htmlspecialchars($record->chief_complaint ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" required><?php echo htmlspecialchars($record->diagnosis ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prescription</label>
                    <textarea name="prescription" class="form-control"><?php echo htmlspecialchars($record->prescription ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"><?php echo htmlspecialchars($record->notes ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80" value="<?php echo htmlspecialchars($record->blood_pressure ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" class="form-control" value="<?php echo htmlspecialchars($record->temperature ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Heart Rate (bpm)</label>
                        <input type="number" name="heart_rate" class="form-control" value="<?php echo htmlspecialchars($record->heart_rate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.01" name="weight" class="form-control" value="<?php echo htmlspecialchars($record->weight ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <a href="<?php echo url('/emr'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </form>
        </div>
    </div>
</div>
```

---

**`medical/app/Views/billing/edit.php`**
```php
<?php
// Fallback: billing edit is handled via AJAX modal on the billing page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Invoice</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/billing/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($record->invoice_number ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Service Description</label>
                    <textarea name="service_description" class="form-control" required><?php echo htmlspecialchars($record->service_description ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required value="<?php echo htmlspecialchars($record->amount ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount (₱)</label>
                        <input type="number" step="0.01" name="discount" class="form-control" value="<?php echo htmlspecialchars($record->discount ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax (₱)</label>
                        <input type="number" step="0.01" name="tax" class="form-control" value="<?php echo htmlspecialchars($record->tax ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <?php foreach (['unpaid','paid','partial','cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($record->payment_status ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach (['cash','card','insurance','online'] as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($record->payment_method ?? '') === $m ? 'selected' : ''; ?>><?php echo ucfirst($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($record->payment_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"><?php echo htmlspecialchars($record->notes ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <a href="<?php echo url('/billing'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Invoice</button>
            </form>
        </div>
    </div>
</div>
```

---

## SELF-VERIFICATION LOOP

After applying every fix above, run through this checklist **from top to bottom**. If any item fails, fix it immediately and restart the checklist from the top. Only stop when all items are checked.

### Round 1 — File existence checks
```
[ ] medical/app/Views/errors/500.php          exists
[ ] medical/app/Views/patients/edit.php       exists
[ ] medical/app/Views/doctors/edit.php        exists
[ ] medical/app/Views/appointments/edit.php   exists
[ ] medical/app/Views/emr/edit.php            exists
[ ] medical/app/Views/billing/edit.php        exists
```

### Round 2 — Code content checks (grep / read the files)

**FIX 1 — .env untracked:**
```
[ ] Running: git ls-files medical/.env
    Expected output: (empty — no output at all)
    If it still shows "medical/.env", the git rm --cached step was not committed.
```

**FIX 2 — Logout CSRF:**
```
[ ] medical/app/Views/layouts/main.php contains:
    <input type="hidden" name="_csrf_token"
    inside the logout <form> block

[ ] medical/app/Controllers/AuthController.php logout() method
    first statement is: $this->validateCsrf();
```

**FIX 3 — session_regenerate_id in logout:**
```
[ ] medical/app/Controllers/AuthController.php logout() contains:
    session_regenerate_id(true);
```

**FIX 4 — Arduino API key:**
```
[ ] arduino/SafeSense_IoT.ino line with API_KEY does NOT contain "SAFESENSE_SECRET_KEY"
[ ] The value in API_KEY exactly matches SAFESENSE_API_KEY in medical/.env
    (Copy both values side by side and confirm character-for-character)
```

**FIX 5 — No redundant session_start:**
```
[ ] medical/app/Controllers/AlertController.php requireAuth() method does NOT contain session_start()
[ ] The only session_start() in AuthController.php is inside logout() (intentional)
```

**FIX 6 — 500 view content:**
```
[ ] medical/app/Views/errors/500.php contains the text "500 - Internal Server Error"
[ ] medical/app/Views/errors/500.php contains a link back to /dashboard
```

**FIX 7 — Edit views content:**
```
[ ] patients/edit.php  — contains form action="/patients/update", fields: name, email, phone, address, date_of_birth, gender
[ ] doctors/edit.php   — contains form action="/doctors/update", fields: name, email, phone, specialization, license_number
[ ] appointments/edit.php — contains form action="/appointments/update", fields: patient_id, doctor_id, appointment_date, appointment_time, status, reason
[ ] emr/edit.php       — contains form action="/emr/update", fields: patient_id, doctor_id, visit_date, chief_complaint, diagnosis, prescription, blood_pressure, temperature, heart_rate, weight
[ ] billing/edit.php   — contains form action="/billing/update", fields: service_description, amount, discount, tax, payment_status, payment_method, payment_date, notes
[ ] ALL edit views contain: <input type="hidden" name="_csrf_token"
[ ] ALL edit views contain: <input type="hidden" name="id"
```

### Round 3 — Logic checks (read the code, do not skip)

```
[ ] AuthController::logout() sequence is exactly:
    1. $this->validateCsrf()
    2. $_SESSION = []
    3. setcookie() to expire session cookie
    4. session_destroy()
    5. session_start()
    6. session_regenerate_id(true)
    7. $_SESSION['flash_success'] = ...
    8. $this->redirect('/login')

[ ] The logout <form> in main.php has _csrf_token BEFORE the submit button
    (not after, not outside the form)

[ ] medical/.env SAFESENSE_API_KEY value is NOT "SAFESENSE_SECRET_KEY"
    and NOT "REPLACE_WITH_STRONG_RANDOM_KEY" — it is a real hex string

[ ] arduino/SafeSense_IoT.ino API_KEY value is NOT "SAFESENSE_SECRET_KEY"
    and matches medical/.env SAFESENSE_API_KEY exactly

[ ] No edit view uses $this-> (views do not have $this — they use extracted variables)
    Correct pattern: $patient->name, $doctor->name, $appointment->id, $record->id

[ ] git log --oneline -5 shows a commit containing "untrack .env" or similar
    AND git ls-files medical/.env returns empty
```

### Round 4 — Regression check (confirm nothing broke)

```
[ ] medical/public/index.php $models array still contains all 6:
    ['Patient', 'Doctor', 'Appointment', 'Alert', 'Emr', 'Billing']

[ ] medical/app/Core/Router.php still uses /\{[^}]+\}/ for the wildcard fix

[ ] medical/app/Config/config.php DB constants still read from $_ENV with fallbacks

[ ] medical/database/migrations/005_create_emr_table.php still has both FK constraints

[ ] All 7 migration files still use CHARSET=utf8mb4

[ ] ROLE_PATIENT is still absent from medical/app/Config/config.php

[ ] arduino/SafeSense_IoT.ino URL is still: SERVER_HOST + SERVER_PATH + API_ENDPOINT

[ ] medical/app/Controllers/AuthController.php authenticate() still has validateCsrf()
    on the first line after the POST check

[ ] medical/app/Views/auth/login.php still has the _csrf_token hidden input

[ ] medical/.gitignore still exists and lists .env, vendor/, cookie*.txt
```

### Round 5 — Final gate

```
[ ] All Round 1 checks passed
[ ] All Round 2 checks passed
[ ] All Round 3 checks passed
[ ] All Round 4 checks passed

ONLY when all 4 rounds are green: the fix pass is complete.
If any check failed during Round 4 (regression), a previous fix introduced a new bug —
identify which file was changed, compare with the original, and restore what was broken.
```

---

## QUICK REFERENCE — Files changed in this pass

| Fix | File(s) |
|-----|---------|
| 1 | `git rm --cached medical/.env` + `medical/.env` (new key) |
| 2 | `medical/app/Views/layouts/main.php`, `medical/app/Controllers/AuthController.php` |
| 3 | `medical/app/Controllers/AuthController.php` (logout body) |
| 4 | `arduino/SafeSense_IoT.ino` (API_KEY value) |
| 5 | `medical/app/Controllers/AlertController.php` (remove session_start) |
| 6 | `medical/app/Views/errors/500.php` (new file) |
| 7 | `medical/app/Views/patients/edit.php` (new file) |
|   | `medical/app/Views/doctors/edit.php` (new file) |
|   | `medical/app/Views/appointments/edit.php` (new file) |
|   | `medical/app/Views/emr/edit.php` (new file) |
|   | `medical/app/Views/billing/edit.php` (new file) |

**Total: 13 files touched. 6 new files created. 1 git operation.**
