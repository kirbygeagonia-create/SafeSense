# SafeSense — Post-Fix Audit Report (v3)
**Re-scan date:** May 2026 · Live repo: `kirbygeagonia-create/SafeSense` (branch: main)

---

## Overall Assessment

The Windsurf/Kimi K2.5 fix session resolved the **majority of critical issues**, especially the modal interaction bug (C5). However, **5 bugs survived** — some were partially implemented incorrectly, and 2 enhancements are missing. The system is now usable but has real bugs that need a second pass.

---

## ✅ CONFIRMED FIXED (14 items)

| ID | What Was Fixed | Verified |
|----|---------------|---------|
| C5 | Duplicate `app.js` + `</html>` block removed from `main.php` — **modal interaction now works** | ✅ |
| C1 | `009_seed_admin_user.php` created — admin can log in after fresh install | ✅ |
| C2 | `SearchController` EMR query: corrected to `emr_records` table + `prescription` column | ✅ |
| C3 | `EmrController::delete()` now actually calls `$this->emrModel->delete()` + `return` added | ✅ |
| C4 | `UserController::store()` AJAX response now uses `$newId` instead of undefined `$id` | ✅ |
| C6 | `Billing.php` `bindParam` fixed — temp vars `$desc`/`$notes` used (PHP 8 safe) | ✅ |
| C7 | `.env.example` rewritten as a proper key=value file | ✅ |
| H1 | `validateCsrf()` now has `exit` after both redirect paths — CSRF bypass closed | ✅ |
| M3 | Session cookies hardened (`httponly`, `samesite=Lax`, `secure`) in `index.php` | ✅ |
| M4 | `DocumentController` now uses `finfo` for real MIME validation | ✅ |
| L2 | `filemtime()` cache-busting on CSS and `app.js` — no more hard-coded `?v=2` | ✅ |
| L3 | Modal backdrop cleanup listener added to `app.js` | ✅ |
| ENH-2 | CSV export routes registered in `App.php` + `exportCsv()` in Patient, Billing, Appointments | ✅ |
| ENH-3 | Alerts-per-day Chart.js widget added to dashboard | ✅ |

---

## ❌ STILL BROKEN (5 items — requires second fix pass)

---

### BUG-R1 🟠 HIGH — `AuthController::logout()` still calls `session_start()` after `session_destroy()`
**File:** `medical/app/Controllers/AuthController.php` · Lines 83–84

H4 was NOT fixed. The code is identical to the broken version:
```php
session_destroy();
session_start();          // ← still here — causes PHP warning in production
session_regenerate_id();  // wait, this is gone — but session_start() is still there
```
`session_start()` after `session_destroy()` emits a **"Session cannot be started after headers have been sent"** warning in some server configs, and is simply wrong — the next page request will create a fresh session automatically.

**Fix required:**
```php
session_destroy();
// Start a new clean session just for the flash message
session_start();
$_SESSION['flash_success'] = 'You have been logged out successfully.';
$this->redirect('/login');
```
OR simply remove the `session_start()` and store the flash before `session_destroy()`:
```php
// Store flash BEFORE destroying
$_SESSION = [];
// ... cookie clear ...
session_destroy();
// Restart for flash
session_start();
$_SESSION['flash_success'] = 'You have been logged out successfully.';
$this->redirect('/login');
```
The actual pattern that works: clear `$_SESSION`, destroy, start fresh, set flash, redirect.

---

### BUG-R2 🟠 HIGH — Brute-force counter is broken (increments on EVERY request, no time window)
**File:** `medical/app/Controllers/AuthController.php` · Lines 25–31

M2 was implemented incorrectly in two ways:

**Problem 1:** The attempt counter increments **before** credential verification — so even a blank-form submit or a correctly-typed password burns an attempt. The counter should only increment on **failed** logins.

**Problem 2:** There is no time-based window. The prompt asked for a 15-minute lockout window, but the counter is a plain session integer that never resets after 15 minutes. A user who makes 4 failed attempts will be locked out until they clear their cookies, even if 2 hours pass.

Current broken code:
```php
$_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;   // increments on EVERY attempt, even successful ones
if ($_SESSION[$key] > 5) { ... lock ... }
```

**Fix required:**
```php
// Only check (don't increment yet) — increment only on failure below
$attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];
$now      = time();

// Reset window if 15 minutes have passed
if (($now - $attempts['first_attempt']) > 900) {
    $attempts = ['count' => 0, 'first_attempt' => $now];
}

if ($attempts['count'] >= 5) {
    $wait = 900 - ($now - $attempts['first_attempt']);
    $_SESSION['flash_error'] = 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' min.';
    $this->redirect('/login');
    return;
}

// ... credential check ...

if ($user) {
    unset($_SESSION[$key]);           // reset on success
    // ... login ...
} else {
    $attempts['count']++;             // only increment on FAILURE
    $_SESSION[$key] = $attempts;
    $_SESSION['flash_error'] = 'Invalid email or password.';
    $this->redirect('/login');
}
```

---

### BUG-R3 🟡 MEDIUM — `M1` password check uses a hardcoded `8` instead of `PASSWORD_MIN_LENGTH` constant
**File:** `medical/app/Controllers/UserController.php` · Lines 58–59

The fix introduced a **local variable** that shadows the config constant:
```php
$PASSWORD_MIN_LENGTH = 8;   // ← wrong: ignores config.php which defines PASSWORD_MIN_LENGTH = 8
```
If someone changes `config.php` to require longer passwords, `UserController` will never pick it up.

**Fix required:** Remove the local variable and use the global constant directly:
```php
if (strlen($password) < PASSWORD_MIN_LENGTH) {
    $msg = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    ...
}
```

---

### BUG-R4 🟡 MEDIUM — Navbar user name/role still missing null-coalesce (PHP 8.1 deprecation)
**File:** `medical/app/Views/layouts/main.php` · Lines 179–180

L1 was only partially fixed. The `role` checks in navigation use `?? ''` correctly, but the user pill that displays the name still has no guard:
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
```
On PHP 8.1+, if `$_SESSION['user']` is set but `name` or `role` key is missing, this throws a deprecation notice that can corrupt output.

**Fix required:**
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'] ?? 'staff')); ?>)</small>
```

---

### BUG-R5 🟡 MEDIUM — `init.php` still unconditionally requires vendor/autoload + bare `session_start()`
**File:** `medical/init.php`

H2 was not touched. The file still does:
```php
require_once 'vendor/autoload.php';  // ← Fatal if Composer not installed
session_start();                      // ← "session already active" if index.php ran first
```

**Fix required:** Either delete this file (it is unused — `public/index.php` bootstraps everything), or add guards:
```php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

### BUG-R6 🟢 LOW — `ENH-1` error handler is incomplete (only a shutdown function, not full handler)
**File:** `medical/public/index.php`

Only `register_shutdown_function` was added, and only when `LOG_PATH` is defined (which it is **not** in `config.php` — so this code never runs). The full `set_error_handler` + `set_exception_handler` that show `errors/500.php` on uncaught exceptions were not implemented.

**Fix required:** Replace the current shutdown-only block with:
```php
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[PHP Error $severity] $message in $file:$line");
    if (defined('APP_DEBUG') && APP_DEBUG) return false;
    if (!headers_sent()) { http_response_code(500); include __DIR__ . '/../app/Views/errors/500.php'; }
    exit;
});

set_exception_handler(function (Throwable $e) {
    error_log('[Uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); include __DIR__ . '/../app/Views/errors/500.php'; }
    exit;
});
```

---

## 📋 Summary Table

| ID | Severity | File | Status |
|----|----------|------|--------|
| BUG-R1 (H4) | 🟠 High | `AuthController.php` | ❌ Not fixed — `session_start()` after destroy still present |
| BUG-R2 (M2) | 🟠 High | `AuthController.php` | ❌ Wrong implementation — counter fires on every request, no time window |
| BUG-R3 (M1) | 🟡 Medium | `UserController.php` | ❌ Wrong — hardcoded `8` ignores `PASSWORD_MIN_LENGTH` constant |
| BUG-R4 (L1) | 🟡 Medium | `layouts/main.php` | ❌ Partial — navbar name/role still unguarded |
| BUG-R5 (H2) | 🟡 Medium | `init.php` | ❌ Not touched — still has bare `require` + `session_start()` |
| BUG-R6 (ENH-1) | 🟢 Low | `public/index.php` | ⚠️ Partial — shutdown-only handler, never actually runs |

---

# Windsurf Kimi K2.5 — Second-Pass Fix Prompt

> **Instructions:** Apply all 6 fixes below to the `SafeSense` repo in one session. Do not modify any file that is not listed. After all fixes, run the verification commands at the bottom.

---

## FIX R1 — Correct `AuthController::logout()` session handling
**File:** `medical/app/Controllers/AuthController.php`

Find the `logout()` method. The current code calls `session_start()` after `session_destroy()`, then tries to set `$_SESSION`. Replace the entire method body with the corrected sequence:

```php
public function logout() {
    $this->validateCsrf();

    // Store flash before clearing the session
    $_SESSION = [];

    // Expire the session cookie
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();

    // Start a fresh session ONLY to carry the flash message to the login page
    session_start();
    $_SESSION['flash_success'] = 'You have been logged out successfully.';

    $this->redirect('/login');
}
```

---

## FIX R2 — Rewrite brute-force protection in `AuthController::authenticate()`
**File:** `medical/app/Controllers/AuthController.php`

The entire brute-force block must be rewritten. Find the `authenticate()` method and replace it with this corrected version:

```php
public function authenticate() {
    if (!$this->isPostRequest()) {
        $this->redirect('/login');
        return;
    }

    $this->validateCsrf();

    // Brute-force protection — keyed by IP with a 15-minute sliding window
    $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'login_attempts_' . md5($ip);
    $now = time();

    $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => $now];

    // Reset window after 15 minutes
    if (($now - $attempts['first_attempt']) > 900) {
        $attempts = ['count' => 0, 'first_attempt' => $now];
    }

    if ($attempts['count'] >= 5) {
        $wait = 900 - ($now - $attempts['first_attempt']);
        $_SESSION['flash_error'] = 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).';
        $this->redirect('/login');
        return;
    }

    $email    = trim($this->getPostData('email') ?? '');
    $password = $this->getPostData('password') ?? '';

    if (empty($email) || empty($password)) {
        $attempts['count']++;
        $_SESSION[$key] = $attempts;
        $_SESSION['flash_error'] = 'Email and password are required.';
        $this->redirect('/login');
        return;
    }

    $database = new Database();
    $db       = $database->getConnection();

    $user = null;
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password'])) {
            $user = ['id' => $row['id'], 'email' => $row['email'], 'role' => $row['role'], 'name' => $row['name'] ?? 'Staff'];
        }
    } catch (Exception $e) {
        // DB not ready — fail gracefully
    }

    if ($user) {
        // Success — reset attempt counter
        unset($_SESSION[$key]);
        session_regenerate_id(true);
        $_SESSION['user']       = $user;
        $_SESSION['login_time'] = time();
        $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['name']) . '!';
        $this->redirect('/dashboard');
    } else {
        // Failure — increment counter
        $attempts['count']++;
        $_SESSION[$key] = $attempts;
        $_SESSION['flash_error'] = 'Invalid email or password.';
        $this->redirect('/login');
    }
}
```

---

## FIX R3 — Use `PASSWORD_MIN_LENGTH` constant in `UserController`
**File:** `medical/app/Controllers/UserController.php`

In the `store()` method, find:
```php
$PASSWORD_MIN_LENGTH = 8;
if (strlen($password) < $PASSWORD_MIN_LENGTH) {
```

Replace with (removes the local variable, uses the global constant):
```php
if (strlen($password) < PASSWORD_MIN_LENGTH) {
```

Update the error message strings in the same block to use `PASSWORD_MIN_LENGTH` instead of `$PASSWORD_MIN_LENGTH`.

If the same pattern exists in `update()`, apply the same change there too.

---

## FIX R4 — Add null-coalesce guards to navbar user pill
**File:** `medical/app/Views/layouts/main.php`

Find:
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
```

Replace with:
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'] ?? 'staff')); ?>)</small>
```

---

## FIX R5 — Guard `init.php` against double-start and missing Composer
**File:** `medical/init.php`

Replace the entire file contents with:
```php
<?php
/**
 * init.php — legacy bootstrap shim.
 * The real entry point is public/index.php.
 * This file is kept only for compatibility and does nothing harmful.
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## FIX R6 — Complete the global error handler in `index.php`
**File:** `medical/public/index.php`

Find the current `ENH-1` block (the `register_shutdown_function` wrapped in `if (defined('LOG_PATH'))`). Replace it entirely with:

```php
// ENH-1: Global error and exception handlers — show 500 page, log to error_log
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log(sprintf('[SafeSense Error %d] %s in %s:%d', $severity, $message, $file, $line));
    if (defined('APP_DEBUG') && APP_DEBUG) return false; // show native errors in dev
    if (!headers_sent()) {
        http_response_code(500);
        $errorFile = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($errorFile)) include $errorFile;
    }
    exit(1);
});

set_exception_handler(function (Throwable $e) {
    error_log(sprintf('[SafeSense Exception] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        $errorFile = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($errorFile)) include $errorFile;
    }
    exit(1);
});
```

---

## ✅ Verification Commands

Run these after all fixes are applied:

```bash
# R1 — logout must NOT have session_regenerate_id after session_destroy
grep -A5 "session_destroy" medical/app/Controllers/AuthController.php
# Expected: session_start() appears, but session_regenerate_id() does NOT

# R2 — attempt counter must NOT be on the line immediately after validateCsrf
grep -n "SESSION\[.key.\]" medical/app/Controllers/AuthController.php
# Expected: first_attempt and count keys used, not a plain integer increment

# R3 — no local $PASSWORD_MIN_LENGTH variable in UserController
grep "PASSWORD_MIN_LENGTH" medical/app/Controllers/UserController.php
# Expected: all references use the bare constant PASSWORD_MIN_LENGTH (no $ prefix)

# R4 — navbar name/role must have null-coalesce
grep "user\]\['name'\]" medical/app/Views/layouts/main.php
# Expected: ?? 'User' present on same line

# R5 — init.php must have session_status guard
grep "session_status" medical/init.php
# Expected: session_status() === PHP_SESSION_NONE check present

# R6 — full error handler must exist
grep "set_exception_handler\|set_error_handler" medical/public/index.php
# Expected: both functions present
```

---

*SafeSense Post-Fix Audit v3 · May 2026*
