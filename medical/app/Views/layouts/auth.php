<?php
/**
 * Standalone layout for authentication pages (login, etc.)
 * No navbar, no session checks — just a clean centered page.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Task 8 — consistent title format -->
    <title><?php echo htmlspecialchars($title ?? 'SafeSense'); ?> — SafeSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css?v=2" rel="stylesheet">
</head>
<body class="auth-body">

<?php
    $flashSuccess = $_SESSION['flash_success'] ?? null;
    $flashError   = $_SESSION['flash_error']   ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="auth-card">
    <!-- Task 8 — fa-shield-halved + "SafeSense" heading + #dc2626 accent bar -->
    <div class="text-center mb-4">
        <div class="auth-logo"><i class="fas fa-shield-halved"></i></div>
        <div class="auth-brand-name">SafeSense</div>
        <div class="auth-accent-bar"></div>
        <div class="auth-subtitle">Hospital Intelligence &amp; IoT Monitoring</div>
    </div>

    <?php if ($flashError): ?>
    <div class="alert-danger-custom">
        <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($flashError); ?>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success py-2 small"><?php echo htmlspecialchars($flashSuccess); ?></div>
    <?php endif; ?>

    <?php echo $content ?? ''; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
