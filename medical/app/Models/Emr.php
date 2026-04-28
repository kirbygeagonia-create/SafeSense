<?php

class Emr {
    private $conn;
    private $table_name = 'emr_records';

    public $id;
    public $patient_id;
    public $doctor_id;
    public $visit_date;
    public $chief_complaint;
    public $diagnosis;
    public $prescription;
    public $notes;
    public $blood_pressure;
    public $temperature;
    public $heart_rate;
    public $weight;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = 'SELECT e.*, p.name as patient_name, d.name as doctor_name 
                  FROM ' . $this->table_name . ' e 
                  LEFT JOIN patients p ON e.patient_id = p.id 
                  LEFT JOIN doctors d ON e.doctor_id = d.id 
                  ORDER BY e.visit_date DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getByPatient($patientId) {
        $query = 'SELECT e.*, p.name as patient_name, d.name as doctor_name 
                  FROM ' . $this->table_name . ' e 
                  LEFT JOIN patients p ON e.patient_id = p.id 
                  LEFT JOIN doctors d ON e.doctor_id = d.id 
                  WHERE e.patient_id = ? 
                  ORDER BY e.visit_date DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$patientId]);
        return $stmt;
    }

    public function getById($id) {
        $query = 'SELECT e.*, p.name as patient_name, d.name as doctor_name 
                  FROM ' . $this->table_name . ' e 
                  LEFT JOIN patients p ON e.patient_id = p.id 
                  LEFT JOIN doctors d ON e.doctor_id = d.id 
                  WHERE e.id = ? LIMIT 0,1';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->patient_id = $row['patient_id'];
            $this->doctor_id = $row['doctor_id'];
            $this->visit_date = $row['visit_date'];
            $this->chief_complaint = $row['chief_complaint'];
            $this->diagnosis = $row['diagnosis'];
            $this->prescription = $row['prescription'];
            $this->notes = $row['notes'];
            $this->blood_pressure = $row['blood_pressure'];
            $this->temperature = $row['temperature'];
            $this->heart_rate = $row['heart_rate'];
            $this->weight = $row['weight'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    public function create() {
        $query = 'INSERT INTO ' . $this->table_name . ' 
                  (patient_id, doctor_id, visit_date, chief_complaint, diagnosis, prescription, notes, blood_pressure, temperature, heart_rate, weight) 
                  VALUES (:patient_id, :doctor_id, :visit_date, :chief_complaint, :diagnosis, :prescription, :notes, :blood_pressure, :temperature, :heart_rate, :weight)';
        
        $stmt = $this->conn->prepare($query);
        
        $this->chief_complaint = htmlspecialchars(strip_tags($this->chief_complaint));
        $this->diagnosis = htmlspecialchars(strip_tags($this->diagnosis));
        $this->prescription = htmlspecialchars(strip_tags($this->prescription));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->blood_pressure = htmlspecialchars(strip_tags($this->blood_pressure));
        
        $stmt->bindParam(':patient_id', $this->patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $this->doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':visit_date', $this->visit_date);
        $stmt->bindParam(':chief_complaint', $this->chief_complaint);
        $stmt->bindParam(':diagnosis', $this->diagnosis);
        $stmt->bindParam(':prescription', $this->prescription);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':blood_pressure', $this->blood_pressure);
        $stmt->bindParam(':temperature', $this->temperature);
        $stmt->bindParam(':heart_rate', $this->heart_rate, PDO::PARAM_INT);
        $stmt->bindParam(':weight', $this->weight);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function update() {
        $query = 'UPDATE ' . $this->table_name . ' 
                  SET patient_id = :patient_id, doctor_id = :doctor_id, visit_date = :visit_date, 
                      chief_complaint = :chief_complaint, diagnosis = :diagnosis, prescription = :prescription, 
                      notes = :notes, blood_pressure = :blood_pressure, temperature = :temperature, 
                      heart_rate = :heart_rate, weight = :weight 
                  WHERE id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        $this->chief_complaint = htmlspecialchars(strip_tags($this->chief_complaint));
        $this->diagnosis = htmlspecialchars(strip_tags($this->diagnosis));
        $this->prescription = htmlspecialchars(strip_tags($this->prescription));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->blood_pressure = htmlspecialchars(strip_tags($this->blood_pressure));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(':patient_id', $this->patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $this->doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':visit_date', $this->visit_date);
        $stmt->bindParam(':chief_complaint', $this->chief_complaint);
        $stmt->bindParam(':diagnosis', $this->diagnosis);
        $stmt->bindParam(':prescription', $this->prescription);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':blood_pressure', $this->blood_pressure);
        $stmt->bindParam(':temperature', $this->temperature);
        $stmt->bindParam(':heart_rate', $this->heart_rate, PDO::PARAM_INT);
        $stmt->bindParam(':weight', $this->weight);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = 'DELETE FROM ' . $this->table_name . ' WHERE id = ?';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        return $stmt->rowCount() > 0;
    }
}
