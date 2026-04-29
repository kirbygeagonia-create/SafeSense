# SafeSense — Fix Prompt for Windsurf / Kimi K2.5

You are working on the **SafeSense Hospital Management System** — a PHP MVC app located at `medical/` with an Arduino IoT integration. A full audit has identified **3 critical bugs**, **5 warnings**, and **4 security issues**. Fix every item below exactly as specified. Do not refactor anything outside the listed changes.

---

## CRITICAL BUG 1 — Missing model preloads (fatal class-not-found on /emr and /billing)

**File:** `medical/public/index.php`

**Problem:** The `$models` array on line 34 only preloads `['Patient', 'Doctor', 'Appointment', 'Alert']`. `BillingController` and `EmrController` both instantiate their models inside `__construct()`, which runs before any `require_once` can be added. PHP throws a fatal *Class 'Billing' not found* / *Class 'Emr' not found* error on every visit to `/billing` or `/emr`.

**Fix:** Change line 34 from:
```php
$models = ['Patient', 'Doctor', 'Appointment', 'Alert'];
```
to:
```php
$models = ['Patient', 'Doctor', 'Appointment', 'Alert', 'Emr', 'Billing'];
```

---

## CRITICAL BUG 2 — Wildcard 404 catch-all route never matches

**File:** `medical/app/Core/Router.php` and `medical/app/Core/App.php`

**Problem:** `App.php` registers the fallback route as:
```php
$this->router->get('/{any:.*}', 'ErrorController@notFound');
```
`Router::match()` uses `preg_replace('/\{(\w+)\}/', '([^/]+)', ...)` to expand `{param}` placeholders. The `\w+` pattern only matches word characters (`[a-zA-Z0-9_]`). The name `any:.*` contains a colon and a dot — neither is a word character — so the placeholder is never replaced. The route stays as the literal string `/{any:.*}` and never matches any real URL. Unknown routes produce a blank page instead of a 404.

**Fix — two-part:**

1. In `medical/app/Core/Router.php`, update the `match()` method's pattern line to support extended param names:
```php
// OLD:
$pattern = '#^' . preg_replace('/\{(\w+)\}/', '([^/]+)', preg_quote($route['route'], '#')) . '$#';

// NEW (supports {name}, {name:.*}, {name:[^/]+} etc.):
$pattern = '#^' . preg_replace('/\{[\w:.*]+\}/', '(.*)', preg_quote($route['route'], '#')) . '$#';
```

2. In `medical/app/Core/App.php`, update the wildcard route to also match slashes (multi-segment paths):
```php
// OLD:
$this->router->get('/{any:.*}', 'ErrorController@notFound');

// NEW (no change needed to the route string once Router is fixed — but make it explicit):
$this->router->get('/{any:.*}', 'ErrorController@notFound');
```
No change needed to `App.php` itself once the Router regex is corrected. Verify by confirming that visiting `/nonexistent-page` now calls `ErrorController@notFound`.

---

## CRITICAL BUG 3 — Flash message lost after logout (broken session sequence)

**File:** `medical/app/Controllers/AuthController.php`

**Problem:** `logout()` calls `session_destroy()` — which destroys the session data and closes the session — then immediately calls `session_start()` to open a new session and writes a flash message into it. However, the browser's cookie still holds the old (now-destroyed) session ID. On the redirect to `/login`, the browser sends the old session ID, PHP finds no matching session, and starts yet another empty session — so the flash message is lost. Additionally, `$_SESSION` is not cleared before destroying.

**Fix:** Replace the `logout()` method body:
```php
public function logout() {
    // Clear superglobal first, then destroy storage
    $_SESSION = [];

    // Invalidate the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();

    // Start a fresh session for the flash message
    session_start();
    session_regenerate_id(true);
    $_SESSION['flash_success'] = 'You have been logged out successfully.';
    $this->redirect('/login');
}
```

---

## WARNING 1 — config.php ignores .env for database credentials

**File:** `medical/app/Config/config.php`

**Problem:** DB constants are hard-coded (`DB_HOST = 'localhost'`, `DB_USER = 'root'`, `DB_PASS = ''`). The `.env` file defines `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — but they are never read. Production overrides in `.env` are silently ignored.

**Fix:** Replace the hard-coded DB block (lines 7–11):
```php
// OLD:
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// NEW:
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'hospital_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
```

---

## WARNING 2 — EMR table missing foreign-key constraints

**File:** `medical/database/migrations/005_create_emr_table.php`

**Problem:** `emr_records.patient_id` and `emr_records.doctor_id` are plain INT columns with only an index — no `FOREIGN KEY` constraint. Orphaned EMR records can accumulate when patients or doctors are deleted.

**Fix:** Add FK constraints inside the `CREATE TABLE` statement, after the index lines and before the closing `)`:
```sql
-- Add these two lines inside the CREATE TABLE block, after the INDEX lines:
FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE RESTRICT
```

The full `pdo->exec()` call should end with:
```sql
        INDEX idx_patient_id (patient_id),
        INDEX idx_visit_date (visit_date),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
        FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
(Also fix the charset — see Warning 3 below.)

---

## WARNING 3 — Inconsistent character set (utf8 vs utf8mb4)

**Files:** `medical/database/migrations/001_create_patients_table.php` and `medical/database/migrations/005_create_emr_table.php`

**Problem:** Both use `ENGINE=InnoDB DEFAULT CHARSET=utf8` while all other tables use `utf8mb4`. MySQL's `utf8` is a 3-byte alias that cannot store 4-byte characters (emoji, some CJK). Cross-table joins between `utf8` and `utf8mb4` columns also force expensive collation conversions.

**Fix:** In both files, change:
```sql
-- OLD:
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- NEW:
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## WARNING 4 — ROLE_PATIENT constant not in users ENUM

**File:** `medical/app/Config/config.php`

**Problem:** `config.php` defines `define('ROLE_PATIENT', 'patient')` but the `users` table ENUM is `('admin','doctor','nurse','staff')` — `'patient'` is absent. Assigning this role to a user record stores an empty string in MySQL.

**Fix:** Remove the unused constant from `config.php`:
```php
// DELETE this line:
define('ROLE_PATIENT', 'patient');
```
Patients are stored in the `patients` table, not as system users. If patient portal login is planned for a future phase, add `'patient'` to the migration ENUM at that time.

---

## WARNING 5 — Arduino API endpoint path may not match server location

**File:** `arduino/SafeSense_IoT.ino`

**Problem:** The sketch constructs the POST URL as `SERVER_HOST + "/api/alert"` (e.g. `http://192.168.1.100/api/alert`). If Apache/Nginx is not configured with `medical/public/` as the document root, the correct URL is `http://192.168.1.100/SafeSense/medical/public/api/alert`. Currently there is no `SERVER_PATH` variable — the sub-path is simply missing.

**Fix:** Add a `SERVER_PATH` constant and use it in the URL construction:
```cpp
// Add near line 47–48:
const char* SERVER_HOST   = "http://192.168.1.100";  // your server IP
const char* SERVER_PATH   = "/SafeSense/medical/public"; // set to "" if doc root IS /public
const char* API_ENDPOINT  = "/api/alert";

// Update the URL construction (around line 306):
// OLD:
String url = String(SERVER_HOST) + String(API_ENDPOINT);

// NEW:
String url = String(SERVER_HOST) + String(SERVER_PATH) + String(API_ENDPOINT);
```

---

## SECURITY 1 — Remove hardcoded demo credentials

**File:** `medical/app/Controllers/AuthController.php`

**Problem:** A hardcoded backdoor fallback allows login with `admin@example.com` / `password` even if the database is empty. This is committed to the public repository.

**Fix:** Delete the demo fallback block entirely from `authenticate()`:
```php
// DELETE these lines (approximately lines 38–41):
// Demo fallback (remove in production)
if (!$user && $email === 'admin@example.com' && $password === 'password') {
    $user = ['email' => $email, 'role' => 'admin', 'name' => 'Admin'];
}
```

Also remove the seed line from `medical/database/migrations/000_create_users_table.php`:
```php
// DELETE these lines:
$hash = password_hash('password', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (name,email,password,role) VALUES (?,?,?,?)");
$stmt->execute(['Admin', 'admin@example.com', $hash, 'admin']);
echo "Users table created. Default: admin@example.com / password\n";
```
Replace the echo with:
```php
echo "Users table created. Add an admin user manually via the /users route.\n";
```

---

## SECURITY 2 — Replace the weak default IoT API key

**File:** `medical/.env` and `arduino/SafeSense_IoT.ino`

**Problem:** Both files use the literal string `SAFESENSE_SECRET_KEY` as the shared secret. This value is committed to a public repo and provides no real security.

**Fix:**

1. In `medical/.env`, replace:
```
SAFESENSE_API_KEY=SAFESENSE_SECRET_KEY
```
with a placeholder that forces the developer to set a real value:
```
# Generate with: php -r "echo bin2hex(random_bytes(32));"
SAFESENSE_API_KEY=REPLACE_WITH_STRONG_RANDOM_KEY
```

2. In `arduino/SafeSense_IoT.ino`, update the comment to make clear the key must be copied from `.env`:
```cpp
// Must match SAFESENSE_API_KEY in medical/.env — generate a strong key and paste it here
const char* API_KEY = "REPLACE_WITH_STRONG_RANDOM_KEY";
```

---

## SECURITY 3 — Delete session cookie files and add .gitignore

**Files:** `medical/cookie.txt`, `medical/cookie_alert.txt`, `medical/cookie_test.txt`

**Problem:** These files contain live `PHPSESSID` values and are committed to the repository. No `.gitignore` exists anywhere in the repo.

**Fix:**

1. Delete all three files:
   - `medical/cookie.txt`
   - `medical/cookie_alert.txt`
   - `medical/cookie_test.txt`

2. Create `.gitignore` at the repo root (`SafeSense/.gitignore`):
```gitignore
# Environment & secrets
medical/.env
*.env

# Session / cookie test artifacts
medical/cookie*.txt
*.txt

# Composer dependencies
medical/vendor/
medical/composer.phar

# OS / editor
.DS_Store
Thumbs.db
.idea/
.vscode/
*.log
```

---

## SECURITY 4 — Add CSRF protection to the login form

**Files:** `medical/app/Controllers/AuthController.php` and `medical/app/Views/auth/login.php`

**Problem:** `authenticate()` is the only POST handler that does not call `$this->validateCsrf()`. The login view has no `_csrf_token` hidden field. This allows cross-site form submission against the login endpoint.

**Fix — two-part:**

1. In `AuthController::authenticate()`, add CSRF validation as the first check after the POST guard:
```php
public function authenticate() {
    if (!$this->isPostRequest()) { $this->redirect('/login'); return; }

    // ADD THIS LINE:
    $this->validateCsrf();

    $email    = trim($this->getPostData('email') ?? '');
    // ... rest of method unchanged
```

2. In `medical/app/Views/auth/login.php`, add the hidden CSRF token field inside the `<form>` tag (first child):
```php
<form method="post" action="<?php echo url('/login/authenticate'); ?>">
    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($this->generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="mb-3">
```
> **Note:** `login.php` is rendered via `BaseController::render()` which `extract($data)` — the view does not have `$this`. Pass the token through the render call instead:

In `AuthController::login()`:
```php
public function login() {
    if (isset($_SESSION['user'])) {
        $this->redirect('/dashboard');
        return;
    }
    $this->render('auth/login', [
        'title'      => 'Login',
        'csrf_token' => $this->generateCsrfToken(),  // ADD THIS
    ]);
}
```

In `medical/app/Views/auth/login.php`:
```php
<form method="post" action="<?php echo url('/login/authenticate'); ?>">
    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- rest of form unchanged -->
```

---

## Verification checklist

After applying all fixes, confirm the following:

- [ ] `GET /billing` and `GET /emr` load without fatal errors
- [ ] `GET /nonexistent` returns a proper 404 page (not a blank page)
- [ ] Logout redirects to `/login` and the "logged out" flash message is visible
- [ ] Changing `DB_HOST` in `.env` is picked up without touching `config.php`
- [ ] `cookie*.txt` files are absent from the repo
- [ ] `.gitignore` exists at the repo root and covers `.env` and `vendor/`
- [ ] The login form submits correctly (CSRF token present and validated)
- [ ] `admin@example.com / password` login no longer works
- [ ] Arduino sketch URL includes `SERVER_PATH` in the POST target
