<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Task 8 — always shows page title — SafeSense -->
    <title><?php echo htmlspecialchars($title ?? 'Tupi'); ?> — Tupi Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css?v=2" rel="stylesheet">
    <script>window.BASE_URL = '<?php echo url(); ?>';</script>
    <?php
    // Task 2 — generate CSRF token once per session and expose it as a meta tag
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
</head>
<body>

<?php
  // Session flash — read and immediately destroy
  $flashSuccess = $_SESSION['flash_success'] ?? null;
  $flashError   = $_SESSION['flash_error']   ?? null;
  unset($_SESSION['flash_success'], $_SESSION['flash_error']);

  // Detect current path for active nav highlighting
  $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
  $basePath    = rtrim(parse_url(url('/'), PHP_URL_PATH), '/');
  $pagePath    = ltrim(str_replace($basePath, '', $currentPath), '/');
  $navPage     = explode('/', $pagePath)[0] ?: 'dashboard';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?php echo url('/dashboard'); ?>">
      <i class="fas fa-satellite-dish me-2"></i><?php echo APP_NAME; ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto gap-1">
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='dashboard'?'active':''; ?>" href="<?php echo url('/dashboard'); ?>">
            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
          </a>
        </li>
        <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='patients'?'active':''; ?>" href="<?php echo url('/patients'); ?>">
            <i class="fas fa-user-injured me-1"></i>Patients
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='doctors'?'active':''; ?>" href="<?php echo url('/doctors'); ?>">
            <i class="fas fa-user-md me-1"></i>Doctors
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='appointments'?'active':''; ?>" href="<?php echo url('/appointments'); ?>">
            <i class="fas fa-calendar-check me-1"></i>Appointments
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='emr'?'active':''; ?>" href="<?php echo url('/emr'); ?>">
            <i class="fas fa-file-medical me-1"></i>Medical Records
          </a>
        </li>
        <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin','staff'])): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='billing'?'active':''; ?>" href="<?php echo url('/billing'); ?>">
            <i class="fas fa-file-invoice-dollar me-1"></i>Billing
          </a>
        </li>
        <?php endif; ?>
        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $navPage==='users'?'active':''; ?>" href="<?php echo url('/users'); ?>">
            <i class="fas fa-users-cog me-1"></i>Users
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $navPage==='alerts'?'active':''; ?>" href="<?php echo url('/alerts'); ?>">
            <i class="fas fa-satellite-dish me-1"></i>SafeSense Alerts<span class="ss-live-dot ss-live-dot--sm"></span>
          </a>
        </li>
      </ul>

      <!-- Vertical separator -->
      <div class="d-none d-lg-block mx-2" style="width:1px;height:28px;background:rgba(255,255,255,0.15);"></div>

      <ul class="navbar-nav align-items-center gap-2">
        <?php if (isset($_SESSION['user'])): ?>
        <li class="nav-item">
          <div class="nav-user-pill">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
            <small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
          </div>
        </li>
        <?php endif; ?>
        <li class="nav-item d-flex align-items-center gap-2">
          <div class="ss-bell-wrap" id="ssBellBtn" title="Open SafeSense Alerts">
            <i class="fas fa-bell"></i>
            <span class="ss-badge" id="ssBadge" data-count="0">0</span>
          </div>
          <form method="post" action="<?php echo url('/logout'); ?>" class="d-inline">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-outline-light btn-sm">
              <i class="fas fa-sign-out-alt me-1"></i>Logout
            </button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ── Notification Drawer ── -->
<div class="ss-drawer-overlay" id="ssDrawerOverlay"></div>
<div class="ss-drawer" id="ssDrawer">
  <div class="ss-drawer-header">
    <h5 class="mb-0"><i class="fas fa-satellite-dish me-2"></i>Live Alerts</h5>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-light py-0" id="ssMarkAllRead" style="font-size:.75rem">Mark all read</button>
      <button class="ss-x-btn" id="ssDrawerClose"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div class="ss-drawer-body" id="ssDrawerBody">
    <p class="text-muted text-center py-4" id="ssNoAlerts">
      <i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>No new alerts — all clear.
    </p>
  </div>
  <div class="ss-drawer-footer">
    <a href="<?php echo url('/alerts'); ?>" class="btn btn-primary btn-sm w-100">
      <i class="fas fa-list me-1"></i>View Full Alert History
    </a>
  </div>
</div>

<!-- ── Alert Modal ── -->
<div class="ss-modal-overlay" id="ssModalOverlay">
  <div class="ss-modal" id="ssModal" data-level="critical">
    <div class="ss-modal-header">
      <div class="ss-modal-icon" id="ssModalIcon"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="ss-modal-title">
        <div class="ss-modal-level" id="ssModalLevel">CRITICAL ALERT</div>
        <div class="ss-modal-device" id="ssModalDevice">SafeSense IoT System</div>
      </div>
      <button class="ss-x-btn ms-auto" id="ssModalClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="ss-modal-body">
      <div class="ss-modal-message" id="ssModalMessage">Loading alert...</div>
      <div class="ss-modal-details">
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-map-marker-alt"></i>Location</div><div class="ss-chip-val" id="ssModalLocation">—</div></div>
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-clock"></i>Time</div><div class="ss-chip-val" id="ssModalTime">—</div></div>
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-calendar-alt"></i>Date</div><div class="ss-chip-val" id="ssModalDate">—</div></div>
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-bolt"></i>Event</div><div class="ss-chip-val" id="ssModalEvent">—</div></div>
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-tint"></i>Water Level</div><div class="ss-chip-val" id="ssModalWater">—</div></div>
        <div class="ss-detail-chip"><div class="ss-chip-label"><i class="fas fa-microchip"></i>Device ID</div><div class="ss-chip-val" id="ssModalDeviceId">—</div></div>
      </div>
      <div id="ssMapLinkWrap" style="display:none;margin-top:10px;">
        <a href="#" id="ssMapLink" target="_blank" class="btn btn-sm btn-outline-secondary w-100">
          <i class="fas fa-map-marked-alt me-1"></i>View Location on Google Maps
        </a>
      </div>
    </div>
    <div class="ss-modal-footer">
      <button class="ss-btn-secondary" id="ssModalDismissBtn"><i class="fas fa-times me-1"></i>Dismiss</button>
      <button class="ss-btn-primary" id="ssModalAckBtn"><i class="fas fa-check me-1"></i>Acknowledge &amp; Respond</button>
    </div>
  </div>
</div>

<!-- ── Toast Container ── -->
<div class="ss-toast-wrap" id="ssToastWrap"></div>

<!-- ── Session Flash Data (consumed by app.js) ── -->
<?php if ($flashSuccess || $flashError): ?>
<div id="ssFlashData" data-success="<?php echo htmlspecialchars($flashSuccess ?? ''); ?>" data-error="<?php echo htmlspecialchars($flashError ?? ''); ?>" style="display:none;"></div>
<?php endif; ?>

<!-- ── Main Content ── -->
<main class="container mt-4 mb-5">
  <?php echo $content ?? ''; ?>
</main>

<footer class="border-top mt-auto">
  <div class="container text-center">
    <small class="text-muted">
      &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>
      &nbsp;·&nbsp;
      <a href="<?php echo url('/alerts'); ?>" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
        <span class="ss-live-dot ss-live-dot--sm"></span>SafeSense IoT Active
      </a>
    </small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 must load BEFORE app.js (app.js references Swal) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  'use strict';
  const POLL_MS  = 5000;
  const TOAST_MS = 9000;

  const $ = id => document.getElementById(id);
  const badge        = $('ssBadge');
  const bellBtn      = $('ssBellBtn');
  const drawer       = $('ssDrawer');
  const overlay      = $('ssDrawerOverlay');
  const drawerClose  = $('ssDrawerClose');
  const drawerBody   = $('ssDrawerBody');
  const markAllBtn   = $('ssMarkAllRead');
  const noAlerts     = $('ssNoAlerts');
  const modalOverlay = $('ssModalOverlay');
  const modal        = $('ssModal');
  const toastWrap    = $('ssToastWrap');

  const ICONS  = { critical:'fa-skull-crossbones', danger:'fa-exclamation-triangle', warning:'fa-cloud-rain' };
  const LABELS = { critical:'CRITICAL', danger:'DANGER', warning:'WARNING' };

  let lastPoll   = new Date().toISOString().replace('T',' ').slice(0,19);
  let modalQueue = [];
  let modalOpen  = false;
  let seen       = {};

  /* ── sessionStorage guard: tracks alert IDs that have already been modal-shown
         in this browser session. Persists across page navigations. ── */
  function getShown() {
    try { return new Set(JSON.parse(sessionStorage.getItem('ss_modal_shown') || '[]')); }
    catch(e) { return new Set(); }
  }
  function markShown(id) {
    try {
      const s = getShown(); s.add(String(id));
      // Keep the Set bounded — evict oldest when over 100 entries
      const arr = [...s];
      if (arr.length > 100) arr.splice(0, arr.length - 100);
      sessionStorage.setItem('ss_modal_shown', JSON.stringify(arr));
    } catch(e) {}
  }
  function wasShown(id) { return getShown().has(String(id)); }

  /* ── Badge ── */
  function setBadge(n){
    badge.textContent = n > 99 ? '99+' : n;
    badge.setAttribute('data-count', n);
    if(n > 0){ badge.style.display='flex'; badge.classList.add('bump'); setTimeout(()=>badge.classList.remove('bump'),300); }
    else { badge.style.display='none'; }
  }
  window.setBadge = setBadge;

  /* ── Drawer ── */
  bellBtn.addEventListener('click', ()=>{ drawer.classList.add('open'); overlay.classList.add('open'); });
  drawerClose.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);
  function closeDrawer(){ drawer.classList.remove('open'); overlay.classList.remove('open'); }

  markAllBtn.addEventListener('click', ()=>{
    post(window.BASE_URL + '/api/alerts/read','id=all')
      .then(d=>{ setBadge(0); document.querySelectorAll('.ss-notif.unread').forEach(el=>el.classList.remove('unread')); });
  });

  /* ── Drawer item ── */
  function addDrawerItem(a){
    if(seen[a.id]) return; seen[a.id]=true;
    noAlerts.style.display='none';
    const dt=new Date(a.created_at);
    const time=dt.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
    const date=dt.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
    const div=document.createElement('div');
    div.className='ss-notif'+(a.is_read==0?' unread':'');
    div.dataset.level=a.alert_level; div.dataset.id=a.id;
    const levelLabel = a.alert_level.toUpperCase();
    const icon = ICONS[a.alert_level] || 'fa-bell';
    div.innerHTML = `
      <button class="ss-notif-x" data-dismiss="${a.id}" title="Dismiss">
        <i class="fas fa-times"></i>
      </button>
      <div class="ss-notif-level">
        <i class="fas ${icon}"></i>${levelLabel}
      </div>
      <div class="ss-notif-msg">${esc(a.message)}</div>
      <div class="ss-notif-meta">
        <span><i class="fas fa-map-marker-alt"></i>${esc(a.location_name || '—')}</span>
        <span><i class="fas fa-clock"></i>${time}</span>
        <span><i class="fas fa-calendar-alt"></i>${date}</span>
        <span><i class="fas fa-bolt"></i>${esc(a.event_type || '—')}</span>
      </div>`;
    div.addEventListener('click',e=>{
      if(e.target.classList.contains('ss-notif-x')||e.target.dataset.dismiss) return;
      markRead(a.id); div.classList.remove('unread'); showModal(a); closeDrawer();
    });
    div.querySelector('.ss-notif-x').addEventListener('click',e=>{ e.stopPropagation(); dismissItem(a.id,div); });
    drawerBody.insertBefore(div, drawerBody.firstChild);
  }

  /* ── Toast ── */
  function showToast(a){
    const dt=new Date(a.created_at);
    const time=dt.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
    const t=document.createElement('div');
    t.className='ss-toast'; t.dataset.level=a.alert_level;
    t.innerHTML=`
      <div class="ss-toast-icon"><i class="fas ${ICONS[a.alert_level]||'fa-bell'}"></i></div>
      <div class="ss-toast-body">
        <div class="ss-toast-title">${LABELS[a.alert_level]} — ${esc((a.event_type || 'ALERT').toUpperCase())}</div>
        <div class="ss-toast-sub">${esc(a.location_name || 'Unknown location')} · ${time}</div>
      </div>
      <button class="ss-toast-x"><i class="fas fa-times"></i></button>`;
    t.querySelector('.ss-toast-x').addEventListener('click',()=>killToast(t));
    t.addEventListener('click',e=>{ if(e.target.closest('.ss-toast-x')) return; killToast(t); showModal(a); });
    toastWrap.appendChild(t);
    setTimeout(()=>killToast(t), TOAST_MS);
  }
  function killToast(t){ t.classList.add('out'); setTimeout(()=>t.remove(),350); }

  /* ── Poll — fetches new alerts since lastPoll every POLL_MS ── */
  function poll(){
    fetch(window.BASE_URL + '/api/alerts/poll?since=' + encodeURIComponent(lastPoll))
    .then(r => r.json())
    .then(data => {
      lastPoll = data.server_time || lastPoll;
      setBadge(data.unread_count || 0);
      (data.alerts || []).forEach(a => {
        addDrawerItem(a);
        showToast(a);
        if (a.alert_level === 'critical' || a.alert_level === 'danger') {
          showModal(a);
        }
      });
    }).catch(e => { console.error('Poll error:', e); });
  }

  /* ── Modal ── */
  /* Guard: only show modal if this alert ID has NOT been shown before in this session */
  function showModal(a){
    if(wasShown(a.id)) return;         // already shown in this session — skip
    if(modalOpen){ modalQueue.push(a); return; }
    openModal(a);
  }

  /* alarmCtx and alarmStop allow stopping the siren when modal closes */
  let alarmCtx  = null;
  let alarmStop = null;

  function openModal(a){
    modalOpen=true;
    markShown(a.id);                   // record in sessionStorage — won't show again
    const dt=new Date(a.created_at || Date.now());
    const time=dt.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const date=dt.toLocaleDateString('en-PH',{weekday:'short',month:'long',day:'numeric',year:'numeric'});
    modal.dataset.level=a.alert_level||'warning'; modal.dataset.id=a.id||'0';
    $('ssModalIcon').innerHTML=`<i class="fas ${ICONS[a.alert_level]||'fa-bell'}"></i>`;
    $('ssModalLevel').textContent=`${LABELS[a.alert_level]||'UNKNOWN'} ALERT`;
    $('ssModalDevice').textContent='Device: '+(a.device_id||'SafeSense');
    $('ssModalMessage').textContent=a.message||a.alert_message||'No message provided';
    $('ssModalLocation').textContent=a.location_name||'—';
    $('ssModalTime').textContent=time;
    $('ssModalDate').textContent=date;
    $('ssModalEvent').textContent=(a.event_type||a.alert_type||'—').toUpperCase();
    $('ssModalWater').textContent=a.water_level ? a.water_level+' cm' : '—';
    $('ssModalDeviceId').textContent=a.device_id||'—';
    if(a.latitude && a.longitude){
      $('ssMapLink').href=`https://www.google.com/maps?q=${a.latitude},${a.longitude}`;
      $('ssMapLinkWrap').style.display='block';
    } else { $('ssMapLinkWrap').style.display='none'; }
    modalOverlay.classList.add('show');
    if(a.alert_level==='critical') startAlarm('critical');
    else if(a.alert_level==='danger') startAlarm('danger');
  }

  function closeModal(){
    stopAlarm();
    modalOverlay.classList.remove('show');
    modalOpen=false;
    if(modalQueue.length) setTimeout(()=>openModal(modalQueue.shift()),350);
  }

  $('ssModalClose').addEventListener('click', closeModal);
  modalOverlay.addEventListener('click', e=>{ if(e.target===modalOverlay) closeModal(); });
  $('ssModalDismissBtn').addEventListener('click',()=>{ dismissItem(modal.dataset.id, document.querySelector(`.ss-notif[data-id="${modal.dataset.id}"]`)); closeModal(); });
  $('ssModalAckBtn').addEventListener('click',()=>{ markRead(modal.dataset.id); closeModal(); });

  /* ── API helpers ── */
  function markRead(id){ post(window.BASE_URL + '/api/alerts/read','id='+id).then(d=>setBadge(d.unread_count||0)); }
  function dismissItem(id,el){ post(window.BASE_URL + '/api/alerts/dismiss','id='+id); if(el){ el.style.opacity='0'; el.style.transform='translateX(40px)'; el.style.transition='.3s'; setTimeout(()=>el.remove(),300); } }
  function post(url,body){
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':csrfToken},body}).then(r=>r.json());
  }

  /* ── Alarm — continuous emergency siren, stops on modal close ── */
  function startAlarm(level) {
    stopAlarm(); // clear any previous alarm first
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      alarmCtx = ctx;

      // DynamicsCompressor prevents clipping at high gain
      const compressor = ctx.createDynamicsCompressor();
      compressor.threshold.setValueAtTime(-6, ctx.currentTime);
      compressor.knee.setValueAtTime(3, ctx.currentTime);
      compressor.ratio.setValueAtTime(20, ctx.currentTime);
      compressor.attack.setValueAtTime(0.001, ctx.currentTime);
      compressor.release.setValueAtTime(0.1, ctx.currentTime);
      compressor.connect(ctx.destination);

      const masterGain = ctx.createGain();
      masterGain.gain.setValueAtTime(0.88, ctx.currentTime);
      masterGain.connect(compressor);

      // Two oscillator layers for a harsh, thick siren sound
      const osc1 = ctx.createOscillator();
      const osc2 = ctx.createOscillator();
      const g1   = ctx.createGain();
      const g2   = ctx.createGain();

      osc1.type = 'sawtooth';
      osc2.type = 'square';

      g1.gain.setValueAtTime(0.6, ctx.currentTime);
      g2.gain.setValueAtTime(0.4, ctx.currentTime);

      osc1.connect(g1); g1.connect(masterGain);
      osc2.connect(g2); g2.connect(masterGain);

      // Siren frequencies: critical = high-pitched warble, danger = slightly lower
      const hiFreq = level === 'danger' ? 880  : 1100;
      const loFreq = level === 'danger' ? 660  : 880;
      const cycleMs = level === 'danger' ? 700  : 500; // ms per half-cycle

      let phase = true; // true = high frequency, false = low frequency
      let t = ctx.currentTime;

      // Schedule the initial siren sweep cycles (10 seconds worth up front)
      function scheduleCycles(fromTime, count) {
        for (let i = 0; i < count; i++) {
          const hi = phase ? hiFreq : loFreq;
          const lo = phase ? loFreq : hiFreq;
          const cycleStart = fromTime + i * (cycleMs / 1000);
          const cycleEnd   = cycleStart + (cycleMs / 1000);

          osc1.frequency.setValueAtTime(hi, cycleStart);
          osc2.frequency.setValueAtTime(hi * 1.5, cycleStart);

          // Smooth sweep to the other frequency
          osc1.frequency.linearRampToValueAtTime(lo, cycleEnd);
          osc2.frequency.linearRampToValueAtTime(lo * 1.5, cycleEnd);

          phase = !phase;
        }
      }

      // Schedule 20 cycles (10 seconds) immediately
      scheduleCycles(t, 20);

      osc1.start(t);
      osc2.start(t);

      // Keep scheduling more cycles every 8 seconds so the alarm loops indefinitely
      let scheduledUntil = t + 10;
      const extendInterval = setInterval(() => {
        if (!alarmCtx) { clearInterval(extendInterval); return; }
        scheduleCycles(scheduledUntil, 20);
        scheduledUntil += 10;
      }, 8000);

      // Expose stop function
      alarmStop = () => {
        clearInterval(extendInterval);
        try {
          // Fade out quickly instead of abrupt cut
          masterGain.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.12);
          setTimeout(() => {
            try { osc1.stop(); osc2.stop(); ctx.close(); } catch(e) {}
          }, 150);
        } catch(e) {}
        alarmCtx  = null;
        alarmStop = null;
      };

    } catch(e) {
      alarmCtx  = null;
      alarmStop = null;
    }
  }

  function stopAlarm() {
    if (typeof alarmStop === 'function') alarmStop();
  }

  function esc(s){ const d=document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }

  /* ── Init ── */
  // Fetch existing alerts to populate the drawer and sync the badge.
  // Modals are suppressed for old alerts (prevents cross-page repeat).
  // Exception: alerts created within the last 30 seconds are considered "just arrived"
  // (e.g. from a simulation that reloaded the page) and ARE shown in the modal.
  fetch(window.BASE_URL + '/api/alerts/poll?since=1970-01-01+00:00:00')
    .then(r => r.json())
    .then(d => {
      if (d.server_time) lastPoll = d.server_time;
      if (d.unread_count) setBadge(d.unread_count);
      (d.alerts || []).forEach(a => {
        addDrawerItem(a);
        // Show modal only for very recent unread critical/danger alerts not yet shown.
        // 30-second window covers simulation-reload delay (~1.8s) with a wide margin.
        const ageMs = Date.now() - new Date(a.created_at).getTime();
        if (
          ageMs < 30000 &&
          !a.is_read &&
          (a.alert_level === 'critical' || a.alert_level === 'danger')
        ) {
          showModal(a);
        }
      });
    })
    .catch(() => {});

  setInterval(poll, POLL_MS);
})();
</script>
<script src="<?php echo ASSETS_URL; ?>/js/app.js?v=2"></script>
</body>
</html>
