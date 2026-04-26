    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h1>Edit Patient</h1>
        <a href='/patients' class='btn btn-secondary'>Back to Patients</a>
    </div>
    
    <div class='card'>
        <div class='card-body'>
            <?php if (isset($patient)): ?>
                <form method='post' action='/patients/update'>
                    <input type='hidden' name='id' value='<?php echo htmlspecialchars($patient->id); ?>'>
                    
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='name' class='form-label'>Full Name *</label>
                            <input type='text' class='form-control' id='name' name='name' value='<?php echo htmlspecialchars($patient->name); ?>' required>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='email' class='form-label'>Email *</label>
                            <input type='email' class='form-control' id='email' name='email' value='<?php echo htmlspecialchars($patient->email); ?>' required>
                        </div>
                    </div>
                    
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='phone' class='form-label'>Phone *</label>
                            <input type='tel' class='form-control' id='phone' name='phone' value='<?php echo htmlspecialchars($patient->phone); ?>' required>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='date_of_birth' class='form-label'>Date of Birth *</label>
                            <input type='date' class='form-control' id='date_of_birth' name='date_of_birth' value='<?php echo htmlspecialchars($patient->date_of_birth); ?>' required>
                        </div>
                    </div>
                    
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='gender' class='form-label'>Gender *</label>
                            <select class='form-select' id='gender' name='gender' required>
                                <option value=''>Select Gender</option>
                                <option value='male' <?php echo $patient->gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value='female' <?php echo $patient->gender === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value='other' <?php echo $patient->gender === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='address' class='form-label'>Address</label>
                            <textarea class='form-control' id='address' name='address' rows='3'><?php echo htmlspecialchars($patient->address); ?></textarea>
                        </div>
                    </div>
                    
                    <div class='d-grid d-md-block'>
                        <button type='submit' class='btn btn-primary'>Update Patient</button>
                    </div>
                </form>
            <?php else: ?>
                <div class='alert alert-danger'>
                    <h4>Patient not found</h4>
                    <p>The patient you are trying to edit could not be found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>