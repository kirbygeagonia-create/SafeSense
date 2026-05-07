<?php
/**
 * SafeSense Hospital Management System — Entry Point
 */

// FIX M3: Harden session cookies before starting session
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session once here; controllers must NOT call session_start() again
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader (if Composer vendor exists)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// Load .env BEFORE config.php so $_ENV values are available (Task 1)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Core configuration
require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/database.php';

// ENH-1: Global error and exception handlers — show 500 page, log to error_log
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log(sprintf('[SafeSense Error %d] %s in %s:%d', $severity, $message, $file, $line));
    if (defined('APP_DEBUG') && APP_DEBUG) return false; // show native errors in dev
    if (!headers_sent()) {
        http_response_code(500);
        $errorFile = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($errorFile)) include $errorFile;
    }
    exit(1);
});

set_exception_handler(function (Throwable $e) {
    error_log(sprintf('[SafeSense Exception] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        $errorFile = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($errorFile)) include $errorFile;
    }
    exit(1);
});

// Core framework files
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/App.php';

// Base controller (always loaded)
require_once __DIR__ . '/../app/Controllers/BaseController.php';

// Models (preload commonly used ones)
$models = ['Patient', 'Doctor', 'Appointment', 'Alert', 'Emr', 'Billing'];
foreach ($models as $model) {
    $file = __DIR__ . '/../app/Models/' . $model . '.php';
    if (file_exists($file)) require_once $file;
}

// Run app
$app = new App();
$app->run();
?>

