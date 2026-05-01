# SafeSense — Presentation Readiness Prompt
## 7 Fixes: Critical Demo Issues, Arduino Connection, Polish

---

## CONTEXT & SCOPE

You are making **7 targeted fixes** to the SafeSense Hospital Management System to prepare it for a live presentation. The system is functionally complete. These changes address one showstopping demo bug, two presentation-critical issues, and four polish/robustness improvements.

**Files to touch: 6**
**Do not modify** any file not listed below.

Read all 7 fixes completely before writing a single line. Apply them in order.

---

## FIX 1 — Auto-open modal for CRITICAL and DANGER alerts on poll *(Showstopper)*

**File:** `medical/app/Views/layouts/main.php`

**Problem:** The entire dramatic effect of the system — a full-screen alert modal erupting on screen the moment the Arduino detects a flood — does not happen. The poll loop calls `addDrawerItem()` and `showToast()` but the modal only opens when a user manually clicks a notification. A 5-second poll delay followed by a tiny corner toast is all that happens during a live demo. The audio alarm is also silenced because it only fires inside `openModal()`.

**Find this exact block** (lines 353–365):
```javascript
  function poll(){
    fetch(window.BASE_URL + '/api/alerts/poll?since='+encodeURIComponent(lastPoll))
    .then(r=>r.json())
    .then(data=>{
      lastPoll=data.server_time||lastPoll;
      setBadge(data.unread_count||0);
      (data.alerts||[]).forEach(a=>{
        addDrawerItem(a);
        showToast(a);
        // Note: Modal only opens when user clicks a notification, not automatically
      });
    }).catch(e=>{ console.error('Poll error:', e); });
  }
```

**Replace with:**
```javascript
  function poll(){
    fetch(window.BASE_URL + '/api/alerts/poll?since='+encodeURIComponent(lastPoll))
    .then(r=>r.json())
    .then(data=>{
      lastPoll=data.server_time||lastPoll;
      setBadge(data.unread_count||0);
      (data.alerts||[]).forEach(a=>{
        addDrawerItem(a);
        showToast(a);
        // Auto-open modal for high-severity alerts so staff see it immediately
        if (a.alert_level === 'critical' || a.alert_level === 'danger') {
          showModal(a);
        }
      });
    }).catch(e=>{ console.error('Poll error:', e); });
  }
```

**What changed:** Three lines added inside the `forEach`. For `critical` and `danger` alerts, `showModal(a)` is called automatically. The existing `modalQueue` system already handles multiple simultaneous alerts gracefully — if a modal is already open, the next one queues and appears when the first is closed. WARNING alerts still show only as toasts, which is the correct behaviour.

---

## FIX 2 — Add role guard to Patients and Doctors nav links *(Demo quality)*

**File:** `medical/app/Views/layouts/main.php`

**Problem:** The navbar shows Patients and Doctors links to all logged-in users. Since `PatientController::index()` and `DoctorController::index()` now require `admin/doctor/nurse` role, a `staff` user sees both links in the nav but gets a 403 error when they click them. During a presentation this looks like a broken system.

**Find this exact block** (lines 51–61):
```php
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='patients'?'active':''; ?>" href="<?php echo url('/patients'); ?>">
            <i class="fas fa-user-injured me-1"></i>Patients
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='doctors'?'active':''; ?>" href="<?php echo url('/doctors'); ?>">
            <i class="fas fa-user-md me-1"></i>Doctors
          </a>
        </li>
```

**Replace with:**
```php
        <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='patients'?'active':''; ?>" href="<?php echo url('/patients'); ?>">
            <i class="fas fa-user-injured me-1"></i>Patients
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='doctors'?'active':''; ?>" href="<?php echo url('/doctors'); ?>">
            <i class="fas fa-user-md me-1"></i>Doctors
          </a>
        </li>
        <?php endif; ?>
```

**What changed:** Both `<li>` elements are now wrapped in a PHP role check matching the same roles used in the controllers. A `staff` user's navbar will no longer show links to pages they cannot access.

---

## FIX 3 — Add simulate alert endpoint for hardware-free demo *(Demo quality)*

**Problem:** There is no way to trigger a test alert from the browser if the Arduino is not physically present. A hardware failure on demo day would mean the audience never sees the most important feature of the system. A simulate endpoint allows an admin to fire a realistic CRITICAL flood alert on demand, which the poll loop picks up within 5 seconds exactly as it would from real hardware.

### Part A — Register the route in `medical/app/Core/App.php`

**Find this block** (line 61 — the IoT Routes section):
```php
        // SafeSense IoT Routes
        $this->router->post('/api/alert', 'AlertController@receive');
        $this->router->get('/alerts', 'AlertController@index');
        $this->router->get('/api/alerts/poll', 'AlertController@poll');
        $this->router->post('/api/alerts/read', 'AlertController@markRead');
        $this->router->post('/api/alerts/dismiss', 'AlertController@dismiss');
```

**Replace with:**
```php
        // SafeSense IoT Routes
        $this->router->post('/api/alert', 'AlertController@receive');
        $this->router->get('/alerts', 'AlertController@index');
        $this->router->get('/api/alerts/poll', 'AlertController@poll');
        $this->router->post('/api/alerts/read', 'AlertController@markRead');
        $this->router->post('/api/alerts/dismiss', 'AlertController@dismiss');
        $this->router->post('/api/alert/simulate', 'AlertController@simulate');
```

### Part B — Add the `simulate()` method to `medical/app/Controllers/AlertController.php`

**Find the line that reads** `protected function sanitize($val) {` (near the bottom of the class, before the HELPERS section). Insert the following complete method **immediately before** `protected function sanitize($val)`:

```php
    // ---------------------------------------------------------------
    // SIMULATE ALERT  —  POST /api/alert/simulate  (admin only)
    // ---------------------------------------------------------------

    /**
     * Admin-only endpoint to inject a realistic test alert without hardware.
     * Useful for demonstrations when the Arduino is not physically connected.
     * The poll loop will pick it up within 5 seconds exactly like a real alert.
     *
     * POST body (form or JSON):
     *   level  = warning | danger | critical   (default: critical)
     *   event  = flood | rain | accident       (default: flood)
     */
    public function simulate() {
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $level = $_POST['level'] ?? 'critical';
        $event = $_POST['event'] ?? 'flood';

        $validLevels = ['warning', 'danger', 'critical'];
        $validEvents = ['rain', 'flood', 'accident', 'vibration', 'test'];
        if (!in_array($level, $validLevels)) $level = 'critical';
        if (!in_array($event, $validEvents)) $event = 'flood';

        $messages = [
            'critical' => 'CRITICAL: Severe flood detected. Water level at 52.4 cm — DANGER threshold exceeded. Immediate response required!',
            'danger'   => 'DANGER: Rising floodwater detected. Water level: 38.1 cm. Road hazard likely. Staff on alert.',
            'warning'  => 'WARNING: Rain detected (moderate). Water level: 18.5 cm. Monitoring conditions.',
        ];

        $waterLevels = ['critical' => 52.4, 'danger' => 38.1, 'warning' => 18.5];

        $database = new Database();
        $db       = $database->getConnection();
        $alert    = new Alert($db);

        $alert->device_id     = 'SAFESENSE-001';
        $alert->station_type  = 'hospital';
        $alert->alert_level   = $level;
        $alert->event_type    = $event;
        $alert->rain_status   = ($level === 'critical') ? 'heavy' : ($level === 'danger' ? 'moderate' : 'light');
        $alert->water_level   = $waterLevels[$level];
        $alert->vibration     = ($event === 'accident') ? 1 : 0;
        $alert->message       = $messages[$level];
        $alert->latitude      = 8.1574;
        $alert->longitude     = 124.9282;
        $alert->location_name = 'Brgy. Casisang, Malaybalay City';

        if ($alert->create()) {
            $this->jsonResponse([
                'success' => true,
                'message' => "Simulated {$level} alert injected. Dashboard will update within 5 seconds.",
                'level'   => $level,
            ], 201);
        } else {
            $this->jsonResponse(['success' => false, 'error' => 'Database write failed.'], 500);
        }
    }
```

### Part C — Add the simulate button to the Alerts page UI in `medical/app/Views/alerts/index.php`

**Find this exact block** (the page header action buttons, first few lines of the file):
```php
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-danger px-3 py-2 fs-6" id="unreadBadge"
          style="border-radius:99px; font-weight:700;">
      <?php echo $unreadCount; ?> Unread
    </span>
    <button class="btn btn-outline-secondary btn-sm" id="markAllReadBtn">
      <i class="fas fa-check-double"></i>Mark All Read
    </button>
  </div>
```

**Replace with:**
```php
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="badge bg-danger px-3 py-2 fs-6" id="unreadBadge"
          style="border-radius:99px; font-weight:700;">
      <?php echo $unreadCount; ?> Unread
    </span>
    <button class="btn btn-outline-secondary btn-sm" id="markAllReadBtn">
      <i class="fas fa-check-double"></i>Mark All Read
    </button>
    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
    <div class="dropdown">
      <button class="btn btn-outline-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Simulate Arduino alert for demo">
        <i class="fas fa-flask"></i> Simulate Alert
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">Inject Test Alert</h6></li>
        <li><button class="dropdown-item simulate-btn" data-level="critical" data-event="flood">
          <i class="fas fa-skull-crossbones text-danger me-2"></i>Critical — Flood
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="danger" data-event="flood">
          <i class="fas fa-exclamation-triangle text-warning me-2"></i>Danger — Flood
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="danger" data-event="accident">
          <i class="fas fa-car-crash text-warning me-2"></i>Danger — Accident
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="warning" data-event="rain">
          <i class="fas fa-cloud-rain text-info me-2"></i>Warning — Rain
        </button></li>
        <li><hr class="dropdown-divider"></li>
        <li><small class="dropdown-item text-muted" style="font-size:.72rem;">
          <i class="fas fa-info-circle me-1"></i>Dashboard updates within 5 seconds
        </small></li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
```

**Also add this JavaScript** inside the existing `<script>` block at the bottom of `alerts/index.php`, immediately before the closing `</script>` tag:

```javascript
// Simulate alert buttons (admin only)
document.querySelectorAll('.simulate-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const level = this.dataset.level;
    const event = this.dataset.event;
    const label = level.toUpperCase() + ' — ' + event;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    ajaxPost(window.BASE_URL + '/api/alert/simulate', { level, event })
      .then(d => {
        if (d.success) {
          // Show brief success state then reload to show new alert
          this.innerHTML = '<i class="fas fa-check me-1"></i>Sent!';
          this.classList.replace('btn-outline-danger', 'btn-outline-success');
          setTimeout(() => window.location.reload(), 1800);
        } else {
          alert('Simulation failed: ' + (d.error || 'Unknown error'));
          this.disabled = false;
          this.innerHTML = '<i class="fas fa-flask me-1"></i>' + label;
        }
      })
      .catch(() => {
        alert('Network error. Check server connection.');
        this.disabled = false;
      });
  });
});
```

---

## FIX 4 — Fix README port mismatch with actual Arduino sketch *(Correctness)*

**File:** `medical/README.md`

**Problem:** The README tells developers to run `php -S localhost:8000` and shows the Arduino endpoint as `http://192.168.1.100:8000/api/alert`. The actual sketch uses no port (targets port 80 — standard XAMPP/Apache). Anyone following the README to set up a presentation environment will have a mismatched configuration.

Make these four exact replacements:

**Line 69 — change:**
```
POST http://YOUR_SERVER_IP:8000/api/alert
```
**To:**
```
POST http://YOUR_SERVER_IP/SafeSense/medical/public/api/alert
```

**Line 205 — change:**
```
php -S localhost:8000
```
**To:**
```
# Use XAMPP (Apache on port 80) — start Apache and MySQL from the XAMPP Control Panel
# Then open http://localhost/SafeSense/medical/public in your browser
```

**Line 208 — change:**
```
Then open `http://localhost:8000` in your browser.
```
**To:**
```
Then open `http://localhost/SafeSense/medical/public` in your browser.
```

**Line 224 — change:**
```
   const char* SERVER_HOST   = "http://192.168.1.100:8000"; // your PC's IP
```
**To:**
```
   const char* SERVER_HOST   = "http://192.168.1.100"; // your PC's LAN IP (XAMPP on port 80)
```

---

## FIX 5 — Add alert_level whitelist validation in AlertController *(Robustness)*

**File:** `medical/app/Controllers/AlertController.php`

**Problem:** `alert_level` is stored in a database `ENUM('warning','danger','critical')` column, but the controller never validates the incoming value against this list. An invalid value (e.g. `"EXTREME"`) causes MySQL to silently store an empty string, which breaks the UI — the alert shows no badge colour and no icon.

**Find this exact block** (lines 59–68 in `receive()`):
```php
        // --- Validate required fields ---
        $required = ['alert_level', 'event_type', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->jsonResponse(['success' => false, 'error' => "Missing field: $field"], 400);
                return;
            }
        }
```

**Replace with:**
```php
        // --- Validate required fields ---
        $required = ['alert_level', 'event_type', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->jsonResponse(['success' => false, 'error' => "Missing field: $field"], 400);
                return;
            }
        }

        // --- Whitelist enum values to match DB constraints ---
        $validLevels = ['warning', 'danger', 'critical'];
        $validEvents = ['rain', 'flood', 'accident', 'vibration', 'test'];
        if (!in_array($data['alert_level'], $validLevels, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid alert_level. Must be: warning, danger, or critical'], 400);
            return;
        }
        if (!in_array($data['event_type'], $validEvents, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid event_type. Must be: rain, flood, accident, vibration, or test'], 400);
            return;
        }
```

---

## FIX 6 — Promote sensor height to a named constant in Arduino sketch *(Presentation clarity)*

**File:** `arduino/SafeSense_IoT.ino`

**Problem:** The sensor mounting height (`100.0 cm`) is embedded as a magic number inside `measureWaterLevel()`. During a presentation, someone will ask "how does the Arduino know the water level?" — the answer requires explaining this constant, but it's invisible at the top of the sketch where all other configuration lives.

**Find this exact block** (in the THRESHOLDS section, around line 79):
```cpp
// ══════════════════════════════════════════════════════════════
//  THRESHOLDS (cm from sensor to water surface)
//  Adjust based on your sensor mounting height
// ══════════════════════════════════════════════════════════════

const float WATER_WARNING  = 20.0;  // cm — Yellow LED on
const float WATER_DANGER   = 35.0;  // cm — Red LED slow blink
const float WATER_CRITICAL = 50.0;  // cm — Red LED fast blink + alert
```

**Replace with:**
```cpp
// ══════════════════════════════════════════════════════════════
//  SENSOR MOUNTING & THRESHOLDS
//  Adjust SENSOR_HEIGHT_CM to match your physical installation.
//  water_level = SENSOR_HEIGHT_CM - (ultrasonic distance reading)
// ══════════════════════════════════════════════════════════════

const float SENSOR_HEIGHT_CM = 100.0; // Height of ultrasonic sensor above ground (cm)
                                       // Measure from sensor face to dry ground surface

const float WATER_WARNING  = 20.0;  // cm — Yellow LED on
const float WATER_DANGER   = 35.0;  // cm — Red LED slow blink
const float WATER_CRITICAL = 50.0;  // cm — Red LED fast blink + alert
```

**Then find this line inside `measureWaterLevel()`** (line 235):
```cpp
  float waterLevel = 100.0 - distanceCm;
```
**Replace with:**
```cpp
  float waterLevel = SENSOR_HEIGHT_CM - distanceCm;
```

---

## FIX 7 — Add visual WiFi-failure indicator to Arduino sketch *(Demo robustness)*

**File:** `arduino/SafeSense_IoT.ino`

**Problem:** When all retry attempts are exhausted and the alert fails to send, the Arduino only prints to Serial Monitor. There is no visual indicator on the device itself. If the WiFi drops during a presentation, staff at the device location have no way to know the transmission failed.

**Find this exact block** at the end of `sendWithRetry()` (lines 349–352):
```cpp
  Serial.println("[Alert] All retry attempts exhausted. Alert may not have been received.");
  return false;
}
```

**Replace with:**
```cpp
  Serial.println("[Alert] All retry attempts exhausted. Alert may not have been received.");
  // Visual indicator: alternate-blink both LEDs 5 times to signal failed transmission
  for (int i = 0; i < 5; i++) {
    digitalWrite(PIN_LED_RED,    HIGH);
    digitalWrite(PIN_LED_YELLOW, LOW);
    delay(200);
    digitalWrite(PIN_LED_RED,    LOW);
    digitalWrite(PIN_LED_YELLOW, HIGH);
    delay(200);
  }
  digitalWrite(PIN_LED_RED,    LOW);
  digitalWrite(PIN_LED_YELLOW, LOW);
  return false;
}
```

---

## VERIFICATION CHECKLIST

Run every check below in order. If any item fails, fix and re-run from the top. Do not commit until all pass.

### Step 1 — File scope
```
[ ] git diff --name-only shows exactly these files (no others):
      medical/app/Views/layouts/main.php
      medical/app/Core/App.php
      medical/app/Controllers/AlertController.php
      medical/app/Views/alerts/index.php
      medical/README.md
      arduino/SafeSense_IoT.ino
```

### Step 2 — Fix 1: Modal auto-open
```
[ ] Open main.php and find the poll() function
[ ] Inside the forEach loop, AFTER showToast(a); these lines exist:
      if (a.alert_level === 'critical' || a.alert_level === 'danger') {
        showModal(a);
      }
[ ] The old comment "Modal only opens when user clicks" is REMOVED
[ ] showModal and showToast are called in this order: addDrawerItem → showToast → showModal
[ ] No other code in poll() was changed
```

### Step 3 — Fix 2: Nav role guard
```
[ ] Open main.php and find the Patients nav link
[ ] Both the Patients <li> and Doctors <li> are wrapped inside:
      <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
      ...
      <?php endif; ?>
[ ] The Appointments, Medical Records, Billing, Users nav links are UNCHANGED
[ ] The role check uses the array ['admin', 'doctor', 'nurse'] — matching the controllers exactly
```

### Step 4 — Fix 3: Simulate endpoint
```
[ ] Open App.php and confirm this line exists in the IoT Routes section:
      $this->router->post('/api/alert/simulate', 'AlertController@simulate');

[ ] Open AlertController.php and confirm simulate() method exists
[ ] simulate() first calls: requireLogin(), requireRole('admin'), validateCsrf()
[ ] simulate() uses in_array() to whitelist level and event before inserting
[ ] simulate() calls $alert->create() and returns JSON with success:true + 201 status
[ ] simulate() does NOT accept api_key (it uses session auth, not IoT auth)

[ ] Open alerts/index.php
[ ] The header buttons area contains a "Simulate Alert" dropdown button
[ ] The dropdown is wrapped in: <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
[ ] The dropdown has 4 options: Critical/Flood, Danger/Flood, Danger/Accident, Warning/Rain
[ ] The simulate JS handler calls ajaxPost() (which sends CSRF token via X-CSRF-Token header)
[ ] On success the page reloads after 1800ms
```

### Step 5 — Fix 4: README port
```
[ ] Open README.md
[ ] Line formerly reading ":8000" in the POST example now shows the full XAMPP path
[ ] The php -S localhost:8000 instruction is replaced with XAMPP Control Panel guidance
[ ] The Arduino code example in README shows SERVER_HOST without :8000
[ ] No other content in README was changed
```

### Step 6 — Fix 5: Alert whitelist
```
[ ] Open AlertController.php receive()
[ ] After the required-fields forEach loop, these checks exist:
      $validLevels = ['warning', 'danger', 'critical'];
      $validEvents = ['rain', 'flood', 'accident', 'vibration', 'test'];
      if (!in_array($data['alert_level'], $validLevels, true)) { ... return; }
      if (!in_array($data['event_type'],  $validEvents,  true)) { ... return; }
[ ] Both use strict comparison (third argument true)
[ ] Both return a 400 response with a descriptive error message
[ ] The rest of receive() is unchanged
```

### Step 7 — Fix 6: Arduino sensor constant
```
[ ] Open SafeSense_IoT.ino
[ ] In the THRESHOLDS section, SENSOR_HEIGHT_CM = 100.0 is declared as a const float
[ ] A comment explains: water_level = SENSOR_HEIGHT_CM - (ultrasonic distance reading)
[ ] Inside measureWaterLevel(), the calculation reads:
      float waterLevel = SENSOR_HEIGHT_CM - distanceCm;
[ ] The literal "100.0" no longer appears in measureWaterLevel()
[ ] WATER_WARNING, WATER_DANGER, WATER_CRITICAL constants are unchanged
```

### Step 8 — Fix 7: Arduino failure blink
```
[ ] Open SafeSense_IoT.ino
[ ] At the end of sendWithRetry(), after the "exhausted" Serial.println, a for loop
    blinks PIN_LED_RED and PIN_LED_YELLOW alternately 5 times (200ms each)
[ ] Both LEDs are set LOW after the loop completes
[ ] return false; follows immediately after
[ ] No other part of sendWithRetry() was changed
```

### Step 9 — Logic & regression check
```
[ ] modal auto-opens: a WARNING alert must NOT trigger showModal() in the poll loop
    Confirm: the condition checks for 'critical' OR 'danger' only — not 'warning'

[ ] simulate() is admin-only: the route uses requireRole('admin') not requireLogin() alone

[ ] simulate() uses validateCsrf() — the JS handler uses ajaxPost() which sends X-CSRF-Token

[ ] The existing manual modal trigger (clicking a drawer item or toast) still works:
    div.addEventListener('click',...showModal(a)) and toast click → showModal(a) are UNCHANGED

[ ] The existing receive() endpoint (/api/alert) is completely unchanged — still accepts
    Arduino POST with api_key, same validation, same sanitization

[ ] All 27 previous checks from Rounds 1–6 remain green:
    - Model preloads, Router regex, Logout sequence, DB from .env
    - EMR FK, charsets, CSRF on login/logout, .env untracked
    - Edit views, 500 page, stat-icon, dismiss animation, etc.
```

### Step 10 — Demo rehearsal check
```
[ ] Log in as admin and navigate to /alerts
[ ] The "Simulate Alert" dropdown is visible in the page header
[ ] Click "Critical — Flood"
[ ] Button shows spinner, then "Sent!"
[ ] Page reloads within 2 seconds showing the new CRITICAL alert card
[ ] Open a second browser tab at /dashboard
[ ] Within 5 seconds of the simulate action, the full-screen modal appears automatically
[ ] The audio alarm plays (4-note sequence)
[ ] The modal shows: location "Brgy. Casisang, Malaybalay City", water level 52.4 cm,
    event type FLOOD, Google Maps button visible
[ ] Log in as a staff role user — Patients and Doctors links do NOT appear in the navbar
[ ] Log in as doctor/nurse — Patients and Doctors links DO appear and work correctly
```

### Step 11 — Commit
```
[ ] All steps 1–10 passed with zero failures

[ ] Commit:
    git commit -m "feat: auto modal on poll, simulate endpoint, nav role guard, whitelist validation, README port fix, Arduino polish"
```

---

## SUMMARY TABLE

| # | Fix | Files | Impact |
|---|-----|-------|--------|
| 1 | Auto-open modal for critical/danger on poll | `main.php` | **Showstopper** — the demo works |
| 2 | Hide Patients/Doctors nav from staff role | `main.php` | No broken 403 links during demo |
| 3 | Simulate alert endpoint + UI button | `App.php`, `AlertController.php`, `alerts/index.php` | Demo without hardware |
| 4 | Fix README port mismatch | `README.md` | Correct setup instructions |
| 5 | Whitelist alert_level and event_type | `AlertController.php` | Prevents silent DB corruption |
| 6 | SENSOR_HEIGHT_CM named constant | `SafeSense_IoT.ino` | Self-documenting, explainable |
| 7 | Alternating LED blink on WiFi failure | `SafeSense_IoT.ino` | Visual failsafe on device |

**6 files. SafeSense is now fully presentation-ready.**
