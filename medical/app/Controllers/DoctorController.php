<?php

class DoctorController extends BaseController {
    private $doctorModel;
    
    public function __construct() {
        // Initialize database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Initialize doctor model
        $this->doctorModel = new Doctor($db);
    }
    
    public function index() {
        // Get all doctors
        $stmt = $this->doctorModel->getAll();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Render view with doctors data
        $this->render('doctors/index', [
            'doctors' => $doctors,
            'title' => 'Doctors'
        ]);
    }
    
    public function create() {
        // Render create doctor form
        $this->render('doctors/create', [
            'title' => 'Add New Doctor'
        ]);
    }
    
    public function store() {
        if ($this->isPostRequest()) {
            // Validate required fields
            $requiredFields = ['name', 'email', 'phone', 'specialization', 'license_number'];
            $errors = $this->validateRequiredFields($requiredFields);
            
            if (empty($errors)) {
                // Set doctor properties
                $this->doctorModel->name = $this->getPostData('name');
                $this->doctorModel->email = $this->getPostData('email');
                $this->doctorModel->phone = $this->getPostData('phone');
                $this->doctorModel->specialization = $this->getPostData('specialization');
                $this->doctorModel->license_number = $this->getPostData('license_number');
                
                // Create doctor
                if ($this->doctorModel->create()) {
                    $this->redirect('/doctors?success=Doctor created successfully');
                } else {
                    $this->redirect('/doctors?error=Failed to create doctor');
                }
            } else {
                // Redirect with errors
                $this->redirect('/doctors/create?error=' . urlencode(implode(', ', $errors)));
            }
        } else {
            // Redirect if not POST request
            $this->redirect('/doctors');
        }
    }
    
    public function edit() {
        $id = $this->getGetData('id');
        
        if ($id) {
            // Get doctor by ID
            if ($this->doctorModel->getById($id)) {
                // Render edit doctor form
                $this->render('doctors/edit', [
                    'title' => 'Edit Doctor',
                    'doctor' => $this->doctorModel
                ]);
            } else {
                $this->redirect('/doctors?error=Doctor not found');
            }
        } else {
            $this->redirect('/doctors');
        }
    }
    
    public function update() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                // Validate required fields
                $requiredFields = ['name', 'email', 'phone', 'specialization', 'license_number'];
                $errors = $this->validateRequiredFields($requiredFields);
                
                if (empty($errors)) {
                    // Set doctor properties
                    $this->doctorModel->id = $id;
                    $this->doctorModel->name = $this->getPostData('name');
                    $this->doctorModel->email = $this->getPostData('email');
                    $this->doctorModel->phone = $this->getPostData('phone');
                    $this->doctorModel->specialization = $this->getPostData('specialization');
                    $this->doctorModel->license_number = $this->getPostData('license_number');
                    
                    // Update doctor
                    if ($this->doctorModel->update()) {
                        $this->redirect('/doctors?success=Doctor updated successfully');
                    } else {
                        $this->redirect('/doctors/edit?id=' . $id . '&error=Failed to update doctor');
                    }
                } else {
                    // Redirect with errors
                    $this->redirect('/doctors/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
                }
            } else {
                $this->redirect('/doctors?error=Invalid doctor ID');
            }
        } else {
            $this->redirect('/doctors');
        }
    }
    
    public function delete() {
        if ($this->isPostRequest()) {
            $id = $this->getPostData('id');
            
            if ($id) {
                $this->doctorModel->id = $id;
                
                // Delete doctor
                if ($this->doctorModel->delete()) {
                    $this->redirect('/doctors?success=Doctor deleted successfully');
                } else {
                    $this->redirect('/doctors?error=Failed to delete doctor');
                }
            } else {
                $this->redirect('/doctors?error=Invalid doctor ID');
            }
        } else {
            $this->redirect('/doctors');
        }
    }
}