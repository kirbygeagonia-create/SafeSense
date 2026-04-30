# SafeSense — Design Fix & Notification Refinement Prompt (Round 3)
### For Windsurf Kimi K2.5

> **Status after Round 2:** The structural fixes (DataTables layout, filter pill colors, stat card overflow, toast gradients) are all in place. However, a large block of CSS component definitions was never added, causing the notification drawer, alert modal, and toasts to render with completely bare/unstyled inner elements. Additionally the confirmed `textContent` bug on "Mark All Read" persists, the dashboard alert rows were not migrated, and the alerts page icon/badge still use Bootstrap's wrong color palette. This round fixes all of that AND refines the notification drawer into a polished, production-quality component.

---

## 🔴 BUG FIX 1 — Mark All Read Destroys the Bell Icon

**File:** `medical/app/Views/alerts/index.php` — inside the `<script>` block at the bottom.

**Root cause:** `ub.textContent = '0 Unread'` replaces ALL child nodes of `.ss-unread-counter`, including the `<i class="fas fa-bell">` icon, leaving only plain text.

**Locate this exact line:**
```js
if (ub) ub.textContent = '0 Unread';
```

**Replace with:**
```js
if (ub) ub.innerHTML = '<i class="fas fa-bell"></i><span>0 Unread</span>';
```

While you're here, also wrap the initial PHP-rendered count in a `<span>` so the structure is consistent:

**In the HTML at the top of the same file, update the counter element:**
```html
<!-- BEFORE -->
<span class="ss-unread-counter" id="unreadBadge">
  <i class="fas fa-bell"></i><?php echo $unreadCount; ?> Unread
</span>

<!-- AFTER -->
<span class="ss-unread-counter" id="unreadBadge">
  <i class="fas fa-bell"></i><span><?php echo $unreadCount; ?> Unread</span>
</span>
```

---

## 🔴 BUG FIX 2 — Dashboard Alert Rows: Still Using Wrong Bootstrap Colors

**File:** `medical/app/Views/dashboard.php` — the "SafeSense Live Alerts" card body (the `foreach ($recentAlerts as $a)` loop).

**Problem:** Still maps `critical → 'danger'`, `danger → 'warning'`, `warning → 'info'` for Bootstrap `bg-*` classes. Critical alerts show Bootstrap red, danger alerts show Bootstrap yellow, warning alerts show Bootstrap cyan-blue. Wrong on all three.

**Locate and fully replace the PHP loop block:**

```php
<!-- REMOVE THIS ENTIRE BLOCK -->
<?php foreach ($recentAlerts as $a):
  $lvl    = $a['alert_level'];
  $badge  = $lvl==='critical'?'danger':($lvl==='danger'?'warning':'info');
  $icon   = $lvl==='critical'?'fa-skull-crossbones':($lvl==='danger'?'fa-exclamation-triangle':'fa-cloud-rain');
  $label  = $lvl==='critical'?'<i class="fas fa-exclamation-circle"></i> CRITICAL':($lvl==='danger'?'<i class="fas fa-exclamation-triangle"></i> DANGER':'<i class="fas fa-info-circle"></i> WARNING');
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
    ...
  </div>
</div>
<?php endforeach; ?>
```

**Replace with:**
```php
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
```

---

## 🔴 BUG FIX 3 — Alerts Page: Icon Bubble & Badge Still Use Bootstrap Colors

**File:** `medical/app/Views/alerts/index.php` — inside the `foreach ($alerts as $a)` loop.

**Problem:** The card container correctly gets `.ss-level-{level}` for its left border, but the icon bubble and the level badge inside still use the old `$badge` variable with Bootstrap `bg-{$badge}` classes.

**Locate and replace the PHP variable block and affected markup:**

```php
<!-- REMOVE $badge and $label, keep only what's needed -->
$levelClass = 'ss-level-' . $level;
$badge  = $level === 'critical' ? 'danger' : ($level === 'danger' ? 'warning' : 'info');  // DELETE THIS LINE
$icon   = $level === 'critical' ? 'fa-skull-crossbones' : ($level === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
$label  = $level === 'critical' ? '<i class="fas fa-exclamation-circle"></i> CRITICAL' : ...;  // DELETE THIS LINE
```

**Correct PHP variables block:**
```php
<?php
  $level      = $a['alert_level'];
  $levelClass = 'ss-level-' . $level;
  $icon       = $level === 'critical' ? 'fa-skull-crossbones' : ($level === 'danger' ? 'fa-exclamation-triangle' : 'fa-cloud-rain');
  $labelText  = strtoupper($level);
  $dt         = new DateTime($a['created_at']);
  $unreadClass = (!$a['is_read']) ? 'ss-alert-card-unread' : '';
?>
```

**Fix the icon bubble (find and replace):**
```html
<!-- BEFORE -->
<div class="ss-alert-icon bg-<?php echo $badge; ?> bg-opacity-10 text-<?php echo $badge; ?>">
  <i class="fas <?php echo $icon; ?>"></i>
</div>

<!-- AFTER -->
<div class="ss-alert-icon <?php echo $levelClass; ?>">
  <i class="fas <?php echo $icon; ?>"></i>
</div>
```

**Fix the level badge (find and replace):**
```html
<!-- BEFORE -->
<span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>

<!-- AFTER -->
<span class="ss-badge-level <?php echo $levelClass; ?>">
  <i class="fas <?php echo $icon; ?>"></i><?php echo $labelText; ?>
</span>
```

---

## 🔴 CRITICAL — Add All Missing CSS Definitions

**File:** `medical/public/css/style.css`

None of the following classes used throughout the HTML and JS have any CSS definitions. Add all of these blocks to `style.css`. Place them in a new clearly-labelled section after the existing "Alerts Page" section.

---

### SECTION: Live Dot

```css
/* ── Live Dot (animated green pulse) ── */
.ss-live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  flex-shrink: 0;
  animation: ss-pulse 2s infinite;
}
@keyframes ss-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
  70%  { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}
```

---

### SECTION: X / Close Button

```css
/* ── X Close Button ── */
.ss-x-btn {
  background: none;
  border: none;
  color: rgba(255,255,255,.7);
  font-size: 1.05rem;
  cursor: pointer;
  padding: .3rem .45rem;
  border-radius: var(--r-sm);
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: color var(--t-fast), background var(--t-fast);
  flex-shrink: 0;
}
.ss-x-btn:hover { color: #fff; background: rgba(255,255,255,.18); }
```

---

### SECTION: Notification Drawer — Full Refinement

Replace the existing "Notification Drawer" and "Notifications" CSS sections entirely with this refined version:

```css
/* ════════════════════════════════════════════
   NOTIFICATION DRAWER — Refined
════════════════════════════════════════════ */

/* Overlay */
.ss-drawer-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.5);
  z-index: 1050;
  backdrop-filter: blur(4px);
}
.ss-drawer-overlay.open { display: block; }

/* Drawer panel */
.ss-drawer {
  position: fixed;
  top: 0;
  right: -460px;
  width: 400px;
  max-width: 96vw;
  height: 100vh;
  background: var(--ss-surface);
  z-index: 1051;
  display: flex;
  flex-direction: column;
  box-shadow: -12px 0 48px rgba(15,23,42,.22);
  transition: right var(--t-slow);
  border-left: 1px solid var(--ss-border);
}
.ss-drawer.open { right: 0; }

/* Header */
.ss-drawer-header {
  padding: 1rem 1.1rem;
  background: linear-gradient(135deg, var(--ss-primary-dark) 0%, #1e3a8a 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.ss-drawer-header h5 {
  font-size: .88rem;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: .5rem;
  letter-spacing: -.01em;
}
.ss-drawer-header h5 i { color: #f87171; }

/* Mark-all-read button in drawer header */
.ss-drawer-header .btn-outline-light {
  font-size: .72rem;
  padding: .2rem .6rem;
  min-height: unset;
  border-color: rgba(255,255,255,.35);
  color: rgba(255,255,255,.85);
  border-radius: var(--r-sm);
}
.ss-drawer-header .btn-outline-light:hover {
  background: rgba(255,255,255,.15);
  color: #fff;
  border-color: rgba(255,255,255,.6);
}

/* Scrollable body */
.ss-drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: .65rem;
  background: var(--ss-surface-2);
  scrollbar-width: thin;
  scrollbar-color: var(--ss-border-strong) transparent;
}
.ss-drawer-body::-webkit-scrollbar { width: 5px; }
.ss-drawer-body::-webkit-scrollbar-track { background: transparent; }
.ss-drawer-body::-webkit-scrollbar-thumb { background: var(--ss-border-strong); border-radius: 99px; }

/* Footer */
.ss-drawer-footer {
  padding: .85rem 1rem;
  border-top: 1px solid var(--ss-border);
  background: var(--ss-surface);
  flex-shrink: 0;
}

/* ── Notification Card ── */
.ss-notif {
  position: relative;
  border-radius: var(--r-md);
  padding: .85rem 2.2rem .85rem .95rem;
  margin-bottom: 5px;
  border-left: 3.5px solid var(--ss-border-strong);
  background: var(--ss-surface);
  cursor: pointer;
  transition: background var(--t-fast), box-shadow var(--t-fast), transform var(--t-fast);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}
.ss-notif::before {
  content: '';
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity var(--t-fast);
  pointer-events: none;
}
.ss-notif:hover {
  box-shadow: var(--shadow-sm);
  transform: translateX(2px);
}
.ss-notif:hover::before { opacity: 1; }

/* Unread state */
.ss-notif.unread {
  background: #f0f5ff;
  border-left-color: var(--ss-primary);
}
.ss-notif.unread::after {
  content: '';
  position: absolute;
  top: .7rem;
  right: 2.3rem;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--ss-primary);
}

/* Level-specific left border + subtle tinted bg on hover */
.ss-notif[data-level=critical] { border-left-color: var(--ss-critical); }
.ss-notif[data-level=critical]:hover { background: #fef8f8; }
.ss-notif[data-level=danger]   { border-left-color: var(--ss-danger); }
.ss-notif[data-level=danger]:hover   { background: #fff9f5; }
.ss-notif[data-level=warning]  { border-left-color: var(--ss-warning); }
.ss-notif[data-level=warning]:hover  { background: #fffdf5; }

/* Dismiss X button on each card */
.ss-notif-x {
  position: absolute;
  top: .55rem;
  right: .5rem;
  background: none;
  border: none;
  color: var(--ss-text-3);
  font-size: .95rem;
  line-height: 1;
  cursor: pointer;
  padding: .2rem .3rem;
  border-radius: var(--r-sm);
  transition: color var(--t-fast), background var(--t-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}
.ss-notif-x:hover { color: var(--ss-text); background: var(--ss-surface-2); }

/* Level label inside card */
.ss-notif-level {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: .3rem;
}
.ss-notif[data-level=critical] .ss-notif-level { color: var(--ss-critical); }
.ss-notif[data-level=danger]   .ss-notif-level { color: var(--ss-danger); }
.ss-notif[data-level=warning]  .ss-notif-level { color: var(--ss-warning); }

/* Message text */
.ss-notif-msg {
  font-size: .82rem;
  color: var(--ss-text);
  font-weight: 500;
  line-height: 1.45;
  margin-bottom: .45rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Meta row (location · time · date · event) */
.ss-notif-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem .75rem;
  font-size: .7rem;
  color: var(--ss-text-3);
  line-height: 1.3;
}
.ss-notif-meta i {
  margin-right: .2rem;
  opacity: .75;
}
```

---

### SECTION: Toast Notification — Full Definition

```css
/* ════════════════════════════════════════════
   TOAST NOTIFICATIONS
════════════════════════════════════════════ */
.ss-toast-wrap {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 3000;
  display: flex;
  flex-direction: column-reverse;
  gap: .55rem;
  pointer-events: none;
  max-width: 360px;
  width: calc(100vw - 3rem);
}
.ss-toast {
  pointer-events: all;
  border-radius: var(--r-lg);
  padding: .85rem 1rem;
  color: #fff;
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  box-shadow: 0 8px 32px rgba(15,23,42,.28), 0 2px 8px rgba(15,23,42,.16);
  animation: toastSlideIn .3s cubic-bezier(.34,1.56,.64,1);
  border: 1px solid rgba(255,255,255,.12);
}
.ss-toast.out {
  animation: toastSlideOut .3s ease forwards;
}
@keyframes toastSlideIn {
  from { opacity: 0; transform: translateX(24px) scale(.95); }
  to   { opacity: 1; transform: translateX(0) scale(1); }
}
@keyframes toastSlideOut {
  from { opacity: 1; transform: translateX(0) scale(1); }
  to   { opacity: 0; transform: translateX(32px) scale(.95); }
}

/* Toast level backgrounds */
.ss-toast[data-level=critical] { background: linear-gradient(135deg, var(--ss-critical) 0%, #991b1b 100%); }
.ss-toast[data-level=danger]   { background: linear-gradient(135deg, var(--ss-danger)   0%, #9a3412 100%); }
.ss-toast[data-level=warning]  { background: linear-gradient(135deg, var(--ss-warning)  0%, #92400e 100%); }

/* Toast inner elements */
.ss-toast-icon {
  font-size: 1.15rem;
  flex-shrink: 0;
  margin-top: .1rem;
  opacity: .95;
  filter: drop-shadow(0 1px 2px rgba(0,0,0,.2));
}
.ss-toast-body {
  flex: 1;
  min-width: 0;
}
.ss-toast-title {
  font-size: .82rem;
  font-weight: 700;
  line-height: 1.35;
  margin-bottom: .18rem;
  letter-spacing: -.01em;
}
.ss-toast-sub {
  font-size: .74rem;
  opacity: .82;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ss-toast-x {
  background: rgba(255,255,255,.12);
  border: none;
  color: rgba(255,255,255,.8);
  font-size: .85rem;
  cursor: pointer;
  padding: .25rem .35rem;
  border-radius: var(--r-sm);
  line-height: 1;
  flex-shrink: 0;
  align-self: flex-start;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background var(--t-fast), color var(--t-fast);
}
.ss-toast-x:hover { background: rgba(255,255,255,.25); color: #fff; }
```

---

### SECTION: Alert Modal — Full Inner Element Styles

```css
/* ════════════════════════════════════════════
   ALERT MODAL — Inner Elements
════════════════════════════════════════════ */
.ss-modal-title { flex: 1; min-width: 0; }

.ss-modal-device {
  color: rgba(255,255,255,.72);
  font-size: .78rem;
  margin-top: .18rem;
  font-weight: 400;
  letter-spacing: 0;
}
.ss-modal-message {
  font-size: .93rem;
  color: var(--ss-text);
  font-weight: 600;
  line-height: 1.55;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--ss-border);
}
.ss-modal-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .55rem;
  margin-bottom: .85rem;
}
.ss-detail-chip {
  background: var(--ss-surface-2);
  border: 1px solid var(--ss-border);
  border-radius: var(--r-md);
  padding: .5rem .7rem;
  transition: border-color var(--t-fast);
}
.ss-detail-chip:hover { border-color: var(--ss-border-strong); }
.ss-chip-label {
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--ss-text-3);
  margin-bottom: .22rem;
  display: flex;
  align-items: center;
  gap: .3rem;
}
.ss-chip-label i { opacity: .7; }
.ss-chip-val {
  font-size: .82rem;
  font-weight: 600;
  color: var(--ss-text);
  word-break: break-word;
  line-height: 1.3;
}
.ss-modal-footer {
  background: var(--ss-surface-2);
  border-top: 1px solid var(--ss-border);
  padding: .9rem 1.5rem;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: .6rem;
}

/* Modal action buttons */
.ss-btn-primary,
.ss-btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem 1.1rem;
  border-radius: var(--r-md);
  font-size: .855rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  min-height: 38px;
  transition: opacity var(--t-fast), transform var(--t-fast), box-shadow var(--t-fast);
  line-height: 1;
  letter-spacing: -.01em;
}
.ss-btn-primary:active,
.ss-btn-secondary:active { transform: scale(.97); }

.ss-btn-primary { background: var(--ss-primary); color: #fff; box-shadow: 0 2px 8px rgba(29,78,216,.25); }
.ss-btn-primary:hover { background: var(--ss-primary-dark); box-shadow: 0 4px 12px rgba(29,78,216,.35); }

/* Primary button adapts to modal severity */
.ss-modal[data-level=critical] .ss-btn-primary { background: var(--ss-critical); box-shadow: 0 2px 8px rgba(185,28,28,.3); }
.ss-modal[data-level=critical] .ss-btn-primary:hover { background: #991b1b; }
.ss-modal[data-level=danger]   .ss-btn-primary { background: var(--ss-danger); box-shadow: 0 2px 8px rgba(194,65,12,.3); }
.ss-modal[data-level=danger]   .ss-btn-primary:hover { background: #9a3412; }
.ss-modal[data-level=warning]  .ss-btn-primary { background: var(--ss-warning); box-shadow: 0 2px 8px rgba(180,83,9,.3); }
.ss-modal[data-level=warning]  .ss-btn-primary:hover { background: #92400e; }

.ss-btn-secondary {
  background: var(--ss-surface);
  color: var(--ss-text-2);
  border: 1.5px solid var(--ss-border-strong);
  box-shadow: var(--shadow-xs);
}
.ss-btn-secondary:hover { background: var(--ss-surface-2); color: var(--ss-text); border-color: var(--ss-border-strong); }

/* Responsive: single column chips on mobile */
@media (max-width: 576px) {
  .ss-modal-details { grid-template-columns: 1fr; }
  .ss-modal-footer { flex-direction: column-reverse; gap: .5rem; }
  .ss-btn-primary, .ss-btn-secondary { width: 100%; justify-content: center; }
}
```

---

### SECTION: Alert Level Color System (Icon + Badge)

```css
/* ════════════════════════════════════════════
   ALERT LEVEL COLOR SYSTEM
════════════════════════════════════════════ */

/* Icon bubbles */
.ss-alert-icon.ss-level-critical { background: #fef2f2; color: var(--ss-critical); }
.ss-alert-icon.ss-level-danger   { background: #fff7ed; color: var(--ss-danger); }
.ss-alert-icon.ss-level-warning  { background: #fffbeb; color: var(--ss-warning); }

/* Level badges (used in alerts page and dashboard) */
.ss-badge-level {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .25em .65em;
  border-radius: var(--r-sm);
  font-size: .68rem;
  font-weight: 800;
  letter-spacing: .05em;
  text-transform: uppercase;
  line-height: 1;
  white-space: nowrap;
}
.ss-badge-level.ss-level-critical {
  background: #fef2f2;
  color: var(--ss-critical);
  border: 1px solid rgba(185,28,28,.18);
}
.ss-badge-level.ss-level-danger {
  background: #fff7ed;
  color: var(--ss-danger);
  border: 1px solid rgba(194,65,12,.18);
}
.ss-badge-level.ss-level-warning {
  background: #fffbeb;
  color: var(--ss-warning);
  border: 1px solid rgba(180,83,9,.18);
}

/* "NEW" badge */
.ss-new-badge {
  display: inline-flex;
  align-items: center;
  padding: .2em .55em;
  border-radius: var(--r-sm);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  background: var(--ss-primary);
  color: #fff;
  line-height: 1;
}

/* Dashboard alert rows */
.ss-dash-alert-row {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: .85rem 1rem;
  border-bottom: 1px solid var(--ss-border);
  transition: background var(--t-fast);
}
.ss-dash-alert-row:last-child { border-bottom: none; }
.ss-dash-alert-row.unread { background: #f7f9ff; }
.ss-dash-alert-row:hover { background: var(--ss-primary-light); }
.ss-dash-alert-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: var(--r-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .9rem;
}
.ss-dash-alert-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .2rem .75rem;
  font-size: .72rem;
  color: var(--ss-text-3);
  margin-top: .3rem;
}
.ss-dash-alert-meta i { opacity: .7; }
```

---

## 🟠 POLISH — Alerts Page: Fix IoT Guide Card Color

**File:** `medical/app/Views/alerts/index.php`

The guide box at the bottom uses Bootstrap `bg-info` (cyan-blue), which doesn't belong in the SafeSense palette.

**Find:**
```html
<div class="card mt-5 border-info border-2">
  <div class="card-header bg-info text-white">
```

**Replace with:**
```html
<div class="card mt-5" style="border: 1.5px solid var(--ss-primary);">
  <div class="card-header text-white" style="background: linear-gradient(135deg, var(--ss-primary) 0%, var(--ss-primary-dark) 100%);">
```

---

## 🟡 POLISH — Notification Drawer: Improve the JS-Built Notification HTML

**File:** `medical/app/Views/layouts/main.php` — inside the inline `<script>` block, inside the `addDrawerItem(a)` function.

The JS currently renders notifications using raw template literals without the refined class structure. Update the `div.innerHTML` to match the new CSS component hierarchy:

**Find the `addDrawerItem` function and update the `div.innerHTML` assignment:**

```js
// BEFORE
div.innerHTML=`
  <button class="ss-notif-x" data-dismiss="${a.id}">×</button>
  <div class="ss-notif-level"><i class="fas ${ICONS[a.alert_level]||'fa-bell'} me-1"></i>${LABELS[a.alert_level]||a.alert_level.toUpperCase()}</div>
  <div class="ss-notif-msg">${esc(a.message)}</div>
  <div class="ss-notif-meta">
    <span><i class="fas fa-map-marker-alt"></i>${esc(a.location_name||'—')}</span>
    <span><i class="fas fa-clock"></i>${time}</span>
    <span><i class="fas fa-calendar-alt"></i>${date}</span>
    <span><i class="fas fa-bolt"></i>${esc(a.event_type)}</span>
  </div>`;
```

```js
// AFTER — refined structure matching new CSS
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
```

Also update the `LABELS` constant — remove the emoji prefixes since the level labels now have proper icons:
```js
// BEFORE
const LABELS = { critical:'🔴 CRITICAL', danger:'🟠 DANGER', warning:'🟡 WARNING' };

// AFTER
const LABELS = { critical:'CRITICAL', danger:'DANGER', warning:'WARNING' };
```

---

## 🟡 POLISH — Unread Counter: Animate to Zero Gracefully

**File:** `medical/app/Views/alerts/index.php` — `<script>` block.

When all alerts are marked read, instead of just showing "0 Unread", transition the counter to a muted "All Read" state. Update the `markAllReadBtn` handler:

```js
document.getElementById('markAllReadBtn').addEventListener('click', () => {
  ajaxPost(window.BASE_URL + '/api/alerts/read', { id: 'all' })
    .then(d => {
      document.querySelectorAll('.ss-alert-card-unread')
        .forEach(el => el.classList.remove('ss-alert-card-unread'));
      document.querySelectorAll('.badge.bg-primary').forEach(el => el.remove());

      const ub = document.getElementById('unreadBadge');
      if (ub) {
        ub.innerHTML = '<i class="fas fa-check-circle"></i><span>All Read</span>';
        ub.style.background = 'linear-gradient(135deg, #16a34a 0%, #15803d 100%)';
      }
      if (typeof setBadge === 'function') setBadge(0);
    });
});
```

Add this CSS to `style.css`:
```css
/* Smooth transition on unread counter state change */
.ss-unread-counter {
  transition: background var(--t-base), box-shadow var(--t-base);
}
```

---

## 📋 FILE TOUCH SUMMARY

| File | Changes |
|------|---------|
| `medical/public/css/style.css` | Add: `.ss-live-dot`, `.ss-x-btn`, full drawer refinement (replace old), toast inner elements, alert modal inner elements, alert level color system (icon + badge + dash rows) |
| `medical/app/Views/alerts/index.php` | Bug fix: `textContent→innerHTML` for mark-all-read; unread counter HTML structure; alerts loop: remove `$badge`/`$label`, use `ss-level-*` for icon + badge; IoT guide card color; improved markAllRead handler |
| `medical/app/Views/dashboard.php` | Replace old `$badge` alert loop with `ss-dash-alert-row` + `ss-level-*` system |
| `medical/app/Views/layouts/main.php` | Update `addDrawerItem` JS innerHTML; remove emoji from `LABELS` constant |

---

## ⚠️ CONSTRAINTS

1. Do NOT change PHP controllers, routes, models, or database code.
2. Do NOT remove the `ss-notif`, `ss-notif.unread`, or `ss-notif[data-level=*]` selectors — only refine and extend them.
3. The drawer open/close, mark-all-read, dismiss, and polling logic must keep working.
4. Do NOT add new files — all CSS goes to `style.css`, all JS stays inline in the views.
5. Maintain Bootstrap 5 compatibility throughout.
