<?php
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS doctors (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        name             VARCHAR(255) NOT NULL,
        email            VARCHAR(255) UNIQUE NOT NULL,
        phone            VARCHAR(20)  NOT NULL,
        specialization   VARCHAR(255) NOT NULL,
        license_number   VARCHAR(50)  UNIQUE NOT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Doctors table created successfully\n";
} catch(PDOException $e) { echo "Error: ".$e->getMessage()."\n"; }
?>

