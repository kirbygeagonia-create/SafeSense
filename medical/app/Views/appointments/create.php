<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Schedule Appointment</h1>
    <a href='/appointments' class='btn btn-secondary'>Back to Appointments</a>
</div>

<div class='card'>
    <div class='card-body'>
        <form method='post' action='/appointments/store'>
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <label for='patient_id' class='form-label'>Patient *</label>
                    <select class='form-select' id='patient_id' name='patient_id' required>
                        <option value=''>Select Patient</option>
                        <?php if (isset($patients)): ?>
                            <?php foreach ($patients as $patient): ?>
                                <option value='<?php echo $patient['id']; ?>'>
                                    <?php echo htmlspecialchars($patient['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class='col-md-6 mb-3'>
                    <label for='doctor_id' class='form-label'>Doctor *</label>
                    <select class='form-select' id='doctor_id' name='doctor_id' required>
                        <option value=''>Select Doctor</option>
                        <?php if (isset($doctors)): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value='<?php echo $doctor['id']; ?>'>
                                    <?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <label for='appointment_date' class='form-label'>Appointment Date *</label>
                    <input type='date' class='form-control' id='appointment_date' name='appointment_date' required>
                </div>
                <div class='col-md-6 mb-3'>
                    <label for='appointment_time' class='form-label'>Appointment Time *</label>
                    <input type='time' class='form-control' id='appointment_time' name='appointment_time' required>
                </div>
            </div>
            
            <div class='mb-3'>
                <label for='status' class='form-label'>Status</label>
                <select class='form-select' id='status' name='status'>
                    <option value='pending'>Pending</option>
                    <option value='confirmed'>Confirmed</option>
                    <option value='cancelled'>Cancelled</option>
                    <option value='completed'>Completed</option>
                </select>
            </div>
            
            <div class='mb-3'>
                <label for='reason' class='form-label'>Reason for Visit</label>
                <textarea class='form-control' id='reason' name='reason' rows='3'></textarea>
            </div>
            
            <div class='d-grid d-md-block'>
                <button type='submit' class='btn btn-primary'>Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>