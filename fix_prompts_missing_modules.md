# Feature Prompts — SafeSense HMS Missing Modules

Three core HMS modules are currently missing. Each prompt below is
self-contained and can be given to an AI to implement independently.
Implement them in order: RBAC first, then EMR, then Billing — because
EMR and Billing both depend on the role guard that RBAC provides.

---
---

## Prompt 1 — Role-Based Access Control (RBAC)

### Context
The `users` table already has a `role` column:
`ENUM('admin','doctor','nurse','staff') DEFAULT 'staff'`

After successful login, `AuthController::authenticate()` stores:
```php
$_SESSION['user'] = [
    'email' => $row['email'],
    'role'  => $row['role'],   // ← already stored
    'name'  => $row['name']
];
```

`BaseController` already has `requireLogin()` which checks
`$_SESSION['user']`. RBAC builds directly on top of this.

### Role Permission Matrix

| Route / Action | admin | doctor | nurse | staff |
|---|---|---|---|---|
| View dashboard | ✅ | ✅ | ✅ | ✅ |
| View patients | ✅ | ✅ | ✅ | ✅ |
| Add / edit patients | ✅ | ✅ | ✅ | ❌ |
| Delete patients | ✅ | ❌ | ❌ | ❌ |
| View doctors | ✅ | ✅ | ✅ | ✅ |
| Add / edit doctors | ✅ | ❌ | ❌ | ❌ |
| Delete doctors | ✅ | ❌ | ❌ | ❌ |
| View appointments | ✅ | ✅ | ✅ | ✅ |
| Add / edit appointments | ✅ | ✅ | ✅ | ❌ |
| Delete appointments | ✅ | ✅ | ❌ | ❌ |
| View EMR records | ✅ | ✅ | ✅ | ❌ |
| Add / edit EMR records | ✅ | ✅ | ❌ | ❌ |
| Delete EMR records | ✅ | ❌ | ❌ | ❌ |
| View billing | ✅ | ❌ | ❌ | ✅ |
| Add / edit billing | ✅ | ❌ | ❌ | ✅ |
| Delete billing | ✅ | ❌ | ❌ | ❌ |
| View alerts | ✅ | ✅ | ✅ | ✅ |
| User management | ✅ | ❌ | ❌ | ❌ |

### Files to Create / Modify

**1. Add `requireRole()` to `BaseController.php`:**
```php
/**
 * Halt execution if the logged-in user's role is not in $allowedRoles.
 * Always call requireLogin() before requireRole().
 *
 * @param string|array $allowedRoles  e.g. 'admin' or ['admin','doctor']
 */
protected function requireRole($allowedRoles): void {
    $role = $_SESSION['user']['role'] ?? '';
    $allowed = (array) $allowedRoles;
    if (!in_array($role, $allowed, true)) {
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'You do not have permission to perform this action.'
            ], 403);
        }
        $_SESSION['flash_error'] = 'Access denied. You do not have permission for that action.';
        $this->redirect('/dashboard');
        exit;
    }
}
```

Also add a helper to expose the current role to views:
```php
protected function currentRole(): string {
    return $_SESSION['user']['role'] ?? 'staff';
}
```

**2. Apply `requireRole()` in all three CRUD controllers:**

In `PatientController`:
```php
public function store()  { $this->requireLogin(); $this->requireRole(['admin','doctor','nurse']); ... }
public function update() { $this->requireLogin(); $this->requireRole(['admin','doctor','nurse']); ... }
public function delete() { $this->requireLogin(); $this->requireRole('admin'); ... }
```

In `DoctorController`:
```php
public function store()  { $this->requireLogin(); $this->requireRole('admin'); ... }
public function update() { $this->requireLogin(); $this->requireRole('admin'); ... }
public function delete() { $this->requireLogin(); $this->requireRole('admin'); ... }
```

In `AppointmentController`:
```php
public function store()  { $this->requireLogin(); $this->requireRole(['admin','doctor','nurse']); ... }
public function update() { $this->requireLogin(); $this->requireRole(['admin','doctor','nurse']); ... }
public function delete() { $this->requireLogin(); $this->requireRole(['admin','doctor']); ... }
```

**3. Hide action buttons in views based on role:**

Pass `$currentRole` to every index view from each controller's `index()`:
```php
$this->render('patients/index', [
    'patients'    => $patients,
    'title'       => 'Patients',
    'currentRole' => $this->currentRole()
]);
```

In `patients/index.php`, `doctors/index.php`, `appointments/index.php`,
wrap Add / Edit / Delete buttons in role checks:
```php
// Show Add button only to roles that can create
<?php if (in_array($currentRole, ['admin','doctor','nurse'])): ?>
  <button id="addPatientBtn" ...>Add Patient</button>
<?php endif; ?>

// Show Edit/Delete buttons per-row
<?php if (in_array($currentRole, ['admin','doctor','nurse'])): ?>
  <button class="btn-edit" ...>Edit</button>
<?php endif; ?>
<?php if ($currentRole === 'admin'): ?>
  <button class="btn-delete" ...>Delete</button>
<?php endif; ?>
```

**4. Create `UserController.php` (admin only):**

New file: `medical/app/Controllers/UserController.php`
- `index()` — list all users, admin only
- `store()` — create user with name, email, password (bcrypt), role
- `update()` — edit user name, email, role; optionally reset password
- `delete()` — delete user (prevent deleting own account)
All methods: `requireLogin()` → `requireRole('admin')` → logic.

**5. Create `medical/app/Views/users/index.php`:**
- DataTables table listing all users (id, name, email, role, created_at)
- Bootstrap Modal for Add / Edit (same pattern as patients/doctors)
- Role dropdown: admin / doctor / nurse / staff
- Add User and Edit User buttons (admin only, so no role check needed in view)
- Delete button with SweetAlert2 confirmation: "This will permanently
  remove the user's access to the system."

**6. Register routes in `App.php`:**
```php
$this->router->get('/users',          'UserController@index');
$this->router->post('/users/store',   'UserController@store');
$this->router->get('/users/edit',     'UserController@edit');
$this->router->post('/users/update',  'UserController@update');
$this->router->post('/users/delete',  'UserController@delete');
```

**7. Add "Users" link to `main.php` navbar (admin only):**
```php
<?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
  <a class="nav-link" href="<?php echo url('/users'); ?>">
    <i class="fas fa-users-cog me-1"></i>Users
  </a>
<?php endif; ?>
```

### Verification
```
grep -c "requireRole" medical/app/Controllers/PatientController.php
# Expected: 3 (store, update, delete)
grep -c "requireRole" medical/app/Controllers/DoctorController.php
# Expected: 3
grep -c "requireRole" medical/app/Controllers/AppointmentController.php
# Expected: 3
grep -c "requireRole" medical/app/Controllers/UserController.php
# Expected: 5 (index, store, edit, update, delete)
```
Test: log in as `staff` → try navigating to `/patients/store` via curl
with AJAX header → must return JSON 403. Log in as `admin` → same
request must succeed.

---
---

## Prompt 2 — Electronic Medical Records (EMR)

### Context
The current `patients` table stores only demographic data (name, email,
phone, address, date_of_birth, gender). There is no table for medical
history, diagnoses, prescriptions, or visit notes. This prompt adds a
full EMR module linked to the existing `patients` table.

The system uses PHP MVC with `BaseController`, PDO prepared statements,
AJAX modals, DataTables, and SweetAlert2. Follow the exact same patterns
used in `PatientController` and `Patient.php`.

### Files to Create

**1. Migration: `medical/database/migrations/005_create_emr_table.php`**
```sql
CREATE TABLE IF NOT EXISTS emr_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    visit_date      DATE NOT NULL,
    chief_complaint TEXT NOT NULL,
    diagnosis       TEXT NOT NULL,
    prescription    TEXT,
    notes           TEXT,
    blood_pressure  VARCHAR(20)  DEFAULT NULL,  -- e.g. "120/80"
    temperature     DECIMAL(4,1) DEFAULT NULL,  -- Celsius
    heart_rate      INT          DEFAULT NULL,  -- bpm
    weight          DECIMAL(5,2) DEFAULT NULL,  -- kg
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_visit_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**2. Model: `medical/app/Models/Emr.php`**

Properties: `$id`, `$patient_id`, `$doctor_id`, `$visit_date`,
`$chief_complaint`, `$diagnosis`, `$prescription`, `$notes`,
`$blood_pressure`, `$temperature`, `$heart_rate`, `$weight`.

Methods (all using PDO prepared statements — no string interpolation):
- `getAll(): PDOStatement` — SELECT with patient name and doctor name via
  JOIN, ORDER BY visit_date DESC
- `getByPatient(int $patientId): PDOStatement` — filter by patient
- `getById(int $id): bool` — hydrate properties, return true/false
- `create(): bool` — INSERT, set `$this->id = lastInsertId()`
- `update(): bool` — UPDATE WHERE id
- `delete(): bool` — DELETE WHERE id

Sanitize all string inputs with `htmlspecialchars(strip_tags())` before
binding, matching the pattern in `Patient.php`.

**3. Controller: `medical/app/Controllers/EmrController.php`**
```php
class EmrController extends BaseController {
    public function index() {
        $this->requireLogin();
        $this->requireRole(['admin','doctor','nurse']);
        // Fetch all EMR records with patient + doctor names
        // Pass allPatients and allDoctors arrays for modal dropdowns
        // (same pattern as AppointmentController::index())
        $this->render('emr/index', [
            'records'     => $records,
            'allPatients' => $allPatients,
            'allDoctors'  => $allDoctors,
            'currentRole' => $this->currentRole(),
            'title'       => 'Medical Records'
        ]);
    }

    public function store()  { $this->requireLogin(); $this->requireRole(['admin','doctor']); ... }
    public function edit()   { $this->requireLogin(); $this->requireRole(['admin','doctor','nurse']); ... }
    public function update() { $this->requireLogin(); $this->requireRole(['admin','doctor']); ... }
    public function delete() { $this->requireLogin(); $this->requireRole('admin'); ... }
}
```

AJAX branching via `$this->isAjax()` on all write actions, identical to
`PatientController` pattern. Return `data` object on success containing
all fields needed to rebuild the DataTables row.

**4. View: `medical/app/Views/emr/index.php`**

- DataTables table: ID | Patient | Doctor | Visit Date | Diagnosis |
  Blood Pressure | Actions
- Bootstrap Modal (single, dynamic — Add and Edit share it)
- Form fields: patient_id (dropdown), doctor_id (dropdown), visit_date,
  chief_complaint (textarea), diagnosis (textarea), prescription
  (textarea), notes (textarea), blood_pressure, temperature, heart_rate,
  weight
- Embed `PATIENTS` and `DOCTORS` as PHP→JS JSON at page top (same as
  `appointments/index.php`)
- Role-gated buttons: Edit visible to admin+doctor, Delete to admin only
- Empty state: `<i class="fas fa-file-medical"></i>` icon with message
  "No medical records yet."

**5. Routes in `App.php`:**
```php
$this->router->get('/emr',          'EmrController@index');
$this->router->post('/emr/store',   'EmrController@store');
$this->router->get('/emr/edit',     'EmrController@edit');
$this->router->post('/emr/update',  'EmrController@update');
$this->router->post('/emr/delete',  'EmrController@delete');
```

**6. Navbar link in `main.php`:**
```php
<a class="nav-link" href="<?php echo url('/emr'); ?>">
  <i class="fas fa-file-medical me-1"></i>Medical Records
</a>
```

**7. Patient profile link (optional enhancement):**
In `patients/index.php`, add a View Records button per row that navigates
to `/emr?patient_id=X` to show only that patient's records (handled by
`EmrController::index()` reading `$_GET['patient_id']` and calling
`$emrModel->getByPatient($patientId)` instead of `getAll()`).

### Verification
```
# Migration file exists
ls medical/database/migrations/005_create_emr_table.php

# Model exists with all required methods
grep -c "public function" medical/app/Models/Emr.php
# Expected: 6 (getAll, getByPatient, getById, create, update, delete)

# Controller exists with all actions
grep -c "public function" medical/app/Controllers/EmrController.php
# Expected: 5 (index, store, edit, update, delete)

# Routes registered
grep "emr" medical/app/Core/App.php | wc -l
# Expected: 5

# No raw $_POST in SQL inside Emr.php
grep '\$_POST\|\$_GET' medical/app/Models/Emr.php
# Expected: no output
```

---
---

## Prompt 3 — Billing Module

### Context
There is currently no billing or invoice system. This prompt adds a
Billing module that creates invoices linked to patients and appointments,
tracks payment status, and allows staff and admins to manage billing
records.

Per the RBAC matrix: admin and staff can view/add/edit billing. Only
admin can delete. Doctors and nurses have no billing access.

The system uses PHP MVC with `BaseController`, PDO prepared statements,
AJAX modals, DataTables, and SweetAlert2.

### Files to Create

**1. Migration: `medical/database/migrations/006_create_billing_table.php`**
```sql
CREATE TABLE IF NOT EXISTS billing (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    appointment_id  INT DEFAULT NULL,
    invoice_number  VARCHAR(50) UNIQUE NOT NULL,  -- auto-generated
    service_description TEXT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) DEFAULT 0.00,
    tax             DECIMAL(10,2) DEFAULT 0.00,
    total_amount    DECIMAL(10,2) NOT NULL,       -- amount - discount + tax
    payment_status  ENUM('unpaid','paid','partial','cancelled') DEFAULT 'unpaid',
    payment_method  ENUM('cash','card','insurance','online') DEFAULT NULL,
    payment_date    DATE DEFAULT NULL,
    notes           TEXT,
    created_by      INT DEFAULT NULL,             -- FK to users.id
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE RESTRICT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    INDEX idx_patient_id     (patient_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**2. Model: `medical/app/Models/Billing.php`**

Properties: `$id`, `$patient_id`, `$appointment_id`, `$invoice_number`,
`$service_description`, `$amount`, `$discount`, `$tax`, `$total_amount`,
`$payment_status`, `$payment_method`, `$payment_date`, `$notes`,
`$created_by`.

Methods:
- `getAll(): PDOStatement` — JOIN patients (get patient name),
  ORDER BY created_at DESC
- `getById(int $id): bool` — hydrate all properties
- `getByPatient(int $patientId): PDOStatement`
- `create(): bool` — auto-generate `invoice_number` as
  `'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5))`
  before INSERT. Calculate `total_amount = amount - discount + tax`.
- `update(): bool` — recalculate `total_amount` on every update
- `delete(): bool`
- `getSummary(): array` — return `['total_invoiced', 'total_collected',
  'total_unpaid', 'invoice_count']` for dashboard widget

**3. Controller: `medical/app/Controllers/BillingController.php`**
```php
class BillingController extends BaseController {
    public function index() {
        $this->requireLogin();
        $this->requireRole(['admin','staff']);
        // Fetch all billing records with patient names
        // Pass allPatients for dropdown
        $this->render('billing/index', [
            'records'     => $records,
            'allPatients' => $allPatients,
            'currentRole' => $this->currentRole(),
            'title'       => 'Billing'
        ]);
    }

    public function store()  { $this->requireLogin(); $this->requireRole(['admin','staff']); ... }
    public function edit()   { $this->requireLogin(); $this->requireRole(['admin','staff']); ... }
    public function update() { $this->requireLogin(); $this->requireRole(['admin','staff']); ... }
    public function delete() { $this->requireLogin(); $this->requireRole('admin'); ... }
}
```

AJAX branching on all write actions. Return `data` object on success
containing all fields needed to rebuild the DataTables row, including
the auto-generated `invoice_number` and calculated `total_amount`.

**4. View: `medical/app/Views/billing/index.php`**

DataTables table columns: Invoice # | Patient | Amount | Discount | Tax |
Total | Status | Payment Method | Date | Actions

Payment status badge colors:
- `unpaid` → `bg-danger`
- `paid` → `bg-success`
- `partial` → `bg-warning`
- `cancelled` → `bg-secondary`

Bootstrap Modal fields:
- `patient_id` (dropdown, from embedded `PATIENTS` JS var)
- `service_description` (textarea)
- `amount` (number input, step 0.01)
- `discount` (number input, default 0)
- `tax` (number input, default 0)
- `payment_status` (select: unpaid/paid/partial/cancelled)
- `payment_method` (select: cash/card/insurance/online)
- `payment_date` (date input, required when status = paid or partial)
- `notes` (textarea, optional)

Note: `invoice_number` and `total_amount` are calculated server-side —
do not show them as editable fields in the modal.

Role-gated: Edit visible to admin+staff, Delete to admin only.

Empty state: `<i class="fas fa-file-invoice-dollar"></i>` icon with
message "No billing records yet."

**5. Routes in `App.php`:**
```php
$this->router->get('/billing',          'BillingController@index');
$this->router->post('/billing/store',   'BillingController@store');
$this->router->get('/billing/edit',     'BillingController@edit');
$this->router->post('/billing/update',  'BillingController@update');
$this->router->post('/billing/delete',  'BillingController@delete');
```

**6. Navbar link in `main.php` (admin + staff only):**
```php
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin','staff'])): ?>
  <a class="nav-link" href="<?php echo url('/billing'); ?>">
    <i class="fas fa-file-invoice-dollar me-1"></i>Billing
  </a>
<?php endif; ?>
```

**7. Dashboard billing summary widget:**

In `DashboardController::stats()` or `AuthController::dashboard()`,
call `$billingModel->getSummary()` and pass it to `dashboard.php`.
Add a new KPI row to the dashboard showing:
- Total Invoiced (sum of all total_amount)
- Total Collected (sum where payment_status = 'paid')
- Outstanding (sum where payment_status = 'unpaid' or 'partial')

### Verification
```
# Migration exists
ls medical/database/migrations/006_create_billing_table.php

# Model has all methods
grep -c "public function" medical/app/Models/Billing.php
# Expected: 7 (getAll, getById, getByPatient, create, update, delete, getSummary)

# Invoice number auto-generated in create()
grep "INV-\|invoice_number\|uniqid" medical/app/Models/Billing.php
# Expected: matches found

# total_amount calculated in create() and update()
grep "total_amount" medical/app/Models/Billing.php
# Expected: at least 2 matches

# Controller exists with all actions
grep -c "public function" medical/app/Controllers/BillingController.php
# Expected: 5

# Routes registered
grep "billing" medical/app/Core/App.php | wc -l
# Expected: 5

# Doctor/nurse cannot access billing (RBAC)
grep "requireRole" medical/app/Controllers/BillingController.php
# Every action must show admin and/or staff — never doctor or nurse
```

---

## Implementation Order

```
1. Prompt 1 (RBAC)   → Must be done first. EMR and Billing depend on requireRole().
2. Prompt 2 (EMR)    → After RBAC is verified working.
3. Prompt 3 (Billing) → After RBAC is verified working.
```

EMR and Billing (Prompts 2 and 3) can be implemented in parallel once
RBAC is in place, as they have no dependency on each other.

## Architecture Reminders (apply to all three)
- All `fetch()` calls in new view JS must use the `ajaxPost()` helper
- All new POST controller actions must call `$this->validateCsrf()` after `requireRole()`
- All new model methods must use PDO prepared statements — no string interpolation in SQL
- All new view output must be wrapped in `htmlspecialchars()`
- New routes go in `App.php::initRoutes()` only
- IoT JS in `main.php` must not be touched
