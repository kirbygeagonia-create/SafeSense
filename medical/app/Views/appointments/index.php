<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Appointments</h1>
    <a href='/appointments/create' class='btn btn-primary'>Schedule New Appointment</a>
</div>

<?php if (isset($appointments) && !empty($appointments)): ?>
    <div class='table-responsive'>
        <table class='table table-striped table-hover'>
            <thead class='table-dark'>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appointment['id']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['appointment_time']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $appointment['status'] === 'pending' ? 'warning' : 
                                     ($appointment['status'] === 'confirmed' ? 'success' : 
                                      ($appointment['status'] === 'completed' ? 'secondary' : 'danger'));
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                        <td>
                            <a href='/appointments/edit?id=<?php echo $appointment['id']; ?>' class='btn btn-sm btn-outline-primary'>Edit</a>
                            <form method='post' action='/appointments/delete' style='display: inline;' onsubmit='return confirm("Are you sure you want to delete this appointment?")'>
                                <input type='hidden' name='id' value='<?php echo $appointment['id']; ?>'>
                                <button type='submit' class='btn btn-sm btn-outline-danger'>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class='alert alert-info'>
        <h4>No appointments found</h4>
        <p>There are no appointments scheduled yet. <a href='/appointments/create'>Schedule a new appointment</a> to get started.</p>
    </div>
<?php endif; ?>