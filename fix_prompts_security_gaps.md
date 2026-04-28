

## Fix 1 — Add `requireLogin()` to BaseController

### Context
`BaseController` is the parent class for all controllers. It already has
`jsonResponse()`, `isAjax()`, `validateCsrf()`, and `redirect()` methods.
A `requireLogin()` guard method is missing. Without it, protected routes
have no way to enforce authentication.

**File to modify:** `medical/app/Controllers/BaseController.php`

### Task
Add the following method to `BaseController`, placed directly after the
existing `validateCsrf()` method:

```php
protected function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        if ($this->isAjax()) {
            $this->jsonResponse(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
        }
        $_SESSION['flash_error'] = 'Please log in to access that page.';
        $this->redirect('/login');
        exit;
    }
}
```

### Verification
1. Read back `BaseController.php` — confirm `requireLogin()` is present
   immediately after `validateCsrf()`.
2. Confirm it checks `$_SESSION['user']` (not `user_id` — the session key
   set in `AuthController::authenticate()` is `user`, not `user_id`).
3. Confirm the AJAX branch returns JSON 401 before redirecting.
4. Confirm `exit` is called after `$this->redirect()` to prevent execution
   continuing past the guard.
5. Do not modify any other method in `BaseController`.

---

## Fix 2 — Call `requireLogin()` in Patient, Doctor, and Appointment Controllers

### Context
`PatientController`, `DoctorController`, and `AppointmentController` all
expose their routes without any session check. Any user who knows the URL
can access `/patients`, `/doctors`, or `/appointments` without logging in.

`requireLogin()` now exists in `BaseController` (added in Fix 1). It must
be called as the **first line** of every public action method in all three
controllers.

**Files to modify:**
- `medical/app/Controllers/PatientController.php`
- `medical/app/Controllers/DoctorController.php`
- `medical/app/Controllers/AppointmentController.php`

### Task

In **each** of the following methods across all three controllers, add
`$this->requireLogin();` as the very first line of the method body,
before any other logic:

| Controller | Methods to guard |
|---|---|
| `PatientController` | `index()`, `store()`, `edit()`, `update()`, `delete()` |
| `DoctorController` | `index()`, `store()`, `edit()`, `update()`, `delete()` |
| `AppointmentController` | `index()`, `store()`, `edit()`, `update()`, `delete()` |

Example — `PatientController::index()` before and after:

```php
// BEFORE
public function index() {
    $stmt = $this->patientModel->getAll();
    ...
}

// AFTER
public function index() {
    $this->requireLogin();
    $stmt = $this->patientModel->getAll();
    ...
}
```

The `store()` methods already call `$this->validateCsrf()`. Place
`requireLogin()` **before** `validateCsrf()` — authentication must be
checked before CSRF is validated.

```php
// AFTER (store example)
public function store() {
    if ($this->isPostRequest()) {
        $this->requireLogin();     // ← authentication first
        $this->validateCsrf();     // ← then CSRF
        ...
    }
}
```

### Do Not Touch
- `AlertController` — the `/api/alert` endpoint is for the Arduino device
  and must remain public (it uses API key auth instead of session auth).
- `AuthController` — login, authenticate, logout must remain public.
- `DashboardController` — it already has its own session check inside
  `AuthController::dashboard()`.

### Verification
1. Read back each of the three controller files.
2. Confirm `$this->requireLogin();` is the first statement inside each of
   the 15 method bodies listed in the table above.
3. Confirm `requireLogin()` appears **before** `validateCsrf()` in all
   `store()`, `update()`, and `delete()` methods.
4. Grep check — confirm the call count per file:
   ```
   grep -c "requireLogin" medical/app/Controllers/PatientController.php
   # Expected: 5
   grep -c "requireLogin" medical/app/Controllers/DoctorController.php
   # Expected: 5
   grep -c "requireLogin" medical/app/Controllers/AppointmentController.php
   # Expected: 5
   ```
5. Confirm `AlertController.php` and `AuthController.php` have zero
   `requireLogin` calls.

---

## Fix 3 — Fix Double `session_start()` in AuthController

### Context
`init.php` calls `session_start()` at bootstrap — this is the single,
correct place for it. However, `AuthController::authenticate()` calls
`session_start()` again unconditionally on line 60 inside the login
success block. Depending on PHP configuration and server setup, calling
`session_start()` when a session is already active can generate
`headers already sent` warnings or silently corrupt session state.

`AlertController` already uses the correct safe pattern:
```php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
```
`AuthController` must be updated to match.

**File to modify:** `medical/app/Controllers/AuthController.php`

### Task
Find the unconditional `session_start()` call inside `authenticate()`.
It will look like this (around line 60, inside the successful login block):

```php
session_start();
```

Replace it with the safe conditional pattern:

```php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
```

Do the same for any other unconditional `session_start()` calls found
anywhere in `AuthController.php` (check `logout()` as well).

### Verification
1. Read back `AuthController.php`.
2. Grep check — confirm zero unconditional `session_start()` remain:
   ```
   grep -n "session_start()" medical/app/Controllers/AuthController.php
   ```
   Expected result: **no output** (zero matches).
3. Confirm the replacement is:
   ```
   grep -n "session_status" medical/app/Controllers/AuthController.php
   ```
   Expected: at least one match per location that was fixed.
4. Do not change any logic around the session — only replace the bare
   `session_start()` call with the conditional version. Everything else
   in `authenticate()` and `logout()` must remain identical.
5. Confirm `init.php` still has its `session_start()` — do not touch it.

---

## Final Verification (Run After All Three Fixes)

```
# 1. requireLogin exists in BaseController
grep -c "requireLogin" medical/app/Controllers/BaseController.php
# Expected: 1

# 2. All three controllers guarded — 5 calls each
grep -c "requireLogin" medical/app/Controllers/PatientController.php
grep -c "requireLogin" medical/app/Controllers/DoctorController.php
grep -c "requireLogin" medical/app/Controllers/AppointmentController.php
# Expected: 5, 5, 5

# 3. AlertController and AuthController untouched
grep -c "requireLogin" medical/app/Controllers/AlertController.php
grep -c "requireLogin" medical/app/Controllers/AuthController.php
# Expected: 0, 0

# 4. No bare session_start() in AuthController
grep "session_start()" medical/app/Controllers/AuthController.php
# Expected: no output

# 5. init.php session_start untouched
grep "session_start" medical/init.php
# Expected: 1 match
```

## Do Not Touch
- `AlertController.php` — Arduino API endpoint, uses API key auth
- `AuthController.php` login/logout logic — only fix `session_start()`
- `init.php` — the canonical `session_start()` location, leave it as-is
- `BaseController.php` existing methods — only add `requireLogin()`
- Any view files — no changes needed in views
