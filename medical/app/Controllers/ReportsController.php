<?php

class ReportsController extends BaseController
{
    public function index()
    {
        $this->requireLogin();
        $this->requireRole(['admin', 'doctor', 'nurse']);

        $database = new Database();
        $db       = $database->getConnection();

        // 1. Patient registrations by month (last 12 months)
        $patientsByMonth = [];
        try {
            $stmt = $db->query(
                "SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
                        DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
                        COUNT(*) AS total
                 FROM patients
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month, sort_key
                 ORDER BY sort_key ASC"
            );
            $patientsByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 2. Appointments by status
        $appointmentsByStatus = [];
        try {
            $stmt = $db->query(
                "SELECT status, COUNT(*) AS total
                 FROM appointments
                 GROUP BY status
                 ORDER BY total DESC"
            );
            $appointmentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 3. Revenue by month (last 6 months)
        $revenueByMonth = [];
        try {
            $stmt = $db->query(
                "SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
                        DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
                        SUM(total_amount)                AS revenue,
                        SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) AS collected
                 FROM billing
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY month, sort_key
                 ORDER BY sort_key ASC"
            );
            $revenueByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 4. Alerts by level
        $alertsByLevel = [];
        try {
            $stmt = $db->query(
                "SELECT alert_level, COUNT(*) AS total
                 FROM safesense_alerts
                 GROUP BY alert_level
                 ORDER BY total DESC"
            );
            $alertsByLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 5. Summary totals
        $totals = [];
        try {
            $totals['patients']     = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
            $totals['doctors']      = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
            $totals['appointments'] = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
            $totals['invoices']     = $db->query("SELECT COUNT(*) FROM billing")->fetchColumn();
            $totals['revenue']      = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing")->fetchColumn();
            $totals['collected']    = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid'")->fetchColumn();
            $totals['alerts']       = $db->query("SELECT COUNT(*) FROM safesense_alerts")->fetchColumn();
        } catch (Exception $e) {}

        $this->render('reports/index', [
            'title'                => 'Reports & Analytics',
            'navPage'              => 'reports',
            'patientsByMonth'      => $patientsByMonth,
            'appointmentsByStatus' => $appointmentsByStatus,
            'revenueByMonth'       => $revenueByMonth,
            'alertsByLevel'        => $alertsByLevel,
            'totals'               => $totals,
        ]);
    }
}
