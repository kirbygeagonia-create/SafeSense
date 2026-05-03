<div class="page-header">
  <div>
    <h1><i class="fas fa-tachometer-alt"></i><span class="ss-shimmer-text">Dashboard</span></h1>
    <div class="page-subtitle">Welcome back — <?php echo date('l, F j, Y'); ?></div>
  </div>
</div>

<!-- ── Stat Cards Skeleton (shown for ~400ms on load, then hidden) ── -->
<div class="ss-skeleton-wrap" id="skelStats">
  <div class="row g-3 mb-4">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="col-sm-6 col-lg-3">
      <div class="ss-skel-stat-card">
        <div class="skel-row">
          <div>
            <div class="ss-skel skel-label"></div>
            <div class="ss-skel skel-value"></div>
            <div class="ss-skel skel-sub"></div>
          </div>
          <div class="ss-skel skel-icon"></div>
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<!-- ── Real stat cards (initially hidden, revealed after skeleton) ── -->
<div class="ss-content-wrap" id="realStats">
<!-- Stats row -->
<div class="row g-3 mb-4">
  <!-- Task 2 — Convert first 4 stat cards -->
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card ss-stat-left-primary">
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
    <div class="stat-card ss-stat-left-success">
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
    <div class="stat-card ss-stat-left-info">
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
      <div class="stat-card ss-stat-left-danger">
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
</div><!-- end #realStats -->

<!-- Billing Stats Skeleton -->
<div class="ss-skeleton-wrap" id="skelBilling">
  <div class="row g-3 mb-4">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="col-sm-6 col-lg-3">
      <div class="ss-skel-stat-card">
        <div class="skel-row">
          <div>
            <div class="ss-skel skel-label"></div>
            <div class="ss-skel skel-value"></div>
            <div class="ss-skel skel-sub"></div>
          </div>
          <div class="ss-skel skel-icon"></div>
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<div class="ss-content-wrap" id="realBilling">
<!-- Billing summary row -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card ss-stat-left-primary">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Invoiced</div>
          <div class="stat-value">₱<?php echo number_format((float)($billingSummary['total_invoiced'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
          <i class="fas fa-file-invoice-dollar"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card ss-stat-left-success">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Collected</div>
          <div class="stat-value">₱<?php echo number_format((float)($billingSummary['total_collected'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-success bg-opacity-10 text-success">
          <i class="fas fa-hand-holding-usd"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card ss-stat-left-warning">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Total Unpaid</div>
          <div class="stat-value">₱<?php echo number_format((float)($billingSummary['total_unpaid'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card ss-stat-left-info">
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
</div><!-- end #realBilling -->

<!-- Main content row -->
<div class="row g-4">

  <!-- Recent SafeSense Alerts -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">
          <span class="ss-live-dot"></span>SafeSense Live Alerts
        </h6>
        <a href="<?php echo url('/alerts'); ?>" class="btn btn-sm btn-outline-danger">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($recentAlerts)): ?>
          <?php foreach ($recentAlerts as $a):
            $lvl        = $a['alert_level'];
            $levelClass = 'ss-level-' . $lvl;
            $icon       = $lvl === 'critical' ? 'fa-skull-crossbones' : ($lvl === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
            $labelText  = strtoupper($lvl);
            $dt         = new DateTime($a['created_at']);
          ?>
          <div class="ss-dash-alert-row <?php echo !$a['is_read'] ? 'unread' : ''; ?> <?php echo $levelClass; ?>">
            <div class="ss-dash-alert-icon <?php echo $levelClass; ?>">
              <i class="fas <?php echo $icon; ?> fa-sm"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="ss-badge-level <?php echo $levelClass; ?>">
                  <i class="fas <?php echo $icon; ?>"></i><?php echo $labelText; ?>
                </span>
                <?php if (!$a['is_read']): ?>
                  <span class="ss-new-badge">NEW</span>
                <?php endif; ?>
              </div>
              <div class="text-truncate small fw-medium"><?php echo htmlspecialchars($a['message']); ?></div>
              <div class="ss-dash-alert-meta">
                <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($a['location_name'] ?? '—'); ?></span>
                <span><i class="fas fa-clock me-1"></i><?php echo $dt->format('h:i A'); ?></span>
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
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
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
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
              <i class="fas fa-user"></i>
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
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2 text-danger"></i>Alerts Over Time (30 Days)</h6>
      </div>
      <div class="card-body d-flex align-items-center justify-content-center" id="alertsChartWrap">
        <!-- Skeleton chart bars (shown while AJAX loads) -->
        <div id="skelAlertsChart" class="ss-skel-chart ss-skeleton-wrap w-100">
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
        </div>
        <canvas id="alertsChart" height="200" style="display:none;"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Appointments by Week (8 Weeks)</h6>
      </div>
      <div class="card-body d-flex align-items-center justify-content-center" id="appointmentsChartWrap">
        <!-- Skeleton chart bars (shown while AJAX loads) -->
        <div id="skelApptChart" class="ss-skel-chart ss-skeleton-wrap w-100">
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
          <div class="ss-skel ss-skel-chart-bar"></div>
        </div>
        <canvas id="appointmentsChart" height="200" style="display:none;"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  function ajax(url) {
    return fetch(url).then(r => r.json());
  }
  ajax(window.BASE_URL + '/api/dashboard/stats')
    .then(data => {
      if (!data.success) return;

      // Task 7 — Alerts chart with empty-state fallback
      const alertLabels = (data.alerts || []).map(r => r.date);
      const alertValues = (data.alerts || []).map(r => parseInt(r.count));
      if (alertLabels.length === 0) {
        const wrap = document.getElementById('alertsChartWrap');
        wrap.style.minHeight = '200px';
        const p = document.createElement('p');
        p.className = 'text-muted mb-0 text-center w-100';
        p.innerHTML = '<i class="fas fa-chart-line fa-2x d-block mb-2 text-muted opacity-50"></i>No alert data in the last 30 days.';
        wrap.appendChild(p);
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
      // Hide alerts chart skeleton and show canvas
      const skelAlertsChart = document.getElementById('skelAlertsChart');
      if (skelAlertsChart) {
        skelAlertsChart.classList.add('ss-fading');
        setTimeout(() => skelAlertsChart.classList.add('ss-loaded'), 200);
      }
      document.getElementById('alertsChart').style.display = '';

      // Task 7 — Appointments chart with empty-state fallback
      const apptLabels = (data.appointments || []).map(r => r.week_start || r.yw);
      const apptValues = (data.appointments || []).map(r => parseInt(r.count));
      if (apptLabels.length === 0) {
        const wrap = document.getElementById('appointmentsChartWrap');
        wrap.style.minHeight = '200px';
        const p = document.createElement('p');
        p.className = 'text-muted mb-0 text-center w-100';
        p.innerHTML = '<i class="fas fa-calendar-times fa-2x d-block mb-2 text-muted opacity-50"></i>No appointment data in the last 8 weeks.';
        wrap.appendChild(p);
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
      // Hide appointments chart skeleton and show canvas
      const skelApptChart = document.getElementById('skelApptChart');
      if (skelApptChart) {
        skelApptChart.classList.add('ss-fading');
        setTimeout(() => skelApptChart.classList.add('ss-loaded'), 200);
      }
      document.getElementById('appointmentsChart').style.display = '';
    })
    .catch(() => {});
})();

// Show skeleton → reveal real content after a brief moment (or after chart loads)
(function() {
  function hideSkel(el) {
    if (!el) return;
    el.classList.add('ss-fading');
    setTimeout(() => el.classList.add('ss-loaded'), 200);
  }
  const skelStats  = document.getElementById('skelStats');
  const realStats  = document.getElementById('realStats');
  const skelBilling = document.getElementById('skelBilling');
  const realBilling = document.getElementById('realBilling');

  // Real content was hidden at start; reveal after 350ms (feels natural, hides flash)
  setTimeout(() => {
    hideSkel(skelStats);
    hideSkel(skelBilling);
    if (realStats)   realStats.classList.add('ss-loaded');
    if (realBilling) realBilling.classList.add('ss-loaded');
  }, 350);
})();
</script>
