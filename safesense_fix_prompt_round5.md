# SafeSense — Final Polish Pass (Round 5)
## 3 Minor Fixes + .env Backup

A full line-by-line audit of every file confirmed the system is functionally complete. Only 3 minor issues remain. Apply all three, then follow the .env backup instructions at the bottom.

---

## FIX 1 — Add auth guard to DashboardController::stats() (Security)

**File:** `medical/app/Controllers/DashboardController.php`

**Problem:** `GET /api/dashboard/stats` returns alert counts and appointment trend data with no session check. Every other endpoint in the system requires login. An unauthenticated user who knows this URL gets read access to aggregate hospital data.

**Fix:** Add `$this->requireLogin();` as the very first line of `stats()`:

```php
// OLD:
public function stats() {
    $database = new Database();

// NEW:
public function stats() {
    $this->requireLogin();
    $database = new Database();
```

That's the only change to this file.

---

## FIX 2 — Add CSRF to AlertController markRead() and dismiss() (Security)

**File:** `medical/app/Controllers/AlertController.php` and `medical/app/Views/layouts/main.php`

**Problem:** `POST /api/alerts/read` and `POST /api/alerts/dismiss` call `requireAuth()` but not `validateCsrf()`. The inline `post()` helper in `main.php` does not send the `X-CSRF-Token` header (unlike the `ajax()` helper in `app.js`). A cross-site request could silently mark all alerts as read, hiding emergency notifications from staff.

### Part A — Add validateCsrf() to both methods in AlertController.php

Find `markRead()`:
```php
// OLD:
public function markRead() {
    $this->requireAuth();

    $database   = new Database();

// NEW:
public function markRead() {
    $this->requireAuth();
    $this->validateCsrf();

    $database   = new Database();
```

Find `dismiss()`:
```php
// OLD:
public function dismiss() {
    $this->requireAuth();

    $database   = new Database();

// NEW:
public function dismiss() {
    $this->requireAuth();
    $this->validateCsrf();

    $database   = new Database();
```

### Part B — Add X-CSRF-Token header to the inline post() function in main.php

Find this exact function in `medical/app/Views/layouts/main.php` (near the bottom of the inline `<script>` block):

```javascript
// OLD:
function post(url,body){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body}).then(r=>r.json()); }

// NEW:
function post(url,body){
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':csrfToken},body}).then(r=>r.json());
}
```

---

## FIX 3 — Update stale comment in AuthController (Code quality)

**File:** `medical/app/Controllers/AuthController.php`

**Problem:** Line 45 reads `// Users table may not exist yet — fall through to demo login`. The demo login was removed long ago. The comment now misleads anyone reading the code into thinking there's a demo fallback.

**Fix:** Find line 45 inside the `authenticate()` catch block:

```php
// OLD:
        } catch (Exception $e) {
            // Users table may not exist yet — fall through to demo login
        }

// NEW:
        } catch (Exception $e) {
            // Users table may not exist yet — login will fail gracefully
        }
```

---

## .env BACKUP — Required action (do this before closing your editor)

Your `medical/.env` is intentionally excluded from git. It contains:
- `SAFESENSE_API_KEY` — the shared secret between the server and the Arduino
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — your database credentials

**If your XAMPP machine fails or is reinstalled, this file is gone permanently.** The Arduino would need to be reflashed with a new key. Back it up now using one of these methods:

### Option A — Password manager (recommended)
1. Open your password manager (Bitwarden, 1Password, KeePass, etc.)
2. Create a new secure note titled **"SafeSense .env — medical/.env"**
3. Paste the full contents of `medical/.env` into the note
4. Save it

### Option B — Encrypted text file on USB
1. Plug in a USB drive
2. Copy `C:\xampp\htdocs\SafeSense\medical\.env` to the USB
3. Rename the copy to `safesense_env_backup.txt`
4. Optionally encrypt the USB drive with BitLocker or VeraCrypt

### Option C — Second machine / cloud (encrypted only)
If you store it in Google Drive, OneDrive, or similar — zip it with a strong password first using 7-Zip (AES-256). Never upload the raw `.env` file to any cloud service.

### What to record at minimum
If you can't back up the whole file, record at least these two values somewhere safe:
```
SAFESENSE_API_KEY=<your real key>
APP_URL=http://localhost/SafeSense/medical
```

---

## VERIFICATION CHECKLIST

Run every check. If any fails, fix and re-run from the top.

### Step 1 — DashboardController
```
[ ] Open medical/app/Controllers/DashboardController.php
[ ] First line of stats() is: $this->requireLogin();
[ ] No other lines were changed in this file
```

### Step 2 — AlertController
```
[ ] Open medical/app/Controllers/AlertController.php
[ ] markRead() has $this->validateCsrf(); immediately after $this->requireAuth();
[ ] dismiss() has $this->validateCsrf(); immediately after $this->requireAuth();
[ ] No other lines were changed in this file
```

### Step 3 — main.php post() function
```
[ ] Open medical/app/Views/layouts/main.php
[ ] The inline post() function now contains:
    'X-CSRF-Token': csrfToken
    in its headers object
[ ] csrfToken is retrieved from:
    document.querySelector('meta[name="csrf-token"]')?.content ?? ''
[ ] The rest of the function (fetch URL, method, body) is unchanged
```

### Step 4 — AuthController comment
```
[ ] Open medical/app/Controllers/AuthController.php
[ ] Line ~45 reads: // Users table may not exist yet — login will fail gracefully
[ ] The line no longer says "fall through to demo login"
[ ] No other lines were changed in this file
```

### Step 5 — Regression check (read each file, do not skip)
```
[ ] DashboardController stats() still returns alertData + appointmentData in jsonResponse
[ ] AlertController markRead() still reads $_POST['id'] and calls markAllRead() or markRead()
[ ] AlertController dismiss() still reads $_POST['id'] and calls dismiss()
[ ] main.php post() function is still called the same way:
    post(window.BASE_URL + '/api/alerts/read', 'id=all')
    post(window.BASE_URL + '/api/alerts/read', 'id='+id)
    post(window.BASE_URL + '/api/alerts/dismiss', 'id='+id)
    (The call sites are unchanged — only the function implementation changed)
[ ] AuthController authenticate() logic is completely unchanged
    (Only the comment on line ~45 was edited)
```

### Step 6 — .env backup confirmation
```
[ ] medical/.env contents have been saved to a password manager
    OR copied to an encrypted USB
    OR stored in another secure offline location
[ ] You have confirmed the backup contains the real SAFESENSE_API_KEY value
    (not a placeholder like "REPLACE_WITH_STRONG_RANDOM_KEY")
[ ] git ls-files medical/.env returns empty (file is still untracked — confirm this
    was not accidentally re-added)
```

### Step 7 — Commit
```
[ ] git status shows only these files modified:
    medical/app/Controllers/DashboardController.php
    medical/app/Controllers/AlertController.php
    medical/app/Views/layouts/main.php
    medical/app/Controllers/AuthController.php

[ ] Commit: git commit -m "fix: auth guard on stats, CSRF on alert actions, update stale comment"
```

---

## Summary

| Fix | File | Change |
|-----|------|--------|
| 1 | `DashboardController.php` | Add `requireLogin()` to `stats()` |
| 2a | `AlertController.php` | Add `validateCsrf()` to `markRead()` and `dismiss()` |
| 2b | `layouts/main.php` | Add `X-CSRF-Token` header to inline `post()` function |
| 3 | `AuthController.php` | Update stale comment on line ~45 |

**4 files. SafeSense is then fully complete and production-ready.**
