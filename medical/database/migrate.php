<?php
/**
 * ENH-4: Simple Migration Runner
 * Run: php database/migrate.php
 */

require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/database.php';

$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    echo "Error: Migrations directory not found at {$migrationsDir}\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.php');
if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

sort($files);

echo "Starting migrations...\n";
echo str_repeat('-', 50) . "\n";

foreach ($files as $file) {
    $filename = basename($file);
    echo "Running {$filename}...\n";
    
    try {
        require_once $file;
        echo "  ✓ Completed\n";
    } catch (Exception $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
    }
}

echo str_repeat('-', 50) . "\n";
echo "Migration complete.\n";
