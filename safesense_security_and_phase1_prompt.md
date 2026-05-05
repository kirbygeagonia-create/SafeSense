# SafeSense — Security Fix + Phase 1 Feature Expansion
## session_regenerate_id on Login · Patient Profile Page · Print Invoice · Role-Aware Dashboard

---

## OVERVIEW

This prompt covers **1 security fix** and **3 new features**. Read all sections before writing any code. Apply in order.

**Files created (new):** 4
**Files modified:** 6
**Database changes:** None — all features use existing tables and models

---

## FIX 0 — session_regenerate_id(true) on successful login *(5 minutes)*

**File:** `medical/app/Controllers/AuthController.php`

**Problem:** `session_regenerate_id(true)` is called on logout but not on login. An attacker who knows a victim's pre-login session ID can hijack the authenticated session (session fixation). One line fixes it.

**Find this exact block** (lines 48–53):
```php
                if ($user) {
            $_SESSION['user']       = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['flash_success'] = 'Welcome back, ' . $user['name'] . '!';
            $this->redirect('/dashboard');
```

**Replace with:**
```php
                if ($user) {
            session_regenerate_id(true);          // Prevent session fixation on login
            $_SESSION['user']       = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['flash_success'] = 'Welcome back, ' . $user['name'] . '!';
            $this->redirect('/dashboard');
```

**What changed:** One line — `session_regenerate_id(true);` added immediately after `if ($user) {` and before any `$_SESSION` writes. The `true` argument deletes the old session file.

---

## FEATURE 1 — Patient Profile Page

A dedicated page at `/patients/view?id={id}` showing a patient's full record: personal info, all EMR visits, all appointments, and all billing invoices — pulled from existing models with zero schema changes.

### Step 1A — Add `getByPatient()` to the Appointment model

**File:** `medical/app/Models/Appointment.php`

The `Appointment` model has no `getByPatient()` method (unlike EMR and Billing which already do). Add it after the `getById()` method:

```php
    public function getByPatient($patientId) {
        $query = 'SELECT a.*, p.name as patient_name, d.name as doctor_name
                  FROM ' . $this->table_name . ' a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN doctors  d ON a.doctor_id  = d.id
                  WHERE a.patient_id = ?
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$patientId]);
        return $stmt;
    }
```

### Step 1B — Register the route in `medical/app/Core/App.php`

**Find the patients routes block** (lines 16–20):
```php
        $this->router->get('/patients', 'PatientController@index');
        $this->router->post('/patients/store', 'PatientController@store');
        $this->router->get('/patients/edit', 'PatientController@edit');
        $this->router->post('/patients/update', 'PatientController@update');
        $this->router->post('/patients/delete', 'PatientController@delete');
```

**Replace with:**
```php
        $this->router->get('/patients',        'PatientController@index');
        $this->router->get('/patients/view',   'PatientController@view');
        $this->router->post('/patients/store', 'PatientController@store');
        $this->router->get('/patients/edit',   'PatientController@edit');
        $this->router->post('/patients/update','PatientController@update');
        $this->router->post('/patients/delete','PatientController@delete');
```

### Step 1C — Add the `view()` method to `PatientController`

**File:** `medical/app/Controllers/PatientController.php`

Add this method immediately after the `index()` method (before `store()`):

```php
    public function view()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid patient ID.';
            $this->redirect('/patients');
            return;
        }

        $database = new Database();
        $db       = $database->getConnection();

        // Patient details
        $found = $this->patientModel->getById($id);
        if (!$found) {
            $_SESSION['flash_error'] = 'Patient not found.';
            $this->redirect('/patients');
            return;
        }

        // EMR records
        $emrModel  = new Emr($db);
        $emrStmt   = $emrModel->getByPatient($id);
        $emrRecords = $emrStmt->fetchAll(PDO::FETCH_ASSOC);

        // Appointments
        $apptModel   = new Appointment($db);
        $apptStmt    = $apptModel->getByPatient($id);
        $appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);

        // Billing
        $billingModel  = new Billing($db);
        $billingStmt   = $billingModel->getByPatient($id);
        $billingRecords = $billingStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('patients/view', [
            'title'          => 'Patient Profile — ' . htmlspecialchars($this->patientModel->name),
            'navPage'        => 'patients',
            'patient'        => $this->patientModel,
            'emrRecords'     => $emrRecords,
            'appointments'   => $appointments,
            'billingRecords' => $billingRecords,
            'currentRole'    => $this->currentRole(),
        ]);
    }
```

### Step 1D — Create the patient profile view

**Create new file:** `medical/app/Views/patients/view.php`

```php
<?php
// Age calculation helper
$dob = new DateTime($patient->date_of_birth ?? 'now');
$age = (new DateTime())->diff($dob)->y;

// Totals for billing summary
$totalBilled    = array_sum(array_column($billingRecords, 'total_amount'));
$totalPaid      = array_sum(array_map(fn($b) => $b['payment_status']==='paid'   ? $b['total_amount'] : 0, $billingRecords));
$totalUnpaid    = array_sum(array_map(fn($b) => $b['payment_status']==='unpaid' ? $b['total_amount'] : 0, $billingRecords));

$genderIcon = $patient->gender === 'female' ? 'fa-venus' : 'fa-mars';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="d-flex align-items-center gap-3">
    <a href="<?php echo url('/patients'); ?>" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h1 style="margin:0;"><i class="fas fa-id-card"></i><?php echo htmlspecialchars($patient->name); ?></h1>
      <div class="page-subtitle">Patient Profile &mdash; Full Medical Record</div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <span class="badge bg-<?php echo $patient->gender==='female'?'danger':'primary'; ?> px-3 py-2" style="border-radius:99px;">
      <i class="fas <?php echo $genderIcon; ?> me-1"></i><?php echo ucfirst(htmlspecialchars($patient->gender ?? '—')); ?>
    </span>
  </div>
</div>

<!-- Info + Quick Stats Row -->
<div class="row g-3 mb-4">
  <!-- Personal info card -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-user me-2"></i>Personal Information</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0" style="font-size:.875rem;">
          <tr><td class="text-muted" style="width:40%">Age</td><td><strong><?php echo $age; ?> years</strong> (<?php echo date('M d, Y', strtotime($patient->date_of_birth)); ?>)</td></tr>
          <tr><td class="text-muted">Email</td><td><?php echo htmlspecialchars($patient->email ?? '—'); ?></td></tr>
          <tr><td class="text-muted">Phone</td><td><?php echo htmlspecialchars($patient->phone ?? '—'); ?></td></tr>
          <tr><td class="text-muted">Address</td><td><?php echo htmlspecialchars($patient->address ?? '—'); ?></td></tr>
          <tr><td class="text-muted">Registered</td><td><?php echo date('M d, Y', strtotime($patient->created_at ?? 'now')); ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick stats -->
  <div class="col-md-7">
    <div class="row g-3 h-100">
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">EMR Visits</div>
          <div class="stat-value"><?php echo count($emrRecords); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">Appointments</div>
          <div class="stat-value"><?php echo count($appointments); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">Invoices</div>
          <div class="stat-value"><?php echo count($billingRecords); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">Total Billed</div>
          <div class="stat-value" style="font-size:1.2rem;">₱<?php echo number_format($totalBilled, 0); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">Paid</div>
          <div class="stat-value" style="font-size:1.2rem; color:#15803d;">₱<?php echo number_format($totalPaid, 0); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card text-center">
          <div class="stat-label">Unpaid</div>
          <div class="stat-value" style="font-size:1.2rem; color:#b91c1c;">₱<?php echo number_format($totalUnpaid, 0); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- EMR Records -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-notes-medical me-2"></i>Medical Records <span class="badge bg-secondary ms-1"><?php echo count($emrRecords); ?></span></span>
  </div>
  <div class="card-body p-0">
    <?php if (!empty($emrRecords)): ?>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead><tr>
          <th>Date</th><th>Doctor</th><th>Chief Complaint</th><th>Diagnosis</th><th>Vitals</th>
        </tr></thead>
        <tbody>
          <?php foreach ($emrRecords as $r): ?>
          <tr>
            <td style="white-space:nowrap;"><?php echo date('M d, Y', strtotime($r['visit_date'])); ?></td>
            <td><?php echo htmlspecialchars($r['doctor_name'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($r['chief_complaint'] ?? '—', 0, 50, '…')); ?></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($r['diagnosis'] ?? '—', 0, 50, '…')); ?></td>
            <td style="white-space:nowrap; font-size:.8rem; color:#64748b;">
              <?php if ($r['blood_pressure']): ?><span title="BP"><i class="fas fa-heartbeat me-1"></i><?php echo htmlspecialchars($r['blood_pressure']); ?></span><?php endif; ?>
              <?php if ($r['temperature']): ?> <span title="Temp"><i class="fas fa-thermometer-half me-1 ms-1"></i><?php echo $r['temperature']; ?>°C</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center py-4 mb-0"><i class="fas fa-folder-open me-2"></i>No medical records on file.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Appointments -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-calendar-check me-2"></i>Appointment History <span class="badge bg-secondary ms-1"><?php echo count($appointments); ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (!empty($appointments)): ?>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a):
            $statusColors = ['pending'=>'warning','confirmed'=>'primary','completed'=>'success','cancelled'=>'secondary'];
            $sc = $statusColors[$a['status']] ?? 'secondary';
          ?>
          <tr>
            <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
            <td><?php echo htmlspecialchars($a['doctor_name'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($a['reason'] ?? '—', 0, 45, '…')); ?></td>
            <td><span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst($a['status']); ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center py-4 mb-0"><i class="fas fa-calendar-times me-2"></i>No appointments on record.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Billing -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-file-invoice-dollar me-2"></i>Billing History <span class="badge bg-secondary ms-1"><?php echo count($billingRecords); ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (!empty($billingRecords)): ?>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead><tr><th>Invoice</th><th>Service</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($billingRecords as $b):
            $psColors = ['paid'=>'success','unpaid'=>'danger','partial'=>'warning','cancelled'=>'secondary'];
            $pc = $psColors[$b['payment_status']] ?? 'secondary';
          ?>
          <tr>
            <td style="font-family:monospace; font-size:.8rem;"><?php echo htmlspecialchars($b['invoice_number']); ?></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($b['service_description'] ?? '—', 0, 40, '…')); ?></td>
            <td><strong>₱<?php echo number_format($b['total_amount'], 2); ?></strong></td>
            <td><span class="badge bg-<?php echo $pc; ?>"><?php echo ucfirst($b['payment_status']); ?></span></td>
            <td><?php echo $b['payment_date'] ? date('M d, Y', strtotime($b['payment_date'])) : '—'; ?></td>
            <td>
              <a href="<?php echo url('/billing/print?id='.$b['id']); ?>" target="_blank"
                 class="btn btn-sm btn-outline-secondary" title="Print Invoice">
                <i class="fas fa-print"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center py-4 mb-0"><i class="fas fa-receipt me-2"></i>No invoices on record.</p>
    <?php endif; ?>
  </div>
</div>
```

### Step 1E — Add "View Profile" button to the patients index table

**File:** `medical/app/Views/patients/index.php`

Find the action buttons for each patient row. They currently look like:
```php
<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $p['id']; ?>"><i class="fas fa-edit"></i></button>
```

Add a View Profile button **before** the Edit button:
```php
<a href="<?php echo url('/patients/view?id='.$p['id']); ?>"
   class="btn btn-sm btn-outline-info me-1" title="View Profile">
  <i class="fas fa-id-card"></i>
</a>
<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $p['id']; ?>"><i class="fas fa-edit"></i></button>
```

---

## FEATURE 2 — Print Invoice

A clean printable invoice page at `/billing/print?id={id}` that opens in a new tab and is optimised for `Ctrl+P`.

### Step 2A — Register the route in `medical/app/Core/App.php`

**Find the billing routes block** (lines 54–58):
```php
        $this->router->get('/billing',          'BillingController@index');
        $this->router->post('/billing/store',   'BillingController@store');
        $this->router->get('/billing/edit',     'BillingController@edit');
        $this->router->post('/billing/update',  'BillingController@update');
        $this->router->post('/billing/delete',  'BillingController@delete');
```

**Replace with:**
```php
        $this->router->get('/billing',          'BillingController@index');
        $this->router->get('/billing/print',    'BillingController@printInvoice');
        $this->router->post('/billing/store',   'BillingController@store');
        $this->router->get('/billing/edit',     'BillingController@edit');
        $this->router->post('/billing/update',  'BillingController@update');
        $this->router->post('/billing/delete',  'BillingController@delete');
```

### Step 2B — Add `printInvoice()` to `BillingController`

**File:** `medical/app/Controllers/BillingController.php`

Add this method immediately after `index()` (before `store()`):

```php
    public function printInvoice()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid invoice ID.';
            $this->redirect('/billing');
            return;
        }

        $found = $this->billingModel->getById($id);
        if (!$found) {
            $_SESSION['flash_error'] = 'Invoice not found.';
            $this->redirect('/billing');
            return;
        }

        // Render the print view — no main layout, standalone page
        $billing = $this->billingModel;
        include APP_PATH . '/Views/billing/print.php';
        exit;
    }
```

> **Note:** `printInvoice()` uses `include` + `exit` instead of `$this->render()` so it renders a completely standalone HTML page without the main navigation layout. This is intentional — the print view must be self-contained.

### Step 2C — Create the print view

**Create new file:** `medical/app/Views/billing/print.php`

```php
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?php echo htmlspecialchars($billing->invoice_number); ?> — SafeSense HMS</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans', sans-serif; font-size: 14px; color: #0f172a; background: #fff; padding: 0; }

    .page { max-width: 800px; margin: 0 auto; padding: 48px 56px; }

    /* Header */
    .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 2px solid #1e3a8a; }
    .inv-brand { display: flex; flex-direction: column; }
    .inv-brand-name { font-size: 20px; font-weight: 700; color: #1e3a8a; letter-spacing: -.02em; }
    .inv-brand-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
    .inv-meta { text-align: right; }
    .inv-number { font-family: 'IBM Plex Mono', monospace; font-size: 18px; font-weight: 600; color: #1e3a8a; }
    .inv-date { font-size: 12px; color: #64748b; margin-top: 4px; }

    /* Status badge */
    .inv-status { display: inline-block; padding: 3px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-top: 8px; }
    .status-paid      { background: #dcfce7; color: #15803d; }
    .status-unpaid    { background: #fee2e2; color: #b91c1c; }
    .status-partial   { background: #fef9c3; color: #854d0e; }
    .status-cancelled { background: #f1f5f9; color: #475569; }

    /* Bill to / from grid */
    .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 36px; }
    .inv-party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 8px; }
    .inv-party-name { font-size: 15px; font-weight: 600; color: #0f172a; }
    .inv-party-detail { font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.55; }

    /* Line items table */
    .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .inv-table thead th { padding: 10px 12px; background: #1e3a8a; color: #fff; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .inv-table thead th:last-child { text-align: right; }
    .inv-table tbody td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
    .inv-table tbody td:last-child { text-align: right; font-weight: 500; }

    /* Totals */
    .inv-totals { display: flex; justify-content: flex-end; margin-bottom: 36px; }
    .inv-totals-table { width: 280px; }
    .inv-totals-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .inv-totals-row:last-child { border-bottom: none; padding-top: 12px; font-size: 15px; font-weight: 700; color: #1e3a8a; }
    .inv-totals-row.discount { color: #15803d; }

    /* Payment info */
    .inv-payment { background: #f8fafc; border-radius: 8px; padding: 16px 20px; margin-bottom: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .inv-payment-item-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
    .inv-payment-item-val   { font-size: 13px; font-weight: 500; color: #0f172a; }

    /* Notes */
    .inv-notes { border-left: 3px solid #e2e8f0; padding-left: 16px; margin-bottom: 40px; }
    .inv-notes-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 6px; }
    .inv-notes-text  { font-size: 13px; color: #475569; line-height: 1.6; }

    /* Footer */
    .inv-footer { border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; }
    .inv-footer-brand { font-size: 12px; color: #94a3b8; }
    .inv-footer-generated { font-size: 11px; color: #cbd5e1; }

    /* Print button (screen only) */
    .print-bar { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 56px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .print-bar button { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; }
    .btn-print { background: #1e3a8a; color: #fff; }
    .btn-print:hover { background: #1d4ed8; }
    .btn-close-tab { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0 !important; }

    @media print {
      .print-bar { display: none !important; }
      .page { padding: 0; max-width: 100%; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .inv-table thead th { background: #1e3a8a !important; color: #fff !important; }
    }
  </style>
</head>
<body>

<!-- Screen-only print toolbar -->
<div class="print-bar">
  <button class="btn-close-tab" onclick="window.close()">✕ Close</button>
  <button class="btn-print" onclick="window.print()">🖨 Print Invoice</button>
</div>

<div class="page">
  <!-- Header -->
  <div class="inv-header">
    <div class="inv-brand">
      <div class="inv-brand-name">🏥 SafeSense Hospital</div>
      <div class="inv-brand-sub">Hospital Management System</div>
      <div class="inv-brand-sub">Malaybalay City, Bukidnon</div>
    </div>
    <div class="inv-meta">
      <div class="inv-number"><?php echo htmlspecialchars($billing->invoice_number); ?></div>
      <div class="inv-date">Issued: <?php echo date('F d, Y'); ?></div>
      <?php
        $sc = ['paid'=>'status-paid','unpaid'=>'status-unpaid','partial'=>'status-partial','cancelled'=>'status-cancelled'];
        $cls = $sc[$billing->payment_status] ?? 'status-unpaid';
      ?>
      <div><span class="inv-status <?php echo $cls; ?>"><?php echo strtoupper($billing->payment_status); ?></span></div>
    </div>
  </div>

  <!-- Bill To / From -->
  <div class="inv-parties">
    <div>
      <div class="inv-party-label">Bill To</div>
      <div class="inv-party-name"><?php echo htmlspecialchars($billing->patient_name ?? 'Patient'); ?></div>
      <div class="inv-party-detail">Patient ID: #<?php echo $billing->patient_id; ?></div>
    </div>
    <div>
      <div class="inv-party-label">Issued By</div>
      <div class="inv-party-name">SafeSense HMS Billing</div>
      <div class="inv-party-detail">billing@safesense.local<br>Generated: <?php echo date('M d, Y, h:i A'); ?></div>
    </div>
  </div>

  <!-- Line items -->
  <table class="inv-table">
    <thead>
      <tr>
        <th style="width:60%;">Description of Service</th>
        <th style="text-align:right;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?php echo htmlspecialchars($billing->service_description ?? 'Medical Services'); ?></td>
        <td>₱<?php echo number_format($billing->amount, 2); ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Totals -->
  <div class="inv-totals">
    <div class="inv-totals-table">
      <div class="inv-totals-row">
        <span>Subtotal</span>
        <span>₱<?php echo number_format($billing->amount, 2); ?></span>
      </div>
      <?php if ($billing->discount > 0): ?>
      <div class="inv-totals-row discount">
        <span>Discount</span>
        <span>− ₱<?php echo number_format($billing->discount, 2); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($billing->tax > 0): ?>
      <div class="inv-totals-row">
        <span>Tax</span>
        <span>+ ₱<?php echo number_format($billing->tax, 2); ?></span>
      </div>
      <?php endif; ?>
      <div class="inv-totals-row">
        <span>Total Due</span>
        <span>₱<?php echo number_format($billing->total_amount, 2); ?></span>
      </div>
    </div>
  </div>

  <!-- Payment info -->
  <div class="inv-payment">
    <div>
      <div class="inv-payment-item-label">Payment Status</div>
      <div class="inv-payment-item-val"><?php echo ucfirst($billing->payment_status); ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Payment Method</div>
      <div class="inv-payment-item-val"><?php echo $billing->payment_method ? ucfirst($billing->payment_method) : '—'; ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Payment Date</div>
      <div class="inv-payment-item-val"><?php echo $billing->payment_date ? date('M d, Y', strtotime($billing->payment_date)) : 'Not yet paid'; ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Invoice Reference</div>
      <div class="inv-payment-item-val" style="font-family:'IBM Plex Mono',monospace;"><?php echo htmlspecialchars($billing->invoice_number); ?></div>
    </div>
  </div>

  <!-- Notes -->
  <?php if (!empty($billing->notes)): ?>
  <div class="inv-notes">
    <div class="inv-notes-label">Notes</div>
    <div class="inv-notes-text"><?php echo htmlspecialchars($billing->notes); ?></div>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="inv-footer">
    <div class="inv-footer-brand">SafeSense Hospital Management System</div>
    <div class="inv-footer-generated">Generated <?php echo date('M d, Y \a\t h:i A'); ?></div>
  </div>
</div>

</body>
</html>
```

### Step 2D — Add Print button to billing index table

**File:** `medical/app/Views/billing/index.php`

Find the action buttons for each billing row (the Edit and Delete buttons). Add a Print button before the Edit button:

```php
<a href="<?php echo url('/billing/print?id='.$b['id']); ?>" target="_blank"
   class="btn btn-sm btn-outline-secondary me-1" title="Print Invoice">
  <i class="fas fa-print"></i>
</a>
```

---

## FEATURE 3 — Role-Aware Dashboard

Show the right information to the right person. Doctors see their own appointments. Nurses see unread alerts. Admins keep the full view.

### Step 3A — Update `AuthController::dashboard()` to pass role-specific data

**File:** `medical/app/Controllers/AuthController.php`

Find the dashboard method. After the existing `$upcomingAppointments` block, add role-specific data. Find this block near the end of `dashboard()`:

```php
        $this->render('dashboard', [
```

**Replace the entire render call** with:
```php
        // Role-specific data
        $role = $_SESSION['user']['role'] ?? 'staff';
        $myAppointments = [];
        $myAlerts       = [];

        try {
            if ($role === 'doctor') {
                // Find the doctor record matching the logged-in user's email
                $stmt = $db->prepare(
                    "SELECT a.*, p.name as patient_name
                     FROM appointments a
                     JOIN patients p ON a.patient_id = p.id
                     JOIN doctors   d ON a.doctor_id  = d.id
                     WHERE d.email = ? AND a.appointment_date >= CURDATE()
                     ORDER BY a.appointment_date ASC, a.appointment_time ASC
                     LIMIT 10"
                );
                $stmt->execute([$_SESSION['user']['email']]);
                $myAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}

        try {
            if ($role === 'nurse') {
                $alertModelFile = APP_PATH . '/Models/Alert.php';
                if (file_exists($alertModelFile)) {
                    require_once $alertModelFile;
                    $alertModel = new Alert($db);
                    $myAlerts   = $alertModel->getUnread();
                    $myAlerts   = is_array($myAlerts)
                        ? $myAlerts
                        : $myAlerts->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {}

        $this->render('dashboard', [
```

Then add `'userRole' => $role`, `'myAppointments' => $myAppointments`, `'myAlerts' => $myAlerts` to the render data array alongside the existing keys.

The final render call should include these three new keys alongside the existing ones like `'patientCount'`, `'doctorCount'`, etc.

### Step 3B — Add role-aware widgets to `dashboard.php`

**File:** `medical/app/Views/dashboard.php`

At the top of the file (before the stats row), add the role-specific panel:

```php
<?php if (($userRole ?? 'staff') === 'doctor' && !empty($myAppointments)): ?>
<div class="card mb-4" style="border-left: 4px solid var(--ss-primary);">
  <div class="card-header">
    <i class="fas fa-calendar-check me-2"></i>Your Upcoming Appointments
    <span class="badge bg-primary ms-2"><?php echo count($myAppointments); ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($myAppointments as $a):
            $sc = ['pending'=>'warning','confirmed'=>'primary','completed'=>'success','cancelled'=>'secondary'][$a['status']] ?? 'secondary';
          ?>
          <tr>
            <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
            <td><strong><?php echo htmlspecialchars($a['patient_name']); ?></strong></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($a['reason'] ?? '—', 0, 40, '…')); ?></td>
            <td><span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst($a['status']); ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (($userRole ?? 'staff') === 'nurse' && !empty($myAlerts)): ?>
<div class="card mb-4" style="border-left: 4px solid var(--ss-critical);">
  <div class="card-header" style="background:#fef2f2; border-color:#fecaca;">
    <i class="fas fa-satellite-dish me-2 text-danger"></i>
    <span class="text-danger fw-bold">Unread Alerts Requiring Attention</span>
    <span class="badge bg-danger ms-2"><?php echo count($myAlerts); ?></span>
  </div>
  <div class="card-body p-0">
    <?php foreach (array_slice($myAlerts, 0, 5) as $a):
      $colors = ['critical'=>'#b91c1c','danger'=>'#c2410c','warning'=>'#b45309'];
      $c = $colors[$a['alert_level']] ?? '#64748b';
    ?>
    <div class="d-flex align-items-start gap-3 p-3 border-bottom">
      <div class="stat-icon flex-shrink-0" style="background:<?php echo $c; ?>18; color:<?php echo $c; ?>;">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div>
        <div class="fw-semibold" style="font-size:.875rem;"><?php echo htmlspecialchars($a['message']); ?></div>
        <div class="text-muted" style="font-size:.78rem;">
          <?php echo htmlspecialchars($a['location_name'] ?? '—'); ?> &middot;
          <?php echo date('M d, h:i A', strtotime($a['created_at'])); ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="p-2 text-center">
      <a href="<?php echo url('/alerts'); ?>" class="btn btn-sm btn-outline-danger">
        <i class="fas fa-list me-1"></i>View All Alerts
      </a>
    </div>
  </div>
</div>
<?php endif; ?>
```

---

## VERIFICATION CHECKLIST

Run every check. Fix and restart from the top if anything fails.

### Step 1 — session_regenerate_id
```
[ ] AuthController.php authenticate(): session_regenerate_id(true) is the FIRST
    statement inside the if ($user) { block — before $_SESSION['user'] is set
[ ] The logout() method still has its own session_regenerate_id(true) — unchanged
```

### Step 2 — Patient Profile Page
```
[ ] Appointment model has new getByPatient($patientId) method returning a PDOStatement
[ ] App.php has route: GET /patients/view → PatientController@view
[ ] PatientController has view() method with requireLogin() + requireRole(['admin','doctor','nurse'])
[ ] view() redirects to /patients if id=0 or patient not found
[ ] view() fetches EMR, Appointments, Billing using respective model getByPatient()
[ ] medical/app/Views/patients/view.php exists
[ ] view.php shows: personal info table, 6 stat cards (visits/appointments/invoices/billed/paid/unpaid)
[ ] view.php shows EMR table, Appointments table, Billing table
[ ] patients/index.php has a "View Profile" link button before the Edit button
[ ] The view.php Print Invoice link points to: url('/billing/print?id='.$b['id'])
```

### Step 3 — Print Invoice
```
[ ] App.php has route: GET /billing/print → BillingController@printInvoice
[ ] BillingController has printInvoice() method with requireRole(['admin','doctor','nurse','staff'])
[ ] printInvoice() uses include + exit NOT $this->render() — no nav layout shown
[ ] medical/app/Views/billing/print.php exists as a complete standalone HTML file
[ ] print.php has <html>, <head>, <body> tags — self-contained document
[ ] print.php has @media print { .print-bar { display:none } } CSS rule
[ ] print.php shows: invoice number, patient name, service description, amount/discount/tax/total
[ ] print.php shows: payment status badge, payment method, payment date
[ ] billing/index.php has a Print button (opens in new tab) before the Edit button
```

### Step 4 — Role-Aware Dashboard
```
[ ] AuthController dashboard() passes: userRole, myAppointments, myAlerts to render()
[ ] Doctor role: myAppointments is populated by querying appointments WHERE doctor.email matches session email
[ ] Nurse role: myAlerts is populated from Alert::getUnread()
[ ] dashboard.php shows Doctor widget only when userRole === 'doctor' AND myAppointments not empty
[ ] dashboard.php shows Nurse widget only when userRole === 'nurse' AND myAlerts not empty
[ ] Admin sees normal dashboard with no role-specific widget at the top
[ ] Staff sees normal dashboard with no role-specific widget at the top
```

### Step 5 — Files changed scope
```
[ ] git diff --name-only shows exactly:
      medical/app/Controllers/AuthController.php        (Fix 0 + Feature 3A)
      medical/app/Controllers/PatientController.php     (Feature 1C)
      medical/app/Controllers/BillingController.php     (Feature 2B)
      medical/app/Core/App.php                          (Feature 1B + 2A)
      medical/app/Models/Appointment.php                (Feature 1A)
      medical/app/Views/patients/index.php              (Feature 1E)
      medical/app/Views/dashboard.php                   (Feature 3B)

[ ] New files created:
      medical/app/Views/patients/view.php               (Feature 1D)
      medical/app/Views/billing/print.php               (Feature 2C)
      medical/app/Views/billing/index.php               (Feature 2D — print button added)
```

### Step 6 — Demo walkthrough
```
[ ] Log in → dashboard loads with no errors
[ ] As doctor role: dashboard shows "Your Upcoming Appointments" panel at top
[ ] As nurse role: dashboard shows "Unread Alerts Requiring Attention" panel at top
[ ] As admin: dashboard shows normal stats, no role panel
[ ] Go to /patients → each row has an info-coloured view button (fa-id-card)
[ ] Click view button → Patient Profile page loads with all 3 sections populated
[ ] Go to /billing → each row has a print button (fa-print)
[ ] Click print button → new tab opens with clean invoice, Print button in toolbar
[ ] Click "Print Invoice" → browser print dialog opens
[ ] @media print hides the toolbar, invoice prints cleanly
```

### Step 7 — Regression
```
[ ] All 40 previous checks remain green
[ ] Login still works and redirects to /dashboard
[ ] Patients CRUD (add/edit/delete) still works via modal
[ ] Billing CRUD still works via modal
[ ] Alert modal still auto-opens for critical/danger alerts from poll
```

### Step 8 — Commit
```
[ ] All steps 1–7 passed

[ ] git commit -m "feat: session fix on login, patient profile page, print invoice, role-aware dashboard"
```

---

## SUMMARY

| # | Feature | New Files | Modified Files | DB Changes |
|---|---------|-----------|----------------|------------|
| Fix 0 | session_regenerate_id on login | 0 | AuthController.php | None |
| 1 | Patient Profile Page | patients/view.php | App.php, PatientController.php, Appointment.php, patients/index.php | None |
| 2 | Print Invoice | billing/print.php | App.php, BillingController.php, billing/index.php | None |
| 3 | Role-Aware Dashboard | 0 | AuthController.php, dashboard.php | None |

**2 new view files. 6 modified files. Zero database changes.**
