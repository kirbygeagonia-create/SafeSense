# SafeSense — Round 8 Fix: Critical `poll()` Function Missing
### For Windsurf Kimi K2.5

> **All previous rounds confirmed clean.** Round 7's `ageMs < 30000` init guard is correctly applied. CSS is complete. Stat card borders, alarm, modal guard, hospital name, CSRF token, DataTables — all verified working. One critical bug remains, introduced during the Round 6 IIFE rewrite.

---

## 🔴 CRITICAL — `poll()` Function Was Accidentally Dropped: Real-Time Alerts Are Dead

**File:** `medical/app/Views/layouts/main.php`

### What's broken

The very last line of the IIFE before `})()` is:
```js
setInterval(poll, POLL_MS);
```

But `poll` is **never defined anywhere in the script**. When this line executes, the browser throws:
```
ReferenceError: poll is not defined
```

This kills the interval immediately and silently. The consequence is:

| Feature | Status |
|---------|--------|
| Live polling for new alerts every 5s | ❌ Completely dead |
| Toast notifications for real IoT alerts | ❌ Never fire |
| Drawer auto-updating with new real alerts | ❌ Never updates |
| Bell badge incrementing for new alerts | ❌ Never increments |
| Modal for real IoT alerts arriving after page load | ❌ Never fires |
| Simulation (via 30s init window) | ✅ Works (init fetch handles it) |

The function was present in the codebase until the Round 6 full IIFE replacement, where it was accidentally omitted.

---

### The Fix — Add the missing `poll()` function

**Location:** Inside the IIFE `(function(){ ... })()`, place this function **immediately before** the `/* ── Alarm ── */` comment block (i.e., after `function killToast(t){ ... }` and before `/* ── Modal ── */`).

Add this exact function:

```js
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
```

**Exact insertion point** — place it between `killToast` and the Modal section:

```js
  function killToast(t){ t.classList.add('out'); setTimeout(()=>t.remove(),350); }

  /* ── Poll — fetches new alerts since lastPoll every POLL_MS ── */   ← INSERT HERE
  function poll(){
    ...
  }

  /* ── Modal ── */
  function showModal(a){
```

---

## 🟡 MINOR — Footer Live Dot Uses Inline Style Instead of CSS Class

**File:** `medical/app/Views/layouts/main.php` — **line 200**

The footer's live dot uses an inline style to set 6px dimensions, but `.ss-live-dot--sm` is already defined in `style.css` with exactly those dimensions. Replace the inline style with the CSS class for consistency:

```html
<!-- BEFORE (line 200): -->
<span class="ss-live-dot" style="width:6px;height:6px;"></span>SafeSense IoT Active

<!-- AFTER: -->
<span class="ss-live-dot ss-live-dot--sm"></span>SafeSense IoT Active
```

---

## ✅ CONFIRMED FULLY WORKING — Do NOT re-touch

| System | Status |
|--------|--------|
| `ageMs < 30000` simulation modal guard | ✅ |
| `sessionStorage` cross-page modal guard (`wasShown`) | ✅ |
| `startAlarm()` / `stopAlarm()` continuous siren | ✅ |
| All CSS components (drawer, toast, modal, badges, icons) | ✅ |
| Stat cards using `ss-stat-left-*` (no Bootstrap border conflicts) | ✅ |
| Hospital name "Tupi" across config, auth, home, titles | ✅ |
| CSRF token meta tag and usage in `post()` | ✅ |
| DataTables `dom` layout (no overlap with create buttons) | ✅ |
| No `console.log` debug statements remaining | ✅ |
| `.ss-live-dot--sm` CSS defined | ✅ |
| `markAllRead` uses `innerHTML` (bell icon preserved) | ✅ |
| `.ss-new-badge` selector in `markAllReadBtn` handler | ✅ |
| `.ss-badge.bump` animation | ✅ |
| `showToast` null guard on `event_type` | ✅ |

---

## 📋 Change Summary

| # | File | Change |
|---|------|--------|
| 1 | `medical/app/Views/layouts/main.php` | Add `function poll(){...}` between `killToast` and the Modal section |
| 2 | `medical/app/Views/layouts/main.php` | Line 200: replace `class="ss-live-dot" style="width:6px;height:6px;"` with `class="ss-live-dot ss-live-dot--sm"` |

**Total: 2 changes, 1 file.** No CSS changes, no other files.

---

## ⚠️ Constraints

1. Only `medical/app/Views/layouts/main.php` changes.
2. Do NOT change `setInterval(poll, POLL_MS)` — that line is correct.
3. Do NOT change any other function in the IIFE.
4. The `poll()` function must use `lastPoll` (the module-level `let lastPoll` variable) — do not introduce a new variable.
5. Keep `console.error('Poll error:', e)` in the catch — this is acceptable for error logging (only debug `console.log` was removed previously).
