<form method="post" action="<?php echo url('/login/authenticate'); ?>">
    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required autofocus>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login mt-1">
        <i class="fas fa-sign-in-alt me-2"></i>Sign In
    </button>
</form>