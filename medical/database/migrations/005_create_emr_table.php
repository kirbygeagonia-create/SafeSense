<?php
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS emr_records (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        patient_id      INT NOT NULL,
        doctor_id       INT NOT NULL,
        visit_date      DATE NOT NULL,
        chief_complaint TEXT NOT NULL,
        diagnosis       TEXT NOT NULL,
        prescription    TEXT,
        notes           TEXT,
        blood_pressure  VARCHAR(20)  DEFAULT NULL,
        temperature     DECIMAL(4,1) DEFAULT NULL,
        heart_rate      INT          DEFAULT NULL,
        weight          DECIMAL(5,2) DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE RESTRICT,
        INDEX idx_patient_id (patient_id),
        INDEX idx_visit_date (visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "EMR records table created successfully.\n";
} catch(PDOException $e) { echo "Error: ".$e->getMessage()."\n"; }
?>
