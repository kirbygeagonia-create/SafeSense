<?php

/**
 * AlertController
 *
 * Handles:
 *   POST /api/alert         — Arduino device posts sensor data here
 *   GET  /alerts            — Alert log view (staff)
 *   GET  /api/alerts/poll   — Long-poll JSON for new alerts (AJAX)
 *   POST /api/alerts/read   — Mark alert(s) read
 *   POST /api/alerts/dismiss — Dismiss an alert
 */
class AlertController extends BaseController {

    // ---------------------------------------------------------------
    // ARDUINO IoT ENDPOINT  —  POST /api/alert
    // ---------------------------------------------------------------

    /**
     * Called by the Arduino (WiFi Shield) when a critical event is detected.
     *
     * Expected JSON body:
     * {
     *   "device_id":     "SAFESENSE-001",
     *   "station_type":  "hospital",          // hospital | police | fire
     *   "alert_level":   "critical",          // warning | danger | critical
     *   "event_type":    "flood",             // rain | flood | accident | vibration | test
     *   "rain_status":   "heavy",             // none | light | moderate | heavy
     *   "water_level":   45.2,               // cm (float)
     *   "vibration":     0,                  // 0 or 1
     *   "message":       "Flood detected...",
     *   "latitude":      8.1574,
     *   "longitude":     124.9282,
     *   "location_name": "Brgy. Crossing Rubber, Tupi",
     *   "api_key":       "SAFESENSE_SECRET_KEY"   // shared secret
     * }
     */
    public function receive() {
        // Only accept POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        // Parse body — accept both JSON and form-POST
        $raw   = file_get_contents('php://input');
        $data  = json_decode($raw, true);
        if (!$data) {
            $data = $_POST; // fallback for form-encoded requests
        }

        // --- Simple shared-secret auth (replace value in config!) ---
        $expectedKey = defined('SAFESENSE_API_KEY') ? SAFESENSE_API_KEY : 'SAFESENSE_SECRET_KEY';
        if (empty($data['api_key']) || $data['api_key'] !== $expectedKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        // --- Validate required fields ---
        $required = ['alert_level', 'event_type', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->jsonResponse(['success' => false, 'error' => "Missing field: $field"], 400);
                return;
            }
        }

        // --- Whitelist enum values to match DB constraints ---
        $validLevels = ['warning', 'danger', 'critical'];
        $validEvents = ['rain', 'flood', 'accident', 'vibration', 'test'];
        if (!in_array($data['alert_level'], $validLevels, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid alert_level. Must be: warning, danger, or critical'], 400);
            return;
        }
        if (!in_array($data['event_type'], $validEvents, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid event_type. Must be: rain, flood, accident, vibration, or test'], 400);
            return;
        }

        // --- Sanitize & store ---
        $database = new Database();
        $db       = $database->getConnection();
        $alert    = new Alert($db);

        $alert->device_id     = $this->sanitize($data['device_id']    ?? 'SAFESENSE-001');
        $alert->station_type  = $this->sanitize($data['station_type'] ?? 'hospital');
        $alert->alert_level   = $this->sanitize($data['alert_level']);
        $alert->event_type    = $this->sanitize($data['event_type']);
        $alert->rain_status   = $this->sanitize($data['rain_status']  ?? null);
        $alert->water_level   = isset($data['water_level'])  ? (float)$data['water_level']  : null;
        $alert->vibration     = isset($data['vibration'])    ? (int)$data['vibration']       : 0;
        $alert->message       = $this->sanitize($data['message']);
        $alert->latitude      = isset($data['latitude'])     ? (float)$data['latitude']      : null;
        $alert->longitude     = isset($data['longitude'])    ? (float)$data['longitude']     : null;
        $alert->location_name = $this->sanitize($data['location_name'] ?? 'Unknown Location');
        $alert->image_path    = null; // set later by uploadImage() when ESP32-CAM posts the photo

        if ($alert->create()) {
            $this->jsonResponse(['success' => true, 'message' => 'Alert received and stored.'], 201);
        } else {
            $this->jsonResponse(['success' => false, 'error' => 'Database write failed.'], 500);
        }
    }

    // ---------------------------------------------------------------
    // STAFF VIEW  —  GET /alerts
    // ---------------------------------------------------------------

    public function index() {
        $this->requireAuth();

        $database = new Database();
        $db       = $database->getConnection();
        $alertModel = new Alert($db);

        $alerts      = $alertModel->getAll(100);
        $unreadCount = $alertModel->countUnread();

        $this->render('alerts/index', [
            'title'       => 'SafeSense Alerts',
            'alerts'      => $alerts,
            'unreadCount' => $unreadCount,
        ]);
    }

    // ---------------------------------------------------------------
    // POLLING ENDPOINT  —  GET /api/alerts/poll?since=TIMESTAMP
    // ---------------------------------------------------------------

    /**
     * JavaScript on the dashboard calls this every 5 s to check for new alerts.
     * Returns JSON { alerts: [...], unread_count: N }
     */
    public function poll() {
        $this->requireAuth();

        $database   = new Database();
        $db         = $database->getConnection();
        $alertModel = new Alert($db);

        $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-10 seconds'));
        $new   = $alertModel->getSince($since);
        $total = $alertModel->countUnread();

        $this->jsonResponse([
            'success'      => true,
            'alerts'       => $new,
            'unread_count' => $total,
            'server_time'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ---------------------------------------------------------------
    // MARK READ  —  POST /api/alerts/read
    // ---------------------------------------------------------------

    public function markRead() {
        $this->requireAuth();
        $this->validateCsrf();

        $database   = new Database();
        $db         = $database->getConnection();
        $alertModel = new Alert($db);

        $id = $_POST['id'] ?? null;

        if ($id === 'all') {
            $alertModel->markAllRead();
        } elseif ($id) {
            $alertModel->markRead((int)$id);
        }

        $this->jsonResponse(['success' => true, 'unread_count' => $alertModel->countUnread()]);
    }

    // ---------------------------------------------------------------
    // DISMISS  —  POST /api/alerts/dismiss
    // ---------------------------------------------------------------

    public function dismiss() {
        $this->requireAuth();
        $this->validateCsrf();

        $database   = new Database();
        $db         = $database->getConnection();
        $alertModel = new Alert($db);

        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $alertModel->dismiss($id);
        }

        $this->jsonResponse(['success' => true]);
    }

    // ---------------------------------------------------------------
    // SIMULATE ALERT  —  POST /api/alert/simulate  (admin only)
    // ---------------------------------------------------------------

    /**
     * Admin-only endpoint to inject a realistic test alert without hardware.
     * Useful for demonstrations when the Arduino is not physically connected.
     * The poll loop will pick it up within 5 seconds exactly like a real alert.
     *
     * POST body (form or JSON):
     *   level  = warning | danger | critical   (default: critical)
     *   event  = flood | rain | accident       (default: flood)
     */
    public function simulate() {
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $level = $_POST['level'] ?? 'critical';
        $event = $_POST['event'] ?? 'flood';

        $validLevels = ['warning', 'danger', 'critical'];
        $validEvents = ['rain', 'flood', 'accident', 'vibration', 'test'];
        if (!in_array($level, $validLevels)) $level = 'critical';
        if (!in_array($event, $validEvents)) $event = 'flood';

        $messages = [
            'critical' => 'CRITICAL: Severe flood detected. Water level at 52.4 cm — DANGER threshold exceeded. Immediate response required!',
            'danger'   => 'DANGER: Rising floodwater detected. Water level: 38.1 cm. Road hazard likely. Staff on alert.',
            'warning'  => 'WARNING: Rain detected (moderate). Water level: 18.5 cm. Monitoring conditions.',
        ];

        $waterLevels = ['critical' => 52.4, 'danger' => 38.1, 'warning' => 18.5];

        $database = new Database();
        $db       = $database->getConnection();
        $alert    = new Alert($db);

        $alert->device_id     = 'SAFESENSE-001';
        $alert->station_type  = 'hospital';
        $alert->alert_level   = $level;
        $alert->event_type    = $event;
        $alert->rain_status   = ($level === 'critical') ? 'heavy' : ($level === 'danger' ? 'moderate' : 'light');
        $alert->water_level   = $waterLevels[$level];
        $alert->vibration     = ($event === 'accident') ? 1 : 0;
        $alert->message       = $messages[$level];
        $alert->latitude      = 8.1574;
        $alert->longitude     = 124.9282;
        $alert->location_name = 'Brgy. Crossing Rubber, Tupi';
        $alert->image_path    = null;

        if ($alert->create()) {
            $this->jsonResponse([
                'success' => true,
                'message' => "Simulated {$level} alert injected. Dashboard will update within 5 seconds.",
                'level'   => $level,
            ], 201);
        } else {
            $this->jsonResponse(['success' => false, 'error' => 'Database write failed.'], 500);
        }
    }

    // ---------------------------------------------------------------
    // CAMERA IMAGE UPLOAD  —  POST /api/alert/image
    // ---------------------------------------------------------------

    /**
     * Called by the ESP32-CAM immediately after sending a JSON alert.
     * Receives a JPEG image captured by the onboard camera during
     * DANGER or CRITICAL events (floods, accidents).
     *
     * The image is stored on disk and linked to the most recent alert
     * from this device, providing visual evidence for responders.
     *
     * POST body: multipart/form-data with fields:
     *   api_key      — shared secret
     *   device_id    — e.g., "SAFESENSE-001"
     *   alert_level  — danger | critical
     *   event_type   — flood | accident | rain
     *   latitude     — float
     *   longitude    — float
     *   image        — JPEG file (typically 30–80 KB at SVGA)
     */
    public function uploadImage() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        // Validate API key (from POST field, since this is multipart/form-data)
        $expectedKey = defined('SAFESENSE_API_KEY') ? SAFESENSE_API_KEY : 'SAFESENSE_SECRET_KEY';
        $apiKey = $_POST['api_key'] ?? '';
        if (empty($apiKey) || $apiKey !== $expectedKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        // Validate image file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = $_FILES['image']['error'] ?? 'No file';
            $this->jsonResponse(['success' => false, 'error' => 'Image upload failed. Error: ' . $uploadError], 400);
            return;
        }

        // Validate MIME type — only accept JPEG images
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['image']['tmp_name']);
        if ($mimeType !== 'image/jpeg') {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid image type. Expected JPEG, got: ' . $mimeType], 400);
            return;
        }

        // Validate file size (max 500KB — ESP32-CAM SVGA JPEG is typically 30-80KB)
        $maxSize = 500 * 1024; // 500 KB
        if ($_FILES['image']['size'] > $maxSize) {
            $this->jsonResponse(['success' => false, 'error' => 'Image too large. Max 500KB.'], 400);
            return;
        }

        // Create storage directory
        $imageDir = APP_PATH . '/../storage/alert_images';
        if (!is_dir($imageDir)) {
            @mkdir($imageDir, 0755, true);
        }

        // Generate unique filename with timestamp and device ID
        $deviceId   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['device_id'] ?? 'unknown');
        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = "safesense_{$deviceId}_{$timestamp}.jpg";
        $filepath   = $imageDir . '/' . $filename;

        // Move uploaded file to storage
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            $this->jsonResponse(['success' => false, 'error' => 'Failed to save image.'], 500);
            return;
        }

        // Link image to the most recent alert from this device (within last 60 seconds)
        $database = new Database();
        $db       = $database->getConnection();

        try {
            $stmt = $db->prepare("
                SELECT id FROM safesense_alerts
                WHERE device_id = :device_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([':device_id' => $this->sanitize($_POST['device_id'] ?? 'SAFESENSE-001')]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $updateStmt = $db->prepare("UPDATE safesense_alerts SET image_path = :path WHERE id = :id");
                $updateStmt->execute([
                    ':path' => 'storage/alert_images/' . $filename,
                    ':id'   => $row['id'],
                ]);
            }
        } catch (\Exception $e) {
            // Non-critical — image is saved on disk even if DB link fails
        }

        $this->jsonResponse([
            'success'  => true,
            'message'  => 'Image received and stored.',
            'filename' => $filename,
        ], 201);
    }

    // ---------------------------------------------------------------
    // DEVICE HEARTBEAT  —  POST /api/heartbeat
    // ---------------------------------------------------------------

    /**
     * Called periodically by the ESP32-CAM (every 5 minutes) to report
     * that the field device is still online and functioning.
     *
     * POST body (JSON):
     * {
     *   "api_key":      "...",
     *   "device_id":    "SAFESENSE-001",
     *   "station_type": "hospital",
     *   "status":       "online",            // online | arduino_disconnected
     *   "wifi_rssi":    -45,                 // WiFi signal strength (dBm)
     *   "uptime_ms":    300000,              // Device uptime in ms
     *   "free_heap":    120000               // Free memory in bytes
     * }
     */
    public function heartbeat() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data) {
            $data = $_POST;
        }

        // Validate API key
        $expectedKey = defined('SAFESENSE_API_KEY') ? SAFESENSE_API_KEY : 'SAFESENSE_SECRET_KEY';
        if (empty($data['api_key']) || $data['api_key'] !== $expectedKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId    = $this->sanitize($data['device_id']    ?? 'SAFESENSE-001');
        $stationType = $this->sanitize($data['station_type'] ?? 'hospital');
        $status      = $this->sanitize($data['status']       ?? 'online');
        $wifiRssi    = isset($data['wifi_rssi'])  ? (int)$data['wifi_rssi']  : null;
        $uptimeMs    = isset($data['uptime_ms'])  ? (int)$data['uptime_ms']  : null;
        $freeHeap    = isset($data['free_heap'])  ? (int)$data['free_heap']  : null;

        // Store the heartbeat in a simple file-based cache
        // (avoids adding a new DB table just for heartbeats)
        $heartbeatDir = APP_PATH . '/../storage/heartbeats';
        if (!is_dir($heartbeatDir)) {
            @mkdir($heartbeatDir, 0755, true);
        }

        $heartbeatData = [
            'device_id'    => $deviceId,
            'station_type' => $stationType,
            'status'       => $status,
            'wifi_rssi'    => $wifiRssi,
            'uptime_ms'    => $uptimeMs,
            'free_heap'    => $freeHeap,
            'last_seen'    => date('Y-m-d H:i:s'),
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        $filename = $heartbeatDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.json';
        file_put_contents($filename, json_encode($heartbeatData, JSON_PRETTY_PRINT));

        $this->jsonResponse([
            'success'     => true,
            'message'     => 'Heartbeat received.',
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    protected function sanitize($val) {
        if ($val === null) return null;
        return htmlspecialchars(strip_tags(trim($val)));
    }

    protected function requireAuth() {
        if (!isset($_SESSION['user'])) {
            // For page requests (non-AJAX), redirect to login instead of JSON error
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $isApi   = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
            if ($isAjax || $isApi) {
                $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            } else {
                $_SESSION['flash_error'] = 'Please log in to access this page.';
                $this->redirect('/login');
            }
            exit;
        }
    }
}
