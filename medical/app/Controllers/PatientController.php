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
                    if ($this->isAjax()) {
                        $this->jsonResponse([
                            'success' => true,
                            'message' => 'Patient created successfully',
                            'data' => [
                                'id' => $this->patientModel->id,
                                'name' => $this->patientModel->name,
                                'email' => $this->patientModel->email,
                                'phone' => $this->patientModel->phone,
                                'address' => $this->patientModel->address,
                                'date_of_birth' => $this->patientModel->date_of_birth,
                                'gender' => $this->patientModel->gender
                            ]
                        ]);
                    }
            $_SESSION['flash_success'] = 'Patient created successfully';
            $this->redirect('/patients');
                } else {
                    if ($this->isAjax()) {
                        $this->jsonResponse(['success' => false, 'message' => 'Failed to create patient'], 500);
                    }
            $_SESSION['flash_error'] = 'Failed to create patient';
            $this->redirect('/patients');
                }
            } else {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
                }
                // Redirect with errors
                $_SESSION['flash_error'] = implode(', ', $errors);
                $this->redirect('/patients');
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            // Redirect if not POST request
            $this->redirect('/patients');
        }
    }
    
    public function edit() {
        $id = $this->getGetData('id');
        
        if ($id) {
            // Get patient by ID
            if ($this->patientModel->getById($id)) {
                if ($this->isAjax()) {
                    $this->jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => $this->patientModel->id,
                            'name' => $this->patientModel->name,
                            'email' => $this->patientModel->email,
                            'phone' => $this->patientModel->phone,
                            'address' => $this->patientModel->address,
                            'date_of_birth' => $this->patientModel->date_of_birth,
                            'gender' => $this->patientModel->gender
                        ]
                    ]);
                }
                // Render edit patient form
                $this->render('patients/edit', [
                    'title' => 'Edit Patient',
                    'patient' => $this->patientModel
                ]);
            } else {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'message' => 'Patient not found'], 404);
                }
                $_SESSION['flash_error'] = 'Patient not found';
                $this->redirect('/patients');
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Invalid patient ID'], 400);
            }
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
                        if ($this->isAjax()) {
                            $this->jsonResponse([
                                'success' => true,
                                'message' => 'Patient updated successfully',
                                'data' => [
                                    'id' => $this->patientModel->id,
                                    'name' => $this->patientModel->name,
                                    'email' => $this->patientModel->email,
                                    'phone' => $this->patientModel->phone,
                                    'address' => $this->patientModel->address,
                                    'date_of_birth' => $this->patientModel->date_of_birth,
                                    'gender' => $this->patientModel->gender
                                ]
                            ]);
                        }
                        $_SESSION['flash_success'] = 'Patient updated successfully';
                        $this->redirect('/patients');
                    } else {
                        if ($this->isAjax()) {
                            $this->jsonResponse(['success' => false, 'message' => 'Failed to update patient'], 500);
                        }
                        $this->redirect('/patients/edit?id=' . $id . '&error=Failed to update patient');
                    }
                } else {
                    if ($this->isAjax()) {
                        $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
                    }
                    // Redirect with errors
                    $this->redirect('/patients/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
                }
            } else {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'message' => 'Invalid patient ID'], 400);
                }
                $_SESSION['flash_error'] = 'Invalid patient ID';
                $this->redirect('/patients');
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
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
                    if ($this->isAjax()) {
                        $this->jsonResponse([
                            'success' => true,
                            'message' => 'Patient deleted successfully'
                        ]);
                    }
                    $_SESSION['flash_success'] = 'Patient deleted successfully';
                    $this->redirect('/patients');
                } else {
                    if ($this->isAjax()) {
                        $this->jsonResponse(['success' => false, 'message' => 'Failed to delete patient'], 500);
                    }
                    $_SESSION['flash_error'] = 'Failed to delete patient';
                    $this->redirect('/patients');
                }
            } else {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'message' => 'Invalid patient ID'], 400);
                }
                $_SESSION['flash_error'] = 'Invalid patient ID';
                $this->redirect('/patients');
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            $this->redirect('/patients');
        }
    }
}