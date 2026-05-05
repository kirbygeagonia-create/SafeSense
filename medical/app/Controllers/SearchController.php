<?php

class SearchController extends BaseController
{
    public function index()
    {
        $this->requireLogin();

        $q   = trim($_GET['q'] ?? '');
        $tab = $_GET['tab'] ?? 'all';

        $patientsResults = [];
        $doctorsResults  = [];
        $emrResults      = [];
        $billingResults  = [];

        if ($q !== '') {
            $database = new Database();
            $db       = $database->getConnection();

            // Patients
            if (in_array($tab, ['all','patients'])) {
                $stmt = $db->prepare(
                    "SELECT id, name, email, phone, address FROM patients
                     WHERE name LIKE :q OR email LIKE :q OR phone LIKE :q
                     ORDER BY name ASC LIMIT 50"
                );
                $stmt->execute([':q' => '%' . $q . '%']);
                $patientsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Doctors
            if (in_array($tab, ['all','doctors'])) {
                $stmt = $db->prepare(
                    "SELECT id, name, email, specialization, phone FROM doctors
                     WHERE name LIKE :q OR email LIKE :q OR specialization LIKE :q
                     ORDER BY name ASC LIMIT 50"
                );
                $stmt->execute([':q' => '%' . $q . '%']);
                $doctorsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // EMR (diagnosis/notes)
            if (in_array($tab, ['all','emr'])) {
                $stmt = $db->prepare(
                    "SELECT e.id, e.diagnosis, e.treatment, e.created_at, p.name as patient_name
                     FROM emr e
                     JOIN patients p ON e.patient_id = p.id
                     WHERE e.diagnosis LIKE :q OR e.treatment LIKE :q OR e.notes LIKE :q
                     ORDER BY e.created_at DESC LIMIT 50"
                );
                $stmt->execute([':q' => '%' . $q . '%']);
                $emrResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Billing
            if (in_array($tab, ['all','billing'])) {
                $stmt = $db->prepare(
                    "SELECT b.id, b.total_amount, b.payment_status, b.created_at, p.name as patient_name
                     FROM billing b
                     JOIN patients p ON b.patient_id = p.id
                     WHERE b.id LIKE :q OR b.notes LIKE :q OR p.name LIKE :q
                     ORDER BY b.created_at DESC LIMIT 50"
                );
                $stmt->execute([':q' => '%' . $q . '%']);
                $billingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $this->render('search/index', [
            'title'           => 'Global Search',
            'navPage'         => 'search',
            'q'               => $q,
            'tab'             => $tab,
            'patientsResults' => $patientsResults,
            'doctorsResults'  => $doctorsResults,
            'emrResults'      => $emrResults,
            'billingResults'  => $billingResults,
        ]);
    }
}
