<?php
// Fallback: billing edit is handled via AJAX modal on the billing page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Invoice</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/billing/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($record->invoice_number ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Service Description</label>
                    <textarea name="service_description" class="form-control" required><?php echo htmlspecialchars($record->service_description ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required value="<?php echo htmlspecialchars($record->amount ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount (₱)</label>
                        <input type="number" step="0.01" name="discount" class="form-control" value="<?php echo htmlspecialchars($record->discount ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax (₱)</label>
                        <input type="number" step="0.01" name="tax" class="form-control" value="<?php echo htmlspecialchars($record->tax ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <?php foreach (['unpaid','paid','partial','cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($record->payment_status ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach (['cash','card','insurance','online'] as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($record->payment_method ?? '') === $m ? 'selected' : ''; ?>><?php echo ucfirst($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($record->payment_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"><?php echo htmlspecialchars($record->notes ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <a href="<?php echo url('/billing'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Invoice</button>
            </form>
        </div>
    </div>
</div>
