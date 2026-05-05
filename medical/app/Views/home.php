<!-- Hero / Home Page -->
<div class="ss-home-hero">
  <div class="d-flex align-items-center gap-3 mb-3">
    <i class="fas fa-satellite-dish fa-2x ss-home-hero-icon"></i>
    <div>
      <h1 class="mb-0 fw-bolder">Tupi Hospital Management</h1>
      <div class="ss-home-subtitle">Hospital Intelligence &amp; SafeSense IoT Monitoring Platform</div>
    </div>
  </div>
  <p class="mb-0 ss-home-lead">
    A comprehensive solution for managing hospital operations, patients, doctors, appointments, and real-time IoT flood &amp; hazard alerts.
  </p>
</div>

<div class="row g-3">
  <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'doctor', 'nurse'])): ?>
  <div class="col-md-4">
    <div class="card h-100 ss-card-top-primary">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-injured me-2 text-primary"></i>Patients</h5>
        <p class="card-text text-muted small">Manage patient records, personal information, medical history, and contact details.</p>
        <a href="<?php echo url('/patients'); ?>" class="btn btn-outline-primary btn-sm">View Patients</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100 ss-card-top-success">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-user-md me-2 text-success"></i>Doctors</h5>
        <p class="card-text text-muted small">Manage doctor profiles, specializations, schedules, and availability.</p>
        <a href="<?php echo url('/doctors'); ?>" class="btn btn-outline-success btn-sm">View Doctors</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-md-4">
    <div class="card h-100 ss-card-top-info">
      <div class="card-body">
        <h5 class="card-title fw-bold"><i class="fas fa-calendar-check me-2 text-info"></i>Appointments</h5>
        <p class="card-text text-muted small">Schedule and manage patient appointments with doctors and track status.</p>
        <a href="<?php echo url('/appointments'); ?>" class="btn btn-outline-info btn-sm">View Appointments</a>
      </div>
    </div>
  </div>
</div>