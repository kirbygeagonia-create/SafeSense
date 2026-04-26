<?php
$host='localhost'; $db_name='hospital_db'; $username='root'; $password='';
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        email      VARCHAR(255) UNIQUE NOT NULL,
        password   VARCHAR(255) NOT NULL,
        role       ENUM('admin','doctor','nurse','staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // Insert default admin (password: 'password')
    $hash = password_hash('password', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $stmt->execute(['Admin', 'admin@example.com', $hash, 'admin']);
    echo "Users table created. Default: admin@example.com / password\n";
} catch(PDOException $e) { echo "Error: ".$e->getMessage()."\n"; }
?>

