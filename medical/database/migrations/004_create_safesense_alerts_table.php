<?php
/**
 * Migration: Create SafeSense Alerts Table
 * 
 * This table stores all incoming alerts from the Arduino SafeSense
 * IoT device via the /api/alert endpoint.
 */

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
$host     = $_ENV['DB_HOST']  ?? 'localhost';
$port     = $_ENV['DB_PORT']  ?? 3306;
$db_name  = $_ENV['DB_NAME']  ?? 'hospital_db';
$username = $_ENV['DB_USER']  ?? 'root';
$password = $_ENV['DB_PASS']  ?? '';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    CREATE TABLE IF NOT EXISTS safesense_alerts (
        id            INT AUTO_INCREMENT PRIMARY KEY,

        -- Who sent this alert
        device_id     VARCHAR(100)  NOT NULL DEFAULT 'SAFESENSE-001',
        station_type  ENUM('hospital','police','fire') NOT NULL DEFAULT 'hospital',

        -- Sensor readings at time of alert
        alert_level   ENUM('warning','danger','critical') NOT NULL,
        event_type    ENUM('rain','flood','accident','vibration','test') NOT NULL,
        rain_status   VARCHAR(50)   DEFAULT NULL,
        water_level   FLOAT         DEFAULT NULL,   -- in cm
        vibration     TINYINT(1)    DEFAULT 0,

        -- Alert message content
        message       TEXT          NOT NULL,

        -- Location data
        latitude      DECIMAL(10,8) DEFAULT NULL,
        longitude     DECIMAL(11,8) DEFAULT NULL,
        location_name VARCHAR(255)  DEFAULT 'Unknown Location',

        -- Camera image captured by ESP32-CAM during the alert
        image_path    VARCHAR(500)  DEFAULT NULL,   -- relative path: storage/alert_images/filename.jpg

        -- Lifecycle
        is_read       TINYINT(1)    NOT NULL DEFAULT 0,
        is_dismissed  TINYINT(1)    NOT NULL DEFAULT 0,
        acknowledged_by INT         DEFAULT NULL,
        acknowledged_at TIMESTAMP NULL DEFAULT NULL,

        created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_is_read      (is_read),
        INDEX idx_alert_level  (alert_level),
        INDEX idx_created_at   (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "safesense_alerts table created successfully\n";

    // Add image_path column to existing tables that were created before this migration
    try {
        $pdo->exec("ALTER TABLE safesense_alerts ADD COLUMN image_path VARCHAR(500) DEFAULT NULL AFTER location_name");
        echo "image_path column added.\n";
    } catch (PDOException $e) {
        // Column already exists — safe to ignore
        echo "image_path column already exists (skipped).\n";
    }

    // Seed a demo alert so the dashboard is not empty
    $seed = "
    INSERT IGNORE INTO safesense_alerts
        (device_id, station_type, alert_level, event_type, rain_status, water_level, vibration, message, latitude, longitude, location_name)
    VALUES
        ('SAFESENSE-001','hospital','critical','flood','heavy',45.2,0,
         'CRITICAL: Flood detected. Water level at 45.2 cm — DANGER threshold exceeded. Immediate evacuation recommended.',
         8.1574,124.9282,'Brgy. Crossing Rubber, Tupi'),
        ('SAFESENSE-001','hospital','warning','rain','moderate',12.5,0,
         'WARNING: Moderate rain detected. Water level rising (12.5 cm). Monitor road conditions.',
         8.1580,124.9295,'Brgy. Crossing Rubber, Tupi'),
        ('SAFESENSE-001','hospital','danger','accident','light',8.0,1,
         'DANGER: Accident detected via vibration sensor during rain event. Possible road incident.',
         8.1560,124.9310,'National Highway, Malaybalay City')
    ;
    ";
    $pdo->exec($seed);
    echo "Demo alerts seeded.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>