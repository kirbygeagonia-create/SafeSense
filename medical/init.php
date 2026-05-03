<?php

// Initialization script for Hospital Management System

// Load configuration
require_once 'app/Config/config.php';

// Load dependencies
require_once 'vendor/autoload.php';

// Initialize session
session_start();

// Load environment variables (if using vlucas/phpdotenv)
if (file_exists('.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

?>