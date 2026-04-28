# CLAUDE.md — SafeSense HMS

## Project Overview
PHP MVC hospital management system with Arduino IoT alert integration.
Stack: PHP 8+, MySQL, Bootstrap 5, jQuery, DataTables, SweetAlert2, Chart.js.
Entry: `medical/public/index.php` → `App.php` router → Controllers → Views.

## Architecture Rules
- AJAX responses: `$this->jsonResponse()` from `BaseController`
- AJAX detection: `$this->isAjax()` checks `HTTP_X_REQUESTED_WITH`
- All `fetch()` calls use `ajaxPost(url, data)` helper in `app.js` — never raw fetch
- Flash messages: `$_SESSION['flash_success']` / `$_SESSION['flash_error']` — never URL params
- IoT JS stays **inline in `main.php`** — never move to `app.js`
- New routes go in `App.php::initRoutes()`

## File Map
```
medical/app/Config/config.php          ← constants, SAFESENSE_API_KEY
medical/app/Controllers/
  BaseController.php                   ← jsonResponse(), isAjax(), redirect(), render()
  AuthController.php                   ← login, logout, session flash
  PatientController.php                ← CRUD + isAjax() branching
  DoctorController.php                 ← CRUD + isAjax() branching
  AppointmentController.php            ← CRUD + allPatients/allDoctors to view
  AlertController.php                  ← POST /api/alert, poll, markRead, dismiss
  DashboardController.php              ← GET /api/dashboard/stats
medical/app/Models/
  Patient.php / Doctor.php             ← PDO prepared statements, XSS sanitize
  Appointment.php                      ← getAppointmentsByWeek()
  Alert.php                            ← getAlertsByDay(), getSince(), countUnread()
medical/app/Views/layouts/main.php     ← IoT JS inline, session flash → SweetAlert2
medical/app/Views/dashboard.php        ← Chart.js canvases
medical/public/js/app.js               ← ajaxPost(), modal state, DataTables API
medical/public/css/style.css           ← WCAG 2.1 AA, 44px targets, prefers-reduced-motion
```

---

## Continuous Verification Loop Protocol

**After implementing EVERY task — and after ALL tasks are done — run this full loop.
Repeat until ZERO issues are found across a complete pass.**

### Loop Step 1 — Read Back Every Changed File
Re-read each file modified. Confirm the change is present, correctly placed,
and has not introduced syntax errors, mismatched braces, or broken indentation.

### Loop Step 2 — Cross-Check Architecture Rules
For every changed file verify:
- [ ] No raw `fetch()` outside the `ajax()` / `ajaxPost()` helper in `app.js`
- [ ] No `?success=` or `?error=` query params in any redirect or URL
- [ ] No IoT polling/drawer/toast JS moved or duplicated outside `main.php`
- [ ] Every new POST controller method calls `$this->validateCsrf()` before any logic
- [ ] Every new model method uses PDO prepared statements — no string interpolation in SQL
- [ ] Every view output wrapped in `htmlspecialchars()` or passed through model sanitizer

### Loop Step 3 — Regression Check on Critical Paths
After each task, explicitly verify these were NOT broken:
- [ ] `POST /api/alert` accepts Arduino JSON payload and returns 201
- [ ] `/api/alerts/poll` returns JSON array (not HTML)
- [ ] IoT drawer, toast, and critical modal JS intact in `main.php`
- [ ] DataTables `row.add().draw()`, `row().data().draw()`, `row().remove().draw()` in `app.js`
- [ ] Session flash consumed and unset in `main.php` on every page load
- [ ] Login/logout redirect with SweetAlert2 flash — no Bootstrap alert remnants

### Loop Step 4 — Logic Validation Per Task
Re-read the task spec and trace the implementation:
- Does the code path match the described behavior exactly?
- Are all edge cases handled (empty input, null values, missing optional params)?
- Does the AJAX error path keep the modal open and surface error via SweetAlert2?
- Does the non-AJAX fallback set a session flash and redirect correctly?

### Loop Step 5 — Security Scan
On every pass, grep the changed files:
- [ ] No `$_GET`, `$_POST`, `$_REQUEST` used directly in SQL — must go through model
- [ ] `SAFESENSE_API_KEY` does not appear hardcoded as a string after Task 1
- [ ] CSRF token validated before any write operation after Task 2
- [ ] No new routes added without auth guard (`AuthController::requireLogin()`)

### Loop Completion Condition
The loop ends only when a full pass through Steps 1–5 finds **zero issues**.
If any issue is found: fix it, then **restart the loop from Step 1**.
Do not consider any task complete until it survives a clean full-loop pass.

---

## Tasks

### Task 1 — Move API Key to .env  [SECURITY · HIGH]
**Files:** `config.php`, create `medical/.env`, `medical/.gitignore`
```php
// medical/.env
SAFESENSE_API_KEY=replace_with_bin2hex_random_bytes_32
// config.php — replace hardcoded define:
define('SAFESENSE_API_KEY', $_ENV['SAFESENSE_API_KEY'] ?? '');
```
Confirm `phpdotenv` (already in vendor) is loaded in `init.php`. Add `.env` to `.gitignore`.
Mirror the key in `arduino/SafeSense_IoT.ino` → `API_KEY` constant.

### Task 2 — CSRF Token Protection  [SECURITY · HIGH]
**Files:** `BaseController.php`, `main.php`, `app.js`
Add `generateCsrfToken()` and `validateCsrf()` to `BaseController`. Embed token in
`<meta name="csrf-token">` in `main.php`. Call `validateCsrf()` at top of every
`store()`, `update()`, `delete()` in all three CRUD controllers.
In `app.js` `ajax()` helper add:
`'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? ''`

### Task 3 — Duplicate Email Graceful Error  [SECURITY · HIGH]
**Files:** `PatientController.php`, `DoctorController.php`
Wrap `$model->create()` and `$model->update()` in try/catch PDOException.
On error code `1062`: AJAX → JSON 422 `"A record with this email already exists."` | Non-AJAX → session flash + redirect.

### Task 4 — Appointment Conflict Detection  [FUNCTIONAL · MEDIUM]
**Files:** `Appointment.php`, `AppointmentController.php`
Add `hasConflict(?int $excludeId = null): bool` — SELECT COUNT on same doctor_id + date + time,
excluding cancelled status and optionally the current record id. Call before `store()` and `update()`.
Return JSON 409 `"This doctor is already booked at the selected date and time."` if true.

### Task 5 — Cascade Delete Warning  [FUNCTIONAL · MEDIUM]
**Files:** `app.js` — patient delete SweetAlert2 confirmation only
```js
text: 'This will also permanently delete all appointments for this patient.',
```

### Task 6 — ORDER BY on getAll()  [FUNCTIONAL · LOW]
**Files:** `Patient.php`, `Doctor.php`
Append `ORDER BY name ASC` to the SELECT query in both `getAll()` methods.

### Task 7 — Empty States  [UI · LOW]
**Files:** `dashboard.php`, `alerts/index.php`
After Chart.js data fetch, if array empty insert `<p class="text-center text-muted">No data yet.</p>`
after each canvas. In `alerts/index.php` DataTables init add:
`language: { emptyTable: 'No alerts received yet.' }`

### Task 8 — Login Branding + Page Titles  [UI · LOW]
**Files:** `Views/auth/login.php`, `layouts/main.php`
Add `fa-shield-halved` + "SafeSense" heading with `#dc2626` accent bar above login card.
In `main.php`: `<title><?php echo htmlspecialchars($title ?? 'SafeSense'); ?> — SafeSense</title>`

### Task 9 — Arduino Retry Logic  [IOT · MEDIUM]
**Files:** `arduino/SafeSense_IoT.ino`
Wrap `sendAlert()` in `sendWithRetry(params, maxRetries=3)` — loop 3 times with `delay(2000)`
between attempts, log failure to Serial. Replace all `sendAlert()` calls in `loop()`.

---

## Do Not Touch
- IoT JS block in `main.php` (drawer, toast, polling, modal queue)
- `AlertController.php` existing methods
- Existing `Alert.php` model methods — add only, never modify
- `arduino/SafeSense_IoT.ino` sensor/threshold logic — only add retry wrapper
