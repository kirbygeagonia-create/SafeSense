<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Doctors</h1>
    <a href='/doctors/create' class='btn btn-primary'>Add New Doctor</a>
</div>

<?php if (isset($doctors) && !empty($doctors)): ?>
    <div class='table-responsive'>
        <table class='table table-striped table-hover'>
            <thead class='table-dark'>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Specialization</th>
                    <th>License Number</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doctor['id']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['license_number']); ?></td>
                        <td>
                            <a href='/doctors/edit?id=<?php echo $doctor['id']; ?>' class='btn btn-sm btn-outline-primary'>Edit</a>
                            <form method='post' action='/doctors/delete' style='display: inline;' onsubmit='return confirm("Are you sure you want to delete this doctor?")'>
                                <input type='hidden' name='id' value='<?php echo $doctor['id']; ?>'>
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
        <h4>No doctors found</h4>
        <p>There are no doctors in the system yet. <a href='/doctors/create'>Add a new doctor</a> to get started.</p>
    </div>
<?php endif; ?>