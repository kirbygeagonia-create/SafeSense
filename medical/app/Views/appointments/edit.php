<?php
// Fallback: appointment edit is handled via AJAX modal on the appointments page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Appointment</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/appointments/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($appointment->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select" required>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($appointment->patient_id ?? '') == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" class="form-select" required>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo ($appointment->doctor_id ?? '') == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="appointment_date" class="form-control" required value="<?php echo htmlspecialchars($appointment->appointment_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Time</label>
                    <input type="time" name="appointment_time" class="form-control" required value="<?php echo htmlspecialchars($appointment->appointment_time ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($appointment->status ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control"><?php echo htmlspecialchars($appointment->reason ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <a href="<?php echo url('/appointments'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Appointment</button>
            </form>
        </div>
    </div>
</div>
