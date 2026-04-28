<?php

class AuthController extends BaseController {

    public function login() {
        // Redirect if already logged in
        if (isset($_SESSION['user'])) {
            $this->redirect('/dashboard');
            return;
        }
        $this->render('auth/login', ['title' => 'Login']);
    }

    public function authenticate() {
        if (!$this->isPostRequest()) { $this->redirect('/login'); return; }

        $email    = trim($this->getPostData('email') ?? '');
        $password = $this->getPostData('password') ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['flash_error'] = 'Email and password are required.';
            $this->redirect('/login');
            return;
        }

        $database = new Database();
        $db       = $database->getConnection();

        // Try users table first; fall back to demo credentials
        $user = null;
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($password, $row['password'])) {
                $user = ['email' => $row['email'], 'role' => $row['role'], 'name' => $row['name'] ?? 'Staff'];
            }
        } catch (Exception $e) {
            // Users table may not exist yet — fall through to demo login
        }

        // Demo fallback (remove in production)
        if (!$user && $email === 'admin@example.com' && $password === 'password') {
            $user = ['email' => $email, 'role' => 'admin', 'name' => 'Admin'];
        }

        if ($user) {
            $_SESSION['user']       = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['flash_success'] = 'Welcome back, ' . $user['name'] . '!';
            $this->redirect('/dashboard');
        } else {
            $_SESSION['flash_error'] = 'Invalid email or password.';
            $this->redirect('/login');
        }
    }

    public function logout() {
        session_destroy();
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
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

        try {
            // Load alert model
            $alertModelFile = APP_PATH . '/Models/Alert.php';
            if (file_exists($alertModelFile)) {
                require_once $alertModelFile;
                $alertModel  = new Alert($db);
                $recentAlerts = $alertModel->getAll(5);
                $unreadAlerts = $alertModel->countUnread();
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

        $this->render('dashboard', [
            'title'                => 'Dashboard',
            'patientCount'         => $patientCount,
            'doctorCount'          => $doctorCount,
            'appointmentCount'     => $appointmentCount,
            'billingSummary'       => $billingSummary,
            'unreadAlerts'         => $unreadAlerts,
            'recentAlerts'         => $recentAlerts,
            'upcomingAppointments' => $upcomingAppointments,
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

