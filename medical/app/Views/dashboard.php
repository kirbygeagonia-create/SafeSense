<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-0">Dashboard</h1>
    <small class="text-muted">Welcome back — <?php echo date('l, F j, Y'); ?></small>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small mb-1">Total Patients</div>
          <h2 class="mb-0 fw-bold"><?php echo $patientCount ?? 0; ?></h2>
        </div>
        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
          <i class="fas fa-user-injured text-primary fa-lg"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small mb-1">Total Doctors</div>
          <h2 class="mb-0 fw-bold"><?php echo $doctorCount ?? 0; ?></h2>
        </div>
        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
          <i class="fas fa-user-md text-success fa-lg"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small mb-1">Appointments</div>
          <h2 class="mb-0 fw-bold"><?php echo $appointmentCount ?? 0; ?></h2>
        </div>
        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
          <i class="fas fa-calendar-check text-info fa-lg"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <a href="/alerts" class="text-decoration-none">
      <div class="card h-100 border-0 shadow-sm border-danger border-start border-4">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small mb-1">Unread Alerts</div>
            <h2 class="mb-0 fw-bold text-danger"><?php echo $unreadAlerts ?? 0; ?></h2>
          </div>
          <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
            <i class="fas fa-satellite-dish text-danger fa-lg"></i>
          </div>
        </div>
      </div>
    </a>
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
        <a href="/alerts" class="btn btn-sm btn-outline-danger">View All</a>
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
        <a href="/appointments" class="btn btn-sm btn-outline-primary">View All</a>
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
      <div class="card-body">
        <canvas id="alertsChart" height="200"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Appointments by Week (8 Weeks)</h6>
      </div>
      <div class="card-body">
        <canvas id="appointmentsChart" height="200"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  fetch('/api/dashboard/stats')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;

      // Alerts chart
      const alertLabels = (data.alerts || []).map(r => r.date);
      const alertValues = (data.alerts || []).map(r => parseInt(r.count));
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

      // Appointments chart
      const apptLabels = (data.appointments || []).map(r => r.week_start || r.yw);
      const apptValues = (data.appointments || []).map(r => parseInt(r.count));
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
    })
    .catch(() => {});
})();
</script>
