<?php

class Appointment {
    private $conn;
    private $table_name = 'appointments';

    public $id;
    public $patient_id;
    public $doctor_id;
    public $appointment_date;
    public $appointment_time;
    public $status;
    public $reason;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = 'SELECT a.*, p.name as patient_name, d.name as doctor_name 
                  FROM ' . $this->table_name . ' a 
                  JOIN patients p ON a.patient_id = p.id 
                  JOIN doctors d ON a.doctor_id = d.id 
                  ORDER BY a.appointment_date DESC';
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
            $this->id               = $row['id'];
            $this->patient_id       = $row['patient_id'];
            $this->doctor_id        = $row['doctor_id'];
            $this->appointment_date = $row['appointment_date'];
            $this->appointment_time = $row['appointment_time'];
            $this->status           = $row['status'];
            $this->reason           = $row['reason'];
            $this->created_at       = $row['created_at'];
            return true;
        }
        return false;
    }

    public function create() {
        $query = 'INSERT INTO ' . $this->table_name . ' 
                  SET patient_id=:patient_id, doctor_id=:doctor_id, 
                      appointment_date=:appointment_date, appointment_time=:appointment_time, 
                      status=:status, reason=:reason';
        
        $stmt = $this->conn->prepare($query);
        
        $this->patient_id       = htmlspecialchars(strip_tags($this->patient_id));
        $this->doctor_id        = htmlspecialchars(strip_tags($this->doctor_id));
        $this->appointment_date = htmlspecialchars(strip_tags($this->appointment_date));
        $this->appointment_time = htmlspecialchars(strip_tags($this->appointment_time));
        $this->status           = htmlspecialchars(strip_tags($this->status));
        $this->reason           = htmlspecialchars(strip_tags($this->reason));
        
        $stmt->bindParam(':patient_id',       $this->patient_id);
        $stmt->bindParam(':doctor_id',        $this->doctor_id);
        $stmt->bindParam(':appointment_date', $this->appointment_date);
        $stmt->bindParam(':appointment_time', $this->appointment_time);
        $stmt->bindParam(':status',           $this->status);
        $stmt->bindParam(':reason',           $this->reason);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function update() {
        $query = 'UPDATE ' . $this->table_name . ' 
                  SET patient_id=:patient_id, doctor_id=:doctor_id, 
                      appointment_date=:appointment_date, appointment_time=:appointment_time, 
                      status=:status, reason=:reason 
                  WHERE id=:id';
        
        $stmt = $this->conn->prepare($query);
        
        $this->patient_id       = htmlspecialchars(strip_tags($this->patient_id));
        $this->doctor_id        = htmlspecialchars(strip_tags($this->doctor_id));
        $this->appointment_date = htmlspecialchars(strip_tags($this->appointment_date));
        $this->appointment_time = htmlspecialchars(strip_tags($this->appointment_time));
        $this->status           = htmlspecialchars(strip_tags($this->status));
        $this->reason           = htmlspecialchars(strip_tags($this->reason));
        $this->id               = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(':patient_id',       $this->patient_id);
        $stmt->bindParam(':doctor_id',        $this->doctor_id);
        $stmt->bindParam(':appointment_date', $this->appointment_date);
        $stmt->bindParam(':appointment_time', $this->appointment_time);
        $stmt->bindParam(':status',           $this->status);
        $stmt->bindParam(':reason',           $this->reason);
        $stmt->bindParam(':id',               $this->id);
        
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

    /** Get appointment counts grouped by week for Chart.js */
    public function getAppointmentsByWeek($weeks = 8) {
        $query = 'SELECT YEARWEEK(appointment_date, 1) as yw,
                         MIN(appointment_date) as week_start,
                         COUNT(*) as count
                  FROM ' . $this->table_name . '
                  WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                  GROUP BY YEARWEEK(appointment_date, 1)
                  ORDER BY yw ASC';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':weeks', $weeks, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Task 4 — Appointment conflict detection.
     * Returns true if the doctor already has a non-cancelled appointment
     * at the same date + time. Pass $excludeId to ignore the current record
     * during edit operations.
     */
    public function hasConflict(?int $excludeId = null): bool {
        $query = 'SELECT COUNT(*) FROM ' . $this->table_name . '
                  WHERE doctor_id        = :doctor_id
                    AND appointment_date = :appointment_date
                    AND appointment_time = :appointment_time
                    AND status          != :cancelled';
        if ($excludeId !== null) {
            $query .= ' AND id != :exclude_id';
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doctor_id',        $this->doctor_id);
        $stmt->bindParam(':appointment_date', $this->appointment_date);
        $stmt->bindParam(':appointment_time', $this->appointment_time);
        $cancelled = APPOINTMENT_STATUS_CANCELLED;
        $stmt->bindParam(':cancelled',        $cancelled);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }
}