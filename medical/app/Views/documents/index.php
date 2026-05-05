<div class="page-header">
  <div>
    <h1><i class="fas fa-file-upload"></i>Patient Documents</h1>
    <div class="page-subtitle">Upload and manage patient documents securely</div>
  </div>
  <?php if ($patient): ?>
  <a href="<?php echo url('/patients/view?id=' . $patient['id']); ?>" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back to Patient
  </a>
  <?php endif; ?>
</div>

<?php if (!$patient): ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>Please navigate to a patient profile to view or upload documents.
    <a href="<?php echo url('/patients'); ?>" class="alert-link">View Patients</a>
  </div>
<?php else: ?>

  <div class="card mb-4">
    <div class="card-header">
      <i class="fas fa-user me-2"></i>Patient: <strong><?php echo htmlspecialchars($patient['name']); ?></strong>
    </div>
    <div class="card-body">
      <!-- Upload form -->
      <form method="POST" action="<?php echo url('/patients/documents/upload'); ?>" enctype="multipart/form-data" class="mb-4">
        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Select File</label>
            <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
            <div class="form-text">Max 10MB. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX</div>
          </div>
          <div class="col-md-5">
            <label class="form-label">Notes (optional)</label>
            <input type="text" name="notes" class="form-control" placeholder="e.g., Lab results, X-ray scan, etc.">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-upload me-1"></i>Upload Document
            </button>
          </div>
        </div>
      </form>

      <hr>

      <!-- Documents list -->
      <h5 class="mb-3">Uploaded Documents (<?php echo count($documents); ?>)</h5>

      <?php if (empty($documents)): ?>
        <div class="text-muted text-center py-4">
          <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
          <p>No documents uploaded yet</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Filename</th>
                <th>Type</th>
                <th>Size</th>
                <th>Notes</th>
                <th>Uploaded By</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($documents as $doc):
                $sizeFormatted = number_format($doc['file_size'] / 1024, 2) . ' KB';
                if ($doc['file_size'] > 1024 * 1024) {
                  $sizeFormatted = number_format($doc['file_size'] / (1024 * 1024), 2) . ' MB';
                }
              ?>
              <tr>
                <td>
                  <i class="fas fa-file me-2 text-primary"></i>
                  <?php echo htmlspecialchars($doc['file_name']); ?>
                </td>
                <td><?php echo htmlspecialchars($doc['file_type']); ?></td>
                <td><?php echo $sizeFormatted; ?></td>
                <td><?php echo htmlspecialchars($doc['notes'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
                <td><?php echo date('M d, Y h:i A', strtotime($doc['created_at'])); ?></td>
                <td>
                  <a href="<?php echo url('/uploads/documents/' . $doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="View/Download">
                    <i class="fas fa-download"></i>
                  </a>
                  <form method="POST" action="<?php echo url('/patients/documents/delete'); ?>" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
