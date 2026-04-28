<?php

class Billing {
    private $conn;
    private $table_name = 'billing';

    public $id;
    public $patient_id;
    public $appointment_id;
    public $invoice_number;
    public $service_description;
    public $amount;
    public $discount;
    public $tax;
    public $total_amount;
    public $payment_status;
    public $payment_method;
    public $payment_date;
    public $notes;
    public $created_by;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = 'SELECT b.*, p.name as patient_name 
                  FROM ' . $this->table_name . ' b 
                  LEFT JOIN patients p ON b.patient_id = p.id 
                  ORDER BY b.created_at DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = 'SELECT b.*, p.name as patient_name 
                  FROM ' . $this->table_name . ' b 
                  LEFT JOIN patients p ON b.patient_id = p.id 
                  WHERE b.id = ? LIMIT 0,1';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->patient_id = $row['patient_id'];
            $this->appointment_id = $row['appointment_id'];
            $this->invoice_number = $row['invoice_number'];
            $this->service_description = $row['service_description'];
            $this->amount = $row['amount'];
            $this->discount = $row['discount'];
            $this->tax = $row['tax'];
            $this->total_amount = $row['total_amount'];
            $this->payment_status = $row['payment_status'];
            $this->payment_method = $row['payment_method'];
            $this->payment_date = $row['payment_date'];
            $this->notes = $row['notes'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    public function getByPatient($patientId) {
        $query = 'SELECT b.*, p.name as patient_name 
                  FROM ' . $this->table_name . ' b 
                  LEFT JOIN patients p ON b.patient_id = p.id 
                  WHERE b.patient_id = ? 
                  ORDER BY b.created_at DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$patientId]);
        return $stmt;
    }

    public function create() {
        $this->invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $this->total_amount = (float)$this->amount - (float)$this->discount + (float)$this->tax;

        $query = 'INSERT INTO ' . $this->table_name . ' 
                  (patient_id, appointment_id, invoice_number, service_description, amount, discount, tax, total_amount, payment_status, payment_method, payment_date, notes, created_by) 
                  VALUES (:patient_id, :appointment_id, :invoice_number, :service_description, :amount, :discount, :tax, :total_amount, :payment_status, :payment_method, :payment_date, :notes, :created_by)';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':patient_id', $this->patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':appointment_id', $this->appointment_id, $this->appointment_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':invoice_number', $this->invoice_number);
        $stmt->bindParam(':service_description', htmlspecialchars(strip_tags($this->service_description)));
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':discount', $this->discount);
        $stmt->bindParam(':tax', $this->tax);
        $stmt->bindParam(':total_amount', $this->total_amount);
        $stmt->bindParam(':payment_status', $this->payment_status);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':payment_date', $this->payment_date);
        $stmt->bindParam(':notes', htmlspecialchars(strip_tags($this->notes)));
        $stmt->bindParam(':created_by', $this->created_by, $this->created_by ? PDO::PARAM_INT : PDO::PARAM_NULL);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function update() {
        $this->total_amount = (float)$this->amount - (float)$this->discount + (float)$this->tax;

        $query = 'UPDATE ' . $this->table_name . ' 
                  SET patient_id = :patient_id, appointment_id = :appointment_id, 
                      service_description = :service_description, amount = :amount, 
                      discount = :discount, tax = :tax, total_amount = :total_amount, 
                      payment_status = :payment_status, payment_method = :payment_method, 
                      payment_date = :payment_date, notes = :notes 
                  WHERE id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':patient_id', $this->patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':appointment_id', $this->appointment_id, $this->appointment_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':service_description', htmlspecialchars(strip_tags($this->service_description)));
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':discount', $this->discount);
        $stmt->bindParam(':tax', $this->tax);
        $stmt->bindParam(':total_amount', $this->total_amount);
        $stmt->bindParam(':payment_status', $this->payment_status);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':payment_date', $this->payment_date);
        $stmt->bindParam(':notes', htmlspecialchars(strip_tags($this->notes)));
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

    public function getSummary() {
        $query = 'SELECT 
            COALESCE(SUM(total_amount), 0) as total_invoiced,
            COALESCE(SUM(CASE WHEN payment_status = "paid" THEN total_amount ELSE 0 END), 0) as total_collected,
            COALESCE(SUM(CASE WHEN payment_status IN ("unpaid","partial") THEN total_amount ELSE 0 END), 0) as total_unpaid,
            COUNT(*) as invoice_count
            FROM ' . $this->table_name;
        $stmt = $this->conn->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
