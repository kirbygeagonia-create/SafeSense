<?php
// Fallback: EMR edit is handled via AJAX modal on the medical records page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Medical Record</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/emr/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient ID</label>
                        <input type="number" name="patient_id" class="form-control" required value="<?php echo htmlspecialchars($record->patient_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Doctor ID</label>
                        <input type="number" name="doctor_id" class="form-control" required value="<?php echo htmlspecialchars($record->doctor_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Visit Date</label>
                    <input type="date" name="visit_date" class="form-control" required value="<?php echo htmlspecialchars($record->visit_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Chief Complaint</label>
                    <textarea name="chief_complaint" class="form-control" required><?php echo htmlspecialchars($record->chief_complaint ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" required><?php echo htmlspecialchars($record->diagnosis ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prescription</label>
                    <textarea name="prescription" class="form-control"><?php echo htmlspecialchars($record->prescription ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"><?php echo htmlspecialchars($record->notes ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80" value="<?php echo htmlspecialchars($record->blood_pressure ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" class="form-control" value="<?php echo htmlspecialchars($record->temperature ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Heart Rate (bpm)</label>
                        <input type="number" name="heart_rate" class="form-control" value="<?php echo htmlspecialchars($record->heart_rate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.01" name="weight" class="form-control" value="<?php echo htmlspecialchars($record->weight ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <a href="<?php echo url('/emr'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </form>
        </div>
    </div>
</div>
