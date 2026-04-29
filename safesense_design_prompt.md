# SafeSense — UI/UX Redesign Prompt
## Complete Design Overhaul: Breathing Room, Refined Color, Icon-Based Alerts, Polished Animations

This is a full front-end design pass. The logic, PHP, and functionality stay **100% untouched**. Only CSS, layout HTML, and inline styles are changed. Read every section carefully before writing a single line of code.

---

## DESIGN DIRECTION

**Aesthetic:** Clean clinical authority. Think of a modern hospital monitoring system that a 70-year-old nurse and a 25-year-old technician can both use without hesitation. Not "medical app" clichés (no stock-photo blues) — refined, calm, trustworthy. Warm neutral backgrounds, strong typographic hierarchy, generous whitespace, and alert colors that feel serious rather than garish.

**Constraints:**
- Bootstrap 5 stays as the grid/component foundation — do not replace it
- Font: keep IBM Plex Sans (already loaded) — it reads well at all ages
- All existing PHP logic and class names used by JavaScript (e.g. `ss-badge`, `ss-drawer`, `ss-notif`, `ss-toast`, etc.) must stay identical — JS depends on them
- Must remain accessible: minimum 44px touch targets, WCAG AA contrast
- Animations must respect `prefers-reduced-motion`

---

## FILE 1 — `medical/public/css/style.css`
**Replace the entire file** with the redesigned version below.

```css
/* ════════════════════════════════════════════════════════
   SafeSense Hospital Management — Design System v2
   Refined Clinical Authority
════════════════════════════════════════════════════════ */

/* ── Design Tokens ─────────────────────────────────── */
:root {
  /* Brand */
  --ss-primary:        #1d4ed8;
  --ss-primary-light:  #eff6ff;
  --ss-primary-dark:   #1e3a8a;

  /* Alert levels */
  --ss-critical:       #b91c1c;
  --ss-critical-bg:    #fef2f2;
  --ss-critical-border:#fecaca;
  --ss-danger:         #c2410c;
  --ss-danger-bg:      #fff7ed;
  --ss-danger-border:  #fed7aa;
  --ss-warning:        #b45309;
  --ss-warning-bg:     #fffbeb;
  --ss-warning-border: #fde68a;

  /* Neutrals */
  --ss-bg:             #f8fafc;
  --ss-surface:        #ffffff;
  --ss-surface-2:      #f1f5f9;
  --ss-border:         #e2e8f0;
  --ss-border-strong:  #cbd5e1;
  --ss-text:           #0f172a;
  --ss-text-2:         #475569;
  --ss-text-3:         #94a3b8;

  /* Typography */
  --font-main:  'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif;
  --font-mono:  'IBM Plex Mono', 'Cascadia Code', monospace;

  /* Radius */
  --r-sm:  6px;
  --r-md:  10px;
  --r-lg:  14px;
  --r-xl:  18px;

  /* Shadows */
  --shadow-xs: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  --shadow-sm: 0 2px 8px rgba(15,23,42,.07), 0 1px 3px rgba(15,23,42,.05);
  --shadow-md: 0 8px 24px rgba(15,23,42,.10), 0 2px 8px rgba(15,23,42,.06);
  --shadow-lg: 0 20px 48px rgba(15,23,42,.14), 0 6px 16px rgba(15,23,42,.08);

  /* Transitions */
  --t-fast:   120ms ease;
  --t-base:   200ms ease;
  --t-slow:   320ms cubic-bezier(.4, 0, .2, 1);
  --t-spring: 360ms cubic-bezier(.34, 1.56, .64, 1);
}

/* ── Base ──────────────────────────────────────────── */
html { font-size: 16px; scroll-behavior: smooth; }

body {
  font-family: var(--font-main);
  background: var(--ss-bg);
  color: var(--ss-text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  line-height: 1.6;
}

main { flex: 1; padding-top: 1.5rem; padding-bottom: 3rem; }

/* ── Navbar ────────────────────────────────────────── */
.navbar {
  background: var(--ss-primary-dark) !important;
  box-shadow: 0 2px 12px rgba(15,23,42,.22);
  padding-top: .75rem;
  padding-bottom: .75rem;
}

.navbar-brand {
  font-weight: 700;
  font-size: 1.1rem;
  letter-spacing: -.02em;
  color: #fff !important;
  display: flex;
  align-items: center;
  gap: .5rem;
}

.navbar-brand i { color: #f87171; font-size: 1.15rem; }

.navbar .nav-link {
  color: rgba(255,255,255,.75) !important;
  font-size: .875rem;
  font-weight: 500;
  padding: .45rem .75rem !important;
  border-radius: var(--r-sm);
  transition: background var(--t-fast), color var(--t-fast);
}

.navbar .nav-link:hover,
.navbar .nav-link:focus-visible {
  color: #fff !important;
  background: rgba(255,255,255,.10);
}

/* ── Page Headers ─────────────────────────────────── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--ss-border);
}

.page-header h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--ss-text);
  margin: 0;
  letter-spacing: -.02em;
  display: flex;
  align-items: center;
  gap: .6rem;
}

.page-header h1 i {
  font-size: 1.2rem;
  color: var(--ss-primary);
  flex-shrink: 0;
}

.page-header .page-subtitle {
  font-size: .8rem;
  color: var(--ss-text-3);
  margin-top: .15rem;
}

/* ── Cards ─────────────────────────────────────────── */
.card {
  border: 1px solid var(--ss-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-xs);
  background: var(--ss-surface);
  transition: box-shadow var(--t-base);
}

.card:hover { box-shadow: var(--shadow-sm); }

.card-header {
  background: var(--ss-surface);
  border-bottom: 1px solid var(--ss-border);
  border-radius: var(--r-lg) var(--r-lg) 0 0 !important;
  padding: 1rem 1.25rem;
  font-weight: 600;
  font-size: .9rem;
  color: var(--ss-text);
}

.card-body { padding: 1.25rem; }

/* Stat cards */
.stat-card {
  border: 1px solid var(--ss-border);
  border-radius: var(--r-lg);
  background: var(--ss-surface);
  padding: 1.25rem 1.5rem;
  box-shadow: var(--shadow-xs);
  transition: box-shadow var(--t-base), transform var(--t-base);
  height: 100%;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.stat-label {
  font-size: .75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--ss-text-3);
  margin-bottom: .4rem;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--ss-text);
  line-height: 1.1;
  letter-spacing: -.03em;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--r-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

/* ── Tables ─────────────────────────────────────────── */
.table {
  background: var(--ss-surface);
  margin: 0;
  font-size: .875rem;
}

.table thead th {
  border-top: none;
  border-bottom: 2px solid var(--ss-border);
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--ss-text-2);
  padding: .85rem 1rem;
  white-space: nowrap;
  background: var(--ss-surface-2);
}

.table tbody td {
  padding: .9rem 1rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--ss-border);
  color: var(--ss-text);
}

.table tbody tr {
  transition: background var(--t-fast);
}

.table tbody tr:hover {
  background: var(--ss-primary-light);
}

.table-dark thead th {
  background: var(--ss-primary-dark) !important;
  color: rgba(255,255,255,.85) !important;
  border-color: rgba(255,255,255,.1) !important;
}

/* ── Buttons ─────────────────────────────────────────── */
.btn {
  border-radius: var(--r-md);
  font-weight: 500;
  font-size: .875rem;
  transition: background var(--t-fast), box-shadow var(--t-fast),
              transform var(--t-fast), border-color var(--t-fast);
  min-height: 44px;
  padding: .55rem 1.1rem;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
}

.btn:active { transform: scale(.97); }

.btn:focus-visible {
  outline: 3px solid var(--ss-primary);
  outline-offset: 2px;
  box-shadow: 0 0 0 4px rgba(29,78,216,.2);
}

.btn-sm {
  min-height: 34px;
  font-size: .8rem;
  padding: .35rem .75rem;
  border-radius: var(--r-sm);
}

.table .btn-sm { min-height: 32px; }

.btn-primary {
  background: var(--ss-primary);
  border-color: var(--ss-primary);
}

.btn-primary:hover {
  background: var(--ss-primary-dark);
  border-color: var(--ss-primary-dark);
  box-shadow: 0 4px 12px rgba(29,78,216,.3);
}

.btn:focus { box-shadow: none; }

/* ── Forms ─────────────────────────────────────────── */
.form-label {
  font-size: .8rem;
  font-weight: 600;
  color: var(--ss-text-2);
  margin-bottom: .35rem;
}

.form-control,
.form-select {
  border-radius: var(--r-md);
  border: 1.5px solid var(--ss-border-strong);
  font-size: .875rem;
  padding: .6rem .9rem;
  color: var(--ss-text);
  transition: border-color var(--t-fast), box-shadow var(--t-fast);
  min-height: 44px;
}

.form-control:focus,
.form-select:focus {
  border-color: var(--ss-primary);
  box-shadow: 0 0 0 3px rgba(29,78,216,.15);
  outline: none;
}

.form-control::placeholder { color: var(--ss-text-3); }

/* ── Modals ─────────────────────────────────────────── */
.modal-content {
  border: none;
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  padding: 1.1rem 1.4rem;
  border-bottom: 1px solid rgba(255,255,255,.1);
}

.modal-header.bg-primary {
  background: linear-gradient(135deg, var(--ss-primary) 0%, var(--ss-primary-dark) 100%) !important;
}

.modal-title {
  font-weight: 600;
  font-size: .975rem;
}

.modal-body { padding: 1.5rem; }

.modal-footer {
  border-top: 1px solid var(--ss-border);
  padding: 1rem 1.4rem;
  background: var(--ss-surface-2);
}

/* ── Badges ─────────────────────────────────────────── */
.badge {
  font-size: .7rem;
  font-weight: 600;
  padding: .28em .65em;
  border-radius: var(--r-sm);
  letter-spacing: .02em;
}

/* ── DataTables ─────────────────────────────────────── */
.dataTables_wrapper {
  padding: 0;
}

.dataTables_wrapper .dataTables_filter {
  margin-bottom: .75rem;
}

.dataTables_wrapper .dataTables_filter input {
  border: 1.5px solid var(--ss-border-strong);
  border-radius: var(--r-md);
  padding: .5rem .9rem;
  font-size: .875rem;
  min-height: 40px;
  color: var(--ss-text);
  transition: border-color var(--t-fast), box-shadow var(--t-fast);
}

.dataTables_wrapper .dataTables_filter input:focus {
  border-color: var(--ss-primary);
  box-shadow: 0 0 0 3px rgba(29,78,216,.12);
  outline: none;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_length .form-select {
  display: inline-block !important;
  width: auto !important;
  min-width: 90px !important;
  padding-right: 2.5rem !important;
  border-radius: var(--r-md);
  border: 1.5px solid var(--ss-border-strong);
}

.dataTables_wrapper .dataTables_info {
  font-size: .78rem;
  color: var(--ss-text-3);
  padding-top: .6rem;
}

.dataTables_wrapper .dataTables_paginate {
  padding-top: .6rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  border-radius: var(--r-sm) !important;
  min-width: 34px;
  min-height: 34px;
  font-size: .8rem;
}

/* ── Live Dot ──────────────────────────────────────── */
.ss-live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  display: inline-block;
  margin-right: 6px;
  vertical-align: middle;
  animation: livePulse 2.4s ease-in-out infinite;
}

@keyframes livePulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
  50%       { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
}

/* ── Bell ───────────────────────────────────────────── */
.ss-bell-wrap {
  position: relative;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: var(--r-md);
  transition: background var(--t-fast);
}

.ss-bell-wrap:hover { background: rgba(255,255,255,.12); }

.ss-bell-wrap .fa-bell {
  font-size: 1.15rem;
  color: rgba(255,255,255,.85);
}

/* ── Notification Badge ─────────────────────────────── */
.ss-badge {
  position: absolute;
  top: -2px;
  right: -3px;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  background: #ef4444;
  border: 2px solid var(--ss-primary-dark);
  border-radius: 99px;
  font-size: .6rem;
  font-weight: 700;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.ss-badge.bump { animation: badgeBump var(--t-spring); }

@keyframes badgeBump {
  0%, 100% { transform: scale(1); }
  50%       { transform: scale(1.6); }
}

/* ── X button (shared) ──────────────────────────────── */
.ss-x-btn {
  background: none;
  border: none;
  color: rgba(255,255,255,.65);
  font-size: .95rem;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: var(--r-sm);
  line-height: 1;
  transition: background var(--t-fast), color var(--t-fast);
}

.ss-x-btn:hover {
  background: rgba(255,255,255,.18);
  color: #fff;
}

/* ═══════════════════════════════════════════════════════
   NOTIFICATION DRAWER
═══════════════════════════════════════════════════════ */
.ss-drawer-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.55);
  z-index: 1050;
  backdrop-filter: blur(3px);
  animation: fadeIn var(--t-base);
}

.ss-drawer-overlay.open { display: block; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.ss-drawer {
  position: fixed;
  top: 0;
  right: -440px;
  width: 420px;
  max-width: 96vw;
  height: 100vh;
  background: var(--ss-surface);
  z-index: 1051;
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 40px rgba(15,23,42,.20);
  transition: right var(--t-slow);
}

.ss-drawer.open { right: 0; }

.ss-drawer-header {
  padding: 1.1rem 1.25rem;
  background: var(--ss-primary-dark);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255,255,255,.08);
}

.ss-drawer-header h5 {
  font-size: .9rem;
  font-weight: 700;
  letter-spacing: -.01em;
}

.ss-drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: .75rem;
  background: var(--ss-surface-2);
}

.ss-drawer-body::-webkit-scrollbar { width: 4px; }
.ss-drawer-body::-webkit-scrollbar-track { background: transparent; }
.ss-drawer-body::-webkit-scrollbar-thumb { background: var(--ss-border-strong); border-radius: 4px; }

.ss-drawer-footer {
  padding: .875rem;
  border-top: 1px solid var(--ss-border);
  flex-shrink: 0;
  background: var(--ss-surface);
}

/* ── Notification Items ─────────────────────────────── */
.ss-notif {
  border-radius: var(--r-md);
  padding: .875rem 2.5rem .875rem 1rem;
  margin-bottom: 6px;
  border-left: 3px solid var(--ss-border-strong);
  background: var(--ss-surface);
  cursor: pointer;
  position: relative;
  transition: background var(--t-fast), box-shadow var(--t-fast), transform var(--t-fast);
  box-shadow: var(--shadow-xs);
}

.ss-notif:hover {
  background: var(--ss-primary-light);
  box-shadow: var(--shadow-sm);
  transform: translateX(3px);
}

.ss-notif.unread {
  background: #f0f5ff;
  border-left-color: var(--ss-primary);
}

.ss-notif[data-level="critical"] { border-left-color: var(--ss-critical); }
.ss-notif[data-level="danger"]   { border-left-color: var(--ss-danger);   }
.ss-notif[data-level="warning"]  { border-left-color: var(--ss-warning);  }

.ss-notif-level {
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .09em;
  margin-bottom: .25rem;
  display: flex;
  align-items: center;
  gap: .3rem;
}

[data-level="critical"] .ss-notif-level { color: var(--ss-critical); }
[data-level="danger"]   .ss-notif-level { color: var(--ss-danger);   }
[data-level="warning"]  .ss-notif-level { color: var(--ss-warning);  }

.ss-notif-msg {
  font-size: .82rem;
  color: var(--ss-text);
  margin-bottom: .4rem;
  line-height: 1.45;
}

.ss-notif-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  font-size: .7rem;
  color: var(--ss-text-3);
}

.ss-notif-meta i { margin-right: 3px; color: var(--ss-text-3); }

.ss-notif-x {
  position: absolute;
  top: 8px;
  right: 8px;
  background: none;
  border: none;
  color: var(--ss-text-3);
  font-size: .9rem;
  cursor: pointer;
  line-height: 1;
  padding: 3px 5px;
  border-radius: var(--r-sm);
  transition: color var(--t-fast), background var(--t-fast);
}

.ss-notif-x:hover {
  color: var(--ss-critical);
  background: var(--ss-critical-bg);
}

/* ═══════════════════════════════════════════════════════
   ALERT MODAL
═══════════════════════════════════════════════════════ */
.ss-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.78);
  z-index: 2000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(5px);
}

.ss-modal-overlay.show { display: flex !important; }

.ss-modal {
  width: 520px;
  max-width: 95vw;
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(15,23,42,.45);
  animation: modalIn var(--t-spring);
}

@keyframes modalIn {
  from { transform: scale(.72) translateY(40px); opacity: 0; }
  to   { transform: scale(1)   translateY(0);    opacity: 1; }
}

.ss-modal-header {
  padding: 1.4rem 1.5rem 1.2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.ss-modal[data-level="critical"] .ss-modal-header { background: var(--ss-critical); }
.ss-modal[data-level="danger"]   .ss-modal-header { background: var(--ss-danger);   }
.ss-modal[data-level="warning"]  .ss-modal-header { background: var(--ss-warning);  }

.ss-modal-icon {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  background: rgba(255,255,255,.18);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: #fff;
  animation: iconPulse 1.8s ease-in-out infinite;
}

@keyframes iconPulse {
  0%, 100% { box-shadow: 0 0 0 0   rgba(255,255,255,.45); }
  55%       { box-shadow: 0 0 0 12px rgba(255,255,255,0);  }
}

.ss-modal-level {
  color: #fff;
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: -.01em;
}

.ss-modal-device {
  color: rgba(255,255,255,.75);
  font-size: .78rem;
  margin-top: 2px;
}

.ss-modal-body {
  background: var(--ss-surface);
  padding: 1.25rem 1.5rem;
}

.ss-modal-message {
  background: var(--ss-surface-2);
  border-radius: var(--r-md);
  padding: .9rem 1.1rem;
  font-size: .9rem;
  color: var(--ss-text);
  line-height: 1.65;
  border-left: 4px solid var(--ss-border-strong);
  margin-bottom: 1.1rem;
}

.ss-modal[data-level="critical"] .ss-modal-message { border-color: var(--ss-critical); background: var(--ss-critical-bg); }
.ss-modal[data-level="danger"]   .ss-modal-message { border-color: var(--ss-danger);   background: var(--ss-danger-bg);   }
.ss-modal[data-level="warning"]  .ss-modal-message { border-color: var(--ss-warning);  background: var(--ss-warning-bg);  }

.ss-modal-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 1rem;
}

.ss-detail-chip {
  background: var(--ss-surface-2);
  border: 1px solid var(--ss-border);
  border-radius: var(--r-md);
  padding: .65rem .875rem;
}

.ss-chip-label {
  font-size: .65rem;
  color: var(--ss-text-3);
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-bottom: .25rem;
  display: flex;
  align-items: center;
  gap: .3rem;
}

.ss-chip-label i { color: var(--ss-primary); font-size: .7rem; }

.ss-chip-val {
  font-size: .875rem;
  color: var(--ss-text);
  font-weight: 600;
}

.ss-modal-footer {
  background: var(--ss-surface);
  padding: .875rem 1.5rem 1.25rem;
  display: flex;
  gap: .625rem;
  justify-content: flex-end;
  border-top: 1px solid var(--ss-border);
}

.ss-btn-secondary {
  padding: .55rem 1.1rem;
  border-radius: var(--r-md);
  font-size: .875rem;
  font-weight: 600;
  border: 1.5px solid var(--ss-border-strong);
  background: var(--ss-surface);
  color: var(--ss-text-2);
  cursor: pointer;
  transition: background var(--t-fast), border-color var(--t-fast);
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}

.ss-btn-secondary:hover {
  background: var(--ss-surface-2);
  border-color: var(--ss-border-strong);
}

.ss-btn-primary {
  padding: .55rem 1.25rem;
  border-radius: var(--r-md);
  font-size: .875rem;
  font-weight: 600;
  border: none;
  color: #fff;
  cursor: pointer;
  transition: filter var(--t-fast), transform var(--t-fast);
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}

.ss-btn-primary:hover  { filter: brightness(1.08); }
.ss-btn-primary:active { transform: scale(.97);    }

.ss-modal[data-level="critical"] .ss-btn-primary { background: var(--ss-critical); }
.ss-modal[data-level="danger"]   .ss-btn-primary { background: var(--ss-danger);   }
.ss-modal[data-level="warning"]  .ss-btn-primary { background: var(--ss-warning);  }

/* ═══════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
═══════════════════════════════════════════════════════ */
.ss-toast-wrap {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 3000;
  display: flex;
  flex-direction: column-reverse;
  gap: .6rem;
  pointer-events: none;
}

.ss-toast {
  pointer-events: all;
  min-width: 300px;
  max-width: 360px;
  border-radius: var(--r-lg);
  padding: .875rem 1rem;
  color: #fff;
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  box-shadow: var(--shadow-lg);
  animation: toastIn 280ms cubic-bezier(.34, 1.56, .64, 1);
  cursor: pointer;
}

.ss-toast[data-level="critical"] { background: var(--ss-critical); }
.ss-toast[data-level="danger"]   { background: var(--ss-danger);   }
.ss-toast[data-level="warning"]  { background: var(--ss-warning);  }

@keyframes toastIn {
  from { transform: translateX(110%) scale(.9); opacity: 0; }
  to   { transform: translateX(0)   scale(1);   opacity: 1; }
}

.ss-toast.out { animation: toastOut 250ms ease forwards; }

@keyframes toastOut {
  to { transform: translateX(110%) scale(.9); opacity: 0; }
}

.ss-toast-icon { font-size: 1.15rem; flex-shrink: 0; margin-top: 2px; }
.ss-toast-body { flex: 1; }
.ss-toast-title { font-size: .85rem; font-weight: 700; }
.ss-toast-sub   { font-size: .75rem; opacity: .85; margin-top: 2px; }

.ss-toast-x {
  margin-left: auto;
  background: none;
  border: none;
  color: rgba(255,255,255,.7);
  font-size: .9rem;
  cursor: pointer;
  flex-shrink: 0;
  padding: 2px 4px;
  border-radius: 4px;
  transition: color var(--t-fast);
}

.ss-toast-x:hover { color: #fff; }

/* ═══════════════════════════════════════════════════════
   ALERTS PAGE
═══════════════════════════════════════════════════════ */
.ss-alert-card {
  border: 1px solid var(--ss-border);
  border-radius: var(--r-lg) !important;
  transition: box-shadow var(--t-base), transform var(--t-base);
  box-shadow: var(--shadow-xs);
}

.ss-alert-card:hover {
  box-shadow: var(--shadow-md) !important;
  transform: translateY(-1px);
}

.ss-alert-card-unread {
  background: #f5f8ff !important;
}

.ss-alert-icon {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border-radius: var(--r-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
}

/* Alert level filter pills */
.ss-filter-pill {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .4rem .9rem;
  border-radius: 99px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid var(--ss-border-strong);
  background: var(--ss-surface);
  color: var(--ss-text-2);
  transition: background var(--t-fast), border-color var(--t-fast),
              color var(--t-fast), box-shadow var(--t-fast);
}

.ss-filter-pill:hover {
  border-color: var(--ss-primary);
  color: var(--ss-primary);
}

.ss-filter-pill.active {
  background: var(--ss-primary-dark);
  border-color: var(--ss-primary-dark);
  color: #fff;
  box-shadow: 0 2px 8px rgba(29,78,216,.25);
}

.ss-filter-pill[data-filter="critical"].active { background: var(--ss-critical); border-color: var(--ss-critical); }
.ss-filter-pill[data-filter="danger"].active   { background: var(--ss-danger);   border-color: var(--ss-danger);   }
.ss-filter-pill[data-filter="warning"].active  { background: var(--ss-warning);  border-color: var(--ss-warning);  }

/* ── Login page button ─────────────────────────────── */
.btn-login {
  background: linear-gradient(135deg, var(--ss-primary), var(--ss-primary-dark));
  border: none;
  border-radius: var(--r-md);
  color: #fff;
  font-weight: 600;
  padding: .75rem 1.25rem;
  width: 100%;
  font-size: .9rem;
  transition: opacity var(--t-fast), transform var(--t-fast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  cursor: pointer;
  min-height: 48px;
}

.btn-login:hover  { opacity: .92; color: #fff; }
.btn-login:active { transform: scale(.98); }

/* ── Accessibility ────────────────────────────────── */
.btn:focus-visible,
.form-control:focus-visible,
.form-select:focus-visible,
.nav-link:focus-visible,
a:focus-visible {
  outline: 3px solid var(--ss-primary);
  outline-offset: 2px;
}

/* ── Reduced motion ───────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: .01ms !important;
  }
}

/* ── Responsive ───────────────────────────────────── */
@media (max-width: 576px) {
  .ss-drawer { width: 100%; }
  .ss-modal-details { grid-template-columns: 1fr; }
  .stat-value { font-size: 1.6rem; }
}
```

---

## FILE 2 — `medical/app/Views/alerts/index.php`
**Replace the entire file.** Key changes: emoji labels → Font Awesome icons, filter buttons → pill style, cards get more breathing room, data rows use icon+text chips.

```php
<?php
$levelMeta = [
  'critical' => ['icon' => 'fa-skull-crossbones', 'label' => 'Critical', 'color' => 'danger',  'hex' => '#b91c1c'],
  'danger'   => ['icon' => 'fa-exclamation-triangle','label' => 'Danger',  'color' => 'warning', 'hex' => '#c2410c'],
  'warning'  => ['icon' => 'fa-cloud-rain',          'label' => 'Warning', 'color' => 'info',    'hex' => '#b45309'],
];
?>

<div class="page-header">
  <div>
    <h1><i class="fas fa-satellite-dish"></i>SafeSense Alert Log</h1>
    <div class="page-subtitle">Real-time IoT flood &amp; hazard alerts from the field</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-danger px-3 py-2 fs-6" id="unreadBadge"
          style="border-radius:99px; font-weight:700;">
      <?php echo $unreadCount; ?> Unread
    </span>
    <button class="btn btn-outline-secondary btn-sm" id="markAllReadBtn">
      <i class="fas fa-check-double"></i>Mark All Read
    </button>
  </div>
</div>

<!-- Level filter pills -->
<div class="d-flex gap-2 flex-wrap mb-4">
  <button class="ss-filter-pill active" data-filter="all">
    <i class="fas fa-list"></i>All Alerts
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
<div class="d-flex flex-column gap-3" id="alertsGrid">
  <?php foreach ($alerts as $a):
    $lvl  = $a['alert_level'] ?? 'warning';
    $meta = $levelMeta[$lvl] ?? $levelMeta['warning'];
    $dt   = new DateTime($a['created_at']);
    $unreadClass = (!$a['is_read']) ? 'ss-alert-card-unread' : '';
    $borderColor = $meta['hex'];
  ?>
  <div class="alert-card-wrap" data-level="<?php echo htmlspecialchars($lvl); ?>">
    <div class="card ss-alert-card <?php echo $unreadClass; ?>"
         style="border-left: 4px solid <?php echo $borderColor; ?>;">
      <div class="card-body" style="padding: 1.1rem 1.25rem;">
        <div class="d-flex align-items-start gap-3">

          <!-- Level icon -->
          <div class="ss-alert-icon flex-shrink-0"
               style="background: <?php echo $borderColor; ?>18; color: <?php echo $borderColor; ?>;">
            <i class="fas <?php echo $meta['icon']; ?>"></i>
          </div>

          <!-- Content -->
          <div class="flex-grow-1 min-w-0">
            <!-- Top row: level badge + event + new pill -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="badge" style="background:<?php echo $borderColor; ?>; color:#fff; font-size:.67rem; border-radius:6px; padding:.3em .75em; letter-spacing:.05em;">
                <i class="fas <?php echo $meta['icon']; ?> me-1"></i><?php echo strtoupper($meta['label']); ?>
              </span>
              <span class="badge bg-secondary" style="font-size:.67rem;">
                <i class="fas fa-bolt me-1"></i><?php echo strtoupper(htmlspecialchars($a['event_type'])); ?>
              </span>
              <?php if (!$a['is_read']): ?>
              <span class="badge" style="background:#1d4ed8; color:#fff; font-size:.67rem;">
                <i class="fas fa-circle me-1" style="font-size:.45rem;"></i>NEW
              </span>
              <?php endif; ?>
            </div>

            <!-- Message -->
            <p class="mb-2 fw-semibold" style="font-size:.9rem; color:#0f172a; line-height:1.5;">
              <?php echo htmlspecialchars($a['message']); ?>
            </p>

            <!-- Meta chips -->
            <div class="d-flex flex-wrap gap-2" style="font-size:.78rem; color:#64748b;">
              <span><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo htmlspecialchars($a['location_name'] ?? '—'); ?></span>
              <span><i class="fas fa-clock me-1"></i><?php echo $dt->format('h:i:s A'); ?></span>
              <span><i class="fas fa-calendar me-1"></i><?php echo $dt->format('M d, Y'); ?></span>
              <?php if ($a['water_level']): ?>
              <span><i class="fas fa-tint me-1 text-primary"></i><?php echo $a['water_level']; ?> cm</span>
              <?php endif; ?>
              <?php if ($a['vibration']): ?>
              <span><i class="fas fa-wave-square me-1 text-warning"></i>Vibration</span>
              <?php endif; ?>
              <span><i class="fas fa-microchip me-1"></i><?php echo htmlspecialchars($a['device_id']); ?></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex flex-column gap-2 ms-2 flex-shrink-0">
            <?php if ($a['latitude'] && $a['longitude']): ?>
            <a href="https://www.google.com/maps?q=<?php echo $a['latitude']; ?>,<?php echo $a['longitude']; ?>"
               target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-primary" title="View on map"
               style="min-height:34px;">
              <i class="fas fa-map-marked-alt"></i>
            </a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary dismiss-btn"
                    data-id="<?php echo $a['id']; ?>" title="Dismiss"
                    style="min-height:34px;">
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
<div class="card" style="border: 2px dashed #e2e8f0;">
  <div class="card-body text-center py-5">
    <div style="width:64px;height:64px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
      <i class="fas fa-check-circle text-success fa-2x"></i>
    </div>
    <h5 style="color:#0f172a; font-weight:700;">All Clear</h5>
    <p class="text-muted mb-0">No SafeSense alerts recorded. The system is monitoring.</p>
  </div>
</div>
<?php endif; ?>

<!-- IoT connection guide -->
<div class="card mt-4" style="border-color:#bfdbfe; border-width:1.5px;">
  <div class="card-header" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
    <i class="fas fa-plug me-2"></i>Arduino → System Connection Guide
  </div>
  <div class="card-body">
    <p class="text-muted mb-3" style="font-size:.875rem;">Your SafeSense Arduino should POST sensor data to:</p>
    <pre class="rounded p-3 mb-3" style="background:#0f172a; color:#4ade80; font-size:.82rem; font-family:'IBM Plex Mono',monospace; overflow-x:auto;">POST <?php echo APP_URL; ?>/api/alert
Content-Type: application/json</pre>
    <p class="text-muted mb-2" style="font-size:.875rem;">Required JSON fields:</p>
    <pre class="rounded p-3" style="background:#0f172a; color:#7dd3fc; font-size:.78rem; font-family:'IBM Plex Mono',monospace; overflow-x:auto;">{
  "api_key":       "your-key-from-.env",
  "device_id":     "SAFESENSE-001",
  "station_type":  "hospital",
  "alert_level":   "critical",
  "event_type":    "flood",
  "rain_status":   "heavy",
  "water_level":   45.2,
  "vibration":     0,
  "message":       "CRITICAL: Flood detected...",
  "latitude":      8.1574,
  "longitude":     124.9282,
  "location_name": "Brgy. Casisang, Malaybalay City"
}</pre>
  </div>
</div>

<script>
// Filter pills
document.querySelectorAll('.ss-filter-pill').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.ss-filter-pill').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const f = this.dataset.filter;
    document.querySelectorAll('.alert-card-wrap').forEach(el => {
      el.style.display = (f === 'all' || el.dataset.level === f) ? '' : 'none';
    });
  });
});

// Dismiss
document.querySelectorAll('.dismiss-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id   = this.dataset.id;
    const card = this.closest('.alert-card-wrap');
    ajaxPost(window.BASE_URL + '/api/alerts/dismiss', { id })
      .then(() => {
        card.style.transition = 'opacity .25s, transform .25s';
        card.style.opacity    = '0';
        card.style.transform  = 'translateX(20px)';
        setTimeout(() => card.remove(), 260);
      });
  });
});

// Mark all read
document.getElementById('markAllReadBtn').addEventListener('click', () => {
  ajaxPost(window.BASE_URL + '/api/alerts/read', { id: 'all' })
    .then(d => {
      document.querySelectorAll('.ss-alert-card-unread').forEach(el => el.classList.remove('ss-alert-card-unread'));
      document.querySelectorAll('[style*="background:#1d4ed8"]').forEach(el => el.remove());
      const ub = document.getElementById('unreadBadge');
      if (ub) ub.textContent = '0 Unread';
      if (typeof setBadge === 'function') setBadge(0);
    });
});
</script>
```

---

## FILE 3 — `medical/app/Views/layouts/auth.php`
**Remove the demo credentials box** (it references the deleted admin@example.com demo login). Replace the `<div class="demo-box ...">` block with nothing. The rest of the auth layout stays identical.

Find and delete these lines:
```php
    <div class="demo-box text-center">
        <i class="fas fa-key me-1"></i> Demo: <strong>admin@example.com</strong> / <strong>password</strong>
    </div>
```

---

## FILE 4 — All CRUD index views: add `page-header` class
**Files:** `patients/index.php`, `doctors/index.php`, `appointments/index.php`, `emr/index.php`, `billing/index.php`, `users/index.php`

In each file, find the opening page header div and replace it with the structured version. Example for `patients/index.php`:

```php
<!-- OLD: -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-injured me-2"></i>Patients</h1>

<!-- NEW: -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-injured"></i>Patients</h1>
        <div class="page-subtitle">Manage patient records</div>
    </div>
```

Apply the same pattern to all 6 index files with appropriate titles and subtitles:
- Patients → "Manage patient records"
- Doctors → "Medical staff directory"
- Appointments → "Schedule & track appointments"
- Medical Records → "Electronic medical records"
- Billing → "Invoices & payment tracking"
- Users → "System user accounts"

---

## FILE 5 — `medical/app/Views/dashboard.php`
**Replace the stats row cards** with the new `stat-card` pattern for breathing room. Find the "Stats row" section and replace each card's inner HTML:

```php
<!-- OLD pattern (example — Patients card): -->
<div class="card h-100 border-0 shadow-sm">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <div class="text-muted small mb-1">Total Patients</div>
      <h2 class="mb-0 fw-bold"><?php echo $patientCount ?? 0; ?></h2>
    </div>
    <div class="rounded-circle bg-primary bg-opacity-10 ..." style="width:52px;height:52px;">
      <i class="fas fa-user-injured text-primary fa-lg"></i>
    </div>
  </div>
</div>

<!-- NEW pattern (apply to all 8 stat cards): -->
<div class="stat-card">
  <div class="d-flex align-items-start justify-content-between mb-3">
    <div class="stat-label">Total Patients</div>
    <div class="stat-icon" style="background:#eff6ff; color:#1d4ed8;">
      <i class="fas fa-user-injured"></i>
    </div>
  </div>
  <div class="stat-value"><?php echo $patientCount ?? 0; ?></div>
</div>
```

Apply to all 8 stat cards with their respective labels, icons, and colors:
- Total Patients: bg `#eff6ff`, color `#1d4ed8`, icon `fa-user-injured`
- Total Doctors: bg `#f0fdf4`, color `#15803d`, icon `fa-user-md`
- Appointments: bg `#f0f9ff`, color `#0369a1`, icon `fa-calendar-check`
- Unread Alerts: bg `#fef2f2`, color `#b91c1c`, icon `fa-satellite-dish`
- Total Invoiced: bg `#eff6ff`, color `#1d4ed8`, icon `fa-file-invoice-dollar`
- Total Collected: bg `#f0fdf4`, color `#15803d`, icon `fa-hand-holding-usd`
- Total Unpaid: bg `#fffbeb`, color `#b45309`, icon `fa-exclamation-triangle`
- Invoice Count: bg `#f0f9ff`, color `#0369a1`, icon `fa-receipt`

Also wrap the Unread Alerts stat-card in its anchor tag:
```php
<a href="<?php echo url('/alerts'); ?>" class="text-decoration-none">
  <div class="stat-card" style="border-left: 3px solid #b91c1c;">
    <!-- content -->
  </div>
</a>
```

Also replace the emoji labels in the dashboard alert list (the `$label` variable) with icon-based badges — same as done in alerts/index.php above.

---

## VERIFICATION CHECKLIST

After applying all changes, confirm:

```
[ ] style.css — no emoji anywhere in the CSS file
[ ] alerts/index.php — zero emoji characters (🔴 🟠 🟡 replaced with Font Awesome icons)
[ ] dashboard.php — stat cards use stat-card class, no inline width/height styles on icon circles
[ ] dashboard.php — alert list labels use <i class="fas ..."> not emoji
[ ] auth.php layout — demo-box div is deleted
[ ] All 6 index pages — opening div uses class="page-header" not d-flex mb-4
[ ] All existing JS class names preserved: ss-badge, ss-drawer, ss-drawer-overlay,
    ss-drawer-body, ss-drawer-footer, ss-notif, ss-notif-level, ss-notif-msg,
    ss-notif-meta, ss-notif-x, ss-modal-overlay, ss-modal, ss-modal-header,
    ss-modal-icon, ss-modal-level, ss-modal-device, ss-modal-body, ss-modal-message,
    ss-modal-details, ss-detail-chip, ss-chip-label, ss-chip-val, ss-modal-footer,
    ss-btn-primary, ss-btn-secondary, ss-toast-wrap, ss-toast, ss-toast-icon,
    ss-toast-body, ss-toast-title, ss-toast-sub, ss-toast-x, ss-alert-card,
    ss-alert-card-unread, ss-alert-icon, ss-live-dot, ss-bell-wrap, ss-badge,
    ss-x-btn, ss-drawer-header, btn-login, alert-card-wrap, ss-filter-pill
[ ] No PHP logic was changed — only HTML structure and CSS classes
[ ] Filter pills in alerts/index.php use class="ss-filter-pill" not btn btn-sm btn-dark
[ ] prefers-reduced-motion block present in style.css
[ ] Dismiss animation uses translateX not opacity alone
[ ] git commit -m "design: full UI refinement — breathing room, icons, refined palette"
```
