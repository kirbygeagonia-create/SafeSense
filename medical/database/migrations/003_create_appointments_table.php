<?php
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS appointments (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        patient_id       INT NOT NULL,
        doctor_id        INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status           ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
        reason           TEXT,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Appointments table created successfully\n";
} catch(PDOException $e) { echo "Error: ".$e->getMessage()."\n"; }
?>

