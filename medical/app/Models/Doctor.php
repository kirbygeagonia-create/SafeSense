<?php

class Doctor {
    private $conn;
    private $table_name = 'doctors';

    public $id;
    public $name;
    public $email;
    public $phone;
    public $specialization;
    public $license_number;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = 'SELECT * FROM ' . $this->table_name . ' ORDER BY name ASC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = 'SELECT * FROM ' . $this->table_name . ' WHERE id = ? LIMIT 0,1';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->specialization = $row['specialization'];
            $this->license_number = $row['license_number'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    public function create() {
        $query = 'INSERT INTO ' . $this->table_name . ' 
                  SET name=:name, email=:email, phone=:phone, 
                      specialization=:specialization, license_number=:license_number';
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->specialization = htmlspecialchars(strip_tags($this->specialization));
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':specialization', $this->specialization);
        $stmt->bindParam(':license_number', $this->license_number);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function update() {
        $query = 'UPDATE ' . $this->table_name . ' 
                  SET name=:name, email=:email, phone=:phone, 
                      specialization=:specialization, license_number=:license_number 
                  WHERE id=:id';
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->specialization = htmlspecialchars(strip_tags($this->specialization));
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':specialization', $this->specialization);
        $stmt->bindParam(':license_number', $this->license_number);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = 'DELETE FROM ' . $this->table_name . ' WHERE id = ?';
        
        $stmt = $this->conn->prepare($query);
        
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(1, $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}