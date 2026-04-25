<?php

class PatientController extends BaseController {
    private $patientModel;
    
    public function __construct() {
        // Initialize database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Initialize patient model
        $this->patientModel = new Patient($db);
    }
    
    public function index() {
        // Get all patients
        $stmt = $this->patientModel->getAll();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Render view with patients data
        $this->render('patients/index', [
            'patients' => $patients,
            'title' => 'Patients'
        ]);
    }
    
    public function create() {
        // Render create patient form
        $this->render('patients/create', [
            'title' => 'Add New Patient'
        ]);
    }
    
    public function store() {
        if ($this->isPostRequest()) {
            // Validate required fields
            $requiredFields = ['name', 'email', 'phone', 'date_of_birth', 'gender'];
            $errors = $this->validateRequiredFields($requiredFields);
            
            if (empty($errors)) {
                // Set patient properties
                $this->patientModel->name = $this->getPostData('name');
                $this->patientModel->email = $this->getPostData('email');
                $this->patientModel->phone = $this->getPostData('phone');
                $this->patientModel->address = $this->getPostData('address', '');
                $this->patientModel->date_of_birth = $this->getPostData('date_of_birth');
                $this->patientModel->gender = $this->getPostData('gender');
                
                // Create patient
                if ($this->patientModel->create()) {
                    $this->redirect('/patients?success=Patient created successfully');
                } else {
                    $this->redirect('/patients?error=Failed to create patient');
                }
            } else {
                // Redirect with errors
                $this->redirect('/patients/create?error=' . urlencode(implode(', ', $errors)));
            }
        } else {
            // Redirect if not POST request
            $this->redirect('/patients');
        }
    }
    
    public function edit() {
        $id = $this->getGetData('id');
        
        if ($id) {
            // Get patient by ID
            if ($this->patientModel->getById($id)) {
                // Render edit patient form
                $this->render('patients/edit', [
                    'title' => 'Edit Patient',
                    'patient' => $this->patientModel
                ]);
            } else {
                $this->redirect('/patients?error=Patient not found');
            }
        } else {
            $this->redirect('/patients');
        }
    }
    
    public function update() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                // Validate required fields
                $requiredFields = ['name', 'email', 'phone', 'date_of_birth', 'gender'];
                $errors = $this->validateRequiredFields($requiredFields);
                
                if (empty($errors)) {
                    // Set patient properties
                    $this->patientModel->id = $id;
                    $this->patientModel->name = $this->getPostData('name');
                    $this->patientModel->email = $this->getPostData('email');
                    $this->patientModel->phone = $this->getPostData('phone');
                    $this->patientModel->address = $this->getPostData('address', '');
                    $this->patientModel->date_of_birth = $this->getPostData('date_of_birth');
                    $this->patientModel->gender = $this->getPostData('gender');
                    
                    // Update patient
                    if ($this->patientModel->update()) {
                        $this->redirect('/patients?success=Patient updated successfully');
                    } else {
                        $this->redirect('/patients/edit?id=' . $id . '&error=Failed to update patient');
                    }
                } else {
                    // Redirect with errors
                    $this->redirect('/patients/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
                }
            } else {
                $this->redirect('/patients?error=Invalid patient ID');
            }
        } else {
            $this->redirect('/patients');
        }
    }
    
    public function delete() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                $this->patientModel->id = $id;
                
                // Delete patient
                if ($this->patientModel->delete()) {
                    $this->redirect('/patients?success=Patient deleted successfully');
                } else {
                    $this->redirect('/patients?error=Failed to delete patient');
                }
            } else {
                $this->redirect('/patients?error=Invalid patient ID');
            }
        } else {
            $this->redirect('/patients');
        }
    }
}