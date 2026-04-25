<?php

class AppointmentController extends BaseController {
    private $appointmentModel;
    
    public function __construct() {
        // Initialize database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Initialize appointment model
        $this->appointmentModel = new Appointment($db);
    }
    
    public function index() {
        // Get all appointments
        $stmt = $this->appointmentModel->getAll();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Render view with appointments data
        $this->render('appointments/index', [
            'appointments' => $appointments,
            'title' => 'Appointments'
        ]);
    }
    
    public function create() {
        // Get patients and doctors for dropdowns
        $database = new Database();
        $db = $database->getConnection();
        
        $patientModel = new Patient($db);
        $doctorModel = new Doctor($db);
        
        $patients = $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
        $doctors = $doctorModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
        
        // Render create appointment form
        $this->render('appointments/create', [
            'title' => 'Schedule Appointment',
            'patients' => $patients,
            'doctors' => $doctors
        ]);
    }
    
    public function store() {
        if ($this->isPostRequest()) {
            // Validate required fields
            $requiredFields = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time'];
            $errors = $this->validateRequiredFields($requiredFields);
            
            if (empty($errors)) {
                // Set appointment properties
                $this->appointmentModel->patient_id = $this->getPostData('patient_id');
                $this->appointmentModel->doctor_id = $this->getPostData('doctor_id');
                $this->appointmentModel->appointment_date = $this->getPostData('appointment_date');
                $this->appointmentModel->appointment_time = $this->getPostData('appointment_time');
                $this->appointmentModel->status = $this->getPostData('status', 'pending');
                $this->appointmentModel->reason = $this->getPostData('reason', '');
                
                // Create appointment
                if ($this->appointmentModel->create()) {
                    $this->redirect('/appointments?success=Appointment scheduled successfully');
                } else {
                    $this->redirect('/appointments?error=Failed to schedule appointment');
                }
            } else {
                // Redirect with errors
                $this->redirect('/appointments/create?error=' . urlencode(implode(', ', $errors)));
            }
        } else {
            // Redirect if not POST request
            $this->redirect('/appointments');
        }
    }
    
    public function edit() {
        $id = $this->getGetData('id');
        
        if ($id) {
            // Get appointment by ID
            if ($this->appointmentModel->getById($id)) {
                // Get patients and doctors for dropdowns
                $database = new Database();
                $db = $database->getConnection();
                
                $patientModel = new Patient($db);
                $doctorModel = new Doctor($db);
                
                $patients = $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
                $doctors = $doctorModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
                
                // Render edit appointment form
                $this->render('appointments/edit', [
                    'title' => 'Edit Appointment',
                    'appointment' => $this->appointmentModel,
                    'patients' => $patients,
                    'doctors' => $doctors
                ]);
            } else {
                $this->redirect('/appointments?error=Appointment not found');
            }
        } else {
            $this->redirect('/appointments');
        }
    }
    
    public function update() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                // Validate required fields
                $requiredFields = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time'];
                $errors = $this->validateRequiredFields($requiredFields);
                
                if (empty($errors)) {
                    // Set appointment properties
                    $this->appointmentModel->id = $id;
                    $this->appointmentModel->patient_id = $this->getPostData('patient_id');
                    $this->appointmentModel->doctor_id = $this->getPostData('doctor_id');
                    $this->appointmentModel->appointment_date = $this->getPostData('appointment_date');
                    $this->appointmentModel->appointment_time = $this->getPostData('appointment_time');
                    $this->appointmentModel->status = $this->getPostData('status');
                    $this->appointmentModel->reason = $this->getPostData('reason');
                    
                    // Update appointment
                    if ($this->appointmentModel->update()) {
                        $this->redirect('/appointments?success=Appointment updated successfully');
                    } else {
                        $this->redirect('/appointments/edit?id=' . $id . '&error=Failed to update appointment');
                    }
                } else {
                    // Redirect with errors
                    $this->redirect('/appointments/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
                }
            } else {
                $this->redirect('/appointments?error=Invalid appointment ID');
            }
        } else {
            $this->redirect('/appointments');
        }
    }
    
    public function delete() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                $this->appointmentModel->id = $id;
                
                // Delete appointment
                if ($this->appointmentModel->delete()) {
                    $this->redirect('/appointments?success=Appointment deleted successfully');
                } else {
                    $this->redirect('/appointments?error=Failed to delete appointment');
                }
            } else {
                $this->redirect('/appointments?error=Invalid appointment ID');
            }
        } else {
            $this->redirect('/appointments');
        }
    }
}