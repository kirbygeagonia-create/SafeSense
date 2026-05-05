<?php

class AuditController extends BaseController
{
    public function index()
    {
        $this->requireLogin();
        $this->requireRole('admin');

        $database = new Database();
        $db       = $database->getConnection();

        $logs = [];
        try {
            $stmt = $db->query(
                "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 500"
            );
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        $this->render('audit/index', [
            'title'   => 'Activity Log',
            'navPage' => 'audit',
            'logs'    => $logs,
        ]);
    }
}
