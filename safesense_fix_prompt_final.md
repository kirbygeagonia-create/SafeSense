# SafeSense — Final Fix Pass (Round 4)
## Last 2 Remaining Issues

This is the final fix pass. Only **2 targeted changes** are needed. Apply both, run the verification checklist, and confirm every item passes before closing.

---

## Context: What's already done ✓

- `.env` is now untracked from git (confirmed via `git rm --cached` + commit `3c01437`)
- The old key `c3afe5b8...` in git history is neutralised because you rotated to a new key — no further action needed on history
- `AppointmentController::edit()` already correctly renders `appointments/edit.php` for non-AJAX requests — **do not touch it**
- `PatientController` and `DoctorController` are also already correct — **do not touch them**

---

## FIX 1 — EmrController::edit() doesn't render view for non-AJAX (Functional)

**File:** `medical/app/Controllers/EmrController.php`

**Problem:** After the AJAX `jsonResponse()` block exits, the method falls through to `$this->redirect('/emr')` for non-AJAX browser requests. The view `emr/edit.php` exists but is never reached.

**Find this exact block** (the end of the `edit()` method, after the `if ($this->isAjax())` block):

```php
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'              => $this->emrModel->id,
                    'patient_id'      => $this->emrModel->patient_id,
                    'doctor_id'       => $this->emrModel->doctor_id,
                    'visit_date'      => $this->emrModel->visit_date,
                    'chief_complaint' => $this->emrModel->chief_complaint,
                    'diagnosis'       => $this->emrModel->diagnosis,
                    'prescription'    => $this->emrModel->prescription,
                    'notes'           => $this->emrModel->notes,
                    'blood_pressure'  => $this->emrModel->blood_pressure,
                    'temperature'     => $this->emrModel->temperature,
                    'heart_rate'      => $this->emrModel->heart_rate,
                    'weight'          => $this->emrModel->weight
                ]
            ]);
        }
        $this->redirect('/emr');
    }
```

**Replace only the last two lines** (`$this->redirect('/emr');` and the closing `}`) with:

```php
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'              => $this->emrModel->id,
                    'patient_id'      => $this->emrModel->patient_id,
                    'doctor_id'       => $this->emrModel->doctor_id,
                    'visit_date'      => $this->emrModel->visit_date,
                    'chief_complaint' => $this->emrModel->chief_complaint,
                    'diagnosis'       => $this->emrModel->diagnosis,
                    'prescription'    => $this->emrModel->prescription,
                    'notes'           => $this->emrModel->notes,
                    'blood_pressure'  => $this->emrModel->blood_pressure,
                    'temperature'     => $this->emrModel->temperature,
                    'heart_rate'      => $this->emrModel->heart_rate,
                    'weight'          => $this->emrModel->weight
                ]
            ]);
        }

        $this->render('emr/edit', [
            'title'  => 'Edit Medical Record',
            'record' => $this->emrModel,
        ]);
    }
```

**What changed:** `$this->redirect('/emr');` → `$this->render('emr/edit', [...]);`
Everything else in the method stays exactly the same.

---

## FIX 2 — BillingController::edit() doesn't render view for non-AJAX (Functional)

**File:** `medical/app/Controllers/BillingController.php`

**Problem:** Same issue — after the AJAX block, the method falls through to `$this->redirect('/billing')`. The view `billing/edit.php` exists but is never reached.

**Find this exact block** (the end of the `edit()` method):

```php
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'                  => $this->billingModel->id,
                    'patient_id'          => $this->billingModel->patient_id,
                    'appointment_id'      => $this->billingModel->appointment_id,
                    'invoice_number'      => $this->billingModel->invoice_number,
                    'service_description' => $this->billingModel->service_description,
                    'amount'              => $this->billingModel->amount,
                    'discount'            => $this->billingModel->discount,
                    'tax'                 => $this->billingModel->tax,
                    'total_amount'        => $this->billingModel->total_amount,
                    'payment_status'      => $this->billingModel->payment_status,
                    'payment_method'      => $this->billingModel->payment_method,
                    'payment_date'        => $this->billingModel->payment_date,
                    'notes'               => $this->billingModel->notes
                ]
            ]);
        }
        $this->redirect('/billing');
    }
```

**Replace only the last two lines** (`$this->redirect('/billing');` and the closing `}`) with:

```php
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'                  => $this->billingModel->id,
                    'patient_id'          => $this->billingModel->patient_id,
                    'appointment_id'      => $this->billingModel->appointment_id,
                    'invoice_number'      => $this->billingModel->invoice_number,
                    'service_description' => $this->billingModel->service_description,
                    'amount'              => $this->billingModel->amount,
                    'discount'            => $this->billingModel->discount,
                    'tax'                 => $this->billingModel->tax,
                    'total_amount'        => $this->billingModel->total_amount,
                    'payment_status'      => $this->billingModel->payment_status,
                    'payment_method'      => $this->billingModel->payment_method,
                    'payment_date'        => $this->billingModel->payment_date,
                    'notes'               => $this->billingModel->notes
                ]
            ]);
        }

        $this->render('billing/edit', [
            'title'  => 'Edit Invoice',
            'record' => $this->billingModel,
        ]);
    }
```

**What changed:** `$this->redirect('/billing');` → `$this->render('billing/edit', [...]);`
Everything else in the method stays exactly the same.

---

## VERIFICATION CHECKLIST

Run every check. If anything fails, fix and re-run from the top.

### Step 1 — Confirm only 2 files were changed

```
[ ] git diff --name-only HEAD shows exactly:
    medical/app/Controllers/EmrController.php
    medical/app/Controllers/BillingController.php

    No other files should be modified.
```

### Step 2 — EmrController code check

Open `medical/app/Controllers/EmrController.php` and read the `edit()` method end.

```
[ ] The last statement before the closing } of edit() is:
    $this->render('emr/edit', ['title' => 'Edit Medical Record', 'record' => $this->emrModel]);

[ ] The line $this->redirect('/emr'); is GONE from inside edit()
    (it may still exist in other methods — that's fine)

[ ] The AJAX jsonResponse() block above it is completely unchanged

[ ] The method still starts with:
    $this->requireLogin();
    $this->requireRole(['admin','doctor','nurse']);
```

### Step 3 — BillingController code check

Open `medical/app/Controllers/BillingController.php` and read the `edit()` method end.

```
[ ] The last statement before the closing } of edit() is:
    $this->render('billing/edit', ['title' => 'Edit Invoice', 'record' => $this->billingModel]);

[ ] The line $this->redirect('/billing'); is GONE from inside edit()
    (it may still exist in other methods — that's fine)

[ ] The AJAX jsonResponse() block above it is completely unchanged

[ ] The method still starts with:
    $this->requireLogin();
    $this->requireRole(['admin','staff']);
```

### Step 4 — View variable name cross-check

```
[ ] medical/app/Views/emr/edit.php uses $record-> (not $emr-> or $this->)
    Confirm: grep "$record->" medical/app/Views/emr/edit.php returns results

[ ] medical/app/Views/billing/edit.php uses $record-> (not $billing-> or $this->)
    Confirm: grep "$record->" medical/app/Views/billing/edit.php returns results
```

### Step 5 — Regression check (read, don't skip)

```
[ ] AppointmentController::edit() is UNCHANGED — it still ends with:
    $this->render('appointments/edit', ['title' => ..., 'appointment' => ..., 'patients' => ..., 'doctors' => ...]);
    Do NOT accidentally modify this file.

[ ] PatientController::edit() is UNCHANGED
[ ] DoctorController::edit() is UNCHANGED

[ ] EmrController::update() is UNCHANGED (the method after edit())
[ ] BillingController::update() is UNCHANGED (the method after edit())
```

### Step 6 — Final gate

```
[ ] All 5 steps above passed with no failures
[ ] git status shows only EmrController.php and BillingController.php modified
[ ] Commit the changes: git commit -m "fix: render edit views for non-AJAX EMR and Billing requests"
```

---

## Summary

| File | Change |
|------|--------|
| `medical/app/Controllers/EmrController.php` | Replace `redirect('/emr')` with `render('emr/edit', ...)` at end of `edit()` |
| `medical/app/Controllers/BillingController.php` | Replace `redirect('/billing')` with `render('billing/edit', ...)` at end of `edit()` |

**2 files. 1 line changed in each. That's it — SafeSense is complete.**
