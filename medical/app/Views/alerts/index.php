<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-0"><i class="fas fa-satellite-dish text-danger me-2"></i>SafeSense Alert Log</h1>
    <small class="text-muted">Real-time IoT flood &amp; hazard alerts from the field</small>
  </div>
  <div class="d-flex gap-2">
    <span class="badge bg-danger fs-6" id="unreadBadge">
      <?php echo $unreadCount; ?> Unread
    </span>
    <button class="btn btn-outline-secondary btn-sm" id="markAllReadBtn">
      <i class="fas fa-check-double me-1"></i>Mark All Read
    </button>
  </div>
</div>

<!-- Level filter pills -->
<div class="mb-3 d-flex gap-2 flex-wrap">
  <button class="btn btn-sm btn-dark active" data-filter="all">All</button>
  <button class="btn btn-sm btn-outline-danger" data-filter="critical">🔴 Critical</button>
  <button class="btn btn-sm btn-outline-warning" data-filter="danger">🟠 Danger</button>
  <button class="btn btn-sm btn-outline-info" data-filter="warning">🟡 Warning</button>
</div>

<?php if (!empty($alerts)): ?>
<div class="row g-3" id="alertsGrid">
  <?php foreach ($alerts as $a): ?>
    <?php
      $level  = $a['alert_level'];
      $border = $level === 'critical' ? 'border-danger' : ($level === 'danger' ? 'border-warning' : 'border-info');
      $badge  = $level === 'critical' ? 'danger' : ($level === 'danger' ? 'warning' : 'info');
      $icon   = $level === 'critical' ? 'fa-skull-crossbones' : ($level === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
      $label  = $level === 'critical' ? '🔴 CRITICAL' : ($level === 'danger' ? '🟠 DANGER' : '🟡 WARNING');
      $dt     = new DateTime($a['created_at']);
      $unreadClass = (!$a['is_read']) ? 'ss-alert-card-unread' : '';
    ?>
    <div class="col-12 alert-card-wrap" data-level="<?php echo $level; ?>">
      <div class="card ss-alert-card <?php echo $border; ?> <?php echo $unreadClass; ?> border-start border-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div class="d-flex gap-3 align-items-start flex-grow-1">
              <div class="ss-alert-icon bg-<?php echo $badge; ?> bg-opacity-10 text-<?php echo $badge; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>
                  <span class="badge bg-secondary"><?php echo strtoupper(htmlspecialchars($a['event_type'])); ?></span>
                  <?php if (!$a['is_read']): ?>
                    <span class="badge bg-primary">NEW</span>
                  <?php endif; ?>
                </div>
                <p class="mb-2 fw-semibold"><?php echo htmlspecialchars($a['message']); ?></p>
                <div class="d-flex flex-wrap gap-3 text-muted" style="font-size:.82rem;">
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
            <div class="d-flex flex-column gap-1 ms-3">
              <?php if ($a['latitude'] && $a['longitude']): ?>
                <a href="https://www.google.com/maps?q=<?php echo $a['latitude']; ?>,<?php echo $a['longitude']; ?>"
                   target="_blank" class="btn btn-sm btn-outline-primary" title="View on map">
                  <i class="fas fa-map-marked-alt"></i>
                </a>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-secondary dismiss-btn" data-id="<?php echo $a['id']; ?>" title="Dismiss">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="text-center py-5">
  <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
  <h4>No Alerts</h4>
  <p class="text-muted">No SafeSense alerts recorded. The system is monitoring.</p>
</div>
<?php endif; ?>

<!-- IoT Connection Info box -->
<div class="card mt-5 border-info border-2">
  <div class="card-header bg-info text-white">
    <h6 class="mb-0"><i class="fas fa-plug me-2"></i>Arduino → System Connection Guide</h6>
  </div>
  <div class="card-body">
    <p class="text-muted mb-3">Your SafeSense Arduino with WiFi Shield should POST to:</p>
    <code class="d-block bg-dark text-success p-3 rounded mb-3" style="font-size:.85rem;">
      POST <?php echo APP_URL; ?>/api/alert<br>
      Content-Type: application/json
    </code>
    <p class="text-muted mb-2">Required JSON fields:</p>
    <code class="d-block bg-dark text-info p-3 rounded" style="font-size:.8rem;">
      {<br>
      &nbsp;&nbsp;"api_key": "SAFESENSE_SECRET_KEY",<br>
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
    document.querySelectorAll('[data-filter]').forEach(b=>b.classList.remove('active','btn-dark'));
    document.querySelectorAll('[data-filter]').forEach(b=>{ if(!b.classList.contains('active')) b.classList.add('btn-outline-'+b.dataset.color); });
    this.classList.add('active');
    const f=this.dataset.filter;
    document.querySelectorAll('.alert-card-wrap').forEach(el=>{
      el.style.display=(f==='all'||el.dataset.level===f)?'':'none';
    });
  });
});

document.querySelectorAll('.dismiss-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const id=this.dataset.id;
    const card=this.closest('.alert-card-wrap');
    fetch('/api/alerts/dismiss',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id})
    .then(()=>{ card.style.opacity='0'; card.style.transition='.3s'; setTimeout(()=>card.remove(),300); });
  });
});

document.getElementById('markAllReadBtn').addEventListener('click',()=>{
  fetch('/api/alerts/read',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id=all'})
  .then(r=>r.json()).then(()=>{
    document.querySelectorAll('.ss-alert-card-unread').forEach(el=>el.classList.remove('ss-alert-card-unread'));
    document.querySelectorAll('.badge.bg-primary').forEach(el=>el.remove());
    const ub=document.getElementById('unreadBadge');
    if(ub) ub.textContent='0 Unread';
  });
});
</script>

