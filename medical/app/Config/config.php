<?php

// ── App ──────────────────────────────────────
define('APP_NAME', 'Tupi Hospital Management');

// Dynamically derive APP_URL from the server environment.
// Falls back to hardcoded value only when running CLI (e.g., migrations).
if (PHP_SAPI === 'cli') {
    define('APP_URL', 'http://localhost/SafeSense/medical');
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme   = $isHttps ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // scriptName = /SafeSense/medical/public/index.php → strip /public/index.php → base
    $script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname(dirname($script)), '/');  // go up two levels
    define('APP_URL', $scheme . '://' . $host . $basePath);
}

// Fallback helper for environment variables
if (!function_exists('env')) {
    function env($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        $val = getenv($key);
        if ($val !== false) return $val;
        return $default;
    }
}

// Debug mode — reads from .env APP_DEBUG value; defaults to false in production
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('APP_ENV',   env('APP_ENV', 'production'));

// ── Database ─────────────────────────────────
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'hospital_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// ── Paths ────────────────────────────────────
define('APP_PATH',    __DIR__ . '/../');
define('PUBLIC_PATH', __DIR__ . '/../../public');

if (PHP_SAPI === 'cli' || str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) !== '/index.php') {
    define('ASSETS_URL',  APP_URL . '/public');
} else {
    // When served from public folder (Render), assets are at root
    define('ASSETS_URL',  APP_URL);
}

// ── Date/Time ────────────────────────────────
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DEFAULT_TIMEZONE', 'Pacific/Pago_Pago');
date_default_timezone_set(DEFAULT_TIMEZONE);

// ── Session ──────────────────────────────────
define('SESSION_LIFETIME', 3600);

// ── File uploads ─────────────────────────────
define('MAX_UPLOAD_SIZE',    5 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['jpg','jpeg','png','pdf']);

// ── Appointment status constants ─────────────
define('APPOINTMENT_STATUS_PENDING',   'pending');
define('APPOINTMENT_STATUS_CONFIRMED', 'confirmed');
define('APPOINTMENT_STATUS_CANCELLED', 'cancelled');
define('APPOINTMENT_STATUS_COMPLETED', 'completed');

// ── Gender ───────────────────────────────────
define('GENDER_MALE',   'male');
define('GENDER_FEMALE', 'female');
define('GENDER_OTHER',  'other');

// ── Roles ────────────────────────────────────
define('ROLE_ADMIN',   'admin');
define('ROLE_DOCTOR',  'doctor');
define('ROLE_NURSE',   'nurse');
define('ROLE_STAFF',   'staff');

// ── Pagination ───────────────────────────────
define('ITEMS_PER_PAGE', 10);

// ── Security ─────────────────────────────────
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_EXPIRY',        3600);

// ── SafeSense IoT Integration ─────────────────
// This key must match the api_key sent by your Arduino WiFi Shield.
// Change this to a strong random string in production!
define('SAFESENSE_API_KEY', env('SAFESENSE_API_KEY', ''));

// Alert level thresholds (mirrors Arduino thresholds)
define('SS_WATER_WARNING',  20.0);
define('SS_WATER_DANGER',   35.0);
define('SS_WATER_CRITICAL', 50.0);

// ── Global URL Helper ─────────────────────────
if (!function_exists('url')) {
    function url($path = '') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        // basePath = everything up to and including /public
        $basePath = rtrim(dirname($script), '/');
        if ($basePath === '.') $basePath = '';
        // Return basePath alone (no trailing slash) if no path given,
        // so JS can do:  window.BASE_URL + '/route'  without double slashes
        if ($path === '') return $basePath;
        return $basePath . '/' . ltrim($path, '/');
    }
}
