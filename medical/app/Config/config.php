<?php

// ── App ──────────────────────────────────────
define('APP_NAME', 'SafeSense Hospital Management');
define('APP_URL',  'http://localhost/medical');

// ── Database ─────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');
define('DB_PASS', '');

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
define('ROLE_PATIENT', 'patient');

// ── Pagination ───────────────────────────────
define('ITEMS_PER_PAGE', 10);

// ── Security ─────────────────────────────────
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_EXPIRY',        3600);

// ── SafeSense IoT Integration ─────────────────
// This key must match the api_key sent by your Arduino WiFi Shield.
// Change this to a strong random string in production!
define('SAFESENSE_API_KEY', 'SAFESENSE_SECRET_KEY');

// Alert level thresholds (mirrors Arduino thresholds)
define('SS_WATER_WARNING',  20.0);
define('SS_WATER_DANGER',   35.0);
define('SS_WATER_CRITICAL', 50.0);

