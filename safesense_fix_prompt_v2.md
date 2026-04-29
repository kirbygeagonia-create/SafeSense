# SafeSense — Remaining Fixes (Round 2)

Four items were not completed in the previous fix pass. Apply all four exactly as specified below. Do not touch anything else.

---

## FIX 1 — Router wildcard regex still broken (Critical)

**File:** `medical/app/Core/Router.php`

**Problem:** The `match()` method uses this line to expand `{param}` placeholders:
```php
$pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>.+)', preg_quote($route['route'], '#')) . '$#';
```
The capture group name still relies on `\w+`, which only matches word characters `[a-zA-Z0-9_]`. The wildcard route is registered as `/{any:.*}` — the name `any:.*` contains a colon and dot, which are not word characters, so the placeholder is never expanded. Unknown URLs still return a blank page instead of a proper 404.

**Fix:** Replace that one line with a pattern that matches any `{...}` block regardless of its contents:
```php
// OLD:
$pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>.+)', preg_quote($route['route'], '#')) . '$#';

// NEW:
$pattern = '#^' . preg_replace('/\{[^}]+\}/', '(.+)', preg_quote($route['route'], '#')) . '$#';
```
This matches any `{anything}` placeholder and replaces it with `(.+)`, regardless of what is inside the braces.

---

## FIX 2 — Remove hardcoded demo credentials (Security — urgent)

### Part A — `medical/app/Controllers/AuthController.php`

Find and delete the demo fallback block inside `authenticate()`. It looks like this:
```php
// Demo fallback (remove in production)
if (!$user && $email === 'admin@example.com' && $password === 'password') {
    $user = ['email' => $email, 'role' => 'admin', 'name' => 'Admin'];
}
```
Delete all four lines including the comment. Nothing else in the method should change.

Also delete the stale comments above the try block that reference the fallback:
```php
// Try users table first; fall back to demo credentials
```
Replace with:
```php
// Query users table
```

### Part B — `medical/database/migrations/000_create_users_table.php`

Find and delete these lines that seed the default admin account:
```php
// Insert default admin (password: 'password')
$hash = password_hash('password', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (name,email,password,role) VALUES (?,?,?,?)");
$stmt->execute(['Admin', 'admin@example.com', $hash, 'admin']);
echo "Users table created. Default: admin@example.com / password\n";
```
Replace the echo with:
```php
echo "Users table created. Add your first admin user via the /users route after setup.\n";
```

---

## FIX 3 — Delete cookie files and create .gitignore (Security)

### Part A — Delete these three files entirely:
- `medical/cookie.txt`
- `medical/cookie_alert.txt`
- `medical/cookie_test.txt`

### Part B — Create `.gitignore` at the repo root (`SafeSense/.gitignore`) with this content:
```gitignore
# Environment & secrets
medical/.env
.env

# Session / cookie test artifacts
medical/cookie*.txt

# Composer dependencies
medical/vendor/
medical/composer.phar

# OS / editor
.DS_Store
Thumbs.db
.idea/
.vscode/
*.log
```

---

## FIX 4 — Arduino SERVER_PATH not used in URL (Warning)

**File:** `arduino/SafeSense_IoT.ino`

**Problem:** `SERVER_PATH` was declared but the URL is still built without it:
```cpp
String url = String(SERVER_HOST) + String(API_ENDPOINT);
```
So the sub-path is silently dropped and the Arduino posts to the wrong URL.

**Fix:** Find that line (around line 307) and change it to:
```cpp
// OLD:
String url = String(SERVER_HOST) + String(API_ENDPOINT);

// NEW:
String url = String(SERVER_HOST) + String(SERVER_PATH) + String(API_ENDPOINT);
```

---

## Verification checklist

- [ ] Visiting any unknown URL (e.g. `/garbage`) shows a proper 404 page — not a blank page
- [ ] Logging in with `admin@example.com` / `password` is rejected
- [ ] `medical/cookie.txt`, `medical/cookie_alert.txt`, `medical/cookie_test.txt` are absent from the repo
- [ ] `.gitignore` exists at the repo root and lists `medical/.env` and `medical/vendor/`
- [ ] Arduino sketch builds with `url = SERVER_HOST + SERVER_PATH + API_ENDPOINT`
