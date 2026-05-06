# SafeSense — Windsurf Kimi K2.5 Master Fix Prompt

> **Instructions for Kimi K2.5 in Windsurf:**  
> You are fixing the `SafeSense` Hospital Management System — a PHP 7.4+ custom MVC app (Bootstrap 5, Vanilla JS, MySQL, Arduino IoT). The repo root is the workspace root. All web files live under `/medical/`. Apply every fix below in one session, in the exact order given. Do **not** refactor or rename anything that is not explicitly listed. After all fixes, do a final self-check pass using the verification commands at the bottom.

---

## ✅ CONFIRMED: Is the Modal Bug in the Audit Report?

**YES — it is Bug C5, the highest-priority item in the audit.**  
Root cause: `medical/app/Views/layouts/main.php` has a duplicated closing block after the real `</html>` tag (lines 655–660). This causes `app.js` to load **twice**, every Bootstrap modal to be initialized **twice**, and every `addEventListener` to fire **2–4×**. The result: the modal DOM appears, but Bootstrap's internal state machine is corrupted — the backdrop traps all click and keyboard events, making the form fields completely unreachable. This is the **exact bug you are experiencing** when you try to add/edit/delete records in any module.

---

## 🔧 PHASE 1 — CRITICAL BUGS (Day 1, in order)

---

### FIX C5 — Remove duplicate HTML/JS block from main.php  
**File:** `medical/app/Views/layouts/main.php`  
**Why:** This is the **root cause of ALL modal interaction failures** across every module.

**Action:** Delete lines 655–660 (the entire block after the first `</html>` on line 654). The file must end with exactly one `</body>` and one `</html>`. After the fix, running `grep -c "app.js" medical/app/Views/layouts/main.php` must return `2` (one reference inside the inline `<script>` IIFE, one `<script src>` tag).

The block to delete looks exactly like this — remove it entirely:
```
  setInterval(poll, POLL_MS);
})();
</script>
<script src="<?php echo ASSETS_URL; ?>/js/app.js?v=2"></script>
</body>
</html>
```
(It appears as a duplicate after the legitimate closing `</body></html>` at lines 651–654.)

Also verify: the inline `<script>` IIFE block above it (starting around line 580) is present **only once**. If the IIFE itself (`(function() { ... setInterval(poll, POLL_MS); })();`) appears twice inside the `<script>` block, remove the second occurrence, leaving only one IIFE.

---

### FIX C1 — Seed the default admin user  
**File:** `medical/database/migrations/009_seed_admin_user.php` *(create this file)*  
**Why:** The users table is created empty; no admin exists; the app is completely unusable after a fresh install.

Create the file with this exact content:
```php
<?php
/**
 * Migration 009 — Seed default admin user
 */
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

$host   = $_ENV['DB_HOST']   ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME']   ?? 'hospital_db';
$user   = $_ENV['DB_USER']   ?? 'root';
$pass   = $_ENV['DB_PASS']   ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hash = password_hash('ChangeMe123!', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO users (name, email, password, role, created_at)
                VALUES ('Administrator', 'admin@example.com', '$hash', 'admin', NOW())");

    echo "Admin user seeded. Login: admin@example.com / ChangeMe123!\n";
} catch (PDOException $e) {
    echo "Migration 009 failed: " . $e->getMessage() . "\n";
}
```

---

### FIX C6 — Fix `bindParam` fatal error in Billing model (PHP 8 crash)  
**File:** `medical/app/Models/Billing.php`  
**Why:** `bindParam` requires a variable reference. Passing `htmlspecialchars(strip_tags(...))` directly is a Fatal Error on PHP 8.x — creating or updating any invoice crashes.

In **both** the `create()` method (around line 91) and the `update()` method (around line 124), replace the direct function-call arguments with temporary variables:

```php
// BEFORE (broken on PHP 8):
$stmt->bindParam(':service_description', htmlspecialchars(strip_tags($this->service_description)));
$stmt->bindParam(':notes', htmlspecialchars(strip_tags($this->notes)));

// AFTER (correct):
$desc  = htmlspecialchars(strip_tags($this->service_description));
$notes = htmlspecialchars(strip_tags($this->notes));
$stmt->bindParam(':service_description', $desc);
$stmt->bindParam(':notes', $notes);
```

Apply this pattern to **every** `bindParam` call in the file that currently wraps a function call around a property. Do not change `bindParam` calls that already pass a plain variable or property reference.

---

### FIX C7 — Fix the `.env.example` file  
**File:** `medical/.env.example`  
**Why:** The file is currently a malformed PHP array literal — useless for onboarding.

Replace the entire file contents with:
```
APP_NAME=SafeSense Hospital Management
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=hospital_db
DB_USER=root
DB_PASS=

SAFESENSE_API_KEY=your-secret-iot-api-key-here
```

---

## 🔧 PHASE 2 — FEATURE-BREAKING BUGS (Day 2)

---

### FIX C2 — SearchController: wrong table name and wrong column  
**File:** `medical/app/Controllers/SearchController.php` (around line 46)  
**Why:** The query uses `FROM emr` (table doesn't exist — correct name is `emr_records`) and selects `e.treatment` (column doesn't exist — correct name is `prescription`). Every search on the EMR tab throws a 500 or returns empty.

Replace the EMR search query block with:
```php
$stmt = $db->prepare(
    "SELECT e.id, e.diagnosis, e.prescription, e.created_at, p.name as patient_name
     FROM emr_records e
     JOIN patients p ON e.patient_id = p.id
     WHERE e.diagnosis LIKE :q OR e.prescription LIKE :q OR e.notes LIKE :q
     ORDER BY e.created_at DESC LIMIT 50"
);
$stmt->execute([':q' => '%' . $q . '%']);
$emrResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

Also update any column reference to `e.treatment` elsewhere in the same file — change them all to `e.prescription`.

---

### FIX C3 — EmrController::delete() does nothing  
**File:** `medical/app/Controllers/EmrController.php` (the `delete()` method, around line 228)  
**Why:** After validating the `$id`, the method ends without calling any delete on the model. The user clicks Delete, sees no error, but the record stays in the database forever. Also, the invalid-`$id` branch calls `$this->redirect('/emr')` twice and is missing a `return`.

Replace the entire `delete()` method body with:
```php
public function delete() {
    if (!$this->isPostRequest()) {
        if ($this->isAjax())
            $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $this->redirect('/emr');
        return;
    }
    $this->requireLogin();
    $this->requireRole('admin');
    $this->validateCsrf();

    $id = (int)($this->getPostData('id') ?? 0);
    if (!$id) {
        if ($this->isAjax())
            $this->jsonResponse(['success' => false, 'message' => 'Invalid record ID'], 400);
        $_SESSION['flash_error'] = 'Invalid record ID';
        $this->redirect('/emr');
        return;  // ← was missing
    }

    $database = new Database();
    $db       = $database->getConnection();

    $this->emrModel->conn = $db;
    $this->emrModel->id   = $id;

    if ($this->emrModel->delete()) {
        $this->logAction('delete', 'emr', $id);
        if ($this->isAjax())
            $this->jsonResponse(['success' => true, 'message' => 'EMR record deleted successfully']);
        $_SESSION['flash_success'] = 'EMR record deleted successfully';
    } else {
        if ($this->isAjax())
            $this->jsonResponse(['success' => false, 'message' => 'Failed to delete record'], 500);
        $_SESSION['flash_error'] = 'Failed to delete EMR record';
    }
    $this->redirect('/emr');
}
```

Also confirm that `Emr.php` (the model) has a working `delete()` method. If it does not, add one:
```php
public function delete(): bool {
    $stmt = $this->conn->prepare("DELETE FROM emr_records WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
    return $stmt->execute();
}
```

---

### FIX C4 — UserController: undefined variable `$id` on user create  
**File:** `medical/app/Controllers/UserController.php` (around line 77, inside `store()`)  
**Why:** The AJAX response returns `'id' => $id` but `$id` is never defined. The correct variable is `$newId` (already set from `$db->lastInsertId()`). The new-user row in the DataTable shows `undefined` or blank for the ID.

Find:
```php
'data' => [
    'id'   => $id,
```
Replace with:
```php
'data' => [
    'id'   => $newId,
```

---

### FIX C8 — EmrController: missing `return` after invalid-ID redirect  
*(Already included in the C3 fix above — the `return;` was added after the first redirect in the invalid-ID branch. Confirm it is present.)*

---

## 🔧 PHASE 3 — SECURITY FIXES (Day 3)

---

### FIX H1 — CSRF validateCsrf() missing `return` (bypass possible)  
**File:** `medical/app/Controllers/BaseController.php` (the `validateCsrf()` method, around line 80)  
**Why:** After calling `$this->redirect('/dashboard')`, there is no `return` or `exit`. PHP continues executing the rest of the controller method — an attacker sending an empty CSRF token could still trigger the action.

Replace the entire `validateCsrf()` method with:
```php
protected function validateCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? $_POST['_csrf_token']
           ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if ($this->isAjax()) {
            $this->jsonResponse(['success' => false, 'message' => 'CSRF token validation failed.'], 403);
            exit;
        }
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        $this->redirect('/dashboard');
        exit;
    }
}
```

---

### FIX H4 — AuthController::logout() calls session_start() after session_destroy()  
**File:** `medical/app/Controllers/AuthController.php` (around line 72)  
**Why:** Calling `session_start()` after `session_destroy()` causes "Session cannot be started after headers have been sent" warnings. The next request will start a fresh session automatically.

Find the logout method and remove the two lines after `session_destroy()`:
```php
// REMOVE these two lines:
session_start();
session_regenerate_id(true);
```

The logout method should end with just:
```php
session_destroy();
$_SESSION['flash_success'] = 'You have been logged out successfully.';
// Note: set flash BEFORE destroy if you want it to persist. 
// Better: redirect immediately and let login page show a default message.
$this->redirect('/login');
```

Or, if you want the flash message on the login page:
```php
session_destroy();
// Start a fresh session just for the flash message
session_start();
$_SESSION['flash_success'] = 'You have been logged out successfully.';
$this->redirect('/login');
```
Pick whichever pattern the rest of the codebase uses for post-logout flash, but remove the orphan `session_regenerate_id(true)` call.

---

### FIX M3 — Harden session cookies  
**File:** `medical/public/index.php` (before the first `session_start()` call)  
**Why:** Session cookies are not set with `httponly`, `samesite`, or `secure` flags — vulnerable to XSS cookie theft and CSRF.

Add this block **immediately before** the existing `session_start()` call:
```php
// Harden session cookie
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```
Remove the old bare `session_start()` call if it no longer has the guard.

---

### FIX M2 — Add login brute-force protection  
**File:** `medical/app/Controllers/AuthController.php` (the `login()` POST handler)  
**Why:** There is no rate limiting — an attacker can try unlimited passwords.

Add an in-session attempt counter (simple, no DB change required):
```php
// At the top of the POST branch of login():
$maxAttempts  = 5;
$lockDuration = 900; // 15 minutes in seconds
$now          = time();

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first_attempt' => $now];
}
$attempts = &$_SESSION['login_attempts'];

// Reset window if 15 minutes have passed
if (($now - $attempts['first_attempt']) > $lockDuration) {
    $attempts = ['count' => 0, 'first_attempt' => $now];
}

if ($attempts['count'] >= $maxAttempts) {
    $wait = $lockDuration - ($now - $attempts['first_attempt']);
    $_SESSION['flash_error'] = "Too many failed login attempts. Please wait " . ceil($wait / 60) . " minute(s).";
    $this->redirect('/login');
    return;
}
```

After a **failed** login attempt (wrong password / user not found), add:
```php
$_SESSION['login_attempts']['count']++;
```

After a **successful** login, reset:
```php
unset($_SESSION['login_attempts']);
```

---

### FIX M1 — Enforce `PASSWORD_MIN_LENGTH` in UserController  
**File:** `medical/app/Controllers/UserController.php` (in both `store()` and `update()`, before `password_hash()`)  
**Why:** The constant `PASSWORD_MIN_LENGTH` is defined in `config.php` but never checked.

Add this validation before the `password_hash()` call in both methods:
```php
if (strlen($password) < PASSWORD_MIN_LENGTH) {
    $msg = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    if ($this->isAjax())
        $this->jsonResponse(['success' => false, 'message' => $msg], 422);
    $_SESSION['flash_error'] = $msg;
    $this->redirect('/users');
    return;
}
```

---

### FIX M4 — Real MIME validation for document uploads  
**File:** `medical/app/Controllers/DocumentController.php` (in `upload()`)  
**Why:** The current check trusts the browser-supplied `$_FILES['file']['type']`, which can be spoofed. Use `finfo` to detect the real MIME type from the file bytes.

Replace the MIME type check with:
```php
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$realMime = $finfo->file($_FILES['file']['tmp_name']);
$allowed  = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif',
              'application/msword',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

if (!in_array($realMime, $allowed, true)) {
    if ($this->isAjax())
        $this->jsonResponse(['success' => false, 'message' => 'File type not allowed.'], 422);
    $_SESSION['flash_error'] = 'File type not allowed.';
    $this->redirect('/documents');
    return;
}
```

---

## 🔧 PHASE 4 — CODE QUALITY & ROBUSTNESS

---

### FIX L1 — Fix navbar null-safety warning (PHP 8.1+)  
**File:** `medical/app/Views/layouts/main.php` (the navbar `<span>` that prints the user's name)  
**Why:** `$_SESSION['user']['name']` throws a deprecation notice on PHP 8.1 if the key is absent.

Find:
```php
<?php echo htmlspecialchars($_SESSION['user']['name']); ?>
```
Replace with:
```php
<?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Unknown'); ?>
```
Apply the same null-coalescing pattern to `$_SESSION['user']['role']` and `$_SESSION['user']['email']` wherever they appear in views without a guard.

---

### FIX L2 — Automate asset cache-buster  
**File:** `medical/app/Views/layouts/main.php` (every `?v=2` on `<link>` and `<script>` tags)  
**Why:** The hard-coded `?v=2` never changes — browsers will cache stale JS/CSS after updates.

Replace every hard-coded version suffix. For example:
```php
// BEFORE:
<script src="<?php echo ASSETS_URL; ?>/js/app.js?v=2"></script>

// AFTER:
<?php $appJsPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/js/app.js'; ?>
<script src="<?php echo ASSETS_URL; ?>/js/app.js?v=<?php echo file_exists($appJsPath) ? filemtime($appJsPath) : '1'; ?>"></script>
```
Apply the same `filemtime()` pattern to the main CSS file.

---

### FIX L3 — Add modal cleanup listener (H6 residual backdrop fix)  
**File:** `medical/public/js/app.js`  
**Why:** Even after C5 is fixed, any stale `modal-open` class on `<body>` (from old browser sessions before the fix) can leave a backdrop `<div>` orphaned in the DOM. Add a defensive cleanup listener.

Add this block near the top of `app.js` (after the `'use strict'` or immediately-invoked function wrapper, before any other modal code):
```javascript
// Clean up any stale modal backdrop on page load (defensive fix for H6)
document.addEventListener('DOMContentLoaded', function () {
    // Remove leftover backdrops from broken previous sessions
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
});
```

---

### FIX M6 — Fix the broken `seed_patients.php` script  
**File:** `medical/database/seed_patients.php` (or wherever it exists)  
**Why:** The file is an orphan method body — no `<?php` opening, no `$pdo`, not executable.

If this file is needed for development seeding, rewrite it as a standalone script following the pattern used in `009_seed_admin_user.php` above. If it is not needed, delete it to avoid confusion.

---

### FIX H2 — Guard `init.php` against conflicts  
**File:** `medical/init.php` (if this file exists at root level)  
**Why:** It calls `require_once 'vendor/autoload.php'` without checking if Composer is installed, and calls `session_start()` without a status guard.

Either delete `init.php` if it is unused, or wrap its contents:
```php
<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## 🚀 PHASE 5 — SUITABLE ENHANCEMENTS (implement these)

The following recommendations from the audit report are **well-suited for this codebase** and should be implemented now alongside the bug fixes.

---

### ENH-1 — Global error handler (prevents raw stack traces in production)  
**File:** `medical/public/index.php` (after the config requires)

```php
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[$severity] $message in $file:$line");
    if (defined('APP_DEBUG') && APP_DEBUG) return false; // let PHP show it in dev
    // In production, show generic error page
    if (!headers_sent()) {
        http_response_code(500);
        include __DIR__ . '/../app/Views/errors/500.php';
    }
    exit;
});

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        include __DIR__ . '/../app/Views/errors/500.php';
    }
    exit;
});
```

---

### ENH-2 — CSV export for Patients, Appointments, Billing  
**Why:** Data export is a core requirement for any clinical system and the data structures already support it.

**File:** Add an `export()` method to `PatientController`, `AppointmentController`, and `BillingController`:
```php
public function export() {
    $this->requireLogin();
    $this->requireRole(['admin', 'doctor']);

    $database = new Database();
    $db       = $database->getConnection();

    // Example for patients — adapt table/columns per controller:
    $stmt = $db->query("SELECT id, name, date_of_birth, gender, phone, email, address, created_at FROM patients ORDER BY name");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patients_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0])); // header row
        foreach ($rows as $row) fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
```

Add routes in `medical/app/Core/Router.php` (or wherever routes are registered):
```php
$router->get('/patients/export',     'PatientController@export');
$router->get('/appointments/export', 'AppointmentController@export');
$router->get('/billing/export',      'BillingController@export');
```

Add Export buttons in the corresponding index views (patients, appointments, billing):
```html
<a href="/patients/export" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-download me-1"></i>Export CSV
</a>
```

---

### ENH-3 — Alert stats widget on dashboard (data already exists)  
**Why:** `getAlertsByDay()` already exists in the Alert model. Wire it to a Chart.js widget.

**File:** `medical/app/Views/dashboard.php` — add a chart card after the existing stat cards:
```html
<div class="col-12 col-lg-6 mt-4">
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">SafeSense Alerts — Last 7 Days</div>
        <div class="card-body">
            <canvas id="alertsChart" height="120"></canvas>
        </div>
    </div>
</div>
```

In `DashboardController::index()`, add:
```php
$alertsByDay = $alertModel->getAlertsByDay(7); // pass to view
```

At the bottom of `dashboard.php` (inside a `<script>` tag, after Chart.js is loaded):
```javascript
(function () {
    const data = <?php echo json_encode($alertsByDay ?? []); ?>;
    const labels = data.map(r => r.day);
    const counts = data.map(r => r.count);
    new Chart(document.getElementById('alertsChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Alerts',
                data: counts,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
})();
```

---

### ENH-4 — Simple migration runner  
**File:** `medical/database/migrate.php` *(create this file)*  
**Why:** Currently migrations must be run manually one by one. This runner auto-applies all new migrations in order.

```php
<?php
/**
 * SafeSense Migration Runner
 * Usage: php database/migrate.php
 */

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$ran = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $ran)) { echo "  SKIP  $name\n"; continue; }
    echo "  RUN   $name ... ";
    try {
        require $file;
        $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$name]);
        echo "OK\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
```

---

## ✅ FINAL VERIFICATION CHECKLIST

After applying all fixes, run these checks:

```bash
# C5 — app.js must appear exactly twice in main.php
grep -c "app.js" medical/app/Views/layouts/main.php
# Expected output: 2

# C5 — only one </html> tag
grep -c "</html>" medical/app/Views/layouts/main.php
# Expected output: 1

# C5 — only one </body> tag
grep -c "</body>" medical/app/Views/layouts/main.php
# Expected output: 1

# C5 — only one setInterval(poll, POLL_MS) call
grep -c "setInterval(poll" medical/app/Views/layouts/main.php
# Expected output: 1

# C2 — SearchController must reference emr_records, not emr
grep "FROM emr" medical/app/Controllers/SearchController.php
# Expected: only "FROM emr_records" — no bare "FROM emr"

# C3 — EmrController delete must call ->delete()
grep "delete()" medical/app/Controllers/EmrController.php
# Expected: at least one call to $this->emrModel->delete()

# C4 — UserController must not reference bare $id in jsonResponse
grep "'id' => \$id" medical/app/Controllers/UserController.php
# Expected: no output (should be $newId now)

# C6 — Billing bindParam must not pass htmlspecialchars() directly
grep "bindParam.*htmlspecialchars" medical/app/Models/Billing.php
# Expected: no output

# H1 — validateCsrf must have exit after redirect
grep -A5 "redirect.*dashboard" medical/app/Controllers/BaseController.php
# Expected: exit; appears after the redirect call

# H4 — logout must not call session_regenerate_id after destroy
grep -A3 "session_destroy" medical/app/Controllers/AuthController.php
# Expected: no session_regenerate_id immediately after session_destroy
```

---

## 📋 BUGS NOT ADDRESSED (deferred / out of scope)

| ID | Reason deferred |
|----|-----------------|
| M5 — double HTML-encoding | Requires touching all view files + all model writes simultaneously; high blast radius. Defer to a dedicated refactor sprint. |
| M7 — router greedy regex | Low risk in practice; safe to defer. |
| M8 — AppointmentController status enum | Low risk; defer. |
| M9 — AuditController 500-row render | Pagination refactor; defer to UI sprint. |
| L4 — PSR-4 autoloader | Large structural refactor; defer. |
| L5 — Database singleton | Refactor; defer. |
| ENH Prescription PDF / SMS reminders / Patient portal | Feature additions; plan separately. |

---

*Prompt prepared from live repo audit — `kirbygeagonia-create/SafeSense` · branch: main · May 2026*  
*Covers: C1–C8 (all criticals), H1, H2, H4, M1–M4, M6, L1–L3 + ENH-1 through ENH-4*
