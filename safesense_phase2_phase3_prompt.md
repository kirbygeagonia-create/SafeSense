# SafeSense — Phase 2 & 3 Feature Expansion
## For Windsurf / Kimi K2.5 — Read Every Section Before Writing Any Code

---

## SYSTEM CONTEXT

SafeSense is a PHP MVC hospital management system at `medical/`. All previous rounds of fixes are complete and verified. This prompt adds **2 minor bug fixes** carried over from the audit, plus **5 new features** across Phase 2 and Phase 3. No feature from a previous phase may be broken. The pattern is: `App.php` registers routes → `Controllers/` handle logic → `Models/` handle DB → `Views/` render HTML.

**Architecture rules (do not violate):**
- Every controller method starts with `$this->requireLogin()` then `$this->requireRole()`
- Every POST handler calls `$this->validateCsrf()`
- Every view output uses `htmlspecialchars()`
- New DB tables follow existing migration file pattern (numbered PHP files in `database/migrations/`)
- Models take `$db` in constructor, have public `$` properties for each column

---

## PART A — 2 REMAINING MINOR FIXES

---

### FIX A1 — home.php: hide Patients & Doctors cards from staff role

**File:** `medical/app/Views/home.php`

**Problem:** The home dashboard page shows "View Patients" and "View Doctors" card buttons to all logged-in users. Staff role users get a 403 when they click them because the controllers require `admin/doctor/nurse`. The navbar already hides these links for staff — the home page cards must match.

**Find the Patients card `<div class="col-md-4">` block** (lines 16–24):
```php
  <div class="col-md-4">
    <div class="card h-100" style="border-top: 3px solid var(--ss-primary);">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-injured me-2 text-primary"></i>Patients</h5>
        <p class="card-text text-muted small">Manage patient records, personal information, medical history, and contact details.</p>
        <a href="<?php echo url('/patients'); ?>" class="btn btn-outline-primary btn-sm">View Patients</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100" style="border-top: 3px solid #16a34a;">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-md me-2 text-success"></i>Doctors</h5>
        <p class="card-text text-muted small">Manage doctor profiles, specializations, schedules, and availability.</p>
        <a href="<?php echo url('/doctors'); ?>" class="btn btn-outline-success btn-sm">View Doctors</a>
      </div>
    </div>
  </div>
```

**Replace with:**
```php
  <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
  <div class="col-md-4">
    <div class="card h-100" style="border-top: 3px solid var(--ss-primary);">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-injured me-2 text-primary"></i>Patients</h5>
        <p class="card-text text-muted small">Manage patient records, personal information, medical history, and contact details.</p>
        <a href="<?php echo url('/patients'); ?>" class="btn btn-outline-primary btn-sm">View Patients</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100" style="border-top: 3px solid #16a34a;">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-md me-2 text-success"></i>Doctors</h5>
        <p class="card-text text-muted small">Manage doctor profiles, specializations, schedules, and availability.</p>
        <a href="<?php echo url('/doctors'); ?>" class="btn btn-outline-success btn-sm">View Doctors</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
```

---

### FIX A2 — Migration files: read DB credentials from .env

**Files:** All 7 migration files in `medical/database/migrations/`

**Problem:** Every migration file starts with `$host='localhost'; $password='';` hardcoded. When the `.env` DB credentials differ from these defaults, migrations fail silently. The fix is to read from `.env` via the already-loaded `phpdotenv` — but migrations run as standalone scripts, so they must load dotenv themselves.

**For each of the 7 migration files**, replace the opening credential block:

```php
// OLD (varies slightly per file but always hardcodes):
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
// OR:
$host = 'localhost';
$db_name = 'hospital_db';
$username = 'root';
$password = '';
```

**Replace with** (same block for all 7 files):
```php
// Load .env if running as standalone migration script
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}
$host     = $_ENV['DB_HOST']  ?? 'localhost';
$db_name  = $_ENV['DB_NAME']  ?? 'hospital_db';
$username = $_ENV['DB_USER']  ?? 'root';
$password = $_ENV['DB_PASS']  ?? '';
```

> The path `__DIR__ . '/../../.env'` points from `database/migrations/` up two levels to `medical/.env`. Apply this same block to all 7 files: `000_create_users_table.php` through `006_create_billing_table.php`.

---

## PART B — PHASE 2: Appointment Calendar, Reports, Audit Log

---

### FEATURE B1 — Appointment Calendar View (FullCalendar.js)

**New files:** `medical/app/Views/appointments/calendar.php`
**Modified files:** `medical/app/Core/App.php`, `medical/app/Controllers/AppointmentController.php`, `medical/app/Views/appointments/index.php`

#### Step B1-1: Register the JSON events endpoint in App.php

Find the appointments routes block and add one line:
```php
        $this->router->get('/appointments', 'AppointmentController@index');
        $this->router->get('/api/appointments/events', 'AppointmentController@calendarEvents'); // ADD
        $this->router->post('/appointments/store', 'AppointmentController@store');
```

#### Step B1-2: Add `calendarEvents()` to AppointmentController

Add after `index()`, before `store()`:
```php
    /**
     * GET /api/appointments/events
     * Returns all appointments as FullCalendar-compatible JSON events.
     */
    public function calendarEvents()
    {
        $this->requireLogin();
        $database = new Database();
        $db       = $database->getConnection();

        $query = "SELECT a.id, a.appointment_date, a.appointment_time,
                         a.status, a.reason,
                         p.name AS patient_name,
                         d.name AS doctor_name
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN doctors  d ON a.doctor_id  = d.id
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusColors = [
            'pending'   => '#f59e0b',
            'confirmed' => '#1d4ed8',
            'completed' => '#15803d',
            'cancelled' => '#94a3b8',
        ];

        $events = [];
        foreach ($rows as $r) {
            $start = $r['appointment_date'] . 'T' . $r['appointment_time'];
            $events[] = [
                'id'    => $r['id'],
                'title' => $r['patient_name'] . ' → Dr. ' . $r['doctor_name'],
                'start' => $start,
                'color' => $statusColors[$r['status']] ?? '#64748b',
                'extendedProps' => [
                    'status'  => $r['status'],
                    'reason'  => $r['reason'],
                    'patient' => $r['patient_name'],
                    'doctor'  => $r['doctor_name'],
                ],
            ];
        }

        $this->jsonResponse(['success' => true, 'events' => $events]);
    }
```

#### Step B1-3: Add view tabs to appointments/index.php

Find the page header `</div>` closing tag (after the Schedule button) and the `<div class="table-responsive mb-3">` line. Insert the tab toggle between them:

```php
<!-- View toggle tabs -->
<ul class="nav nav-tabs mb-3" id="appointmentViewTabs">
  <li class="nav-item">
    <button class="nav-link active" id="tableTabBtn" data-view="table">
      <i class="fas fa-table me-1"></i>Table View
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" id="calendarTabBtn" data-view="calendar">
      <i class="fas fa-calendar-alt me-1"></i>Calendar View
    </button>
  </li>
</ul>

<!-- Table view (existing table stays exactly as-is) -->
<div id="tableView">
```

Then after the closing `</table>` tag and before the existing modal, close the tableView div and add the calendar div:

```php
</div><!-- end tableView -->

<!-- Calendar view -->
<div id="calendarView" style="display:none;">
  <div id="ssCalendar" style="background:#fff; padding:1rem; border-radius:var(--r-lg); border:1px solid var(--ss-border);"></div>
</div>
```

Then at the bottom of `appointments/index.php`, inside the existing `<script>` block (before the closing `</script>`), add:

```javascript
// ── Calendar view toggle ──────────────────────────────
(function () {
  const tableView    = document.getElementById('tableView');
  const calView      = document.getElementById('calendarView');
  const tableTabBtn  = document.getElementById('tableTabBtn');
  const calTabBtn    = document.getElementById('calendarTabBtn');
  let calInitialised = false;

  function initCalendar() {
    if (calInitialised) return;
    calInitialised = true;
    const calEl = document.getElementById('ssCalendar');
    const calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
      height: 'auto',
      events: window.BASE_URL + '/api/appointments/events',
      eventClick: function (info) {
        const p = info.event.extendedProps;
        Swal.fire({
          title: info.event.title,
          html: `<div style="text-align:left;font-size:.9rem;">
            <p><strong>Status:</strong> ${p.status}</p>
            <p><strong>Reason:</strong> ${p.reason || '—'}</p>
            <p><strong>Date:</strong> ${info.event.startStr.slice(0,10)}</p>
            <p><strong>Time:</strong> ${info.event.startStr.slice(11,16)}</p>
          </div>`,
          icon: 'info',
          confirmButtonText: 'Close',
          confirmButtonColor: '#1d4ed8',
        });
      },
    });
    calendar.render();
  }

  tableTabBtn.addEventListener('click', () => {
    tableTabBtn.classList.add('active');
    calTabBtn.classList.remove('active');
    tableView.style.display = '';
    calView.style.display = 'none';
  });

  calTabBtn.addEventListener('click', () => {
    calTabBtn.classList.add('active');
    tableTabBtn.classList.remove('active');
    tableView.style.display = 'none';
    calView.style.display = '';
    initCalendar();
  });
})();
```

#### Step B1-4: Load FullCalendar in the main layout

**File:** `medical/app/Views/layouts/main.php`

Find the closing `</head>` tag and add FullCalendar CDN before it:
```html
  <!-- FullCalendar (loaded globally, used on appointments page) -->
  <link  href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
</head>
```

---

### FEATURE B2 — Reports & Analytics Page

**New files:** `medical/app/Controllers/ReportsController.php`, `medical/app/Views/reports/index.php`
**Modified files:** `medical/app/Core/App.php`, `medical/app/Views/layouts/main.php`

#### Step B2-1: Register route in App.php

Add after the dashboard stats line:
```php
        $this->router->get('/api/dashboard/stats', 'DashboardController@stats');
        $this->router->get('/reports', 'ReportsController@index'); // ADD
```

#### Step B2-2: Create ReportsController.php

**Create:** `medical/app/Controllers/ReportsController.php`

```php
<?php

class ReportsController extends BaseController
{
    public function index()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);

        $database = new Database();
        $db       = $database->getConnection();

        // 1. Patient registrations by month (last 12 months)
        $patientsByMonth = [];
        try {
            $stmt = $db->query(
                "SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
                        DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
                        COUNT(*) AS total
                 FROM patients
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month, sort_key
                 ORDER BY sort_key ASC"
            );
            $patientsByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 2. Appointments by status
        $appointmentsByStatus = [];
        try {
            $stmt = $db->query(
                "SELECT status, COUNT(*) AS total
                 FROM appointments
                 GROUP BY status
                 ORDER BY total DESC"
            );
            $appointmentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 3. Revenue by month (last 6 months)
        $revenueByMonth = [];
        try {
            $stmt = $db->query(
                "SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
                        DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
                        SUM(total_amount)                AS revenue,
                        SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) AS collected
                 FROM billing
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY month, sort_key
                 ORDER BY sort_key ASC"
            );
            $revenueByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 4. Alerts by level
        $alertsByLevel = [];
        try {
            $stmt = $db->query(
                "SELECT alert_level, COUNT(*) AS total
                 FROM safesense_alerts
                 GROUP BY alert_level
                 ORDER BY total DESC"
            );
            $alertsByLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 5. Summary totals
        $totals = [];
        try {
            $totals['patients']     = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
            $totals['doctors']      = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
            $totals['appointments'] = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
            $totals['invoices']     = $db->query("SELECT COUNT(*) FROM billing")->fetchColumn();
            $totals['revenue']      = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing")->fetchColumn();
            $totals['collected']    = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid'")->fetchColumn();
            $totals['alerts']       = $db->query("SELECT COUNT(*) FROM safesense_alerts")->fetchColumn();
        } catch (Exception $e) {}

        $this->render('reports/index', [
            'title'                => 'Reports & Analytics',
            'navPage'              => 'reports',
            'patientsByMonth'      => $patientsByMonth,
            'appointmentsByStatus' => $appointmentsByStatus,
            'revenueByMonth'       => $revenueByMonth,
            'alertsByLevel'        => $alertsByLevel,
            'totals'               => $totals,
        ]);
    }
}
```

#### Step B2-3: Create reports/index.php view

**Create:** `medical/app/Views/reports/index.php`

```php
<div class="page-header">
  <div>
    <h1><i class="fas fa-chart-bar"></i>Reports &amp; Analytics</h1>
    <div class="page-subtitle">System-wide statistics and trend analysis</div>
  </div>
  <div class="text-muted" style="font-size:.8rem;">
    <i class="fas fa-clock me-1"></i>Generated: <?php echo date('M d, Y h:i A'); ?>
  </div>
</div>

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
  <?php
  $summaryCards = [
    ['label'=>'Total Patients',     'val'=>$totals['patients']??0,                           'icon'=>'fa-user-injured',       'color'=>'#1d4ed8'],
    ['label'=>'Total Doctors',      'val'=>$totals['doctors']??0,                            'icon'=>'fa-user-md',            'color'=>'#15803d'],
    ['label'=>'Total Appointments', 'val'=>$totals['appointments']??0,                       'icon'=>'fa-calendar-check',     'color'=>'#0369a1'],
    ['label'=>'Total Invoices',     'val'=>$totals['invoices']??0,                           'icon'=>'fa-file-invoice-dollar','color'=>'#7c3aed'],
    ['label'=>'Total Revenue',      'val'=>'₱'.number_format($totals['revenue']??0,0),      'icon'=>'fa-hand-holding-usd',   'color'=>'#15803d'],
    ['label'=>'Collected',          'val'=>'₱'.number_format($totals['collected']??0,0),    'icon'=>'fa-check-circle',       'color'=>'#15803d'],
    ['label'=>'IoT Alerts',         'val'=>$totals['alerts']??0,                             'icon'=>'fa-satellite-dish',     'color'=>'#b91c1c'],
  ];
  foreach ($summaryCards as $c): ?>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div class="stat-label"><?php echo $c['label']; ?></div>
        <div class="stat-icon" style="background:<?php echo $c['color']; ?>18; color:<?php echo $c['color']; ?>;">
          <i class="fas <?php echo $c['icon']; ?>"></i>
        </div>
      </div>
      <div class="stat-value" style="font-size:1.6rem;"><?php echo $c['val']; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row 1 -->
<div class="row g-4 mb-4">
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-users me-2"></i>Patient Registrations — Last 12 Months</div>
      <div class="card-body"><canvas id="chartPatients" height="90"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-calendar-check me-2"></i>Appointments by Status</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="chartApptStatus" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Charts row 2 -->
<div class="row g-4 mb-4">
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-chart-line me-2"></i>Revenue — Last 6 Months</div>
      <div class="card-body"><canvas id="chartRevenue" height="90"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-satellite-dish me-2"></i>Alerts by Level</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="chartAlerts" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
const patientData      = <?php echo json_encode($patientsByMonth); ?>;
const apptStatusData   = <?php echo json_encode($appointmentsByStatus); ?>;
const revenueData      = <?php echo json_encode($revenueByMonth); ?>;
const alertLevelData   = <?php echo json_encode($alertsByLevel); ?>;

// Patient registrations bar chart
new Chart(document.getElementById('chartPatients'), {
  type: 'bar',
  data: {
    labels: patientData.map(r => r.month),
    datasets: [{ label: 'New Patients', data: patientData.map(r => r.total),
      backgroundColor: '#1d4ed820', borderColor: '#1d4ed8', borderWidth: 2, borderRadius: 6 }]
  },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Appointment status doughnut
const apptColors = { pending:'#f59e0b', confirmed:'#1d4ed8', completed:'#15803d', cancelled:'#94a3b8' };
new Chart(document.getElementById('chartApptStatus'), {
  type: 'doughnut',
  data: {
    labels: apptStatusData.map(r => r.status.charAt(0).toUpperCase()+r.status.slice(1)),
    datasets: [{ data: apptStatusData.map(r => r.total),
      backgroundColor: apptStatusData.map(r => apptColors[r.status] ?? '#64748b'),
      borderWidth: 2, hoverOffset: 4 }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

// Revenue line chart
new Chart(document.getElementById('chartRevenue'), {
  type: 'line',
  data: {
    labels: revenueData.map(r => r.month),
    datasets: [
      { label: 'Total Billed',  data: revenueData.map(r => r.revenue),   borderColor: '#1d4ed8', backgroundColor: '#1d4ed808', tension: .3, fill: true },
      { label: 'Collected',     data: revenueData.map(r => r.collected), borderColor: '#15803d', backgroundColor: '#15803d08', tension: .3, fill: true },
    ]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});

// Alerts by level doughnut
const alertColors = { critical:'#b91c1c', danger:'#c2410c', warning:'#b45309' };
new Chart(document.getElementById('chartAlerts'), {
  type: 'doughnut',
  data: {
    labels: alertLevelData.map(r => r.alert_level.toUpperCase()),
    datasets: [{ data: alertLevelData.map(r => r.total),
      backgroundColor: alertLevelData.map(r => alertColors[r.alert_level] ?? '#64748b'),
      borderWidth: 2, hoverOffset: 4 }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
```

#### Step B2-4: Add Reports link to the navbar

**File:** `medical/app/Views/layouts/main.php`

Find the Alerts nav link and add Reports after it:
```php
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='alerts'?'active':''; ?>" href="<?php echo url('/alerts'); ?>">
            <i class="fas fa-satellite-dish me-1"></i>Alerts
          </a>
        </li>
        <!-- ADD: -->
        <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='reports'?'active':''; ?>" href="<?php echo url('/reports'); ?>">
            <i class="fas fa-chart-bar me-1"></i>Reports
          </a>
        </li>
        <?php endif; ?>
```

---

### FEATURE B3 — Activity / Audit Log

**New files:** `medical/database/migrations/007_create_audit_logs_table.php`, `medical/app/Controllers/AuditController.php`, `medical/app/Views/audit/index.php`
**Modified files:** `medical/app/Core/App.php`, `medical/app/Controllers/BaseController.php`, `medical/app/Views/layouts/main.php`

#### Step B3-1: Create the migration file

**Create:** `medical/database/migrations/007_create_audit_logs_table.php`

```php
<?php
// Load .env if running as standalone migration script
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}
$host    = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'hospital_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_email  VARCHAR(255)  NOT NULL,
            user_role   VARCHAR(50)   NOT NULL,
            action      VARCHAR(100)  NOT NULL,
            resource    VARCHAR(100)  NOT NULL,
            resource_id INT           NULL,
            detail      TEXT          NULL,
            ip_address  VARCHAR(45)   NULL,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user  (user_email),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "audit_logs table created successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
```

#### Step B3-2: Add `logAction()` to BaseController

**File:** `medical/app/Controllers/BaseController.php`

Add this method at the end of the class (before the closing `}`):

```php
    /**
     * Write an entry to the audit_logs table.
     * Call this inside any store(), update(), or delete() method after a successful DB operation.
     *
     * @param string   $action     e.g. 'create', 'update', 'delete'
     * @param string   $resource   e.g. 'patient', 'appointment', 'billing'
     * @param int|null $resourceId The ID of the affected record
     * @param string   $detail     Optional human-readable description
     */
    protected function logAction(string $action, string $resource, ?int $resourceId = null, string $detail = ''): void
    {
        try {
            $database = new Database();
            $db       = $database->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (user_email, user_role, action, resource, resource_id, detail, ip_address)
                 VALUES (:email, :role, :action, :resource, :resource_id, :detail, :ip)"
            );
            $stmt->execute([
                ':email'       => $_SESSION['user']['email']  ?? 'unknown',
                ':role'        => $_SESSION['user']['role']   ?? 'unknown',
                ':action'      => $action,
                ':resource'    => $resource,
                ':resource_id' => $resourceId,
                ':detail'      => $detail,
                ':ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
            ]);
        } catch (Exception $e) {
            // Audit log failure must never crash the app — log silently
            error_log('Audit log error: ' . $e->getMessage());
        }
    }
```

#### Step B3-3: Hook logAction() into all store/update/delete methods

Add `$this->logAction()` calls immediately after each successful DB operation in these controllers:

**PatientController:**
```php
// After $this->patientModel->create() succeeds:
$this->logAction('create', 'patient', (int)$this->patientModel->id, 'Patient created: ' . $this->patientModel->name);

// After $this->patientModel->update() succeeds:
$this->logAction('update', 'patient', (int)$this->patientModel->id, 'Patient updated: ' . $this->patientModel->name);

// After $this->patientModel->delete() succeeds:
$this->logAction('delete', 'patient', (int)$id, 'Patient deleted ID: ' . $id);
```

**DoctorController** — same pattern with `'doctor'` as resource.

**AppointmentController** — same pattern with `'appointment'`.

**EmrController** — same pattern with `'emr_record'`.

**BillingController** — same pattern with `'billing'`.

**UserController** — same pattern with `'user'`.

#### Step B3-4: Create AuditController

**Create:** `medical/app/Controllers/AuditController.php`

```php
<?php

class AuditController extends BaseController
{
    public function index()
    {
        $this->requireLogin();
        $this->requireRole('admin');

        $database = new Database();
        $db       = $database->getConnection();

        $logs = [];
        try {
            $stmt = $db->query(
                "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 500"
            );
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        $this->render('audit/index', [
            'title'   => 'Activity Log',
            'navPage' => 'audit',
            'logs'    => $logs,
        ]);
    }
}
```

#### Step B3-5: Register the route in App.php

```php
        $this->router->get('/reports', 'ReportsController@index');
        $this->router->get('/audit',   'AuditController@index');   // ADD
```

#### Step B3-6: Create the audit log view

**Create:** `medical/app/Views/audit/index.php`

```php
<div class="page-header">
  <div>
    <h1><i class="fas fa-clipboard-list"></i>Activity Log</h1>
    <div class="page-subtitle">All create, update, and delete actions across the system</div>
  </div>
  <span class="badge bg-secondary px-3 py-2"><?php echo count($logs); ?> entries</span>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="auditTable" class="table mb-0" style="width:100%">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Resource</th>
            <th>ID</th>
            <th>Detail</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l):
            $actionColors = ['create'=>'success','update'=>'primary','delete'=>'danger','simulate'=>'warning'];
            $ac = $actionColors[$l['action']] ?? 'secondary';
          ?>
          <tr>
            <td style="white-space:nowrap; font-size:.8rem;"><?php echo date('M d, Y h:i A', strtotime($l['created_at'])); ?></td>
            <td style="font-size:.85rem;"><?php echo htmlspecialchars($l['user_email']); ?></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($l['user_role']); ?></span></td>
            <td><span class="badge bg-<?php echo $ac; ?>"><?php echo htmlspecialchars($l['action']); ?></span></td>
            <td style="font-size:.85rem;"><?php echo htmlspecialchars($l['resource']); ?></td>
            <td style="font-size:.85rem; color:#64748b;"><?php echo $l['resource_id'] ?? '—'; ?></td>
            <td style="font-size:.82rem; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              <?php echo htmlspecialchars($l['detail'] ?? '—'); ?>
            </td>
            <td style="font-size:.78rem; color:#94a3b8;"><?php echo htmlspecialchars($l['ip_address'] ?? '—'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('#auditTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    language: { search: 'Filter logs:' }
  });
});
</script>
```

#### Step B3-7: Add Audit link to navbar (admin only)

**File:** `medical/app/Views/layouts/main.php`

Add after the Reports link (also admin-only):
```php
        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='audit'?'active':''; ?>" href="<?php echo url('/audit'); ?>">
            <i class="fas fa-clipboard-list me-1"></i>Audit Log
          </a>
        </li>
        <?php endif; ?>
```

---

## PART C — PHASE 3: Global Search, Document Upload

---

### FEATURE C1 — Global Search

**New files:** None
**Modified files:** `medical/app/Core/App.php`, `medical/app/Controllers/PatientController.php` (adds `search()` method), `medical/app/Views/layouts/main.php`

#### Step C1-1: Register search route

```php
        $this->router->get('/api/search', 'PatientController@search');
```

#### Step C1-2: Add `search()` to PatientController

```php
    /**
     * GET /api/search?q=term
     * Searches patients, doctors, and appointments. Returns JSON for the navbar dropdown.
     */
    public function search()
    {
        $this->requireLogin();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->jsonResponse(['success' => true, 'results' => []]);
            return;
        }

        $database = new Database();
        $db       = $database->getConnection();
        $like     = '%' . $q . '%';
        $results  = [];

        try {
            // Patients
            $stmt = $db->prepare(
                "SELECT id, name, email, phone FROM patients
                 WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
                 LIMIT 5"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'    => 'patient',
                    'icon'    => 'fa-user-injured',
                    'label'   => $r['name'],
                    'sub'     => $r['email'],
                    'url'     => url('/patients/view?id=' . $r['id']),
                ];
            }

            // Doctors (if role allows)
            if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])) {
                $stmt = $db->prepare(
                    "SELECT id, name, specialization FROM doctors
                     WHERE name LIKE ? OR specialization LIKE ?
                     LIMIT 3"
                );
                $stmt->execute([$like, $like]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $results[] = [
                        'type'  => 'doctor',
                        'icon'  => 'fa-user-md',
                        'label' => 'Dr. ' . $r['name'],
                        'sub'   => $r['specialization'],
                        'url'   => url('/doctors'),
                    ];
                }
            }

            // Appointments
            $stmt = $db->prepare(
                "SELECT a.id, p.name AS patient_name, d.name AS doctor_name,
                        a.appointment_date, a.status
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN doctors  d ON a.doctor_id  = d.id
                 WHERE p.name LIKE ? OR d.name LIKE ?
                 LIMIT 3"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'  => 'appointment',
                    'icon'  => 'fa-calendar-check',
                    'label' => $r['patient_name'] . ' → Dr. ' . $r['doctor_name'],
                    'sub'   => date('M d, Y', strtotime($r['appointment_date'])) . ' · ' . ucfirst($r['status']),
                    'url'   => url('/appointments'),
                ];
            }
        } catch (Exception $e) {}

        $this->jsonResponse(['success' => true, 'results' => $results]);
    }
```

#### Step C1-3: Add search bar to the navbar

**File:** `medical/app/Views/layouts/main.php`

Find the `</ul>` that closes the `navbar-nav me-auto` list and add the search bar between the nav list and the bell icon:

```php
      </ul>

      <!-- Global search -->
      <div class="position-relative me-3" id="ssSearchWrap" style="width:220px;">
        <input type="text" id="ssSearchInput" class="form-control form-control-sm"
               placeholder="Search patients, doctors…"
               style="background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.2);
                      color:#fff; border-radius:var(--r-md); padding-left:2rem; font-size:.8rem;">
        <i class="fas fa-search" style="position:absolute;left:.6rem;top:50%;transform:translateY(-50%);
                                         color:rgba(255,255,255,.5); font-size:.75rem; pointer-events:none;"></i>
        <div id="ssSearchDrop" style="display:none; position:absolute; top:calc(100% + 6px); left:0; right:0;
             background:var(--ss-surface); border:1px solid var(--ss-border); border-radius:var(--r-md);
             box-shadow:var(--shadow-md); z-index:1060; max-height:320px; overflow-y:auto;">
        </div>
      </div>
```

Then add this JavaScript inside the existing `<script>` block in `main.php` (before the closing `</script>`):

```javascript
  // ── Global search ──────────────────────────────────
  (function () {
    const input   = document.getElementById('ssSearchInput');
    const drop    = document.getElementById('ssSearchDrop');
    if (!input) return;
    let timer;

    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { drop.style.display = 'none'; return; }
      timer = setTimeout(() => {
        fetch(window.BASE_URL + '/api/search?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => {
            const results = data.results || [];
            if (!results.length) {
              drop.innerHTML = '<div style="padding:.75rem 1rem;font-size:.82rem;color:#94a3b8;">No results found.</div>';
            } else {
              drop.innerHTML = results.map(r => `
                <a href="${r.url}" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;
                   text-decoration:none;color:var(--ss-text);font-size:.82rem;border-bottom:.5px solid var(--ss-border);"
                   onmouseover="this.style.background='var(--ss-surface-2)'"
                   onmouseout="this.style.background=''">
                  <i class="fas ${r.icon}" style="color:#64748b;width:14px;text-align:center;flex-shrink:0;"></i>
                  <div>
                    <div style="font-weight:500;">${r.label}</div>
                    <div style="font-size:.75rem;color:#94a3b8;">${r.sub}</div>
                  </div>
                </a>`).join('');
            }
            drop.style.display = 'block';
          }).catch(() => { drop.style.display = 'none'; });
      }, 280);
    });

    document.addEventListener('click', e => {
      if (!document.getElementById('ssSearchWrap').contains(e.target)) {
        drop.style.display = 'none';
      }
    });

    input.addEventListener('keydown', e => {
      if (e.key === 'Escape') { drop.style.display = 'none'; input.blur(); }
    });
  })();
```

---

### FEATURE C2 — Document Upload (attached to Patient Profile)

**New files:** `medical/database/migrations/008_create_patient_documents_table.php`, `medical/app/Views/patients/documents_partial.php`
**Modified files:** `medical/app/Core/App.php`, `medical/app/Controllers/PatientController.php`, `medical/app/Views/patients/view.php`

#### Step C2-1: Create the migration

**Create:** `medical/database/migrations/008_create_patient_documents_table.php`

```php
<?php
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}
$host = $_ENV['DB_HOST'] ?? 'localhost'; $db_name = $_ENV['DB_NAME'] ?? 'hospital_db';
$username = $_ENV['DB_USER'] ?? 'root'; $password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patient_documents (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            patient_id   INT           NOT NULL,
            filename     VARCHAR(255)  NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_type    VARCHAR(50)   NOT NULL,
            file_size    INT           NOT NULL,
            description  TEXT          NULL,
            uploaded_by  VARCHAR(255)  NOT NULL,
            created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_patient (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "patient_documents table created successfully.\n";

    // Create upload directory
    $uploadDir = __DIR__ . '/../../storage/uploads/patient_docs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nDeny from all\n");
    }
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
```

#### Step C2-2: Add upload and download routes in App.php

```php
        $this->router->post('/patients/upload',    'PatientController@uploadDocument');
        $this->router->get('/patients/document',   'PatientController@downloadDocument');
        $this->router->post('/patients/doc-delete','PatientController@deleteDocument');
```

#### Step C2-3: Add document methods to PatientController

Add these three methods to `PatientController` after `view()`:

```php
    public function uploadDocument()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);
        $this->validateCsrf();

        $patientId = (int)($this->getPostData('patient_id') ?? 0);
        if (!$patientId) { $this->jsonResponse(['success'=>false,'error'=>'Invalid patient.'], 400); return; }

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['success'=>false,'error'=>'No file uploaded or upload error.'], 400); return;
        }

        $file = $_FILES['document'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_FILE_TYPES, true)) {
            $this->jsonResponse(['success'=>false,'error'=>'File type not allowed. Accepted: '.implode(', ',ALLOWED_FILE_TYPES)], 400); return;
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $this->jsonResponse(['success'=>false,'error'=>'File too large. Max: '.round(MAX_UPLOAD_SIZE/1024/1024,1).' MB'], 400); return;
        }

        $uploadDir  = APP_PATH . '/../storage/uploads/patient_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename   = 'pat_' . $patientId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest       = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->jsonResponse(['success'=>false,'error'=>'Failed to save file.'], 500); return;
        }

        $database = new Database();
        $db       = $database->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO patient_documents (patient_id, filename, original_name, file_type, file_size, description, uploaded_by)
             VALUES (?,?,?,?,?,?,?)"
        );
        $desc = htmlspecialchars(strip_tags($this->getPostData('description') ?? ''));
        $stmt->execute([$patientId, $filename, htmlspecialchars(strip_tags($file['name'])), $ext, $file['size'], $desc, $_SESSION['user']['email'] ?? 'unknown']);
        $docId = (int)$db->lastInsertId();

        $this->logAction('create', 'patient_document', $docId, 'Uploaded: ' . $file['name'] . ' for patient ' . $patientId);
        $this->jsonResponse(['success'=>true,'message'=>'Document uploaded.','id'=>$docId]);
    }

    public function downloadDocument()
    {
        $this->requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); exit('Invalid document ID.'); }

        $database = new Database();
        $db       = $database->getConnection();
        $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) { http_response_code(404); exit('Document not found.'); }

        $path = APP_PATH . '/../storage/uploads/patient_docs/' . $doc['filename'];
        if (!file_exists($path)) { http_response_code(404); exit('File missing.'); }

        $mimes = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        $mime  = $mimes[$doc['file_type']] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($doc['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function deleteDocument()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) { $this->jsonResponse(['success'=>false,'error'=>'Invalid ID.'], 400); return; }

        $database = new Database();
        $db       = $database->getConnection();
        $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) { $this->jsonResponse(['success'=>false,'error'=>'Not found.'], 404); return; }

        $path = APP_PATH . '/../storage/uploads/patient_docs/' . $doc['filename'];
        if (file_exists($path)) unlink($path);

        $db->prepare("DELETE FROM patient_documents WHERE id = ?")->execute([$id]);
        $this->logAction('delete', 'patient_document', $id, 'Deleted: ' . $doc['original_name']);
        $this->jsonResponse(['success'=>true]);
    }
```

#### Step C2-4: Add documents section to patients/view.php

At the end of `patients/view.php` (after the Billing card), add:

```php
<!-- Documents -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-paperclip me-2"></i>Documents</span>
    <button class="btn btn-sm btn-outline-primary" id="uploadDocBtn">
      <i class="fas fa-upload me-1"></i>Upload
    </button>
  </div>
  <!-- Upload form (hidden by default) -->
  <div id="uploadDocForm" style="display:none; border-bottom:1px solid var(--ss-border); padding:1rem 1.25rem; background:var(--ss-surface-2);">
    <form id="docUploadForm" enctype="multipart/form-data">
      <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']??'',ENT_QUOTES,'UTF-8'); ?>">
      <input type="hidden" name="patient_id" value="<?php echo $patient->id; ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label">File <small class="text-muted">(PDF, JPG, PNG — max 5MB)</small></label>
          <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Description <small class="text-muted">(optional)</small></label>
          <input type="text" name="description" class="form-control form-control-sm" placeholder="e.g. X-Ray result, consent form…">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-sm btn-primary w-100">Upload</button>
        </div>
      </div>
    </form>
  </div>
  <div class="card-body p-0" id="docList">
    <p class="text-muted text-center py-4 mb-0" id="noDocsMsg">
      <i class="fas fa-folder-open me-2"></i>No documents uploaded yet.
    </p>
  </div>
</div>

<script>
const PATIENT_ID = <?php echo (int)$patient->id; ?>;
const DOC_UPLOAD_URL  = window.BASE_URL + '/patients/upload';
const DOC_DELETE_URL  = window.BASE_URL + '/patients/doc-delete';

// Load existing documents
function loadDocs() {
  fetch(window.BASE_URL + '/api/search?q=' + PATIENT_ID)
    .then(() => {}); // placeholder — documents loaded via separate endpoint below
}

// Toggle upload form
document.getElementById('uploadDocBtn').addEventListener('click', () => {
  const f = document.getElementById('uploadDocForm');
  f.style.display = f.style.display === 'none' ? '' : 'none';
});

// Upload
document.getElementById('docUploadForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fetch(DOC_UPLOAD_URL, { method:'POST', body: fd }).then(r=>r.json()).then(d => {
    if (d.success) {
      document.getElementById('uploadDocForm').style.display = 'none';
      this.reset();
      window.location.reload();
    } else { alert(d.error || 'Upload failed.'); }
  });
});

// Delete
document.querySelectorAll('.doc-delete-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    if (!confirm('Delete this document?')) return;
    ajaxPost(DOC_DELETE_URL, { id: this.dataset.id }).then(d => {
      if (d.success) this.closest('tr').remove();
    });
  });
});
</script>
```

Then update `PatientController::view()` to also query documents. After the `$billingRecords` line, add:

```php
        // Documents
        $docRecords = [];
        try {
            $docStmt = $db->prepare("SELECT * FROM patient_documents WHERE patient_id = ? ORDER BY created_at DESC");
            $docStmt->execute([$id]);
            $docRecords = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
```

And pass `'docRecords' => $docRecords` in the render array.

Then add the documents table to `patients/view.php` inside the `#docList` div (before the `</div>` closing the `card-body`):

```php
<?php if (!empty($docRecords)): ?>
<div class="table-responsive">
  <table class="table mb-0" style="font-size:.875rem;">
    <thead><tr><th>File</th><th>Type</th><th>Size</th><th>Description</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($docRecords as $doc): ?>
      <tr>
        <td>
          <a href="<?php echo url('/patients/document?id='.$doc['id']); ?>" target="_blank" style="font-weight:500;">
            <i class="fas fa-<?php echo $doc['file_type']==='pdf'?'file-pdf':'file-image'; ?> me-1 text-primary"></i>
            <?php echo htmlspecialchars($doc['original_name']); ?>
          </a>
        </td>
        <td><span class="badge bg-secondary"><?php echo strtoupper($doc['file_type']); ?></span></td>
        <td style="color:#64748b;"><?php echo round($doc['file_size']/1024,1); ?> KB</td>
        <td style="color:#64748b;"><?php echo htmlspecialchars($doc['description']??'—'); ?></td>
        <td style="font-size:.8rem;color:#64748b;"><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
        <td style="white-space:nowrap;font-size:.8rem;"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
        <td>
          <button class="btn btn-sm btn-outline-danger doc-delete-btn" data-id="<?php echo $doc['id']; ?>">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<p class="text-muted text-center py-4 mb-0" id="noDocsMsg">
  <i class="fas fa-folder-open me-2"></i>No documents uploaded yet.
</p>
<?php endif; ?>
```

---

## VERIFICATION CHECKLIST

Run every check. Fix and restart from the top if anything fails.

### Part A — Fixes
```
[ ] home.php: staff role user does NOT see Patients/Doctors cards
[ ] home.php: admin/doctor/nurse still see all cards
[ ] All 7 migration files (000–006) start with the .env-reading block
[ ] Each migration file resolves DB_HOST, DB_NAME, DB_USER, DB_PASS from $_ENV
```

### Part B — Phase 2
```
[ ] GET /api/appointments/events returns JSON with success:true and events array
[ ] Each event has id, title, start (ISO datetime), color, extendedProps
[ ] Appointments page has two tabs: "Table View" and "Calendar View"
[ ] Clicking Calendar View loads FullCalendar, clicking an event shows Swal.fire popup
[ ] FullCalendar CDN loaded in main.php <head> (link + script)
[ ] GET /reports renders page with 4 charts and 7 summary stat cards
[ ] Charts: patient registrations (bar), appointment status (doughnut), revenue (line), alerts (doughnut)
[ ] Reports nav link visible to admin/doctor/nurse, hidden from staff
[ ] GET /audit renders paginated DataTable of audit log entries (admin only)
[ ] Audit nav link only visible when role === 'admin'
[ ] logAction() method exists in BaseController with 4 parameters
[ ] logAction() calls exist in PatientController store/update/delete after success
[ ] logAction() calls exist in DoctorController, AppointmentController, EmrController, BillingController, UserController
[ ] 007_create_audit_logs_table.php migration creates audit_logs table with correct columns
[ ] Audit log failure (DB error) does NOT crash the app — silently caught
```

### Part C — Phase 3
```
[ ] GET /api/search?q=term returns JSON results array
[ ] Results include patients (with /patients/view links), doctors, appointments
[ ] Minimum query length is 2 characters (shorter returns empty results)
[ ] Search bar visible in navbar on all pages
[ ] Typing 2+ characters shows dropdown within ~300ms
[ ] Clicking a result navigates to the correct page
[ ] Clicking outside the dropdown closes it
[ ] Escape key closes the dropdown
[ ] POST /patients/upload: validates file type against ALLOWED_FILE_TYPES constant
[ ] POST /patients/upload: validates file size against MAX_UPLOAD_SIZE constant
[ ] Files stored in medical/storage/uploads/patient_docs/ with .htaccess protection
[ ] GET /patients/document?id=N: streams file with correct Content-Type header
[ ] Patient profile page shows Documents section with upload form toggle
[ ] Documents table shows filename (linked), type, size, description, uploader, date, delete button
[ ] 008_create_patient_documents_table.php creates table with patient_id FK → patients(id) CASCADE
```

### Regression
```
[ ] All previous 36 checks still green
[ ] Login/logout still work (session_regenerate_id on both)
[ ] Patient profile page (/patients/view?id=N) still loads with all 3 sections
[ ] Print invoice still opens in new tab without navbar
[ ] Alert modal still auto-opens for critical/danger from poll
[ ] Simulate alert button still works on /alerts page
[ ] Appointment CRUD modal still works (store/update/delete)
[ ] No new PHP warnings or errors on any page
```

### Commit
```
[ ] All checks passed

[ ] Run migrations: php medical/database/migrations/007_create_audit_logs_table.php
                    php medical/database/migrations/008_create_patient_documents_table.php

[ ] git add -A
[ ] git commit -m "feat: phase 2 (calendar, reports, audit log) + phase 3 (global search, document upload) + minor fixes"
```

---

## FILES SUMMARY

| File | Status | Purpose |
|------|--------|---------|
| `home.php` | Modified | Role-gate Patients/Doctors cards |
| `migrations/000–006.php` | Modified | Read .env credentials |
| `migrations/007_create_audit_logs_table.php` | **New** | Audit log schema |
| `migrations/008_create_patient_documents_table.php` | **New** | Document storage schema |
| `App.php` | Modified | +5 new routes |
| `BaseController.php` | Modified | +logAction() method |
| `PatientController.php` | Modified | +search(), +uploadDocument(), +downloadDocument(), +deleteDocument() |
| `AppointmentController.php` | Modified | +calendarEvents(), +logAction calls |
| `DoctorController.php` | Modified | +logAction calls |
| `EmrController.php` | Modified | +logAction calls |
| `BillingController.php` | Modified | +logAction calls |
| `UserController.php` | Modified | +logAction calls |
| `ReportsController.php` | **New** | Reports page logic |
| `AuditController.php` | **New** | Audit log page logic |
| `layouts/main.php` | Modified | FullCalendar CDN, Reports/Audit nav links, global search bar + JS |
| `appointments/index.php` | Modified | Tab toggle + FullCalendar init JS |
| `reports/index.php` | **New** | Reports view with 4 Chart.js charts |
| `audit/index.php` | **New** | Audit log DataTable view |
| `patients/view.php` | Modified | Documents section + upload form |
| `storage/uploads/patient_docs/` | Created by migration | File storage directory |

**7 new files. 13 modified files. 2 new DB tables. Zero breaking changes.**
