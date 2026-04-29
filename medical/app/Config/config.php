<?php

// ── App ──────────────────────────────────────
define('APP_NAME', 'SafeSense Hospital Management');
define('APP_URL',  'http://localhost/SafeSense/medical');

// ── Database ─────────────────────────────────
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'hospital_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// ── Paths ────────────────────────────────────
define('APP_PATH',    __DIR__ . '/../');
define('PUBLIC_PATH', __DIR__ . '/../../public');
define('ASSETS_URL',  APP_URL . '/public');

// ── Date/Time ────────────────────────────────
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DEFAULT_TIMEZONE', 'Asia/Manila');
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

// ── Pagination ───────────────────────────────
define('ITEMS_PER_PAGE', 10);

// ── Security ─────────────────────────────────
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_EXPIRY',        3600);

// ── SafeSense IoT Integration ─────────────────
// This key must match the api_key sent by your Arduino WiFi Shield.
// Change this to a strong random string in production!
define('SAFESENSE_API_KEY', $_ENV['SAFESENSE_API_KEY'] ?? '');

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
