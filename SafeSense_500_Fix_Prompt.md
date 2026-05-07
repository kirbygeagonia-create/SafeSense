# SafeSense — 500 Internal Server Error: Root Cause Report & Fix Prompt

**Scan date:** May 2026 · Live repo: `kirbygeagonia-create/SafeSense` (branch: main)

---

## Root Cause Analysis

There are **4 bugs** causing the 500 errors, and they interact with each other. The most destructive is Bug #1 — it turns minor PHP notices into hard crashes across the entire application.

---

## BUG 1 🔴 CRITICAL — Error handler kills the app: `APP_DEBUG` constant is never defined

**File:** `medical/public/index.php` (error handler block) AND `medical/app/Config/config.php`

The new `set_error_handler` added in the last fix session was written correctly, but it references `APP_DEBUG` as a PHP constant:

```php
if (defined('APP_DEBUG') && APP_DEBUG) return false; // show native errors in dev
```

**`APP_DEBUG` is never defined as a constant anywhere in `config.php`.** The `.env` file has `APP_DEBUG=true` which loads into `$_ENV['APP_DEBUG']` via vlucas/phpdotenv — but no code ever calls `define('APP_DEBUG', ...)`.

**The consequence:** Since `defined('APP_DEBUG')` is always `false`, the error handler **never returns `false`**. Every PHP notice, warning, or deprecation — including harmless ones like "undefined array key" — causes the handler to call `exit(1)` and show the 500 page. This is the primary reason for widespread 500 errors.

---

## BUG 2 🔴 CRITICAL — PHP syntax error in `search/index.php` line 112

**File:** `medical/app/Views/search/index.php`

Line 112 has a broken PHP ternary expression inside an HTML attribute. The closing `?>` tag is missing before the `"`:

```php
// BROKEN (parse error — PHP cannot compile this file):
<td><span class="badge bg-<?php echo $b['payment_status']==='paid'?'success':'warning';"><?php ...
```

The `?>` before the `"` closer is missing, so PHP sees `warning';"` as part of the PHP expression, causing a **Fatal Parse Error**. Every request to `/search` throws a 500.

---

## BUG 3 🟠 HIGH — Undefined index notice on every page → caught by error handler → 500

**File:** `medical/app/Views/layouts/main.php` lines 179–180

This was partially flagged in the last audit but not fixed. The navbar user pill reads:

```php
<span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
```

No null-coalesce `?? ''` guard. On PHP 8.1+, accessing an undefined array key fires an `E_DEPRECATED` notice. With Bug #1 still present (the aggressive error handler), this notice is caught and converted into a **500 crash on every authenticated page** — dashboard, patients, billing, EMR — everywhere that uses `main.php` layout.

---

## BUG 4 🟡 MEDIUM — Wrong column name in dashboard alertStats query (silent DB error)

**File:** `medical/app/Controllers/AuthController.php` line 152

The alertStats query uses a column named `level` that does not exist. The real column name is `alert_level`:

```php
// WRONG — column "level" does not exist in safesense_alerts:
$stmt = $db->query("SELECT level, COUNT(*) AS cnt FROM safesense_alerts WHERE is_read = 0 GROUP BY level");
$alertStats[$r['level']] = (int)$r['cnt'];

// The migration 004 defines:   alert_level ENUM('warning','danger','critical')
```

This is wrapped in `try/catch` so it doesn't directly cause a 500 — instead `$alertStats` stays empty and the dashboard alert badge silently shows nothing. However with Bug #1 active, if the PDO exception somehow escapes the catch (e.g. strict error modes), it could also surface as a 500.

---

## Impact Summary

| Bug | Pages Affected | Error Type |
|-----|----------------|-----------|
| #1 — `APP_DEBUG` undefined | **Every page** | All PHP notices become 500s |
| #2 — Syntax error search/index.php | `/search` | Fatal 500 always |
| #3 — Missing null-coalesce in navbar | **Every authenticated page** | 500 via error handler |
| #4 — Wrong column `level` in dashboard query | `/dashboard` alert badge | Silent wrong data |

**Fixing Bug #1 alone would likely restore most of the site.** But all 4 must be fixed together.

---

# Windsurf Kimi K2.5 — Targeted 500 Fix Prompt

> **Instructions:** Apply all 4 fixes below in the exact order given. These are surgical changes — do not modify any file, class, or function not explicitly listed. After applying all fixes, run the verification commands at the bottom.

---

## FIX 1 — Define `APP_DEBUG` constant in `config.php`
**File:** `medical/app/Config/config.php`

Open the file. At the very top, after the existing `define('APP_NAME', ...)` and `define('APP_URL', ...)` lines, add these two lines:

```php
// Debug mode — reads from .env APP_DEBUG value; defaults to false in production
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_ENV',   $_ENV['APP_ENV'] ?? 'production');
```

These must be placed **after** the `.env` file is loaded but **before** the error handler in `index.php` reads `APP_DEBUG`. Because `config.php` is required by `index.php` after the session setup but before the error handler registration, the order is already correct — no other changes to `index.php` are needed.

---

## FIX 2 — Fix syntax error in `search/index.php` line 112
**File:** `medical/app/Views/search/index.php`

Find line 112. The broken line looks like this:
```php
<td><span class="badge bg-<?php echo $b['payment_status']==='paid'?'success':'warning';"><?php echo $b['payment_status']; ?></span></td>
```

Replace it with the correctly closed version (note the added `?>` before the closing `"`):
```php
<td><span class="badge bg-<?php echo $b['payment_status']==='paid'?'success':'warning'; ?>"><?php echo htmlspecialchars($b['payment_status']); ?></span></td>
```

---

## FIX 3 — Add null-coalesce guards to the navbar user pill
**File:** `medical/app/Views/layouts/main.php`

Find these two lines (they appear inside the `<!-- User pill -->` section, around line 179):
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'])); ?>)</small>
```

Replace with:
```php
<span><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?></span>
<small>(<?php echo ucfirst(htmlspecialchars($_SESSION['user']['role'] ?? 'staff')); ?>)</small>
```

---

## FIX 4 — Fix wrong column name in dashboard alertStats query
**File:** `medical/app/Controllers/AuthController.php`

Find the alertStats query block inside the `dashboard()` method (around line 152). It currently reads:
```php
$stmt = $db->query("SELECT level, COUNT(*) AS cnt FROM safesense_alerts WHERE is_read = 0 GROUP BY level");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $alertStats[$r['level']] = (int)$r['cnt'];
}
```

Replace with the correct column name `alert_level`:
```php
$stmt = $db->query("SELECT alert_level, COUNT(*) AS cnt FROM safesense_alerts WHERE is_read = 0 GROUP BY alert_level");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $alertStats[$r['alert_level']] = (int)$r['cnt'];
}
```

---

## ✅ Verification Commands

Run these after all 4 fixes are applied:

```bash
# Fix 2 — search/index.php must have no syntax errors
php -l medical/app/Views/search/index.php
# Expected: No syntax errors detected

# Fix 1 — APP_DEBUG must be defined in config.php
grep "define.*APP_DEBUG" medical/app/Config/config.php
# Expected: define('APP_DEBUG', filter_var(...))

# Fix 3 — navbar must have null-coalesce on name and role
grep "user\]\['name'\]" medical/app/Views/layouts/main.php
# Expected: ?? 'User' on same line

grep "user\]\['role'\]" medical/app/Views/layouts/main.php | grep "htmlspecialchars\|ucfirst" | head -3
# Expected: at least the user-pill line shows ?? 'staff'

# Fix 4 — alertStats query must use alert_level column
grep "SELECT.*level.*safesense_alerts" medical/app/Controllers/AuthController.php
# Expected: SELECT alert_level (not bare "level")

# Full syntax check — all PHP files must pass
find medical -name "*.php" ! -path "*/vendor/*" | xargs -I{} php -l {} 2>&1 | grep -v "No syntax errors"
# Expected: no output (all files clean)
```

---

*SafeSense 500 Error Report · May 2026*
