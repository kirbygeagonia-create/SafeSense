<?php

/**
 * Alert Model
 * Handles all database operations for SafeSense IoT alerts.
 */
class Alert {

    private $conn;
    private $table = 'safesense_alerts';

    public $id;
    public $device_id;
    public $station_type;
    public $alert_level;
    public $event_type;
    public $rain_status;
    public $water_level;
    public $vibration;
    public $message;
    public $latitude;
    public $longitude;
    public $location_name;
    public $is_read;
    public $is_dismissed;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Insert a new alert from the Arduino device */
    public function create() {
        $query = "INSERT INTO {$this->table}
                    (device_id, station_type, alert_level, event_type, rain_status,
                     water_level, vibration, message, latitude, longitude, location_name)
                  VALUES
                    (:device_id, :station_type, :alert_level, :event_type, :rain_status,
                     :water_level, :vibration, :message, :latitude, :longitude, :location_name)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':device_id',     $this->device_id);
        $stmt->bindParam(':station_type',  $this->station_type);
        $stmt->bindParam(':alert_level',   $this->alert_level);
        $stmt->bindParam(':event_type',    $this->event_type);
        $stmt->bindParam(':rain_status',   $this->rain_status);
        $stmt->bindParam(':water_level',   $this->water_level);
        $stmt->bindParam(':vibration',     $this->vibration);
        $stmt->bindParam(':message',       $this->message);
        $stmt->bindParam(':latitude',      $this->latitude);
        $stmt->bindParam(':longitude',     $this->longitude);
        $stmt->bindParam(':location_name', $this->location_name);

        return $stmt->execute();
    }

    /** Get all alerts, newest first */
    public function getAll($limit = 50) {
        $query = "SELECT * FROM {$this->table}
                  WHERE is_dismissed = 0
                  ORDER BY created_at DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Get only unread alerts — used by the notification badge */
    public function getUnread() {
        $query = "SELECT * FROM {$this->table}
                  WHERE is_read = 0 AND is_dismissed = 0
                  ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Count unread alerts */
    public function countUnread() {
        $query = "SELECT COUNT(*) as cnt FROM {$this->table}
                  WHERE is_read = 0 AND is_dismissed = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }

    /** Get newest N alerts created after a given timestamp (for polling) */
    public function getSince($timestamp, $limit = 20) {
        $query = "SELECT * FROM {$this->table}
                  WHERE created_at > :ts AND is_dismissed = 0
                  ORDER BY created_at DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ts',    $timestamp);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mark a single alert as read */
    public function markRead($id) {
        $query = "UPDATE {$this->table} SET is_read = 1 WHERE id = :id";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Mark all alerts as read */
    public function markAllRead() {
        $query = "UPDATE {$this->table} SET is_read = 1 WHERE is_read = 0";
        $stmt  = $this->conn->prepare($query);
        return $stmt->execute();
    }

    /** Dismiss (soft-delete) a single alert */
    public function dismiss($id) {
        $query = "UPDATE {$this->table} SET is_dismissed = 1 WHERE id = :id";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Single alert by ID */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

