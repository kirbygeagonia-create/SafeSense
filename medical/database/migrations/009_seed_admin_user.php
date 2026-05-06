<?php
/**
 * Migration 009 — Seed default admin user
 */
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

$host   = $_ENV['DB_HOST']   ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME']   ?? 'hospital_db';
$user   = $_ENV['DB_USER']   ?? 'root';
$pass   = $_ENV['DB_PASS']   ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hash = password_hash('ChangeMe123!', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO users (name, email, password, role, created_at)
                VALUES ('Administrator', 'admin@example.com', '$hash', 'admin', NOW())");

    echo "Admin user seeded. Login: admin@example.com / ChangeMe123!\n";
} catch (PDOException $e) {
    echo "Migration 009 failed: " . $e->getMessage() . "\n";
}
