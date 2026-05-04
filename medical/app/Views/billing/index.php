<?php
$allPatients = isset($allPatients) ? $allPatients : [];
?>
<script>
  const PATIENTS = <?php echo json_encode($allPatients); ?>;
</script>

<!-- Task 1 — Page header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice-dollar me-2"></i>Billing</h1>
        <div class="page-subtitle">Invoice management and payment tracking</div>
    </div>
    <?php if (in_array($currentRole ?? '', ['admin','staff'])): ?>
    <button type="button" class="btn btn-primary" id="addBillingBtn">
        <i class="fas fa-plus me-1"></i>Create Invoice
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive mb-3">
    <table id="billingTable" class="table table-striped table-hover" style="width:100%">
        <thead class="table-dark">
            <tr>
                <th>Invoice #</th>
                <th>Patient</th>
                <th>Amount (₱)</th>
                <th>Discount (₱)</th>
                <th>Tax (₱)</th>
                <th>Total (₱)</th>
                <th>Status</th>
                <th>Method</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($records) && !empty($records)): ?>
                <?php foreach ($records as $r): ?>
                <?php
                    $statusColor = [
                        'unpaid'    => 'danger',
                        'paid'      => 'success',
                        'partial'   => 'warning',
                        'cancelled' => 'secondary'
                    ][$r['payment_status']] ?? 'secondary';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                    <td><?php echo htmlspecialchars($r['patient_name'] ?? '—'); ?></td>
                    <td>₱<?php echo number_format((float)$r['amount'], 2); ?></td>
                    <td>₱<?php echo number_format((float)$r['discount'], 2); ?></td>
                    <td>₱<?php echo number_format((float)$r['tax'], 2); ?></td>
                    <td>₱<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                    <td><span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst(htmlspecialchars($r['payment_status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($r['payment_method'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['payment_date'] ?? '—'); ?></td>
                    <td>
                        <a href="<?php echo url('/billing/print?id='.$r['id']); ?>" target="_blank"
                           class="btn btn-sm btn-outline-secondary me-1" title="Print Invoice">
                          <i class="fas fa-print"></i>
                        </a>
                        <?php if (in_array($currentRole ?? '', ['admin','staff'])): ?>
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
    <i class="fas fa-file-invoice-dollar fa-3x mb-3 d-block"></i>
    <p class="fs-5">No billing records yet.</p>
</div>
<?php endif; ?>

<!-- Billing Modal -->
<div class="modal fade" id="billingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Create Invoice</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="billingForm" data-action="<?php echo url('/billing/store'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="b_patient" class="form-label">Patient <span class="text-danger">*</span></label>
              <select class="form-select" id="b_patient" name="patient_id" required>
                <option value="">Select Patient</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="b_appointment" class="form-label">Appointment ID</label>
              <input type="number" class="form-control" id="b_appointment" name="appointment_id">
            </div>
          </div>
          <div class="mb-3">
            <label for="b_service" class="form-label">Service Description <span class="text-danger">*</span></label>
            <textarea class="form-control" id="b_service" name="service_description" rows="2" required></textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="b_amount" class="form-label">Amount <span class="text-danger">*</span></label>
              <input type="number" step="0.01" class="form-control" id="b_amount" name="amount" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="b_discount" class="form-label">Discount</label>
              <input type="number" step="0.01" class="form-control" id="b_discount" name="discount" value="0.00">
            </div>
            <div class="col-md-4 mb-3">
              <label for="b_tax" class="form-label">Tax</label>
              <input type="number" step="0.01" class="form-control" id="b_tax" name="tax" value="0.00">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="b_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
              <select class="form-select" id="b_status" name="payment_status" required>
                <option value="unpaid" selected>Unpaid</option>
                <option value="paid">Paid</option>
                <option value="partial">Partial</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="b_method" class="form-label">Payment Method</label>
              <select class="form-select" id="b_method" name="payment_method">
                <option value="">Select</option>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="insurance">Insurance</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="b_payment_date" class="form-label">Payment Date</label>
              <input type="date" class="form-control" id="b_payment_date" name="payment_date">
            </div>
          </div>
          <div class="mb-3">
            <label for="b_notes" class="form-label">Notes</label>
            <textarea class="form-control" id="b_notes" name="notes" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>
