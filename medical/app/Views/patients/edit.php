<?php
// Fallback: patient edit is handled via AJAX modal on the patients page.
// This view only renders for direct (non-AJAX) browser navigation.
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Patient</h5></div>
        <div class="card-body">
            <form method="post" action="<?php echo url('/patients/update'); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($patient->id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($patient->name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($patient->email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($patient->phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?php echo htmlspecialchars($patient->address ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?php echo htmlspecialchars($patient->date_of_birth ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="male"   <?php echo ($patient->gender ?? '') === 'male'   ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($patient->gender ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other"  <?php echo ($patient->gender ?? '') === 'other'  ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <a href="<?php echo url('/patients'); ?>" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Patient</button>
            </form>
        </div>
    </div>
</div>
