<?php
/**
 * SafeSense Hospital Management System — Entry Point
 */

// Start session once here; controllers must NOT call session_start() again
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader (if Composer vendor exists)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// Core configuration
require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/database.php';

// Core framework files
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/App.php';

// Base controller (always loaded)
require_once __DIR__ . '/../app/Controllers/BaseController.php';

// Models (preload commonly used ones)
$models = ['Patient', 'Doctor', 'Appointment', 'Alert'];
foreach ($models as $model) {
    $file = __DIR__ . '/../app/Models/' . $model . '.php';
    if (file_exists($file)) require_once $file;
}

// Run app
$app = new App();
$app->run();
?>

