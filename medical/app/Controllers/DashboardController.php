<?php

/**
 * DashboardController
 * Serves the /api/dashboard/stats endpoint for Chart.js analytics.
 * Kept separate from AuthController per single-responsibility principle.
 */
class DashboardController extends BaseController {

    public function stats() {
        $database = new Database();
        $db = $database->getConnection();

        $alertData       = [];
        $appointmentData = [];

        try {
            $alertModelFile = APP_PATH . '/Models/Alert.php';
            if (file_exists($alertModelFile)) {
                require_once $alertModelFile;
                $alertModel = new Alert($db);
                $alertData  = $alertModel->getAlertsByDay(30);
            }
        } catch (Exception $e) {}

        try {
            $appointmentModel = new Appointment($db);
            $appointmentData  = $appointmentModel->getAppointmentsByWeek(8);
        } catch (Exception $e) {}

        $this->jsonResponse([
            'success'      => true,
            'alerts'       => $alertData,
            'appointments' => $appointmentData
        ]);
    }
}
