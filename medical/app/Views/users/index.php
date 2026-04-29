<!-- Task 1 — Page header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-users-cog me-2"></i>Users</h1>
        <div class="page-subtitle">System user management and permissions</div>
    </div>
    <button type="button" class="btn btn-primary" id="addUserBtn">
        <i class="fas fa-plus me-1"></i>Add User
    </button>
</div>

<div class="table-responsive mb-3">
    <table id="usersTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($users) && !empty($users)): ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['id']); ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo ucfirst(htmlspecialchars($u['role'])); ?></td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="<?php echo $u['id']; ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $u['id']; ?>"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-users-cog me-2"></i>Add User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="userForm" data-action="<?php echo url('/users/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="u_name" class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="u_name" name="name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="u_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="u_email" name="email" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="u_role" class="form-label">Role <span class="text-danger">*</span></label>
              <select class="form-select" id="u_role" name="role" required>
                <option value="admin">Admin</option>
                <option value="doctor">Doctor</option>
                <option value="nurse">Nurse</option>
                <option value="staff" selected>Staff</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="u_password" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="u_password" name="password">
              <small class="text-muted" id="pwHint">Required for new users. Leave blank to keep existing password when editing.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>
