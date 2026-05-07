<div class="page-header">
  <div>
    <h1><i class="fas fa-chart-bar"></i>Reports &amp; Analytics</h1>
    <div class="page-subtitle">System-wide statistics and trend analysis</div>
  </div>
  <div class="text-muted ss-report-meta">
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
    // TASK-3G: Revenue only visible to admin
    ['label'=>'Total Revenue',      'val'=>'₱'.number_format($totals['revenue']??0,0),      'icon'=>'fa-hand-holding-usd',   'color'=>'#15803d', 'admin_only'=>true],
    ['label'=>'Collected',          'val'=>'₱'.number_format($totals['collected']??0,0),    'icon'=>'fa-check-circle',       'color'=>'#15803d', 'admin_only'=>true],
    ['label'=>'IoT Alerts',         'val'=>$totals['alerts']??0,                             'icon'=>'fa-satellite-dish',     'color'=>'#b91c1c'],
  ];
  foreach ($summaryCards as $c):
    // Skip revenue cards for non-admin users
    if (!empty($c['admin_only']) && ($_SESSION['user']['role'] ?? '') !== 'admin') continue;
  ?>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div class="stat-label"><?php echo $c['label']; ?></div>
        <div class="stat-icon" style="background:<?php echo $c['color']; ?>18; color:<?php echo $c['color']; ?>;">
          <i class="fas <?php echo $c['icon']; ?>"></i>
        </div>
      </div>
      <div class="ss-stat-value-md"><?php echo $c['val']; ?></div>
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
  <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
  <!-- TASK-3G: Revenue chart only for admin -->
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
  <?php else: ?>
  <!-- Non-admin: only show alerts chart -->
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-satellite-dish me-2"></i>Alerts by Level</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="chartAlerts" height="160"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>
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
