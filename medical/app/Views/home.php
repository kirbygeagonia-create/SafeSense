<!-- Hero / Home Page -->
<div class="ss-home-hero rounded-xl p-5 mb-4 text-white" style="background: linear-gradient(135deg, var(--ss-primary-dark) 0%, var(--ss-primary) 100%);">
  <div class="d-flex align-items-center gap-3 mb-3">
    <i class="fas fa-satellite-dish fa-2x" style="color:#f87171;"></i>
    <div>
      <h1 class="mb-0 fw-800" style="font-size:2rem;letter-spacing:-.03em;">SafeSense HMS</h1>
      <div style="font-size:.9rem;opacity:.8;">Hospital Intelligence &amp; IoT Monitoring Platform</div>
    </div>
  </div>
  <p class="mb-0" style="opacity:.85;max-width:560px;font-size:.95rem;line-height:1.65;">
    A comprehensive solution for managing hospital operations, patients, doctors, appointments, and real-time IoT flood &amp; hazard alerts.
  </p>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card h-100 border-primary border-top border-3">
      <div class="card-body">
        <h5 class="card-title fw-700"><i class="fas fa-user-injured me-2 text-primary"></i>Patients</h5>
        <p class="card-text text-muted small">Manage patient records, personal information, medical history, and contact details.</p>
        <a href="<?php echo url('/patients'); ?>" class="btn btn-outline-primary btn-sm">View Patients</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100 border-success border-top border-3">
      <div class="card-body">
        <h5 class="card-title fw-700"><i class="fas fa-user-md me-2 text-success"></i>Doctors</h5>
        <p class="card-text text-muted small">Manage doctor profiles, specializations, schedules, and availability.</p>
        <a href="<?php echo url('/doctors'); ?>" class="btn btn-outline-success btn-sm">View Doctors</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100 border-info border-top border-3">
      <div class="card-body">
        <h5 class="card-title fw-700"><i class="fas fa-calendar-check me-2 text-info"></i>Appointments</h5>
        <p class="card-text text-muted small">Schedule and manage patient appointments with doctors and track status.</p>
        <a href="<?php echo url('/appointments'); ?>" class="btn btn-outline-info btn-sm">View Appointments</a>
      </div>
    </div>
  </div>
</div>