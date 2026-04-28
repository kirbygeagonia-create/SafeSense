<?php

class EmrController extends BaseController {
    private $emrModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emrModel = new Emr($db);
    }

    public function index() {
        $this->requireLogin();
        $this->requireRole(['admin','doctor','nurse']);

        $database = new Database();
        $db = $database->getConnection();

        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        if ($patientId > 0) {
            $stmt = $this->emrModel->getByPatient($patientId);
        } else {
            $stmt = $this->emrModel->getAll();
        }
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $patientModel = new Patient($db);
        $doctorModel  = new Doctor($db);
        $allPatients = $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
        $allDoctors  = $doctorModel->getAll()->fetchAll(PDO::FETCH_ASSOC);

        $this->render('emr/index', [
            'records'     => $records,
            'allPatients' => $allPatients,
            'allDoctors'  => $allDoctors,
            'currentRole' => $this->currentRole(),
            'title'       => 'Medical Records'
        ]);
    }

    public function store() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/emr');
            return;
        }
        $this->requireLogin();
        $this->requireRole(['admin','doctor']);
        $this->validateCsrf();

        $requiredFields = ['patient_id','doctor_id','visit_date','chief_complaint','diagnosis'];
        $errors = $this->validateRequiredFields($requiredFields);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/emr');
            return;
        }

        $this->emrModel->patient_id      = (int)$this->getPostData('patient_id');
        $this->emrModel->doctor_id       = (int)$this->getPostData('doctor_id');
        $this->emrModel->visit_date      = $this->getPostData('visit_date');
        $this->emrModel->chief_complaint = $this->getPostData('chief_complaint');
        $this->emrModel->diagnosis       = $this->getPostData('diagnosis');
        $this->emrModel->prescription    = $this->getPostData('prescription', '');
        $this->emrModel->notes           = $this->getPostData('notes', '');
        $this->emrModel->blood_pressure  = $this->getPostData('blood_pressure', '');
        $this->emrModel->temperature     = $this->getPostData('temperature') !== '' ? (float)$this->getPostData('temperature') : null;
        $this->emrModel->heart_rate      = $this->getPostData('heart_rate') !== '' ? (int)$this->getPostData('heart_rate') : null;
        $this->emrModel->weight          = $this->getPostData('weight') !== '' ? (float)$this->getPostData('weight') : null;

        if ($this->emrModel->create()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Medical record created successfully',
                    'data'    => [
                        'id'              => $this->emrModel->id,
                        'patient_id'      => $this->emrModel->patient_id,
                        'doctor_id'       => $this->emrModel->doctor_id,
                        'visit_date'      => $this->emrModel->visit_date,
                        'chief_complaint' => $this->emrModel->chief_complaint,
                        'diagnosis'       => $this->emrModel->diagnosis,
                        'prescription'    => $this->emrModel->prescription,
                        'notes'           => $this->emrModel->notes,
                        'blood_pressure'  => $this->emrModel->blood_pressure,
                        'temperature'     => $this->emrModel->temperature,
                        'heart_rate'      => $this->emrModel->heart_rate,
                        'weight'          => $this->emrModel->weight
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Medical record created successfully';
            $this->redirect('/emr');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to create medical record'], 500);
            $_SESSION['flash_error'] = 'Failed to create medical record';
            $this->redirect('/emr');
        }
    }

    public function edit() {
        $this->requireLogin();
        $this->requireRole(['admin','doctor','nurse']);

        $id = (int)($this->getGetData('id') ?? 0);
        if (!$id || !$this->emrModel->getById($id)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
            $_SESSION['flash_error'] = 'Record not found';
            $this->redirect('/emr');
            return;
        }

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'              => $this->emrModel->id,
                    'patient_id'      => $this->emrModel->patient_id,
                    'doctor_id'       => $this->emrModel->doctor_id,
                    'visit_date'      => $this->emrModel->visit_date,
                    'chief_complaint' => $this->emrModel->chief_complaint,
                    'diagnosis'       => $this->emrModel->diagnosis,
                    'prescription'    => $this->emrModel->prescription,
                    'notes'           => $this->emrModel->notes,
                    'blood_pressure'  => $this->emrModel->blood_pressure,
                    'temperature'     => $this->emrModel->temperature,
                    'heart_rate'      => $this->emrModel->heart_rate,
                    'weight'          => $this->emrModel->weight
                ]
            ]);
        }
        $this->redirect('/emr');
    }

    public function update() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/emr');
            return;
        }
        $this->requireLogin();
        $this->requireRole(['admin','doctor']);
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid record ID'], 400);
            $_SESSION['flash_error'] = 'Invalid record ID';
            $this->redirect('/emr');
            return;
        }

        $requiredFields = ['patient_id','doctor_id','visit_date','chief_complaint','diagnosis'];
        $errors = $this->validateRequiredFields($requiredFields);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/emr');
            return;
        }

        $this->emrModel->id              = $id;
        $this->emrModel->patient_id      = (int)$this->getPostData('patient_id');
        $this->emrModel->doctor_id       = (int)$this->getPostData('doctor_id');
        $this->emrModel->visit_date      = $this->getPostData('visit_date');
        $this->emrModel->chief_complaint = $this->getPostData('chief_complaint');
        $this->emrModel->diagnosis       = $this->getPostData('diagnosis');
        $this->emrModel->prescription    = $this->getPostData('prescription', '');
        $this->emrModel->notes           = $this->getPostData('notes', '');
        $this->emrModel->blood_pressure  = $this->getPostData('blood_pressure', '');
        $this->emrModel->temperature     = $this->getPostData('temperature') !== '' ? (float)$this->getPostData('temperature') : null;
        $this->emrModel->heart_rate      = $this->getPostData('heart_rate') !== '' ? (int)$this->getPostData('heart_rate') : null;
        $this->emrModel->weight          = $this->getPostData('weight') !== '' ? (float)$this->getPostData('weight') : null;

        if ($this->emrModel->update()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Medical record updated successfully',
                    'data'    => [
                        'id'              => $id,
                        'patient_id'      => $this->emrModel->patient_id,
                        'doctor_id'       => $this->emrModel->doctor_id,
                        'visit_date'      => $this->emrModel->visit_date,
                        'chief_complaint' => $this->emrModel->chief_complaint,
                        'diagnosis'       => $this->emrModel->diagnosis,
                        'prescription'    => $this->emrModel->prescription,
                        'notes'           => $this->emrModel->notes,
                        'blood_pressure'  => $this->emrModel->blood_pressure,
                        'temperature'     => $this->emrModel->temperature,
                        'heart_rate'      => $this->emrModel->heart_rate,
                        'weight'          => $this->emrModel->weight
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Medical record updated successfully';
            $this->redirect('/emr');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to update medical record'], 500);
            $_SESSION['flash_error'] = 'Failed to update medical record';
            $this->redirect('/emr');
        }
    }

    public function delete() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/emr');
            return;
        }
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid record ID'], 400);
            $_SESSION['flash_error'] = 'Invalid record ID';
            $this->redirect('/emr');
            return;
        }

        $this->emrModel->id = $id;
        if ($this->emrModel->delete()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => true, 'message' => 'Medical record deleted successfully']);
            $_SESSION['flash_success'] = 'Medical record deleted successfully';
            $this->redirect('/emr');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to delete medical record'], 500);
            $_SESSION['flash_error'] = 'Failed to delete medical record';
            $this->redirect('/emr');
        }
    }
}
