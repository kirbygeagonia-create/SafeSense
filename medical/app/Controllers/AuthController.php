<?php

class AuthController extends BaseController {

    public function login() {
        // Redirect if already logged in
        if (isset($_SESSION['user'])) {
            $this->redirect('/dashboard');
            return;
        }
        $this->render('auth/login', ['title' => 'Login', 'csrf_token' => $this->generateCsrfToken()]);
    }

    public function authenticate() {
        if (!$this->isPostRequest()) {
            $this->redirect('/login');
            return;
        }

        $this->validateCsrf();

        // Brute-force protection — keyed by IP with a 15-minute sliding window
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'login_attempts_' . md5($ip);
        $now = time();

        $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => $now];

        // Reset window after 15 minutes
        if (($now - $attempts['first_attempt']) > 900) {
            $attempts = ['count' => 0, 'first_attempt' => $now];
        }

        if ($attempts['count'] >= 5) {
            $wait = 900 - ($now - $attempts['first_attempt']);
            $_SESSION['flash_error'] = 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).';
            $this->redirect('/login');
            return;
        }

        $email    = trim($this->getPostData('email') ?? '');
        $password = $this->getPostData('password') ?? '';

        if (empty($email) || empty($password)) {
            $attempts['count']++;
            $_SESSION[$key] = $attempts;
            $_SESSION['flash_error'] = 'Email and password are required.';
            $this->redirect('/login');
            return;
        }

        $database = new Database();
        $db       = $database->getConnection();

        $user = null;
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($password, $row['password'])) {
                $user = ['id' => $row['id'], 'email' => $row['email'], 'role' => $row['role'], 'name' => $row['name'] ?? 'Staff'];
            }
        } catch (Exception $e) {
            // DB not ready — fail gracefully
        }

        if ($user) {
            // Success — reset attempt counter
            unset($_SESSION[$key]);
            session_regenerate_id(true);
            $_SESSION['user']       = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['name']) . '!';
            $this->redirect('/dashboard');
        } else {
            // Failure — increment counter
            $attempts['count']++;
            $_SESSION[$key] = $attempts;
            $_SESSION['flash_error'] = 'Invalid email or password.';
            $this->redirect('/login');
        }
    }

    public function logout() {
        $this->validateCsrf();

        // Store flash before clearing the session
        $_SESSION = [];

        // Expire the session cookie
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();

        // Start a fresh session ONLY to carry the flash message to the login page
        session_start();
        $_SESSION['flash_success'] = 'You have been logged out successfully.';

        $this->redirect('/login');
    }

    public function dashboard() {
        if (!isset($_SESSION['user'])) {
            $this->redirect('/login');
            return;
        }

        $database = new Database();
        $db       = $database->getConnection();

        // Count stats (safe — graceful if tables don't exist)
        $patientCount     = $this->safeCount($db, 'patients');
        $doctorCount      = $this->safeCount($db, 'doctors');
        $appointmentCount = $this->safeCount($db, 'appointments');

        // Billing summary for dashboard widget
        $billingSummary = [
            'total_invoiced'  => 0,
            'total_collected' => 0,
            'total_unpaid'    => 0,
            'invoice_count'   => 0
        ];
        try {
            $billingModelFile = APP_PATH . '/Models/Billing.php';
            if (file_exists($billingModelFile)) {
                require_once $billingModelFile;
                $billingModel = new Billing($db);
                $billingSummary = $billingModel->getSummary();
            }
        } catch (Exception $e) {}

        // Recent alerts for dashboard widget
        $recentAlerts        = [];
        $unreadAlerts        = 0;
        $upcomingAppointments = [];
        $alertStats          = ['critical' => 0, 'warning' => 0, 'info' => 0, 'total_unread' => 0];

        try {
            // Load alert model
            $alertModelFile = APP_PATH . '/Models/Alert.php';
            if (file_exists($alertModelFile)) {
                require_once $alertModelFile;
                $alertModel  = new Alert($db);
                $recentAlerts = $alertModel->getAll(5);
                $unreadAlerts = $alertModel->countUnread();

                // ENH-3: Alert stats by level
                $stmt = $db->query("SELECT alert_level, COUNT(*) AS cnt FROM safesense_alerts WHERE is_read = 0 GROUP BY alert_level");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $alertStats[$r['alert_level']] = (int)$r['cnt'];
                }
                $alertStats['total_unread'] = $unreadAlerts;
            }
        } catch (Exception $e) {}

        try {
            $stmt = $db->prepare("
                SELECT a.*, p.name as patient_name, d.name as doctor_name
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors  d ON a.doctor_id  = d.id
                WHERE a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT 5
            ");
            $stmt->execute();
            $upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Role-specific data
        $role = $_SESSION['user']['role'] ?? 'staff';
        $myAppointments = [];
        $myAlerts       = [];

        try {
            if ($role === 'doctor') {
                // Find the doctor record matching the logged-in user's email
                $stmt = $db->prepare(
                    "SELECT a.*, p.name as patient_name
                     FROM appointments a
                     JOIN patients p ON a.patient_id = p.id
                     JOIN doctors   d ON a.doctor_id  = d.id
                     WHERE d.email = ? AND a.appointment_date >= CURDATE()
                     ORDER BY a.appointment_date ASC, a.appointment_time ASC
                     LIMIT 10"
                );
                $stmt->execute([$_SESSION['user']['email']]);
                $myAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}

        try {
            if ($role === 'nurse') {
                $alertModelFile = APP_PATH . '/Models/Alert.php';
                if (file_exists($alertModelFile)) {
                    require_once $alertModelFile;
                    $alertModel = new Alert($db);
                    $myAlerts   = $alertModel->getUnread();
                    $myAlerts   = is_array($myAlerts)
                        ? $myAlerts
                        : $myAlerts->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {}

        // Today's summary metrics (TASK-3F)
        $todayStats = [];
        try {
            $todayStats['appointments_today']  = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
            $todayStats['new_patients_today']  = $db->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $todayStats['alerts_today']        = $db->query("SELECT COUNT(*) FROM safesense_alerts WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $todayStats['unread_alerts']       = $db->query("SELECT COUNT(*) FROM safesense_alerts WHERE is_read = 0")->fetchColumn();
            $todayStats['unpaid_invoices']     = $db->query("SELECT COUNT(*) FROM billing WHERE payment_status = 'unpaid'")->fetchColumn();
        } catch (Exception $e) {
            $todayStats = array_fill_keys(['appointments_today','new_patients_today','alerts_today','unread_alerts','unpaid_invoices'], 0);
        }

        $this->render('dashboard', [
            'title'                => 'Dashboard',
            'patientCount'         => $patientCount,
            'doctorCount'          => $doctorCount,
            'appointmentCount'     => $appointmentCount,
            'billingSummary'       => $billingSummary,
            'unreadAlerts'         => $unreadAlerts,
            'recentAlerts'         => $recentAlerts,
            'upcomingAppointments' => $upcomingAppointments,
            'userRole'             => $role,
            'myAppointments'       => $myAppointments,
            'myAlerts'             => $myAlerts,
            'todayStats'           => $todayStats,
            'alertStats'           => $alertStats,
        ]);
    }

    private function safeCount($db, $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as c FROM `$table`");
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($row['c'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

