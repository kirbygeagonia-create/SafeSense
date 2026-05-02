# SafeSense — Round 6 Fix Prompt: Alarm Sound, Modal Repeat Bug & Hospital Name
### For Windsurf Kimi K2.5

> **Round 5 confirmed fully clean.** All stat card border conflicts resolved, `line-clamp` removed. This round addresses three completely separate issues reported after testing: a nearly-inaudible alarm, a modal that re-fires on every page navigation, and a global hospital name change.

---

## 🔴 BUG 1 — Alert Modal Re-Fires on Every Page Navigation

**File:** `medical/app/Views/layouts/main.php` — the inline `<script>` block at the bottom

### Root Cause — Two Combined Bugs

**Bug A — Init always re-shows old unread alerts on every page load:**

The init code at the bottom of the script block does:
```js
fetch(window.BASE_URL + '/api/alerts/poll?since=1970-01-01+00:00:00')
  .then(d => {
    // ...
    const urgent = (d.alerts || []).find(
      a => (a.alert_level === 'critical' || a.alert_level === 'danger') && !a.is_read
    );
    if (urgent) showModal(urgent);  // ← fires on EVERY page load
  })
```

This fetches ALL alerts ever created (since 1970), finds the first unread critical/danger one, and shows the modal. Because this runs on **every page load**, and the `seen` object is reset in memory each time, navigating to any page (Patients, Doctors, Billing…) re-triggers the modal for whichever unread critical/danger alerts still exist in the database.

**Bug B — The poll auto-shows modal for any critical/danger alert it returns:**
```js
if (a.alert_level === 'critical' || a.alert_level === 'danger') {
  showModal(a);  // ← no check if this alert was already shown
}
```

This is correct behavior for genuinely new real-time alerts, but without a persistent "already shown" guard, it can also re-show alerts across sessions.

### The Fix — `sessionStorage` modal guard

**Replace the entire inline `<script>` block** (from `(function(){` to the closing `})();`) with this corrected version:

```js
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
    if(a.alert_level==='critical') startAlarm();
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

  /* ── Poll ── */
  function poll(){
    fetch(window.BASE_URL + '/api/alerts/poll?since='+encodeURIComponent(lastPoll))
    .then(r=>r.json())
    .then(data=>{
      lastPoll=data.server_time||lastPoll;
      setBadge(data.unread_count||0);
      (data.alerts||[]).forEach(a=>{
        addDrawerItem(a);
        showToast(a);
        /* showModal already guards via wasShown() — only truly new unseen alerts pop up */
        if (a.alert_level === 'critical' || a.alert_level === 'danger') {
          showModal(a);
        }
      });
    }).catch(e=>{ console.error('Poll error:', e); });
  }

  /* ── Alarm (Web Audio) — see BUG 2 section below for full replacement ── */
  // startAlarm() and stopAlarm() are defined in BUG 2 fix

  function esc(s){ const d=document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }

  /* ── Init ── */
  // Fetch existing alerts to populate the drawer and sync the badge.
  // DO NOT auto-show modals for existing old alerts — only new real-time polls trigger modals.
  fetch(window.BASE_URL + '/api/alerts/poll?since=1970-01-01+00:00:00')
    .then(r => r.json())
    .then(d => {
      if (d.server_time) lastPoll = d.server_time;
      if (d.unread_count) setBadge(d.unread_count);
      // Populate the drawer only — no modals for historical alerts
      (d.alerts || []).forEach(a => addDrawerItem(a));
    })
    .catch(() => {});

  setInterval(poll, POLL_MS);
})();
```

**Key changes from previous version:**
1. `getShown()` / `markShown()` / `wasShown()` — a `sessionStorage`-backed Set of alert IDs that have been modal-shown. Persists across page navigations within the same browser tab.
2. `showModal(a)` now checks `wasShown(a.id)` first and returns early if already shown.
3. `openModal(a)` calls `markShown(a.id)` immediately when opening so it's recorded.
4. The init fetch **no longer calls** `showModal()` for any historical alert — drawer population only.
5. The poll still calls `showModal()` for new real-time critical/danger alerts, but `wasShown()` prevents re-showing them after page navigation.

---

## 🔴 BUG 2 — Alarm Sound Is Too Quiet and Too Short

**File:** `medical/app/Views/layouts/main.php` — the `alarm()` function in the inline `<script>` block.

### Root Cause

The current alarm uses a gain of `0.18` (max is `1.0`) — nearly inaudible. It plays 4 short tones over ~1 second total, then stops. There is no looping, no frequency sweep, and no layering. The volume is less than 20% of maximum.

### The Fix — Replace `alarm()` with `startAlarm()` / `stopAlarm()`

The alarm must now:
- **Play loud** (gain ~0.85, with a DynamicsCompressor to prevent distortion)
- **Loop continuously** while the modal is open (stops only when user clicks a button)
- **Produce a proper emergency siren pattern** — a high-pitched two-tone warble alternating between two frequencies, the classic emergency alert sound
- **Layer two oscillators** (sawtooth + square wave simultaneously) for an aggressive, harsh timbre that cannot be mistaken for background noise

**Replace the old `alarm()` function** with these two functions. Place them inside the IIFE, after `closeModal` is defined:

```js
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
```

Also **remove the old `alarm()` function** entirely from the script, and update the two places in `openModal` that call `alarm()`:

```js
// BEFORE (in openModal):
if(a.alert_level==='critical') alarm();

// AFTER (in openModal):
if(a.alert_level==='critical') startAlarm('critical');
else if(a.alert_level==='danger') startAlarm('danger');
```

And ensure `closeModal` calls `stopAlarm()`:
```js
function closeModal(){
  stopAlarm();  // ← ensure this is present
  modalOverlay.classList.remove('show');
  modalOpen=false;
  if(modalQueue.length) setTimeout(()=>openModal(modalQueue.shift()),350);
}
```

> **Note:** `alarmCtx` and `alarmStop` must be declared at the top of the IIFE (next to the other `let` declarations):
> ```js
> let alarmCtx  = null;
> let alarmStop = null;
> ```

---

## 🟠 CHANGE 3 — Hospital Name: "SafeSense" → "Tupi"

The hospital is named **Tupi**. The IoT monitoring technology is still called **SafeSense** (the Arduino device and alert system keep their "SafeSense" branding). Only the hospital name changes.

Apply these changes exactly as listed:

### A. `medical/app/Config/config.php` — line 4
```php
// BEFORE:
define('APP_NAME', 'SafeSense Hospital Management');

// AFTER:
define('APP_NAME', 'Tupi Hospital Management');
```

### B. `medical/app/Views/layouts/main.php` — `<title>` tag (line 7)
```php
// BEFORE:
<title><?php echo htmlspecialchars($title ?? 'SafeSense'); ?> — SafeSense</title>

// AFTER:
<title><?php echo htmlspecialchars($title ?? 'Tupi'); ?> — Tupi Hospital</title>
```

### C. `medical/app/Views/layouts/auth.php` — `<title>` tag (line 13)
```php
// BEFORE:
<title><?php echo htmlspecialchars($title ?? 'SafeSense'); ?> — SafeSense</title>

// AFTER:
<title><?php echo htmlspecialchars($title ?? 'Tupi'); ?> — Tupi Hospital</title>
```

### D. `medical/app/Views/layouts/auth.php` — brand name (line 31)
```html
<!-- BEFORE: -->
<div class="auth-brand-name">SafeSense</div>
<div class="auth-subtitle">Hospital Intelligence &amp; IoT Monitoring</div>

<!-- AFTER: -->
<div class="auth-brand-name">Tupi Hospital</div>
<div class="auth-subtitle">Powered by SafeSense IoT Monitoring</div>
```

### E. `medical/app/Views/home.php` — hero heading (line 6)
```html
<!-- BEFORE: -->
<h1 class="mb-0 fw-bolder" style="font-size:2rem;letter-spacing:-.03em;">SafeSense HMS</h1>
<div style="font-size:.9rem;opacity:.8;">Hospital Intelligence &amp; IoT Monitoring Platform</div>

<!-- AFTER: -->
<h1 class="mb-0 fw-bolder" style="font-size:2rem;letter-spacing:-.03em;">Tupi Hospital Management</h1>
<div style="font-size:.9rem;opacity:.8;">Hospital Intelligence &amp; SafeSense IoT Monitoring Platform</div>
```

### F. Do NOT change these — they correctly refer to the SafeSense IoT device/system:
- `SafeSense Alerts` (nav link, page title, tab)
- `SafeSense Alert Log` (alerts page heading)
- `SafeSense IoT Active` (footer)
- `SafeSense Live Alerts` (dashboard card)
- `SafeSense Arduino` (IoT guide)
- `SafeSense-001` (device ID)
- `SAFESENSE_API_KEY` (constant)
- `Device: SafeSense` (modal fallback)

---

## 🟡 BONUS — CSS: Missing `.ss-live-dot--sm` Modifier

**File:** `medical/public/css/style.css`

The navbar SafeSense Alerts link uses `class="ss-live-dot ss-live-dot--sm"` but `.ss-live-dot--sm` has no CSS definition. The dot appears at full 8px size in the nav link.

Add this rule immediately after the `.ss-live-dot` block:
```css
/* Small variant for use inside nav links */
.ss-live-dot--sm {
  width: 6px;
  height: 6px;
}
```

---

## 📋 COMPLETE FILE TOUCH LIST

| File | Changes |
|------|---------|
| `medical/app/Views/layouts/main.php` | Replace full IIFE script with sessionStorage-guarded version; replace `alarm()` with `startAlarm()`/`stopAlarm()`; update `openModal` call; ensure `closeModal` calls `stopAlarm()` |
| `medical/app/Config/config.php` | `APP_NAME` → `'Tupi Hospital Management'` |
| `medical/app/Views/layouts/main.php` | `<title>` → `Tupi Hospital` |
| `medical/app/Views/layouts/auth.php` | `<title>` → `Tupi Hospital`; `auth-brand-name` → `Tupi Hospital`; subtitle → `Powered by SafeSense IoT Monitoring` |
| `medical/app/Views/home.php` | Hero heading → `Tupi Hospital Management`; subtitle mentions SafeSense |
| `medical/public/css/style.css` | Add `.ss-live-dot--sm` modifier |

---

## ⚠️ CONSTRAINTS

1. Do NOT change any PHP controllers, routes, models, or database code.
2. The IoT-facing `SafeSense` references (Alerts page, device IDs, API key, Arduino guide, footer IoT link) must NOT be changed — they are product/device names, not the hospital name.
3. The `sessionStorage` key `ss_modal_shown` is intentional — it provides cross-page-navigation persistence within the same browser tab session. Do not change it to `localStorage` (that would persist across sessions permanently).
4. The `alarmCtx` and `alarmStop` variables MUST be declared at the IIFE top-level, not inside `startAlarm` — they are shared between `startAlarm` and `stopAlarm`.
5. The alarm must loop indefinitely until `stopAlarm()` is called — do not add a fixed timeout that stops it automatically.
