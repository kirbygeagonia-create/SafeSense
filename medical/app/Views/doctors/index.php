<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-md me-2"></i>Doctors</h1>
    <button type="button" class="btn btn-primary" id="addDoctorBtn">
        <i class="fas fa-plus me-1"></i>Add Doctor
    </button>
</div>

<div class="table-responsive">
    <table id="doctorsTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Specialization</th>
                <th>License Number</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($doctors) && !empty($doctors)): ?>
                <?php foreach ($doctors as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['id']); ?></td>
                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                    <td><?php echo htmlspecialchars($d['email']); ?></td>
                    <td><?php echo htmlspecialchars($d['phone']); ?></td>
                    <td><?php echo htmlspecialchars($d['specialization']); ?></td>
                    <td><?php echo htmlspecialchars($d['license_number']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $d['id']; ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $d['id']; ?>"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Doctor Modal -->
<div class="modal fade" id="doctorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-user-md me-2"></i>Add Doctor</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="doctorForm" data-action="<?php echo url('/doctors/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="d_name" class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="d_name" name="name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="d_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="d_email" name="email" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="d_phone" class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" id="d_phone" name="phone" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="d_spec" class="form-label">Specialization <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="d_spec" name="specialization" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="d_license" class="form-label">License Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="d_license" name="license_number" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Doctor</button>
        </div>
      </form>
    </div>
  </div>
</div>