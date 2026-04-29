<?php
// Fallback: doctor edit is handled via AJAX modal on the doctors page.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Doctor</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/doctors/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($doctor->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($doctor->name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($doctor->email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($doctor->phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Specialization</label>
                    <input type="text" name="specialization" class="form-control" required value="<?php echo htmlspecialchars($doctor->specialization ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">License Number</label>
                    <input type="text" name="license_number" class="form-control" required value="<?php echo htmlspecialchars($doctor->license_number ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <a href="<?php echo url('/doctors'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Doctor</button>
            </form>
        </div>
    </div>
</div>
