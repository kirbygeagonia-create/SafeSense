<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-0">Dashboard</h1>
    <small class="text-muted">Welcome back — <?php echo date('l, F j, Y'); ?></small>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <!-- Task 2 — Convert first 4 stat cards -->
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Patients</div>
          <div class="stat-value"><?php echo $patientCount ?? 0; ?></div>
        </div>
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
          <i class="fas fa-user-injured"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Doctors</div>
          <div class="stat-value"><?php echo $doctorCount ?? 0; ?></div>
        </div>
        <div class="stat-icon bg-success bg-opacity-10 text-success">
          <i class="fas fa-user-md"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Appointments</div>
          <div class="stat-value"><?php echo $appointmentCount ?? 0; ?></div>
        </div>
        <div class="stat-icon bg-info bg-opacity-10 text-info">
          <i class="fas fa-calendar-check"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <a href="<?php echo url('/alerts'); ?>" class="text-decoration-none">
      <div class="stat-card border-danger border-start border-4">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Unread Alerts</div>
            <div class="stat-value text-danger"><?php echo $unreadAlerts ?? 0; ?></div>
          </div>
          <div class="stat-icon bg-danger bg-opacity-10 text-danger">
            <i class="fas fa-satellite-dish"></i>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Billing summary row -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card border-primary border-start border-4">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Invoiced</div>
          <div class="stat-value"><?php echo number_format((float)($billingSummary['total_invoiced'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
          <i class="fas fa-file-invoice-dollar"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card border-success border-start border-4">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Collected</div>
          <div class="stat-value"><?php echo number_format((float)($billingSummary['total_collected'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-success bg-opacity-10 text-success">
          <i class="fas fa-hand-holding-usd"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card border-warning border-start border-4">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Unpaid</div>
          <div class="stat-value"><?php echo number_format((float)($billingSummary['total_unpaid'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card border-info border-start border-4">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Invoice Count</div>
          <div class="stat-value"><?php echo (int)($billingSummary['invoice_count'] ?? 0); ?></div>
        </div>
        <div class="stat-icon bg-info bg-opacity-10 text-info">
          <i class="fas fa-receipt"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main content row -->
<div class="row g-4">

  <!-- Recent SafeSense Alerts -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold">
          <span class="ss-live-dot"></span>SafeSense Live Alerts
        </h6>
        <a href="<?php echo url('/alerts'); ?>" class="btn btn-sm btn-outline-danger">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($recentAlerts)): ?>
          <?php foreach ($recentAlerts as $a):
            $lvl    = $a['alert_level'];
            $badge  = $lvl==='critical'?'danger':($lvl==='danger'?'warning':'info');
            $icon   = $lvl==='critical'?'fa-skull-crossbones':($lvl==='danger'?'fa-exclamation-triangle':'fa-cloud-rain');
            $label  = $lvl==='critical'?'🔴 CRITICAL':($lvl==='danger'?'🟠 DANGER':'🟡 WARNING');
            $dt     = new DateTime($a['created_at']);
          ?>
          <div class="d-flex align-items-start gap-3 p-3 border-bottom <?php echo !$a['is_read']?'bg-light':''; ?>">
            <div class="rounded-circle bg-<?php echo $badge; ?> bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;">
              <i class="fas <?php echo $icon; ?> text-<?php echo $badge; ?> fa-sm"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-<?php echo $badge; ?> badge-sm"><?php echo $label; ?></span>
                <?php if(!$a['is_read']): ?><span class="badge bg-primary badge-sm">NEW</span><?php endif; ?>
              </div>
              <div class="text-truncate small fw-medium"><?php echo htmlspecialchars($a['message']); ?></div>
              <div class="text-muted" style="font-size:.75rem;">
                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($a['location_name']??'—'); ?>
                &nbsp;·&nbsp;
                <i class="fas fa-clock me-1"></i><?php echo $dt->format('h:i A'); ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center py-4 text-muted">
            <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
            No recent alerts — system nominal.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Upcoming appointments -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-check me-2 text-primary"></i>Upcoming Appointments</h6>
        <a href="<?php echo url('/appointments'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($upcomingAppointments)): ?>
          <?php foreach ($upcomingAppointments as $appt):
            $status = $appt['status'] ?? 'pending';
            $sc = $status==='confirmed'?'success':($status==='pending'?'warning':'secondary');
          ?>
          <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;">
              <i class="fas fa-user text-primary fa-sm"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-medium small"><?php echo htmlspecialchars($appt['patient_name']??'—'); ?></div>
              <div class="text-muted" style="font-size:.75rem;">
                <?php echo htmlspecialchars($appt['appointment_date']??''); ?>
                &nbsp;<?php echo htmlspecialchars($appt['appointment_time']??''); ?>
              </div>
            </div>
            <span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst($status); ?></span>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center py-4 text-muted">
            <i class="fas fa-calendar-times fa-2x mb-2 d-block text-muted"></i>
            No upcoming appointments.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js Analytics -->
<div class="row g-4 mt-2">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2 text-danger"></i>Alerts Over Time (30 Days)</h6>
      </div>
      <div class="card-body" id="alertsChartWrap">
        <canvas id="alertsChart" height="200"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Appointments by Week (8 Weeks)</h6>
      </div>
      <div class="card-body" id="appointmentsChartWrap">
        <canvas id="appointmentsChart" height="200"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  ajax(window.BASE_URL + '/api/dashboard/stats')
    .then(data => {
      if (!data.success) return;

      // Task 7 — Alerts chart with empty-state fallback
      const alertLabels = (data.alerts || []).map(r => r.date);
      const alertValues = (data.alerts || []).map(r => parseInt(r.count));
      if (alertLabels.length === 0) {
        document.getElementById('alertsChart').style.display = 'none';
        const p = document.createElement('p');
        p.className = 'text-center text-muted py-4';
        p.textContent = 'No data yet.';
        document.getElementById('alertsChartWrap').appendChild(p);
      } else {
        new Chart(document.getElementById('alertsChart'), {
          type: 'line',
          data: {
            labels: alertLabels,
            datasets: [{
              label: 'Alerts',
              data: alertValues,
              borderColor: '#dc2626',
              backgroundColor: 'rgba(220,38,38,0.1)',
              fill: true,
              tension: 0.3,
              pointRadius: 4,
              pointBackgroundColor: '#dc2626'
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { stepSize: 1 } },
              x: { ticks: { maxTicksLimit: 10 } }
            }
          }
        });
      }

      // Task 7 — Appointments chart with empty-state fallback
      const apptLabels = (data.appointments || []).map(r => r.week_start || r.yw);
      const apptValues = (data.appointments || []).map(r => parseInt(r.count));
      if (apptLabels.length === 0) {
        document.getElementById('appointmentsChart').style.display = 'none';
        const p = document.createElement('p');
        p.className = 'text-center text-muted py-4';
        p.textContent = 'No data yet.';
        document.getElementById('appointmentsChartWrap').appendChild(p);
      } else {
        new Chart(document.getElementById('appointmentsChart'), {
          type: 'bar',
          data: {
            labels: apptLabels,
            datasets: [{
              label: 'Appointments',
              data: apptValues,
              backgroundColor: 'rgba(37,99,235,0.7)',
              borderColor: '#2563eb',
              borderWidth: 1,
              borderRadius: 6
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { stepSize: 1 } },
              x: { ticks: { maxTicksLimit: 8 } }
            }
          }
        });
      }
    })
    .catch(() => {});
})();
</script>
