<?php

class AppointmentController extends BaseController
{
    private $appointmentModel;

    public function __construct()
    {
        $database = new Database();
        $db = $database->getConnection();
        $this->appointmentModel = new Appointment($db);
    }

    public function index()
    {
        $this->requireLogin();
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $this->appointmentModel->getAll();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $patientModel = new Patient($db);
        $doctorModel  = new Doctor($db);
        $this->render('appointments/index', [
            'appointments' => $appointments,
            'allPatients'  => $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC),
            'allDoctors'   => $doctorModel->getAll()->fetchAll(PDO::FETCH_ASSOC),
            'title'        => 'Appointments',
            'currentRole'  => $this->currentRole()
        ]);
    }

    public function store()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/appointments');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole(['admin','doctor','nurse']);
        $this->validateCsrf(); // Task 2

        $errors = $this->validateRequiredFields(['patient_id', 'doctor_id', 'appointment_date', 'appointment_time']);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/appointments');
            return;
        }
        $this->appointmentModel->patient_id       = $this->getPostData('patient_id');
        $this->appointmentModel->doctor_id        = $this->getPostData('doctor_id');
        $this->appointmentModel->appointment_date = $this->getPostData('appointment_date');
        $this->appointmentModel->appointment_time = $this->getPostData('appointment_time');
        $this->appointmentModel->status           = $this->getPostData('status', 'pending');
        $this->appointmentModel->reason           = $this->getPostData('reason', '');

        // Task 4 — conflict detection
        if ($this->appointmentModel->hasConflict()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'This doctor is already booked at the selected date and time.'], 409);
            $_SESSION['flash_error'] = 'This doctor is already booked at the selected date and time.';
            $this->redirect('/appointments');
            return;
        }

        if ($this->appointmentModel->create()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Appointment scheduled successfully',
                    'data'    => [
                        'id'               => $this->appointmentModel->id,
                        'patient_id'       => $this->appointmentModel->patient_id,
                        'doctor_id'        => $this->appointmentModel->doctor_id,
                        'appointment_date' => $this->appointmentModel->appointment_date,
                        'appointment_time' => $this->appointmentModel->appointment_time,
                        'status'           => $this->appointmentModel->status,
                        'reason'           => $this->appointmentModel->reason
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Appointment scheduled successfully';
            $this->redirect('/appointments');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to schedule appointment'], 500);
            $_SESSION['flash_error'] = 'Failed to schedule appointment';
            $this->redirect('/appointments');
        }
    }

    public function edit()
    {
        $this->requireLogin();
        $id = $this->getGetData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid appointment ID'], 400);
            $this->redirect('/appointments');
            return;
        }
        if ($this->appointmentModel->getById($id)) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'data'    => [
                        'id'               => $this->appointmentModel->id,
                        'patient_id'       => $this->appointmentModel->patient_id,
                        'doctor_id'        => $this->appointmentModel->doctor_id,
                        'appointment_date' => $this->appointmentModel->appointment_date,
                        'appointment_time' => $this->appointmentModel->appointment_time,
                        'status'           => $this->appointmentModel->status,
                        'reason'           => $this->appointmentModel->reason
                    ]
                ]);
            }
            $database = new Database();
            $db = $database->getConnection();
            $patientModel = new Patient($db);
            $doctorModel  = new Doctor($db);
            $patients = $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
            $doctors  = $doctorModel->getAll()->fetchAll(PDO::FETCH_ASSOC);
            $this->render('appointments/edit', [
                'title'       => 'Edit Appointment',
                'appointment' => $this->appointmentModel,
                'patients'    => $patients,
                'doctors'     => $doctors
            ]);
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Appointment not found'], 404);
            $_SESSION['flash_error'] = 'Appointment not found';
            $this->redirect('/appointments');
        }
    }

    public function update()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/appointments');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole(['admin','doctor','nurse']);
        $this->validateCsrf(); // Task 2

        $id = $this->getPostData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid appointment ID'], 400);
            $_SESSION['flash_error'] = 'Invalid appointment ID';
            $this->redirect('/appointments');
            return;
        }
        $errors = $this->validateRequiredFields(['patient_id', 'doctor_id', 'appointment_date', 'appointment_time']);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $this->redirect('/appointments/edit?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
            return;
        }
        $this->appointmentModel->id               = $id;
        $this->appointmentModel->patient_id       = $this->getPostData('patient_id');
        $this->appointmentModel->doctor_id        = $this->getPostData('doctor_id');
        $this->appointmentModel->appointment_date = $this->getPostData('appointment_date');
        $this->appointmentModel->appointment_time = $this->getPostData('appointment_time');
        $this->appointmentModel->status           = $this->getPostData('status');
        $this->appointmentModel->reason           = $this->getPostData('reason');

        // Task 4 — conflict detection (exclude current record)
        if ($this->appointmentModel->hasConflict((int) $id)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'This doctor is already booked at the selected date and time.'], 409);
            $_SESSION['flash_error'] = 'This doctor is already booked at the selected date and time.';
            $this->redirect('/appointments');
            return;
        }

        if ($this->appointmentModel->update()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Appointment updated successfully',
                    'data'    => [
                        'id'               => $this->appointmentModel->id,
                        'patient_id'       => $this->appointmentModel->patient_id,
                        'doctor_id'        => $this->appointmentModel->doctor_id,
                        'appointment_date' => $this->appointmentModel->appointment_date,
                        'appointment_time' => $this->appointmentModel->appointment_time,
                        'status'           => $this->appointmentModel->status,
                        'reason'           => $this->appointmentModel->reason
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Appointment updated successfully';
            $this->redirect('/appointments');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to update appointment'], 500);
            $this->redirect('/appointments/edit?id=' . $id . '&error=Failed to update appointment');
        }
    }

    public function delete()
    {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/appointments');
            return;
        }
        $this->requireLogin(); // authentication first
        $this->requireRole(['admin','doctor']);
        $this->validateCsrf(); // Task 2

        $id = $this->getPostData('id');
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid appointment ID'], 400);
            $_SESSION['flash_error'] = 'Invalid appointment ID';
            $this->redirect('/appointments');
            return;
        }
        $this->appointmentModel->id = $id;
        if ($this->appointmentModel->delete()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => true, 'message' => 'Appointment deleted successfully']);
            $_SESSION['flash_success'] = 'Appointment deleted successfully';
            $this->redirect('/appointments');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to delete appointment'], 500);
            $_SESSION['flash_error'] = 'Failed to delete appointment';
            $this->redirect('/appointments');
        }
    }
}