<?php

class Database {
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Sync MySQL connection timezone with PHP timezone to fix timestamp comparisons
            $offset = (new DateTime())->format('P'); // e.g. +08:00 or -11:00
            $this->conn->exec("SET time_zone = '{$offset}'");
        } catch(PDOException $exception) {
            error_log("SafeSense DB connection error: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}