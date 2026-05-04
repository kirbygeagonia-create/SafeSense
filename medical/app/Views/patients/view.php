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
