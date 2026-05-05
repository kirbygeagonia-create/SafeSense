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

    /**
     * GET /api/appointments/events
     * Returns all appointments as FullCalendar-compatible JSON events.
     */
    public function calendarEvents()
    {
        $this->requireLogin();
        $database = new Database();
        $db       = $database->getConnection();

        $query = "SELECT a.id, a.appointment_date, a.appointment_time,
                         a.status, a.reason,
                         p.name AS patient_name,
                         d.name AS doctor_name
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN doctors  d ON a.doctor_id  = d.id
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusColors = [
            'pending'   => '#f59e0b',
            'confirmed' => '#1d4ed8',
            'completed' => '#15803d',
            'cancelled' => '#94a3b8',
        ];

        $events = [];
        foreach ($rows as $r) {
            $start = $r['appointment_date'] . 'T' . $r['appointment_time'];
            $events[] = [
                'id'    => $r['id'],
                'title' => $r['patient_name'] . ' → Dr. ' . $r['doctor_name'],
                'start' => $start,
                'color' => $statusColors[$r['status']] ?? '#64748b',
                'extendedProps' => [
                    'status'  => $r['status'],
                    'reason'  => $r['reason'],
                    'patient' => $r['patient_name'],
                    'doctor'  => $r['doctor_name'],
                ],
            ];
        }

        $this->jsonResponse(['success' => true, 'events' => $events]);
    }

    /**
     * GET /api/appointments/today
     * Returns today's appointments count and list for navbar badge.
     */
    public function upcomingToday()
    {
        $this->requireLogin();
        $database = new Database();
        $db       = $database->getConnection();

        $stmt = $db->prepare(
            "SELECT a.id, a.appointment_time, a.status, a.reason,
                    p.name AS patient_name, d.name AS doctor_name
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             JOIN doctors  d ON a.doctor_id  = d.id
             WHERE a.appointment_date = CURDATE()
               AND a.status IN ('pending','confirmed')
             ORDER BY a.appointment_time ASC
             LIMIT 20"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'count' => count($rows), 'appointments' => $rows]);
            return;
        }
        $this->jsonResponse(['success' => false, 'message' => 'AJAX only'], 400);
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
            $this->logAction('create', 'appointment', (int)$this->appointmentModel->id, 'Appointment scheduled');
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
            $this->logAction('update', 'appointment', (int)$id, 'Appointment updated');
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
            $this->logAction('delete', 'appointment', (int)$id, 'Appointment deleted ID: ' . $id);
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