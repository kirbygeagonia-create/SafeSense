    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h1>Patients</h1>
        <a href='/patients/create' class='btn btn-primary'>Add New Patient</a>
    </div>
    
    <?php if (isset($patients) && !empty($patients)): ?>
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['id']); ?></td>
                            <td><?php echo htmlspecialchars($patient['name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                            <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                            <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                            <td>
                                <a href='/patients/edit?id=<?php echo $patient['id']; ?>' class='btn btn-sm btn-outline-primary'>Edit</a>
                                <form method='post' action='/patients/delete' style='display: inline;' onsubmit='return confirm("Are you sure you want to delete this patient?")'>
                                    <input type='hidden' name='id' value='<?php echo $patient['id']; ?>'>
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
            <h4>No patients found</h4>
            <p>There are no patients in the system yet. <a href='/patients/create'>Add a new patient</a> to get started.</p>
        </div>
    <?php endif; ?>