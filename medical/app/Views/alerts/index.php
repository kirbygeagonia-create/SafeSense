<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-0"><i class="fas fa-satellite-dish text-danger me-2"></i>SafeSense Alert Log</h1>
    <small class="text-muted">Real-time IoT flood &amp; hazard alerts from the field</small>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="ss-unread-counter" id="unreadBadge">
      <i class="fas fa-bell"></i><span><?php echo $unreadCount; ?> Unread</span>
    </span>
    <button class="btn btn-outline-secondary btn-sm" id="markAllReadBtn">
      <i class="fas fa-check-double me-1"></i>Mark All Read
    </button>
    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
    <div class="dropdown">
      <button class="btn btn-outline-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Simulate Arduino alert for demo">
        <i class="fas fa-flask"></i> Simulate Alert
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">Inject Test Alert</h6></li>
        <li><button class="dropdown-item simulate-btn" data-level="critical" data-event="flood">
          <i class="fas fa-skull-crossbones text-danger me-2"></i>Critical — Flood
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="danger" data-event="flood">
          <i class="fas fa-exclamation-triangle text-warning me-2"></i>Danger — Flood
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="danger" data-event="accident">
          <i class="fas fa-car-crash text-warning me-2"></i>Danger — Accident
        </button></li>
        <li><button class="dropdown-item simulate-btn" data-level="warning" data-event="rain">
          <i class="fas fa-cloud-rain text-info me-2"></i>Warning — Rain
        </button></li>
        <li><hr class="dropdown-divider"></li>
        <li><small class="dropdown-item text-muted ss-simulate-hint">
          <i class="fas fa-info-circle me-1"></i>Dashboard updates within 5 seconds
        </small></li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Level filter pills -->
<div class="mb-3 d-flex gap-2 flex-wrap">
  <button class="ss-filter-pill active" data-filter="all">
    <i class="fas fa-layer-group"></i>All
  </button>
  <button class="ss-filter-pill" data-filter="critical">
    <i class="fas fa-skull-crossbones"></i>Critical
  </button>
  <button class="ss-filter-pill" data-filter="danger">
    <i class="fas fa-exclamation-triangle"></i>Danger
  </button>
  <button class="ss-filter-pill" data-filter="warning">
    <i class="fas fa-cloud-rain"></i>Warning
  </button>
</div>

<?php if (!empty($alerts)): ?>
<div class="row g-3" id="alertsGrid">
  <?php foreach ($alerts as $a): ?>
    <?php
      $level      = $a['alert_level'];
      $levelClass = 'ss-level-' . $level;
      $icon       = $level === 'critical' ? 'fa-skull-crossbones' : ($level === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
      $labelText  = strtoupper($level);
      $dt         = new DateTime($a['created_at']);
      $unreadClass = (!$a['is_read']) ? 'ss-alert-card-unread' : '';
      $opacityClass = $a['is_dismissed'] ? 'opacity-50' : '';
    ?>
    <div class="col-12 alert-card-wrap" data-level="<?php echo $level; ?>">
      <div class="card ss-alert-card <?php echo $levelClass; ?> <?php echo $unreadClass; ?> <?php echo $opacityClass; ?> border-start border-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div class="d-flex gap-3 align-items-start flex-grow-1">
              <div class="ss-alert-icon <?php echo $levelClass; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="ss-badge-level <?php echo $levelClass; ?>">
                    <i class="fas <?php echo $icon; ?>"></i><?php echo $labelText; ?>
                  </span>
                  <span class="badge bg-secondary"><i class="fas fa-tag"></i> <?php echo strtoupper(htmlspecialchars($a['event_type'])); ?></span>
                  <?php if ($a['is_dismissed']): ?>
                    <span class="badge bg-dark"><i class="fas fa-check"></i> DISMISSED</span>
                  <?php elseif (!$a['is_read']): ?>
                    <span class="ss-new-badge">NEW</span>
                  <?php endif; ?>
                </div>
                <p class="mb-2 fw-semibold"><?php echo htmlspecialchars($a['message']); ?></p>
                <div class="d-flex flex-wrap gap-3 text-muted ss-alert-meta-row">
                  <span><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo htmlspecialchars($a['location_name'] ?? '—'); ?></span>
                  <span><i class="fas fa-clock me-1"></i><?php echo $dt->format('h:i:s A'); ?></span>
                  <span><i class="fas fa-calendar-alt me-1"></i><?php echo $dt->format('M d, Y'); ?></span>
                  <?php if ($a['water_level']): ?>
                    <span><i class="fas fa-tint me-1 text-primary"></i><?php echo $a['water_level']; ?> cm</span>
                  <?php endif; ?>
                  <?php if ($a['vibration']): ?>
                    <span><i class="fas fa-wave-square me-1 text-warning"></i>Vibration detected</span>
                  <?php endif; ?>
                  <span><i class="fas fa-microchip me-1"></i><?php echo htmlspecialchars($a['device_id']); ?></span>
                </div>
              </div>
            </div>
            <div class="d-flex flex-row gap-2 ms-3 align-items-center">
              <?php if ($a['latitude'] && $a['longitude']): ?>
                <a href="https://www.google.com/maps?q=<?php echo $a['latitude']; ?>,<?php echo $a['longitude']; ?>"
                   target="_blank" class="btn btn-sm btn-outline-primary" title="View on map">
                  <i class="fas fa-map-marked-alt"></i>
                </a>
              <?php endif; ?>
              <?php if (!$a['is_dismissed']): ?>
              <button class="btn btn-sm btn-outline-secondary dismiss-btn" data-id="<?php echo $a['id']; ?>" title="Dismiss">
                <i class="fas fa-times"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Task 7 — empty state: "No alerts received yet." -->
<div class="text-center py-5">
  <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
  <h4>No Alerts Received Yet</h4>
  <p class="text-muted">No SafeSense alerts recorded. The system is monitoring.</p>
</div>
<?php endif; ?>

<!-- IoT Connection Info box -->
<div class="card ss-iot-guide mt-5">
  <div class="card-header">
    <h6 class="mb-0"><i class="fas fa-plug me-2"></i>Arduino → System Connection Guide</h6>
  </div>
  <div class="card-body">
    <p class="text-muted mb-3">Your SafeSense Arduino with WiFi Shield should POST to:</p>
    <code class="d-block bg-dark text-success p-3 rounded mb-3">
      POST <?php echo url('/api/alert'); ?><br>
      Content-Type: application/json
    </code>
    <p class="text-muted mb-2">Required JSON fields:</p>
    <code class="d-block bg-dark text-info p-3 rounded ss-iot-guide-code-sm">
      {<br>
      &nbsp;&nbsp;"api_key": "your-key-from-.env",<br>
      &nbsp;&nbsp;"device_id": "SAFESENSE-001",<br>
      &nbsp;&nbsp;"station_type": "hospital",<br>
      &nbsp;&nbsp;"alert_level": "critical",<br>
      &nbsp;&nbsp;"event_type": "flood",<br>
      &nbsp;&nbsp;"rain_status": "heavy",<br>
      &nbsp;&nbsp;"water_level": 45.2,<br>
      &nbsp;&nbsp;"vibration": 0,<br>
      &nbsp;&nbsp;"message": "CRITICAL: Flood detected...",<br>
      &nbsp;&nbsp;"latitude": 8.1574,<br>
      &nbsp;&nbsp;"longitude": 124.9282,<br>
      &nbsp;&nbsp;"location_name": "Brgy. Casisang, Malaybalay City"<br>
      }
    </code>
  </div>
</div>

<script>
document.querySelectorAll('[data-filter]').forEach(btn=>{
  btn.addEventListener('click',function(){
    document.querySelectorAll('[data-filter]').forEach(b=>b.classList.remove('active'));
    this.classList.add('active');
    const f=this.dataset.filter;
    document.querySelectorAll('.alert-card-wrap').forEach(el=>{
      el.style.display=(f==='all'||el.dataset.level===f)?'':'none';
    });
  });
});

function safeAjaxPost(url, data) {
  if (typeof ajaxPost === 'function') return ajaxPost(url, data);
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      if (typeof ajaxPost === 'function') resolve(ajaxPost(url, data));
      else reject(new Error('ajaxPost not available'));
    }, 150);
  });
}

document.querySelectorAll('.dismiss-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id   = this.dataset.id;
    const card = this.closest('.alert-card-wrap');
    const innerCard = card.querySelector('.ss-alert-card');
    safeAjaxPost(window.BASE_URL + '/api/alerts/dismiss', { id })
      .then(() => {
        innerCard.style.transition = 'opacity .5s ease';
        innerCard.classList.add('opacity-50');
        this.remove(); // Just remove the dismiss button, keep the log entry
      });
  });
});

document.getElementById('markAllReadBtn').addEventListener('click', () => {
  safeAjaxPost(window.BASE_URL + '/api/alerts/read', { id: 'all' })
    .then(d => {
      document.querySelectorAll('.ss-alert-card-unread')
        .forEach(el => el.classList.remove('ss-alert-card-unread'));
      document.querySelectorAll('.ss-new-badge').forEach(el => el.remove());

      const ub = document.getElementById('unreadBadge');
      if (ub) {
        ub.innerHTML = '<i class="fas fa-check-circle"></i><span>All Read</span>';
        ub.style.background = 'linear-gradient(135deg, #16a34a 0%, #15803d 100%)';
      }
      if (typeof setBadge === 'function') setBadge(0);
    });
});

// Simulate alert buttons (admin only)
document.querySelectorAll('.simulate-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const level = this.dataset.level;
    const event = this.dataset.event;
    const label = level.toUpperCase() + ' — ' + event;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    safeAjaxPost(window.BASE_URL + '/api/alert/simulate', { level, event })
      .then(d => {
        if (d.success) {
          // Show brief success state (do not reload, so the modal and siren can play uninterrupted)
          this.innerHTML = '<i class="fas fa-check me-1"></i>Sent!';
          this.classList.replace('btn-outline-danger', 'btn-outline-success');
          setTimeout(() => {
            this.innerHTML = '<i class="fas fa-flask me-1"></i>' + label;
            this.classList.replace('btn-outline-success', 'btn-outline-danger');
            this.disabled = false;
          }, 3000);
        } else {
          alert('Simulation failed: ' + (d.error || 'Unknown error'));
          this.disabled = false;
          this.innerHTML = '<i class="fas fa-flask me-1"></i>' + label;
        }
      })
      .catch(() => {
        alert('Network error. Check server connection.');
        this.disabled = false;
      });
  });
});

window.ssInjectAlert = function(a) {
  // If the empty state is visible, remove it and create the grid
  let grid = document.getElementById('alertsGrid');
  if (!grid) {
    const emptyState = document.querySelector('.text-center.py-5');
    if (emptyState) emptyState.remove();
    grid = document.createElement('div');
    grid.id = 'alertsGrid';
    grid.className = 'row g-3';
    document.querySelector('.ss-filter-pill').parentElement.after(grid);
  }

  const levelClass = 'ss-level-' + a.alert_level;
  const icon = a.alert_level === 'critical' ? 'fa-skull-crossbones' : (a.alert_level === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
  const labelText = a.alert_level.toUpperCase();
  const dt = new Date(a.created_at);
  const timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit', second:'2-digit'});
  const dateStr = dt.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
  
  const div = document.createElement('div');
  div.className = 'col-12 alert-card-wrap';
  div.dataset.level = a.alert_level;
  
  div.innerHTML = `
    <div class="card ss-alert-card ${levelClass} ss-alert-card-unread border-start border-4" style="animation: pulse-border 2s infinite">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div class="d-flex gap-3 align-items-start flex-grow-1">
            <div class="ss-alert-icon ${levelClass}">
              <i class="fas ${icon}"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="ss-badge-level ${levelClass}">
                  <i class="fas ${icon}"></i>${labelText}
                </span>
                <span class="badge bg-secondary"><i class="fas fa-tag"></i> ${a.event_type.toUpperCase()}</span>
                <span class="ss-new-badge">NEW</span>
              </div>
              <p class="mb-2 fw-semibold">${a.message}</p>
              <div class="d-flex flex-wrap gap-3 text-muted ss-alert-meta-row">
                <span><i class="fas fa-map-marker-alt me-1 text-danger"></i>${a.location_name || '—'}</span>
                <span><i class="fas fa-clock me-1"></i>${timeStr}</span>
                <span><i class="fas fa-calendar-alt me-1"></i>${dateStr}</span>
                ${a.water_level ? '<span><i class="fas fa-tint me-1 text-primary"></i>' + a.water_level + ' cm</span>' : ''}
                ${a.vibration ? '<span><i class="fas fa-wave-square me-1 text-warning"></i>Vibration detected</span>' : ''}
                <span><i class="fas fa-microchip me-1"></i>${a.device_id}</span>
              </div>
            </div>
          </div>
          <div class="d-flex flex-row gap-2 ms-3 align-items-center">
            ${a.latitude && a.longitude ? `
              <a href="https://www.google.com/maps?q=${a.latitude},${a.longitude}" target="_blank" class="btn btn-sm btn-outline-primary" title="View on map">
                <i class="fas fa-map-marked-alt"></i>
              </a>
            ` : ''}
            <button class="btn btn-sm btn-outline-secondary dismiss-btn" data-id="${a.id}" title="Dismiss">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  // Attach dismiss listener to the new button
  div.querySelector('.dismiss-btn').addEventListener('click', function() {
    const id = this.dataset.id;
    const innerCard = div.querySelector('.ss-alert-card');
    safeAjaxPost(window.BASE_URL + '/api/alerts/dismiss', { id })
      .then(() => {
        innerCard.style.transition = 'opacity .5s ease';
        innerCard.classList.add('opacity-50');
        innerCard.style.animation = 'none'; // Stop pulse
        this.remove();
      });
  });

  // Prepend to grid with a nice fade-in effect
  div.style.opacity = '0';
  div.style.transform = 'translateY(-10px)';
  grid.insertBefore(div, grid.firstChild);
  
  // Trigger reflow
  void div.offsetWidth;
  div.style.transition = 'all 0.5s ease-out';
  div.style.opacity = '1';
  div.style.transform = 'translateY(0)';
};

</script>
