<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-injured me-2"></i>Patients</h1>
    <?php if (in_array($currentRole ?? '', ['admin','doctor','nurse'])): ?>
    <button type="button" class="btn btn-primary" id="addPatientBtn">
        <i class="fas fa-plus me-1"></i>Add Patient
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table id="patientsTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Date of Birth</th>
                <th>Gender</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($patients) && !empty($patients)): ?>
                <?php foreach ($patients as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['id']); ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                    <td><?php echo htmlspecialchars($p['phone']); ?></td>
                    <td><?php echo htmlspecialchars($p['date_of_birth']); ?></td>
                    <td><?php echo htmlspecialchars($p['gender']); ?></td>
                    <td>
                        <?php if (in_array($currentRole ?? '', ['admin','doctor','nurse'])): ?>
                        <button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $p['id']; ?>"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (($currentRole ?? '') === 'admin'): ?>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $p['id']; ?>"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Patient Modal -->
<div class="modal fade" id="patientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-user-injured me-2"></i>Add Patient</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="patientForm" data-action="<?php echo url('/patients/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="p_name" class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="p_name" name="name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="p_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="p_email" name="email" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="p_phone" class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" id="p_phone" name="phone" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="p_dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="p_dob" name="date_of_birth" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="p_gender" class="form-label">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="p_gender" name="gender" required>
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="p_address" class="form-label">Address</label>
              <textarea class="form-control" id="p_address" name="address" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Patient</button>
        </div>
      </form>
    </div>
  </div>
</div>