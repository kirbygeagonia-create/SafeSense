<div class="page-header">
  <div>
    <h1><i class="fas fa-clipboard-list"></i>Activity Log</h1>
    <div class="page-subtitle">All create, update, and delete actions across the system</div>
  </div>
  <span class="badge bg-secondary px-3 py-2"><?php echo count($logs); ?> entries</span>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="auditTable" class="table mb-0" style="width:100%">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Resource</th>
            <th>ID</th>
            <th>Detail</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l):
            $actionColors = ['create'=>'success','update'=>'primary','delete'=>'danger','simulate'=>'warning'];
            $ac = $actionColors[$l['action']] ?? 'secondary';
          ?>
          <tr>
            <td class="ss-td-date"><?php echo date('M d, Y h:i A', strtotime($l['created_at'])); ?></td>
            <td class="ss-td-sm"><?php echo htmlspecialchars($l['user_email']); ?></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($l['user_role']); ?></span></td>
            <td><span class="badge bg-<?php echo $ac; ?>"><?php echo htmlspecialchars($l['action']); ?></span></td>
            <td class="ss-td-sm"><?php echo htmlspecialchars($l['resource']); ?></td>
            <td class="ss-td-muted"><?php echo $l['resource_id'] ?? '—'; ?></td>
            <td class="ss-td-detail">
              <?php echo htmlspecialchars($l['detail'] ?? '—'); ?>
            </td>
            <td class="ss-td-ip"><?php echo htmlspecialchars($l['ip_address'] ?? '—'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('#auditTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    language: { search: 'Filter logs:' }
  });
});
</script>
