<?php

class DoctorController extends BaseController
{
    private $doctorModel;

    public function __construct()
    {
        $database = new Database();
        $db = $database->getConnection();
        $this->doctorModel = new Doctor($db);
    }

    public function index()
    {
        $this->requireLogin();
        $stmt = $this->doctorModel->getAll();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->render('doctors/index', [
            'doctors'     => $doctors,
            'title'       => 'Doctors',
            'currentRole' => $this->currentRole()
        ]);
    }

    public function store()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/doctors');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole('admin');
        $this->validateCsrf(); // Task 2

        $errors = $this->validateRequiredFields(['name', 'email', 'phone', 'specialization', 'license_number']);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/doctors');
            return;
        }
        $this->doctorModel->name           = $this->getPostData('name');
        $this->doctorModel->email          = $this->getPostData('email');
        $this->doctorModel->phone          = $this->getPostData('phone');
        $this->doctorModel->specialization = $this->getPostData('specialization');
        $this->doctorModel->license_number = $this->getPostData('license_number');

        // Task 3 — duplicate email guard
        try {
            $created = $this->doctorModel->create();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                if ($this->isAjax())
                    $this->jsonResponse(['success' => false, 'message' => 'A record with this email already exists.'], 422);
                $_SESSION['flash_error'] = 'A record with this email already exists.';
                $this->redirect('/doctors');
                return;
            }
            throw $e;
        }

        if ($created) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Doctor created successfully',
                    'data'    => [
                        'id'             => $this->doctorModel->id,
                        'name'           => $this->doctorModel->name,
                        'email'          => $this->doctorModel->email,
                        'phone'          => $this->doctorModel->phone,
                        'specialization' => $this->doctorModel->specialization,
                        'license_number' => $this->doctorModel->license_number
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Doctor created successfully';
            $this->redirect('/doctors');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to create doctor'], 500);
            $_SESSION['flash_error'] = 'Failed to create doctor';
            $this->redirect('/doctors');
        }
    }

    public function edit()
    {
        $this->requireLogin();
        $id = $this->getGetData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid doctor ID'], 400);
            $this->redirect('/doctors');
            return;
        }
        if ($this->doctorModel->getById($id)) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'data'    => [
                        'id'             => $this->doctorModel->id,
                        'name'           => $this->doctorModel->name,
                        'email'          => $this->doctorModel->email,
                        'phone'          => $this->doctorModel->phone,
                        'specialization' => $this->doctorModel->specialization,
                        'license_number' => $this->doctorModel->license_number
                    ]
                ]);
            }
            $this->render('doctors/edit', ['title' => 'Edit Doctor', 'doctor' => $this->doctorModel]);
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Doctor not found'], 404);
            $_SESSION['flash_error'] = 'Doctor not found';
            $this->redirect('/doctors');
        }
    }

    public function update()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/doctors');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole('admin');
        $this->validateCsrf(); // Task 2

        $id = $this->getPostData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid doctor ID'], 400);
            $_SESSION['flash_error'] = 'Invalid doctor ID';
            $this->redirect('/doctors');
            return;
        }
        $errors = $this->validateRequiredFields(['name', 'email', 'phone', 'specialization', 'license_number']);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $this->redirect('/doctors/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
            return;
        }
        $this->doctorModel->id             = $id;
        $this->doctorModel->name           = $this->getPostData('name');
        $this->doctorModel->email          = $this->getPostData('email');
        $this->doctorModel->phone          = $this->getPostData('phone');
        $this->doctorModel->specialization = $this->getPostData('specialization');
        $this->doctorModel->license_number = $this->getPostData('license_number');

        // Task 3 — duplicate email guard
        try {
            $updated = $this->doctorModel->update();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                if ($this->isAjax())
                    $this->jsonResponse(['success' => false, 'message' => 'A record with this email already exists.'], 422);
                $_SESSION['flash_error'] = 'A record with this email already exists.';
                $this->redirect('/doctors');
                return;
            }
            throw $e;
        }

        if ($updated) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Doctor updated successfully',
                    'data'    => [
                        'id'             => $this->doctorModel->id,
                        'name'           => $this->doctorModel->name,
                        'email'          => $this->doctorModel->email,
                        'phone'          => $this->doctorModel->phone,
                        'specialization' => $this->doctorModel->specialization,
                        'license_number' => $this->doctorModel->license_number
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Doctor updated successfully';
            $this->redirect('/doctors');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to update doctor'], 500);
            $this->redirect('/doctors/edit?id=' . $id . '&error=Failed to update doctor');
        }
    }

    public function delete()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/doctors');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole('admin');
        $this->validateCsrf(); // Task 2

        $id = $this->getPostData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid doctor ID'], 400);
            $_SESSION['flash_error'] = 'Invalid doctor ID';
            $this->redirect('/doctors');
            return;
        }
        $this->doctorModel->id = $id;
        if ($this->doctorModel->delete()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => true, 'message' => 'Doctor deleted successfully']);
            $_SESSION['flash_success'] = 'Doctor deleted successfully';
            $this->redirect('/doctors');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to delete doctor'], 500);
            $_SESSION['flash_error'] = 'Failed to delete doctor';
            $this->redirect('/doctors');
        }
    }
}