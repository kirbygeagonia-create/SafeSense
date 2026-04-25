<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Dashboard</h1>
    <a href='/logout' class='btn btn-outline-danger'>Logout</a>
</div>

<div class='row'>
    <div class='col-md-3 mb-4'>
        <div class='card bg-primary text-white h-100'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div>
                        <h6 class='text-white-50 mb-0'>Total Patients</h6>
                        <h2 class='mb-0'><?php echo $patientCount ?? 0; ?></h2>
                    </div>
                    <div class='icon'>
                        <i class='fas fa-user-injury fa-2x'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class='col-md-3 mb-4'>
        <div class='card bg-success text-white h-100'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div>
                        <h6 class='text-white-50 mb-0'>Total Doctors</h6>
                        <h2 class='mb-0'><?php echo $doctorCount ?? 0; ?></h2>
                    </div>
                    <div class='icon'>
                        <i class='fas fa-user-md fa-2x'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class='col-md-3 mb-4'>
        <div class='card bg-info text-white h-100'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div>
                        <h6 class='text-white-50 mb-0'>Today\'s Appointments</h6>
                        <h2 class='mb-0'><?php echo $appointmentCount ?? 0; ?></h2>
                    </div>
                    <div class='icon'>
                        <i class='fas fa-calendar-check fa-2x'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class='col-md-3 mb-4'>
        <div class='card bg-warning text-white h-100'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div>
                        <h6 class='text-white-50 mb-0'>Pending Appointments</h6>
                        <h2 class='mb-0'>12</h2>
                    </div>
                    <div class='icon'>
                        <i class='fas fa-clock fa-2x'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class='row'>
    <div class='col-md-8'>
        <div class='card'>
            <div class='card-header'>
                <h5 class='mb-0'>Recent Activity</h5>
            </div>
            <div class='card-body'>
                <div class='table-responsive'>
                    <table class='table table-borderless'>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Just now</td>
                                <td>New patient registered: John Doe</td>
                                <td><span class='badge bg-success'>Success</span></td>
                            </tr>
                            <tr>
                                <td>5 minutes ago</td>
                                <td>Appointment scheduled: Jane Smith with Dr. Smith</td>
                                <td><span class='badge bg-info'>Scheduled</span></td>
                            </tr>
                            <tr>
                                <td>15 minutes ago</td>
                                <td>Doctor profile updated: Dr. Johnson</td>
                                <td><span class='badge bg-warning'>Updated</span></td>
                            </tr>
                            <tr>
                                <td>30 minutes ago</td>
                                <td>Patient record accessed: Robert Johnson</td>
                                <td><span class='badge bg-secondary'>Accessed</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class='col-md-4'>
        <div class='card'>
            <div class='card-header'>
                <h5 class='mb-0'>Upcoming Appointments</h5>
            </div>
            <div class='card-body'>
                <div class='list-group'>
                    <a href='#' class='list-group-item list-group-item-action'>
                        <div class='d-flex w-100 justify-content-between'>
                            <h6 class='mb-1'>John Doe with Dr. Smith</h6>
                            <small>Today, 10:00 AM</small>
                        </div>
                        <p class='mb-1'>Routine checkup</p>
                        <small class='text-muted'>Confirmed</small>
                    </a>
                    <a href='#' class='list-group-item list-group-item-action'>
                        <div class='d-flex w-100 justify-content-between'>
                            <h6 class='mb-1'>Jane Smith with Dr. Johnson</h6>
                            <small>Today, 2:30 PM</small>
                        </div>
                        <p class='mb-1'>Follow-up consultation</p>
                        <small class='text-muted'>Pending</small>
                    </a>
                    <a href='#' class='list-group-item list-group-item-action'>
                        <div class='d-flex w-100 justify-content-between'>
                            <h6 class='mb-1'>Robert Johnson with Dr. Williams</h6>
                            <small>Tomorrow, 9:15 AM</small>
                        </div>
                        <p class='mb-1'>Dental cleaning</p>
                        <small class='text-muted'>Confirmed</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>