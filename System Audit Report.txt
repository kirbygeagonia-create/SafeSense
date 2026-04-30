System Audit Report

## 🐛 Bugs & Errors

**1. `btn-login` class double-declared**
In `style.css` there are two `.btn-login` definitions — one at line 158 (global, using CSS vars) and another inside `.auth-card` at line 825 (with hardcoded hex `#2563eb`). The `.auth-card .btn-login` will always win due to specificity, making the global one dead code. The global one should be removed, or the auth-specific one should be renamed `.auth-card .btn-login` cleanly. More importantly, the global `.btn-login` has `min-height: 48px` while the auth one has `padding: .75rem` — inconsistent sizing.

**2. `buildOptions` / `findName` re-declared across modules (app.js)**
The functions `buildOptions` and `findName` are each declared three separate times inside the IIFE — once in the appointments block, once in EMR, once in billing. Since they're all `function` declarations inside an `if (apptEl)` / `if (emrEl)` / `if (billEl)` block, this works but is a silent conflict waiting to cause issues if JS strict mode is tightened or the scope changes. These should be hoisted to the top of the IIFE as shared utilities.

**3. `ss-badge` display logic conflict**
In CSS (line 127-128), `.ss-badge` uses `display: none` as the base with `display: flex` set via `[data-count]:not([data-count="0"])`. But `setBadge()` in JS manually sets `badge.style.display = 'flex'` and `badge.style.display = 'none'` as inline styles — which will override the CSS attribute selector indefinitely. If the inline style is set to `none`, the CSS selector `[data-count]:not([data-count="0"])` can never override it. These two approaches conflict. Pick one: either use only the CSS attribute selector (and remove the `style.display` JS lines), or use only JS-driven inline styles (and remove the CSS selector logic).

**4. `ss-badge.unread` class is vestigial**
Line 131 defines `.ss-badge.unread { background: #ef4444 !important; animation: badgeBump... }` but `.ss-badge` is already `background: #ef4444` by default (line 127). The `.unread` modifier is never added by JS — only `.bump` is toggled. This rule is dead code.

**5. Dashboard h1 missing design system class**
In `dashboard.php` (line 2), the title is `<h1 class="mb-0">Dashboard</h1>` — no `page-header` wrapper, no icon, no subtitle structure — while every other page (Patients, Doctors, etc.) uses the `.page-header` component correctly. The dashboard header is visually inconsistent with the rest of the system.

**6. `home.php` uses non-existent utility classes**
`class="fw-800"` and `class="fw-700"` (lines 6, 19, 28) are not Bootstrap 5 classes (Bootstrap only ships `fw-bold` = 700 and `fw-bolder` = 800). They silently fall back to the inherited weight and do nothing. Use `style="font-weight: 800"` or `fw-bolder` / `fw-bold` instead.

**7. `ss-modal-overlay` uses `display: none` then `display: flex !important`**
The overlay (line 519) uses `display: none` then `.show { display: flex !important }`. The `!important` is unnecessary since `.show` is a more-specific class activation — but more importantly, this pattern conflicts with Bootstrap's own modal which also uses `display` toggling. If Bootstrap's JS is ever triggered on this element, they'll fight. The `!important` should be removed and the overlay should be a sibling element clearly separated from Bootstrap modals.

**8. Polling starts 30 seconds in the past on first load**
Line 231: `let lastPoll = new Date(Date.now() - 30000).toISOString()...` — this means on first load the poll fetches alerts from 30 seconds ago. But the initial load on line 383 fetches from `1970-01-01` to get the badge count. This creates two separate initial fetches with different time scopes, which could cause duplicate drawer items if alerts were created in the last 30 seconds before page load. The `lastPoll` variable should start at page-load time, not -30 seconds.

---

## 🎨 Design Refinements Needed

**9. Dashboard stat cards: `border-start border-4` conflicts with `.stat-card` styles**
The stat cards use Bootstrap's `border-start border-4` utility classes, but `style.css` defines `.stat-card.border-primary { border-top: 3px solid ... !important }` — these are border-top rules, while the HTML is applying border-start (left). The result is both a left and a top border visible on the cards. You need to pick one accent direction and be consistent: either remove the CSS `border-top` rules and let Bootstrap's `border-start` work, or switch the HTML to `border-top border-3` and keep the CSS.

**10. `live dot` gap in nav alert link**
The nav link (line 88 of `main.php`): `SafeSense Alerts<span class="ss-live-dot ms-1"></span>` — the dot is positioned after the text but has no vertical alignment. Since the link uses `d-flex align-items-center`, the dot should align fine, but the `ms-1` gives only 4px gap after the word "Alerts". This looks cramped. Use `gap-2` on the parent instead of margin-start on the dot.

**11. Dashboard card headers mix Bootstrap utility classes with custom CSS**
Lines 129-133 of `dashboard.php`: `class="card border-0 shadow-sm h-100"` — these cards bypass the `.card` custom styles in `style.css` entirely because `border-0` overrides `var(--ss-border)` and `shadow-sm` replaces `var(--shadow-xs)`. The dashboard cards look subtly different from cards on other pages. Either use `class="card"` alone and let the CSS design system handle it, or move the custom card CSS into a modifier class.

**12. Billing stat cards show raw numbers without currency symbol**
Lines 74-100 of `dashboard.php`: `number_format(..., 2)` outputs e.g. `12,500.00` with no peso sign (₱) or any currency indicator. For a Philippine hospital system, amounts should be prefixed with `₱` or at minimum `PHP`.

**13. Inconsistent icon color for "alert icon" in dashboard rows**
In `dashboard.php`, the alert icon div uses `class="ss-dash-alert-icon"` with the level class, but in `style.css` only `.ss-alert-icon.ss-level-*` is defined — not `.ss-dash-alert-icon.ss-level-*`. The CSS file defines icon colors for the Alerts page icons (`.ss-alert-icon`) but the dashboard icon class (`.ss-dash-alert-icon`) has no background/color rules tied to level. The icon div will be unstyled (transparent background, inherited text color).

**14. `ss-filter-pill` hover conflict on active state**
When a pill is active, hovering it should keep it in the active color. But CSS specificity means `.ss-filter-pill[data-filter=critical]:not(.active):hover` won't fire on active pills — good. However `.ss-filter-pill:hover` (line 112) applies `border-color: var(--ss-primary); color: var(--ss-primary)` to ALL pills on hover, including active ones, which will override the active critical/danger/warning colors since it's lower specificity than the level-specific hover. Test this: hover an active "Critical" pill — it turns blue. Add `:not(.active)` to the base hover rule: `.ss-filter-pill:not(.active):hover`.

**15. Toast z-index (3000) higher than custom modal (2000)**
If a toast fires while the alert modal is open, the toast renders on top of the modal. Swap these — the modal should be the top layer (z-index 3000) since it requires acknowledgement, and toasts should be below it (z-index 2500 or lower).

**16. Modal animation `scale(.72)` is jarring**
The `@keyframes modalIn` (line 529) starts from `scale(.72)` which is an unusually dramatic scale-in. Standard practice is `scale(.95)` for a subtle pop — `.72` makes the modal feel like it's shooting in from a great distance. Change to `scale(.92) translateY(16px)` for a more refined feel.

**17. Auth card missing `font-family` on `btn-login`**
The `.auth-card .btn-login` rule doesn't set `font-family`. Since the `<button>` element doesn't inherit `font-family` in some browsers, it may render in the browser's default system font instead of IBM Plex Sans. Add `font-family: var(--font-main)` to that rule.

**18. Footer `ss-live-dot` is 6×6px inline override**
Line 198: `style="width:6px;height:6px;"` overrides the 8×8px defined in CSS. If you want a smaller dot in the footer, make a `.ss-live-dot--sm` modifier class instead of an inline override. This is a maintainability concern.

**19. No responsive handling for the notification drawer at medium breakpoints**
The drawer is 400px wide with a `right: -460px` starting position. At ~500px viewport width (tablet portrait), a 400px drawer covers 80% of the screen with no visual treatment difference. The existing `@media (max-width: 576px)` rule sets `width: 100%`, which is correct for mobile — but there's no intermediate breakpoint. At 500–576px, the drawer is still 400px and feels oversized.

**20. Dashboard chart empty state `<p>` appended to wrong container**
Lines 253-256: when no alert data exists, a `<p>` element is appended to `#alertsChartWrap`. But `#alertsChartWrap` is the `.card-body` div, and the `<canvas>` is hidden with `style.display = 'none'` — the `<p>` is appended as a sibling after the canvas. This works, but `card-body` has `padding: 1.25rem` already. The empty state paragraph doesn't have vertical centering and will left-align inside the card. Use flexbox centering on the wrapper, or match the styling from the server-side empty states on the alerts page.

---

## 💡 Polish & UX Improvements

**21. No loading state on form submission in CRUD modals**
`app.js`'s `initCrudModule` form submit handler (line 183) fires the AJAX request with no visual feedback. The submit button should be disabled and show a spinner while the request is in-flight. Add:
```js
const submitBtn = form.querySelector('[type="submit"]');
submitBtn.disabled = true;
submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
// restore in .then and .catch
```

**22. The `ajaxPost` dismiss in alerts page uses a different call pattern**
Alerts page JS (line 150) calls `ajaxPost(url, { id: id })` — passing an object. But the global `ajaxPost` in `app.js` (line 29-34) uses `new URLSearchParams(formData).toString()` which handles objects fine. However, the alerts page JS calls `ajaxPost` before `app.js` is loaded (the alerts page script is inline, before the `app.js` `<script>` tag at the bottom of `main.php`). This works because `app.js` is loaded at the end of `<body>` and the click handler fires later — but it's a fragile dependency. The `window.ajaxPost = ajaxPost` global exposure in `app.js` (line 38) makes this work, but only after DOM-ready. If someone clicks a dismiss button immediately on load, `ajaxPost` won't exist yet. Use a small debounce or move the global exposure earlier.

**23. `SweetAlert2` is loaded but not imported in `main.php`'s inline `<script>`**
The inline polling script (lines 210-385) calls `Swal.fire()` — wait, it doesn't. Only `app.js` uses `Swal`. But `app.js` references `Swal` which requires SweetAlert2 to be loaded first. The load order in `main.php` is: Bootstrap → jQuery → DataTables → **SweetAlert2** → inline poll script → `app.js`. SweetAlert2 loads before `app.js`, so this is fine — but it's worth noting the order is fragile. Any script reordering breaks `app.js`.

**24. IoT connection guide box at bottom of alerts page has inline styles**
Lines 104-132 of `alerts/index.php` use `style="border: 1.5px solid var(--ss-primary)"`, `style="background: linear-gradient(...)"`, `style="font-size:.85rem"`. These should be moved to a CSS class for maintainability. Suggested class: `.ss-iot-guide`.

**25. `markAllReadBtn` doesn't update the nav bell badge on the alerts page**
Line 171 of `alerts/index.php`: `if (typeof setBadge === 'function') setBadge(0);` — `setBadge` is defined inside an IIFE in `main.php`'s inline `<script>` and is not exposed to `window`. So `typeof setBadge` will always be `'undefined'` and the bell badge won't update when "Mark All Read" is clicked from the Alerts page. Either expose `setBadge` to `window` in `main.php`, or add a custom event that the inline script listens for.

---

## Summary Priority Table

| Priority | Issue | Type |
|---|---|---|
| 🔴 High | `ss-badge` display logic JS/CSS conflict (#3) | Bug |
| 🔴 High | `buildOptions`/`findName` re-declared 3× (#2) | Bug |
| 🔴 High | Dashboard alert icon level colors missing (#13) | Visual |
| 🔴 High | `setBadge` not exposed to window (#25) | Bug |
| 🟠 Medium | `btn-login` double declaration (#1) | Bug |
| 🟠 Medium | `border-start` vs `border-top` stat card conflict (#9) | Visual |
| 🟠 Medium | Filter pill active hover turns blue (#14) | Bug |
| 🟠 Medium | Toast z-index above modal (#15) | UX |
| 🟠 Medium | No loading state on form submit (#21) | UX |
| 🟠 Medium | Currency symbol missing on billing (#12) | UX |
| 🟡 Low | Modal scale-in too dramatic (#16) | Polish |
| 🟡 Low | Dashboard h1 missing page-header class (#5) | Visual |
| 🟡 Low | `fw-800`/`fw-700` invalid Bootstrap classes (#6) | Bug |
| 🟡 Low | IoT guide box inline styles (#24) | Maintainability |
| 🟡 Low | Footer live dot inline size override (#18) | Maintainability |

The system is impressively close to production quality — most issues are small inconsistencies rather than architectural problems. The alert system design in particular (drawer + toast + modal + polling + CSRF) is robust. Focus first on the `ss-badge` JS/CSS conflict and the missing dashboard alert icon colors, as those are the most visible to end users.