<div class="page-header">
  <div>
    <h1><i class="fas fa-search"></i>Global Search</h1>
    <div class="page-subtitle">Search across patients, doctors, EMR, and billing records</div>
  </div>
</div>

<!-- Search form -->
<form method="GET" action="<?php echo url('/search'); ?>" class="mb-4">
  <div class="input-group input-group-lg" style="max-width: 700px;">
    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, email, phone, diagnosis, etc." value="<?php echo htmlspecialchars($q); ?>" autofocus>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($q): ?>
    <a href="<?php echo url('/search'); ?>" class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </div>

  <!-- Filter tabs -->
  <div class="btn-group mt-3" role="group">
    <?php
    $tabs = ['all'=>'All', 'patients'=>'Patients', 'doctors'=>'Doctors', 'emr'=>'EMR', 'billing'=>'Billing'];
    foreach ($tabs as $key=>$label): ?>
      <input type="radio" class="btn-check" name="tab" id="tab_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $tab===$key?'checked':''; ?> onchange="this.form.submit()">
      <label class="btn btn-outline-secondary" for="tab_<?php echo $key; ?>"><?php echo $label; ?></label>
    <?php endforeach; ?>
  </div>
</form>

<?php if ($q): ?>
  <?php
  $hasResults = !empty($patientsResults) || !empty($doctorsResults) || !empty($emrResults) || !empty($billingResults);
  if (!$hasResults):
  ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No results found for "<?php echo htmlspecialchars($q); ?>"
    </div>
  <?php else: ?>

    <!-- Patients -->
    <?php if (!empty($patientsResults) && in_array($tab, ['all','patients'])): ?>
    <h5 class="mt-4 mb-3"><i class="fas fa-user-injured me-2 text-primary"></i>Patients (<?php echo count($patientsResults); ?>)</h5>
    <div class="table-responsive mb-4">
      <table class="table table-hover">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($patientsResults as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['email']); ?></td>
            <td><?php echo htmlspecialchars($p['phone']); ?></td>
            <td><?php echo htmlspecialchars($p['address']); ?></td>
            <td><a href="<?php echo url('/patients/view?id=' . $p['id']); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Doctors -->
    <?php if (!empty($doctorsResults) && in_array($tab, ['all','doctors'])): ?>
    <h5 class="mt-4 mb-3"><i class="fas fa-user-md me-2 text-success"></i>Doctors (<?php echo count($doctorsResults); ?>)</h5>
    <div class="table-responsive mb-4">
      <table class="table table-hover">
        <thead><tr><th>Name</th><th>Email</th><th>Specialization</th><th>Phone</th></tr></thead>
        <tbody>
          <?php foreach ($doctorsResults as $d): ?>
          <tr>
            <td><?php echo htmlspecialchars($d['name']); ?></td>
            <td><?php echo htmlspecialchars($d['email']); ?></td>
            <td><?php echo htmlspecialchars($d['specialization']); ?></td>
            <td><?php echo htmlspecialchars($d['phone']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- EMR -->
    <?php if (!empty($emrResults) && in_array($tab, ['all','emr'])): ?>
    <h5 class="mt-4 mb-3"><i class="fas fa-file-medical me-2 text-info"></i>EMR Records (<?php echo count($emrResults); ?>)</h5>
    <div class="row g-3 mb-4">
      <?php foreach ($emrResults as $e): ?>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title"><?php echo htmlspecialchars($e['patient_name']); ?></h6>
            <p class="card-text small mb-1"><strong>Diagnosis:</strong> <?php echo htmlspecialchars($e['diagnosis']); ?></p>
            <p class="card-text small mb-1"><strong>Treatment:</strong> <?php echo htmlspecialchars($e['treatment']); ?></p>
            <p class="card-text small text-muted"><?php echo date('M d, Y', strtotime($e['created_at'])); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Billing -->
    <?php if (!empty($billingResults) && in_array($tab, ['all','billing'])): ?>
    <h5 class="mt-4 mb-3"><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Billing Records (<?php echo count($billingResults); ?>)</h5>
    <div class="table-responsive mb-4">
      <table class="table table-hover">
        <thead><tr><th>Invoice #</th><th>Patient</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($billingResults as $b): ?>
          <tr>
            <td>#<?php echo $b['id']; ?></td>
            <td><?php echo htmlspecialchars($b['patient_name']); ?></td>
            <td>₱<?php echo number_format($b['total_amount'], 2); ?></td>
            <td><span class="badge bg-<?php echo $b['payment_status']==='paid'?'success':'warning';"><?php echo $b['payment_status']; ?></span></td>
            <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <?php endif; ?>
<?php endif; ?>
