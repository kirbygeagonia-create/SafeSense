<?php

class BillingController extends BaseController {
    private $billingModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->billingModel = new Billing($db);
    }

    public function index() {
        $this->requireLogin();
        $this->requireRole(['admin','staff']);

        $database = new Database();
        $db = $database->getConnection();
        $stmt = $this->billingModel->getAll();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $patientModel = new Patient($db);
        $allPatients = $patientModel->getAll()->fetchAll(PDO::FETCH_ASSOC);

        $this->render('billing/index', [
            'records'     => $records,
            'allPatients' => $allPatients,
            'currentRole' => $this->currentRole(),
            'title'       => 'Billing'
        ]);
    }

    public function printInvoice()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid invoice ID.';
            $this->redirect('/billing');
            return;
        }

        $found = $this->billingModel->getById($id);
        if (!$found) {
            $_SESSION['flash_error'] = 'Invoice not found.';
            $this->redirect('/billing');
            return;
        }

        // Render the print view — no main layout, standalone page
        $billing = $this->billingModel;
        include APP_PATH . '/Views/billing/print.php';
        exit;
    }

    public function store() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/billing');
            return;
        }
        $this->requireLogin();
        $this->requireRole(['admin','staff']);
        $this->validateCsrf();

        $requiredFields = ['patient_id','service_description','amount'];
        $errors = $this->validateRequiredFields($requiredFields);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/billing');
            return;
        }

        $this->billingModel->patient_id         = (int)$this->getPostData('patient_id');
        $this->billingModel->appointment_id      = $this->getPostData('appointment_id') !== '' ? (int)$this->getPostData('appointment_id') : null;
        $this->billingModel->service_description = $this->getPostData('service_description');
        $this->billingModel->amount              = (float)$this->getPostData('amount');
        $this->billingModel->discount            = (float)($this->getPostData('discount') ?? 0);
        $this->billingModel->tax                 = (float)($this->getPostData('tax') ?? 0);
        $this->billingModel->payment_status      = $this->getPostData('payment_status') ?? 'unpaid';
        $this->billingModel->payment_method      = $this->getPostData('payment_method') ?: null;
        $this->billingModel->payment_date        = $this->getPostData('payment_date') ?: null;
        $this->billingModel->notes               = $this->getPostData('notes', '');
        $this->billingModel->created_by          = $_SESSION['user']['id'] ?? null;

        if ($this->billingModel->create()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Invoice created successfully',
                    'data'    => [
                        'id'                => $this->billingModel->id,
                        'patient_id'        => $this->billingModel->patient_id,
                        'invoice_number'    => $this->billingModel->invoice_number,
                        'service_description'=> $this->billingModel->service_description,
                        'amount'            => $this->billingModel->amount,
                        'discount'          => $this->billingModel->discount,
                        'tax'               => $this->billingModel->tax,
                        'total_amount'      => $this->billingModel->total_amount,
                        'payment_status'    => $this->billingModel->payment_status,
                        'payment_method'    => $this->billingModel->payment_method,
                        'payment_date'      => $this->billingModel->payment_date,
                        'notes'             => $this->billingModel->notes
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Invoice created successfully';
            $this->redirect('/billing');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to create invoice'], 500);
            $_SESSION['flash_error'] = 'Failed to create invoice';
            $this->redirect('/billing');
        }
    }

    public function edit() {
        $this->requireLogin();
        $this->requireRole(['admin','staff']);

        $id = (int)($this->getGetData('id') ?? 0);
        if (!$id || !$this->billingModel->getById($id)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invoice not found'], 404);
            $_SESSION['flash_error'] = 'Invoice not found';
            $this->redirect('/billing');
            return;
        }

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => [
                    'id'                  => $this->billingModel->id,
                    'patient_id'          => $this->billingModel->patient_id,
                    'appointment_id'      => $this->billingModel->appointment_id,
                    'invoice_number'      => $this->billingModel->invoice_number,
                    'service_description' => $this->billingModel->service_description,
                    'amount'              => $this->billingModel->amount,
                    'discount'            => $this->billingModel->discount,
                    'tax'                 => $this->billingModel->tax,
                    'total_amount'        => $this->billingModel->total_amount,
                    'payment_status'      => $this->billingModel->payment_status,
                    'payment_method'      => $this->billingModel->payment_method,
                    'payment_date'        => $this->billingModel->payment_date,
                    'notes'               => $this->billingModel->notes
                ]
            ]);
        }

        $this->render('billing/edit', [
            'title'  => 'Edit Invoice',
            'record' => $this->billingModel,
        ]);
    }

    public function update() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/billing');
            return;
        }
        $this->requireLogin();
        $this->requireRole(['admin','staff']);
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid invoice ID'], 400);
            $_SESSION['flash_error'] = 'Invalid invoice ID';
            $this->redirect('/billing');
            return;
        }

        $requiredFields = ['patient_id','service_description','amount'];
        $errors = $this->validateRequiredFields($requiredFields);
        if (!empty($errors)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => implode(', ', $errors)], 422);
            $_SESSION['flash_error'] = implode(', ', $errors);
            $this->redirect('/billing');
            return;
        }

        $this->billingModel->id                  = $id;
        $this->billingModel->patient_id          = (int)$this->getPostData('patient_id');
        $this->billingModel->appointment_id      = $this->getPostData('appointment_id') !== '' ? (int)$this->getPostData('appointment_id') : null;
        $this->billingModel->service_description = $this->getPostData('service_description');
        $this->billingModel->amount              = (float)$this->getPostData('amount');
        $this->billingModel->discount            = (float)($this->getPostData('discount') ?? 0);
        $this->billingModel->tax                 = (float)($this->getPostData('tax') ?? 0);
        $this->billingModel->payment_status      = $this->getPostData('payment_status') ?? 'unpaid';
        $this->billingModel->payment_method      = $this->getPostData('payment_method') ?: null;
        $this->billingModel->payment_date        = $this->getPostData('payment_date') ?: null;
        $this->billingModel->notes               = $this->getPostData('notes', '');

        if ($this->billingModel->update()) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Invoice updated successfully',
                    'data'    => [
                        'id'                  => $id,
                        'patient_id'          => $this->billingModel->patient_id,
                        'invoice_number'      => $this->billingModel->invoice_number,
                        'service_description' => $this->billingModel->service_description,
                        'amount'              => $this->billingModel->amount,
                        'discount'            => $this->billingModel->discount,
                        'tax'                 => $this->billingModel->tax,
                        'total_amount'        => $this->billingModel->total_amount,
                        'payment_status'      => $this->billingModel->payment_status,
                        'payment_method'      => $this->billingModel->payment_method,
                        'payment_date'        => $this->billingModel->payment_date,
                        'notes'               => $this->billingModel->notes
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'Invoice updated successfully';
            $this->redirect('/billing');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to update invoice'], 500);
            $_SESSION['flash_error'] = 'Failed to update invoice';
            $this->redirect('/billing');
        }
    }

    public function delete() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/billing');
            return;
        }
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid invoice ID'], 400);
            $_SESSION['flash_error'] = 'Invalid invoice ID';
            $this->redirect('/billing');
            return;
        }

        $this->billingModel->id = $id;
        if ($this->billingModel->delete()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => true, 'message' => 'Invoice deleted successfully']);
            $_SESSION['flash_success'] = 'Invoice deleted successfully';
            $this->redirect('/billing');
        } else {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Failed to delete invoice'], 500);
            $_SESSION['flash_error'] = 'Failed to delete invoice';
            $this->redirect('/billing');
        }
    }
}
