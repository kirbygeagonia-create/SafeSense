<?php

class PatientController extends BaseController
{
    private $patientModel;

    public function __construct()
    {
        $database = new Database();
        $db = $database->getConnection();
        $this->patientModel = new Patient($db);
    }

    public function index()
    {
        $stmt = $this->patientModel->getAll();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('patients/index', [
            'patients' => $patients,
            'title'    => 'Patients'
        ]);
    }

    public function store()
    {
        if ($this->isPostRequest()) {
            // Task 2 — CSRF guard
            $this->validateCsrf();

            $requiredFields = ['name', 'email', 'phone', 'date_of_birth', 'gender'];
            $errors = $this->validateRequiredFields($requiredFields);

            if (empty($errors)) {
                $this->patientModel->name         = $this->getPostData('name');
                $this->patientModel->email        = $this->getPostData('email');
                $this->patientModel->phone        = $this->getPostData('phone');
                $this->patientModel->address      = $this->getPostData('address', '');
                $this->patientModel->date_of_birth = $this->getPostData('date_of_birth');
                $this->patientModel->gender       = $this->getPostData('gender');

                // Task 3 — duplicate email guard
                try {
                    $created = $this->patientModel->create();
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        if ($this->isAjax()) {
                            $this->jsonResponse(['success' => false, 'message' => 'A record with this email already exists.'], 422);
                        }
                        $_SESSION['flash_error'] = 'A record with this email already exists.';
                        $this->redirect('/patients');
                        return;
                    }
                    throw $e;
                }

                if ($created) {
                    if ($this->isAjax()) {
                        $this->jsonResponse([
                            'success' => true,
                            'message' => 'Patient created successfully',
                            'data'    => [
                                'id'            => $this->patientModel->id,
                                'name'          => $this->patientModel->name,
                                'email'         => $this->patientModel->email,
                                'phone'         => $this->patientModel->phone,
                                'address'       => $this->patientModel->address,
                                'date_of_birth' => $this->patientModel->date_of_birth,
                                'gender'        => $this->patientModel->gender
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
                $_SESSION['flash_error'] = implode(', ', $errors);
                $this->redirect('/patients');
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            $this->redirect('/patients');
        }
    }

    public function edit()
    {
        $id = $this->getGetData('id');
        if ($id) {
            if ($this->patientModel->getById($id)) {
                if ($this->isAjax()) {
                    $this->jsonResponse([
                        'success' => true,
                        'data'    => [
                            'id'            => $this->patientModel->id,
                            'name'          => $this->patientModel->name,
                            'email'         => $this->patientModel->email,
                            'phone'         => $this->patientModel->phone,
                            'address'       => $this->patientModel->address,
                            'date_of_birth' => $this->patientModel->date_of_birth,
                            'gender'        => $this->patientModel->gender
                        ]
                    ]);
                }
                $this->render('patients/edit', [
                    'title'   => 'Edit Patient',
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

    public function update()
    {
        if ($this->isPostRequest()) {
            // Task 2 — CSRF guard
            $this->validateCsrf();

            $id = $this->getPostData('id');
            if ($id) {
                $requiredFields = ['name', 'email', 'phone', 'date_of_birth', 'gender'];
                $errors = $this->validateRequiredFields($requiredFields);

                if (empty($errors)) {
                    $this->patientModel->id           = $id;
                    $this->patientModel->name         = $this->getPostData('name');
                    $this->patientModel->email        = $this->getPostData('email');
                    $this->patientModel->phone        = $this->getPostData('phone');
                    $this->patientModel->address      = $this->getPostData('address', '');
                    $this->patientModel->date_of_birth = $this->getPostData('date_of_birth');
                    $this->patientModel->gender       = $this->getPostData('gender');

                    // Task 3 — duplicate email guard
                    try {
                        $updated = $this->patientModel->update();
                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000') {
                            if ($this->isAjax()) {
                                $this->jsonResponse(['success' => false, 'message' => 'A record with this email already exists.'], 422);
                            }
                            $_SESSION['flash_error'] = 'A record with this email already exists.';
                            $this->redirect('/patients');
                            return;
                        }
                        throw $e;
                    }

                    if ($updated) {
                        if ($this->isAjax()) {
                            $this->jsonResponse([
                                'success' => true,
                                'message' => 'Patient updated successfully',
                                'data'    => [
                                    'id'            => $this->patientModel->id,
                                    'name'          => $this->patientModel->name,
                                    'email'         => $this->patientModel->email,
                                    'phone'         => $this->patientModel->phone,
                                    'address'       => $this->patientModel->address,
                                    'date_of_birth' => $this->patientModel->date_of_birth,
                                    'gender'        => $this->patientModel->gender
                                ]
                            ]);
                        }
                        $_SESSION['flash_success'] = 'Patient updated successfully';
                        $this->redirect('/patients');
                    } else {
                        if ($this->isAjax()) {
                            $this->jsonResponse(['success' => false, 'message' => 'Failed to update patient'], 500);
                        }
                        $_SESSION['flash_error'] = 'Failed to update patient';
                        $this->redirect('/patients');
                    }
                } else {
                    if ($this->isAjax()) {
                        $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
                    }
                    $_SESSION['flash_error'] = implode(', ', $errors);
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

    public function delete()
    {
        if ($this->isPostRequest()) {
            // Task 2 — CSRF guard
            $this->validateCsrf();

            $id = $this->getPostData('id');
            if ($id) {
                $this->patientModel->id = $id;
                if ($this->patientModel->delete()) {
                    if ($this->isAjax()) {
                        $this->jsonResponse(['success' => true, 'message' => 'Patient deleted successfully']);
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