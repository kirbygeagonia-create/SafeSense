<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Edit Appointment</h1>
    <a href='/appointments' class='btn btn-secondary'>Back to Appointments</a>
</div>

<div class='card'>
    <div class='card-body'>
        <?php if (isset($appointment)): ?>
            <form method='post' action='/appointments/update'>
                <input type='hidden' name='id' value='<?php echo htmlspecialchars($appointment->id); ?>'>
                
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='patient_id' class='form-label'>Patient *</label>
                        <select class='form-select' id='patient_id' name='patient_id' required>
                            <option value=''>Select Patient</option>
                            <?php if (isset($patients)): ?>
                                <?php foreach ($patients as $patient): ?>
                                    <option value='<?php echo $patient['id']; ?>'
                                        <?php echo $appointment->patient_id == $patient['id'] ? 'selected' : ''; ?>>
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
                                    <option value='<?php echo $doctor['id']; ?>'
                                        <?php echo $appointment->doctor_id == $doctor['id'] ? 'selected' : ''; ?>>
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
                        <input type='date' class='form-control' id='appointment_date' name='appointment_date' 
                               value='<?php echo htmlspecialchars($appointment->appointment_date); ?>' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='appointment_time' class='form-label'>Appointment Time *</label>
                        <input type='time' class='form-control' id='appointment_time' name='appointment_time' 
                               value='<?php echo htmlspecialchars($appointment->appointment_time); ?>' required>
                    </div>
                </div>
                
                <div class='mb-3'>
                    <label for='status' class='form-label'>Status</label>
                    <select class='form-select' id='status' name='status'>
                        <option value='pending' <?php echo $appointment->status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value='confirmed' <?php echo $appointment->status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value='cancelled' <?php echo $appointment->status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value='completed' <?php echo $appointment->status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class='mb-3'>
                    <label for='reason' class='form-label'>Reason for Visit</label>
                    <textarea class='form-control' id='reason' name='reason' rows='3'><?php echo htmlspecialchars($appointment->reason); ?></textarea>
                </div>
                
                <div class='d-grid d-md-block'>
                    <button type='submit' class='btn btn-primary'>Update Appointment</button>
                </div>
            </form>
        <?php else: ?>
            <div class='alert alert-danger'>
                <h4>Appointment not found</h4>
                <p>The appointment you are trying to edit could not be found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>