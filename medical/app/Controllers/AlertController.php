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
     *   "location_name": "Brgy. Casisang, Malaybalay City",
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
?>

