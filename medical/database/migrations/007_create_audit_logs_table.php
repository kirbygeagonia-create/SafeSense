<?php
// Load .env if running as standalone migration script
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}
$host    = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'hospital_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_email  VARCHAR(255)  NOT NULL,
            user_role   VARCHAR(50)   NOT NULL,
            action      VARCHAR(100)  NOT NULL,
            resource    VARCHAR(100)  NOT NULL,
            resource_id INT           NULL,
            detail      TEXT          NULL,
            ip_address  VARCHAR(45)   NULL,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user  (user_email),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "audit_logs table created successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
