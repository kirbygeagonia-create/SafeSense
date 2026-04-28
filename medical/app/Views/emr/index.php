<?php
$allPatients = isset($allPatients) ? $allPatients : [];
$allDoctors  = isset($allDoctors)  ? $allDoctors  : [];
?>
<script>
  const PATIENTS = <?php echo json_encode($allPatients); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors); ?>;
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-medical me-2"></i>Medical Records</h1>
    <?php if (in_array($currentRole ?? '', ['admin','doctor'])): ?>
    <button type="button" class="btn btn-primary" id="addEmrBtn">
        <i class="fas fa-plus me-1"></i>Add Record
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table id="emrTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Visit Date</th>
                <th>Diagnosis</th>
                <th>Blood Pressure</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($records) && !empty($records)): ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['id']); ?></td>
                    <td><?php echo htmlspecialchars($r['patient_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['doctor_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['visit_date']); ?></td>
                    <td><?php echo htmlspecialchars($r['diagnosis']); ?></td>
                    <td><?php echo htmlspecialchars($r['blood_pressure'] ?? '—'); ?></td>
                    <td>
                        <?php if (in_array($currentRole ?? '', ['admin','doctor'])): ?>
                        <button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $r['id']; ?>"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (($currentRole ?? '') === 'admin'): ?>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $r['id']; ?>"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (empty($records)): ?>
<div class="text-center py-5 text-muted">
    <i class="fas fa-file-medical fa-3x mb-3 d-block"></i>
    <p class="fs-5">No medical records yet.</p>
</div>
<?php endif; ?>

<!-- EMR Modal -->
<div class="modal fade" id="emrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-file-medical me-2"></i>Add Medical Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="emrForm" data-action="<?php echo url('/emr/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="e_patient" class="form-label">Patient <span class="text-danger">*</span></label>
              <select class="form-select" id="e_patient" name="patient_id" required>
                <option value="">Select Patient</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="e_doctor" class="form-label">Doctor <span class="text-danger">*</span></label>
              <select class="form-select" id="e_doctor" name="doctor_id" required>
                <option value="">Select Doctor</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="e_visit_date" class="form-label">Visit Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="e_visit_date" name="visit_date" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="e_blood_pressure" class="form-label">Blood Pressure</label>
              <input type="text" class="form-control" id="e_blood_pressure" name="blood_pressure" placeholder="e.g. 120/80">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="e_temperature" class="form-label">Temperature (°C)</label>
              <input type="number" step="0.1" class="form-control" id="e_temperature" name="temperature">
            </div>
            <div class="col-md-4 mb-3">
              <label for="e_heart_rate" class="form-label">Heart Rate (bpm)</label>
              <input type="number" class="form-control" id="e_heart_rate" name="heart_rate">
            </div>
            <div class="col-md-4 mb-3">
              <label for="e_weight" class="form-label">Weight (kg)</label>
              <input type="number" step="0.01" class="form-control" id="e_weight" name="weight">
            </div>
          </div>
          <div class="mb-3">
            <label for="e_chief_complaint" class="form-label">Chief Complaint <span class="text-danger">*</span></label>
            <textarea class="form-control" id="e_chief_complaint" name="chief_complaint" rows="2" required></textarea>
          </div>
          <div class="mb-3">
            <label for="e_diagnosis" class="form-label">Diagnosis <span class="text-danger">*</span></label>
            <textarea class="form-control" id="e_diagnosis" name="diagnosis" rows="2" required></textarea>
          </div>
          <div class="mb-3">
            <label for="e_prescription" class="form-label">Prescription</label>
            <textarea class="form-control" id="e_prescription" name="prescription" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="e_notes" class="form-label">Notes</label>
            <textarea class="form-control" id="e_notes" name="notes" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Record</button>
        </div>
      </form>
    </div>
  </div>
</div>
