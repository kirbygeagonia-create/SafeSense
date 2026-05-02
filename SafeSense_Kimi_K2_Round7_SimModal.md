# SafeSense — Round 7 Fix: Simulation Modal Not Appearing
### For Windsurf Kimi K2.5

> **All Round 6 fixes confirmed clean:** `sessionStorage` modal guard working, `startAlarm()`/`stopAlarm()` implemented, hospital name changed to "Tupi" across all files, `.ss-live-dot--sm` added. This round fixes one single remaining bug introduced by the Round 6 modal guard.

---

## 🔴 THE BUG — Root Cause (Precisely Traced)

### What happens when you click "Simulate Alert"

1. The simulate button in `alerts/index.php` POSTs to `/api/alert/simulate`, which creates a new alert in the database.
2. On success, the JS does:
   ```js
   setTimeout(() => window.location.reload(), 1800);
   ```
   The page **reloads** after 1.8 seconds so the new alert appears in the server-rendered list.

3. After the reload, the IIFE in `main.php` runs completely fresh. The init fetch runs:
   ```js
   fetch(window.BASE_URL + '/api/alerts/poll?since=1970-01-01+00:00:00')
     .then(d => {
       if (d.server_time) lastPoll = d.server_time;  // ← set to current server time
       if (d.unread_count) setBadge(d.unread_count);
       (d.alerts || []).forEach(a => addDrawerItem(a)); // ← drawer only, NO modal
     })
   ```
   The init gets ALL alerts including the freshly-created simulated one. But by design (from Round 6), the init deliberately does **NOT** call `showModal()` for any of them — only `addDrawerItem()`. This was correct for stopping the cross-page modal repeat.

4. `lastPoll` is now set to the server's current time — which is **AFTER** the simulated alert was created.

5. When the poll fires 5 seconds later:
   ```
   GET /api/alerts/poll?since={server_time_from_step_4}
   ```
   It asks for alerts created **after** `lastPoll`. The simulated alert was created **before** `lastPoll`. So the poll returns **nothing new**.

6. **The modal never fires.** The alert only appears silently in the drawer and the rendered list.

### Why the old cross-page repeat bug was different

When navigating between pages (Patients → Doctors → etc.), there is **no page reload** involved in creating the alert — the alert already existed for minutes or hours. The fix correctly blocked those. The simulation is different: it creates a brand-new alert and then immediately reloads the page, so the alert is always less than 2 seconds old when the init runs.

---

## ✅ THE FIX — One Surgical Change to One File

**File:** `medical/app/Views/layouts/main.php`
**Location:** The init fetch `.then()` callback at the very bottom of the inline `<script>` block.

**Find this exact block:**
```js
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
```

**Replace with:**
```js
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
```

---

## Why This Fix Is Safe

| Scenario | Result |
|----------|--------|
| User navigates Patients → Doctors — old unread critical alert from 10 min ago | `ageMs = 600000 > 30000` → no modal ✅ |
| User runs simulation → page reloads → init runs | `ageMs ≈ 1800 < 30000` → modal shows ✅ |
| Real IoT alert arrives, user navigates within 30s | `wasShown(a.id)` = true (set when poll showed it) → `showModal` returns early ✅ |
| Real IoT alert arrives, user navigates after 30s | `ageMs > 30000` → no modal ✅ |
| Same simulation alert shown on another page nav | `wasShown(a.id)` = true (set when modal opened) → `showModal` returns early ✅ |

The `wasShown()` sessionStorage guard from Round 6 still handles all re-show prevention. This change only restores the modal for the narrow "alert just created → page reloaded" scenario.

---

## Change Summary

| File | Lines Changed | What |
|------|--------------|------|
| `medical/app/Views/layouts/main.php` | ~6 lines in init `.then()` | Replace `forEach(a => addDrawerItem(a))` with expanded forEach that also calls `showModal(a)` for alerts with `ageMs < 30000` |

**No other files need to change.**

---

## ⚠️ Constraints

1. Only `medical/app/Views/layouts/main.php` changes — no other files.
2. Do NOT change the `showModal()` function, `wasShown()`, `markShown()`, or any other part of the IIFE.
3. Do NOT remove the `window.location.reload()` from `alerts/index.php` — the page reload is needed so the new simulated alert appears in the server-rendered alert list.
4. The `30000` ms threshold is intentional — it is large enough to cover the 1.8s reload delay plus network latency, but small enough that historical alerts (minutes/hours old) are never shown.
