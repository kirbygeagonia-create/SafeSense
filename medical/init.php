<?php
/**
 * init.php — legacy bootstrap shim.
 * The real entry point is public/index.php.
 * This file is kept only for compatibility and does nothing harmful.
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
