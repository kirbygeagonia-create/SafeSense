<?php
// Embed patient and doctor data for JS dropdown population (Phase 3 requirement)
$allPatients = isset($allPatients) ? $allPatients : [];
$allDoctors  = isset($allDoctors)  ? $allDoctors  : [];
?>
<script>
  const PATIENTS = <?php echo json_encode($allPatients); ?>;
  const DOCTORS  = <?php echo json_encode($allDoctors); ?>;
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-calendar-check me-2"></i>Appointments</h1>
    <?php if (in_array($currentRole ?? '', ['admin','doctor','nurse'])): ?>
    <button type="button" class="btn btn-primary" id="addAppointmentBtn">
        <i class="fas fa-plus me-1"></i>Schedule Appointment
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table id="appointmentsTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Reason</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($appointments) && !empty($appointments)): ?>
                <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['id']); ?></td>
                    <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['appointment_date']); ?></td>
                    <td><?php echo htmlspecialchars($a['appointment_time']); ?></td>
                    <td>
                        <span class="badge bg-<?php
                            echo $a['status'] === 'pending' ? 'warning' :
                                 ($a['status'] === 'confirmed' ? 'success' :
                                  ($a['status'] === 'completed' ? 'secondary' : 'danger'));
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($a['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($a['reason']); ?></td>
                    <td>
                        <?php if (in_array($currentRole ?? '', ['admin','doctor','nurse'])): ?>
                        <button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $a['id']; ?>"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (in_array($currentRole ?? '', ['admin','doctor'])): ?>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $a['id']; ?>"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Schedule Appointment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="appointmentForm" data-action="<?php echo url('/appointments/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="a_patient" class="form-label">Patient <span class="text-danger">*</span></label>
              <select class="form-select" id="a_patient" name="patient_id" required>
                <option value="">Select Patient</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="a_doctor" class="form-label">Doctor <span class="text-danger">*</span></label>
              <select class="form-select" id="a_doctor" name="doctor_id" required>
                <option value="">Select Doctor</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="a_date" class="form-label">Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="a_date" name="appointment_date" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="a_time" class="form-label">Time <span class="text-danger">*</span></label>
              <input type="time" class="form-control" id="a_time" name="appointment_time" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="a_status" class="form-label">Status</label>
              <select class="form-select" id="a_status" name="status">
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="a_reason" class="form-label">Reason</label>
              <textarea class="form-control" id="a_reason" name="reason" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Appointment</button>
        </div>
      </form>
    </div>
  </div>
</div>