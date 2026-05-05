<?php
// Minimal printable prescription view — no main layout
if (!isset($_SESSION['user'])) { header('Location: ' . url('/login')); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Prescription — <?php echo htmlspecialchars($patient->name ?? ''); ?></title>
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 2cm; color: #111; font-size: 13pt; }
  .rx-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #2563eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
  .rx-logo { font-weight: 700; font-size: 1.4rem; color: #2563eb; }
  .rx-subtitle { color: #6b7280; font-size: .85rem; }
  .rx-body { margin-top: 1.5rem; }
  .rx-symbol { font-size: 2.5rem; font-weight: 700; color: #2563eb; float: left; margin-right: .5rem; line-height: 1; }
  .rx-prescription { font-size: 1.05rem; line-height: 1.7; white-space: pre-wrap; }
  .rx-footer { margin-top: 3rem; border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; }
  .sig-line { border-top: 1px solid #111; width: 200px; margin-top: 2rem; text-align: center; font-size: .85rem; color: #6b7280; }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:1rem;">
  <button onclick="window.print()" style="padding:.5rem 1.5rem; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:1rem;">
    🖨️ Print Prescription
  </button>
  <button onclick="history.back()" style="padding:.5rem 1.5rem; margin-left:.75rem; background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; cursor:pointer; font-size:1rem;">
    ← Back
  </button>
</div>

<div class="rx-header">
  <div>
    <div class="rx-logo">🏥 <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'SafeSense Hospital'); ?></div>
    <div class="rx-subtitle">Medical Prescription</div>
  </div>
  <div style="text-align:right; font-size:.9rem; color:#374151;">
    <strong>Date:</strong> <?php echo htmlspecialchars($record->visit_date ?? date('Y-m-d')); ?><br>
    <strong>Dr.:</strong> <?php echo htmlspecialchars($doctor->name ?? '—'); ?><br>
    <strong>License:</strong> <?php echo htmlspecialchars($doctor->license_number ?? '—'); ?>
  </div>
</div>

<div style="margin-bottom:1.2rem;">
  <strong>Patient:</strong> <?php echo htmlspecialchars($patient->name ?? '—'); ?> &nbsp;|&nbsp;
  <strong>Age:</strong> <?php
    $age = '—';
    if (!empty($patient->date_of_birth)) {
        try { $age = (new DateTime())->diff(new DateTime($patient->date_of_birth))->y . ' yrs'; } catch(Exception $e) {}
    }
    echo $age;
  ?> &nbsp;|&nbsp;
  <strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($patient->gender ?? '—')); ?>
</div>

<hr style="border-color:#e5e7eb;">
<div class="rx-body">
  <div class="rx-symbol">℞</div>
  <div class="rx-prescription"><?php echo nl2br(htmlspecialchars($record->prescription ?? 'No prescription recorded.')); ?></div>
  <div style="clear:both;"></div>
</div>

<?php if (!empty($record->notes)): ?>
<div style="margin-top:1.5rem; padding:1rem; background:#f8fafc; border-left:3px solid #2563eb; border-radius:4px;">
  <strong>Clinical Notes:</strong><br>
  <span style="white-space:pre-wrap;"><?php echo htmlspecialchars($record->notes); ?></span>
</div>
<?php endif; ?>

<div class="rx-footer">
  <div>
    <div class="sig-line">Physician's Signature</div>
  </div>
  <div style="font-size:.8rem; color:#9ca3af;">
    Printed: <?php echo date('F j, Y g:i A'); ?>
  </div>
</div>
</body>
</html>
