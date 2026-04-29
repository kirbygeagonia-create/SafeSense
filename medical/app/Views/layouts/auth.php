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
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'IBM Plex Sans', sans-serif;
        }
        .auth-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
        /* Task 8 — shield + red accent bar branding */
        .auth-accent-bar {
            height: 4px;
            width: 56px;
            background: #dc2626;
            border-radius: 2px;
            margin: 0 auto 1.25rem;
        }
        .auth-logo {
            font-size: 2.4rem;
            color: #dc2626;
            margin-bottom: 0.4rem;
        }
        .auth-brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #f1f5f9;
            letter-spacing: -0.5px;
            margin-bottom: 0.2rem;
        }
        .auth-title {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        .auth-subtitle {
            color: #94a3b8;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        .form-label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-control {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            color: #f1f5f9;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.1);
            border-color: #3b82f6;
            color: #f1f5f9;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
        }
        .form-control::placeholder { color: #64748b; }
        .btn-login {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem;
            width: 100%;
            transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; color: #fff; }
        .alert-danger-custom {
            background: rgba(220,38,38,0.15);
            border: 1px solid rgba(220,38,38,0.3);
            border-radius: 8px;
            color: #fca5a5;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

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
