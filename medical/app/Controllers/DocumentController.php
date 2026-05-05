<?php

class DocumentController extends BaseController
{
    public function index()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);

        $database = new Database();
        $db = $database->getConnection();

        $patientId = (int)($_GET['patient_id'] ?? 0);

        // Fetch documents for a specific patient if provided
        $documents = [];
        $patient = null;

        if ($patientId > 0) {
            $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($patient) {
                $stmt = $db->prepare("SELECT * FROM patient_documents WHERE patient_id = ? ORDER BY created_at DESC");
                $stmt->execute([$patientId]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $this->render('documents/index', [
            'title'     => 'Patient Documents',
            'navPage'   => 'documents',
            'patient'   => $patient,
            'documents' => $documents,
            'patientId' => $patientId,
        ]);
    }

    public function upload()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);
        $this->validateCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/patients/documents');
            return;
        }

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($patientId <= 0) {
            $_SESSION['flash_error'] = 'Invalid patient ID';
            $this->redirect('/patients/documents');
            return;
        }

        // Check if file was uploaded
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'No file uploaded or upload error occurred';
            $this->redirect('/patients/documents?patient_id=' . $patientId);
            return;
        }

        $file = $_FILES['document'];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            $_SESSION['flash_error'] = 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX';
            $this->redirect('/patients/documents?patient_id=' . $patientId);
            return;
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            $_SESSION['flash_error'] = 'File size exceeds 10MB limit';
            $this->redirect('/patients/documents?patient_id=' . $patientId);
            return;
        }

        // Create upload directory if not exists (inside public folder for direct access)
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $originalName = basename($file['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = uniqid('doc_') . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $uniqueName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $_SESSION['flash_error'] = 'Failed to save uploaded file';
            $this->redirect('/patients/documents?patient_id=' . $patientId);
            return;
        }

        // Save to database
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare(
            "INSERT INTO patient_documents (patient_id, file_name, file_path, file_type, file_size, uploaded_by, notes)
             VALUES (:patient_id, :file_name, :file_path, :file_type, :file_size, :uploaded_by, :notes)"
        );

        $stmt->execute([
            ':patient_id' => $patientId,
            ':file_name'  => $originalName,
            ':file_path'  => $uniqueName,
            ':file_type'  => $file['type'],
            ':file_size'  => $file['size'],
            ':uploaded_by'=> $_SESSION['user']['email'] ?? 'unknown',
            ':notes'      => $notes,
        ]);

        $this->logAction('create', 'document', (int)$db->lastInsertId(), 'Document uploaded for patient ID: ' . $patientId);

        $_SESSION['flash_success'] = 'Document uploaded successfully';
        $this->redirect('/patients/documents?patient_id=' . $patientId);
    }

    public function delete()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);
        $this->validateCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/patients/documents');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Invalid document ID';
            $this->redirect('/patients/documents' . ($patientId ? '?patient_id=' . $patientId : ''));
            return;
        }

        $database = new Database();
        $db = $database->getConnection();

        // Get document info
        $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            $_SESSION['flash_error'] = 'Document not found';
            $this->redirect('/patients/documents' . ($patientId ? '?patient_id=' . $patientId : ''));
            return;
        }

        // Delete file from filesystem
        $filePath = dirname(__DIR__, 3) . '/public/uploads/documents/' . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $stmt = $db->prepare("DELETE FROM patient_documents WHERE id = ?");
        $stmt->execute([$id]);

        $this->logAction('delete', 'document', $id, 'Document deleted ID: ' . $id);

        $_SESSION['flash_success'] = 'Document deleted successfully';
        $this->redirect('/patients/documents' . ($patientId ? '?patient_id=' . $patientId : ''));
    }

    public function download()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse', 'staff']);

        $database = new Database();
        $db       = $database->getConnection();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid document ID.';
            $this->redirect('/patients/documents');
            return;
        }

        $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            $_SESSION['flash_error'] = 'Document not found.';
            $this->redirect('/patients/documents');
            return;
        }

        // File is stored relative to public/uploads/documents/
        $filePath = dirname(__DIR__, 3) . '/public/uploads/documents/' . basename($doc['file_path']);

        if (!file_exists($filePath)) {
            $_SESSION['flash_error'] = 'File no longer exists on disk.';
            $this->redirect('/patients/documents?patient_id=' . $doc['patient_id']);
            return;
        }

        $this->logAction('read', 'document', $id, 'Document downloaded: ' . $doc['file_name']);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['file_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Pragma: no-cache');
        header('Cache-Control: must-revalidate');
        readfile($filePath);
        exit;
    }
}
