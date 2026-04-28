<?php
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS billing (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        patient_id      INT NOT NULL,
        appointment_id  INT DEFAULT NULL,
        invoice_number  VARCHAR(50) UNIQUE NOT NULL,
        service_description TEXT NOT NULL,
        amount          DECIMAL(10,2) NOT NULL,
        discount        DECIMAL(10,2) DEFAULT 0.00,
        tax             DECIMAL(10,2) DEFAULT 0.00,
        total_amount    DECIMAL(10,2) NOT NULL,
        payment_status  ENUM('unpaid','paid','partial','cancelled') DEFAULT 'unpaid',
        payment_method  ENUM('cash','card','insurance','online') DEFAULT NULL,
        payment_date    DATE DEFAULT NULL,
        notes           TEXT,
        created_by      INT DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE RESTRICT,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
        INDEX idx_patient_id     (patient_id),
        INDEX idx_payment_status (payment_status),
        INDEX idx_invoice_number (invoice_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Billing table created successfully.\n";
} catch(PDOException $e) { echo "Error: ".$e->getMessage()."\n"; }
?>
