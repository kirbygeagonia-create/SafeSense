# SafeSense — Final Precision Fix Prompt (Round 5)
### For Windsurf Kimi K2.5

> **Audit Status — Round 4 was nearly perfect.** All 9 previous issues were addressed correctly: the `markAllRead` bell icon bug is fixed, `.ss-new-badge` selector is correct, `.ss-badge.bump` animation exists, the `showToast` crash guard is applied, the redundant bell nav icon was replaced with `fa-satellite-dish`, all three debug `console.log` lines are gone, stat card first-row borders are added, `.stat-card > .d-flex` is correctly scoped, and `.ss-unread-counter` is merged into one rule. Active nav highlighting and footer polish were also added as bonuses.
>
> **Only 2 issues remain.** Both are surgical CSS fixes.

---

## ISSUE 1 — Stat Cards: Bootstrap Border Utilities Create Incorrect Multi-Sided Thick Borders

**Files:** `medical/public/css/style.css` + `medical/app/Views/dashboard.php`

### The Problem

All 8 stat cards use this Bootstrap class combination:
```
border-{color}  border-start  border-4
```

These three classes interact destructively:
- `.border-{color}` → sets `border-color` on ALL 4 sides (with `!important`)
- `.border-4` → sets `border-width: 4px` on ALL 4 sides (with `!important`)
- `.border-start` → references the now-4px-thick, Bootstrap-colored left border

**Visible result:** All 4 edges of every stat card become thick and colored in Bootstrap's palette (`#0d6efd` blue, `#198754` green, etc.) — NOT the SafeSense palette. The existing CSS rules `.stat-card.border-primary { border-top: 3px solid var(--ss-primary) }` are also fighting Bootstrap's `!important` overrides on border-color, creating further visual conflicts.

### Step 1 — In `style.css`: Remove the 5 old rules; add 5 clean custom ones

**Find and DELETE these 5 lines** (in the "Stat cards" section, after `.stat-icon`):
```css
.stat-card.border-primary { border-top: 3px solid var(--ss-primary) !important; }
.stat-card.border-success { border-top: 3px solid #22c55e !important; }
.stat-card.border-info { border-top: 3px solid #0ea5e9 !important; }
.stat-card.border-warning { border-top: 3px solid #f59e0b !important; }
.stat-card.border-danger { border-top: 3px solid var(--ss-critical) !important; }
```

**Replace with these 5 new rules** in the same location:
```css
/* ── Stat card accent borders — left only, SafeSense palette ── */
.ss-stat-left-primary { border-left: 4px solid var(--ss-primary) !important; }
.ss-stat-left-success { border-left: 4px solid #16a34a !important; }
.ss-stat-left-info    { border-left: 4px solid #0891b2 !important; }
.ss-stat-left-warning { border-left: 4px solid #d97706 !important; }
.ss-stat-left-danger  { border-left: 4px solid var(--ss-critical) !important; }
```

### Step 2 — In `dashboard.php`: Replace class attributes on all 8 stat card divs

```html
<!-- Total Patients -->
<!-- BEFORE: --> <div class="stat-card border-primary border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-primary">

<!-- Total Doctors -->
<!-- BEFORE: --> <div class="stat-card border-success border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-success">

<!-- Appointments -->
<!-- BEFORE: --> <div class="stat-card border-info border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-info">

<!-- Unread Alerts -->
<!-- BEFORE: --> <div class="stat-card border-danger border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-danger">

<!-- Total Invoiced -->
<!-- BEFORE: --> <div class="stat-card border-primary border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-primary">

<!-- Total Collected -->
<!-- BEFORE: --> <div class="stat-card border-success border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-success">

<!-- Total Unpaid -->
<!-- BEFORE: --> <div class="stat-card border-warning border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-warning">

<!-- Invoice Count -->
<!-- BEFORE: --> <div class="stat-card border-info border-start border-4">
<!-- AFTER:  --> <div class="stat-card ss-stat-left-info">
```

**Result:** Each stat card gets exactly one clean 4px left accent stripe in the correct SafeSense palette color. The other 3 sides keep the base `1px solid var(--ss-border)` gray border from `.stat-card`. No conflicts, no Bootstrap color overrides, no multi-sided thick borders.

---

## ISSUE 2 — CSS: Non-Standard `line-clamp: 2` Still Present

**File:** `medical/public/css/style.css` — line ~399

`display: box` was removed in Round 4 as instructed, but the companion `line-clamp: 2` (bare, non-prefixed) was missed. The working property is `-webkit-line-clamp: 2` which is already present. The bare form is invalid in browsers.

**Find this block:**
```css
.ss-notif-msg {
  ...
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;            /* ← remove this line only */
  -webkit-box-orient: vertical;
  overflow: hidden;
}
```

**Remove only `line-clamp: 2;` — final block:**
```css
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
```

---

## Change Summary

| # | File | Change |
|---|------|--------|
| 1a | `style.css` | Delete 5 old `.stat-card.border-*` rules; add 5 `.ss-stat-left-*` rules |
| 1b | `dashboard.php` | Replace `border-{color} border-start border-4` → `ss-stat-left-{color}` on 8 stat card divs |
| 2 | `style.css` | Remove `line-clamp: 2` from `.ss-notif-msg` |

Total: ~20 lines changed across 2 files. These are the final two items.

## Constraints

1. Only `style.css` and `dashboard.php` need to change — no other files.
2. Do NOT change any PHP controllers, routes, models, or JS files.
3. Do NOT alter anything else in `dashboard.php` — only the `class=` attribute of the 8 stat card divs listed above.
4. The new `.ss-stat-left-*` rules go in the same location as the old `.stat-card.border-*` rules (Stat cards section, after `.stat-icon`).
