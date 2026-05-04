<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?php echo htmlspecialchars($billing->invoice_number); ?> — SafeSense HMS</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans', sans-serif; font-size: 14px; color: #0f172a; background: #fff; padding: 0; }

    .page { max-width: 800px; margin: 0 auto; padding: 48px 56px; }

    /* Header */
    .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 2px solid #1e3a8a; }
    .inv-brand { display: flex; flex-direction: column; }
    .inv-brand-name { font-size: 20px; font-weight: 700; color: #1e3a8a; letter-spacing: -.02em; }
    .inv-brand-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
    .inv-meta { text-align: right; }
    .inv-number { font-family: 'IBM Plex Mono', monospace; font-size: 18px; font-weight: 600; color: #1e3a8a; }
    .inv-date { font-size: 12px; color: #64748b; margin-top: 4px; }

    /* Status badge */
    .inv-status { display: inline-block; padding: 3px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-top: 8px; }
    .status-paid      { background: #dcfce7; color: #15803d; }
    .status-unpaid    { background: #fee2e2; color: #b91c1c; }
    .status-partial   { background: #fef9c3; color: #854d0e; }
    .status-cancelled { background: #f1f5f9; color: #475569; }

    /* Bill to / from grid */
    .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 36px; }
    .inv-party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 8px; }
    .inv-party-name { font-size: 15px; font-weight: 600; color: #0f172a; }
    .inv-party-detail { font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.55; }

    /* Line items table */
    .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .inv-table thead th { padding: 10px 12px; background: #1e3a8a; color: #fff; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .inv-table thead th:last-child { text-align: right; }
    .inv-table tbody td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
    .inv-table tbody td:last-child { text-align: right; font-weight: 500; }

    /* Totals */
    .inv-totals { display: flex; justify-content: flex-end; margin-bottom: 36px; }
    .inv-totals-table { width: 280px; }
    .inv-totals-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .inv-totals-row:last-child { border-bottom: none; padding-top: 12px; font-size: 15px; font-weight: 700; color: #1e3a8a; }
    .inv-totals-row.discount { color: #15803d; }

    /* Payment info */
    .inv-payment { background: #f8fafc; border-radius: 8px; padding: 16px 20px; margin-bottom: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .inv-payment-item-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
    .inv-payment-item-val   { font-size: 13px; font-weight: 500; color: #0f172a; }

    /* Notes */
    .inv-notes { border-left: 3px solid #e2e8f0; padding-left: 16px; margin-bottom: 40px; }
    .inv-notes-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 6px; }
    .inv-notes-text  { font-size: 13px; color: #475569; line-height: 1.6; }

    /* Footer */
    .inv-footer { border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; }
    .inv-footer-brand { font-size: 12px; color: #94a3b8; }
    .inv-footer-generated { font-size: 11px; color: #cbd5e1; }

    /* Print button (screen only) */
    .print-bar { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 56px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .print-bar button { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; }
    .btn-print { background: #1e3a8a; color: #fff; }
    .btn-print:hover { background: #1d4ed8; }
    .btn-close-tab { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0 !important; }

    @media print {
      .print-bar { display: none !important; }
      .page { padding: 0; max-width: 100%; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .inv-table thead th { background: #1e3a8a !important; color: #fff !important; }
    }
  </style>
</head>
<body>

<!-- Screen-only print toolbar -->
<div class="print-bar">
  <button class="btn-close-tab" onclick="window.close()">✕ Close</button>
  <button class="btn-print" onclick="window.print()">🖨 Print Invoice</button>
</div>

<div class="page">
  <!-- Header -->
  <div class="inv-header">
    <div class="inv-brand">
      <div class="inv-brand-name">🏥 SafeSense Hospital</div>
      <div class="inv-brand-sub">Hospital Management System</div>
      <div class="inv-brand-sub">Malaybalay City, Bukidnon</div>
    </div>
    <div class="inv-meta">
      <div class="inv-number"><?php echo htmlspecialchars($billing->invoice_number); ?></div>
      <div class="inv-date">Issued: <?php echo date('F d, Y'); ?></div>
      <?php
        $sc = ['paid'=>'status-paid','unpaid'=>'status-unpaid','partial'=>'status-partial','cancelled'=>'status-cancelled'];
        $cls = $sc[$billing->payment_status] ?? 'status-unpaid';
      ?>
      <div><span class="inv-status <?php echo $cls; ?>"><?php echo strtoupper($billing->payment_status); ?></span></div>
    </div>
  </div>

  <!-- Bill To / From -->
  <div class="inv-parties">
    <div>
      <div class="inv-party-label">Bill To</div>
      <div class="inv-party-name"><?php echo htmlspecialchars($billing->patient_name ?? 'Patient'); ?></div>
      <div class="inv-party-detail">Patient ID: #<?php echo $billing->patient_id; ?></div>
    </div>
    <div>
      <div class="inv-party-label">Issued By</div>
      <div class="inv-party-name">SafeSense HMS Billing</div>
      <div class="inv-party-detail">billing@safesense.local<br>Generated: <?php echo date('M d, Y, h:i A'); ?></div>
    </div>
  </div>

  <!-- Line items -->
  <table class="inv-table">
    <thead>
      <tr>
        <th style="width:60%;">Description of Service</th>
        <th style="text-align:right;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?php echo htmlspecialchars($billing->service_description ?? 'Medical Services'); ?></td>
        <td>₱<?php echo number_format($billing->amount, 2); ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Totals -->
  <div class="inv-totals">
    <div class="inv-totals-table">
      <div class="inv-totals-row">
        <span>Subtotal</span>
        <span>₱<?php echo number_format($billing->amount, 2); ?></span>
      </div>
      <?php if ($billing->discount > 0): ?>
      <div class="inv-totals-row discount">
        <span>Discount</span>
        <span>− ₱<?php echo number_format($billing->discount, 2); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($billing->tax > 0): ?>
      <div class="inv-totals-row">
        <span>Tax</span>
        <span>+ ₱<?php echo number_format($billing->tax, 2); ?></span>
      </div>
      <?php endif; ?>
      <div class="inv-totals-row">
        <span>Total Due</span>
        <span>₱<?php echo number_format($billing->total_amount, 2); ?></span>
      </div>
    </div>
  </div>

  <!-- Payment info -->
  <div class="inv-payment">
    <div>
      <div class="inv-payment-item-label">Payment Status</div>
      <div class="inv-payment-item-val"><?php echo ucfirst($billing->payment_status); ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Payment Method</div>
      <div class="inv-payment-item-val"><?php echo $billing->payment_method ? ucfirst($billing->payment_method) : '—'; ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Payment Date</div>
      <div class="inv-payment-item-val"><?php echo $billing->payment_date ? date('M d, Y', strtotime($billing->payment_date)) : 'Not yet paid'; ?></div>
    </div>
    <div>
      <div class="inv-payment-item-label">Invoice Reference</div>
      <div class="inv-payment-item-val" style="font-family:'IBM Plex Mono',monospace;"><?php echo htmlspecialchars($billing->invoice_number); ?></div>
    </div>
  </div>

  <!-- Notes -->
  <?php if (!empty($billing->notes)): ?>
  <div class="inv-notes">
    <div class="inv-notes-label">Notes</div>
    <div class="inv-notes-text"><?php echo htmlspecialchars($billing->notes); ?></div>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="inv-footer">
    <div class="inv-footer-brand">SafeSense Hospital Management System</div>
    <div class="inv-footer-generated">Generated <?php echo date('M d, Y \a\t h:i A'); ?></div>
  </div>
</div>

</body>
</html>
