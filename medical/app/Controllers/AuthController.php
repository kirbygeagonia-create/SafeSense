<?php

class AuthController extends BaseController {
    
    public function login() {
        // Render login view
        $this->render('auth/login', [
            'title' => 'Login'
        ]);
    }
    
    public function authenticate() {
        if ($this->isPostRequest()) {
            $email = $this->getPostData('email');
            $password = $this->getPostData('password');
            
            // In a real application, you would validate credentials against a database
            // For this example, we'll use a simple check
            
            // Example validation (replace with actual database check)
            if ($email === 'admin@example.com' && $password === 'password') {
                // Set session variables
                session_start();
                $_SESSION['user'] = [
                    'email' => $email,
                    'role' => 'admin'
                ];
                
                // Redirect to dashboard
                $this->redirect('/dashboard');
            } else {
                // Redirect back with error
                $this->redirect('/login?error=Invalid credentials');
            }
        } else {
            $this->redirect('/login');
        }
    }
    
    public function logout() {
        // Destroy session
        session_start();
        session_destroy();
        
        // Redirect to login
        $this->redirect('/login?success=You have been logged out');
    }
    
    public function dashboard() {
        // Check if user is logged in
        session_start();
        if (!isset($_SESSION['user'])) {
            $this->redirect('/login?error=Please log in to access the dashboard');
            return;
        }
        
        // Get statistics for dashboard
        $database = new Database();
        $db = $database->getConnection();
        
        // Count patients
        $patientModel = new Patient($db);
        $patientStmt = $patientModel->getAll();
        $patientCount = $patientStmt->rowCount();
        
        // Count doctors
        $doctorModel = new Doctor($db);
        $doctorStmt = $doctorModel->getAll();
        $doctorCount = $doctorStmt->rowCount();
        
        // Count appointments
        $appointmentModel = new Appointment($db);
        $appointmentStmt = $appointmentModel->getAll();
        $appointmentCount = $appointmentStmt->rowCount();
        
        // Render dashboard view
        $this->render('dashboard', [
            'title' => 'Dashboard',
            'patientCount' => $patientCount,
            'doctorCount' => $doctorCount,
            'appointmentCount' => $appointmentCount
        ]);
    }
}