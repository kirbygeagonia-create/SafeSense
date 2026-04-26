    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h1>Add New Patient</h1>
        <a href='/patients' class='btn btn-secondary'>Back to Patients</a>
    </div>
    
    <div class='card'>
        <div class='card-body'>
            <form method='post' action='/patients/store'>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='name' class='form-label'>Full Name *</label>
                        <input type='text' class='form-control' id='name' name='name' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='email' class='form-label'>Email *</label>
                        <input type='email' class='form-control' id='email' name='email' required>
                    </div>
                </div>
                
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='phone' class='form-label'>Phone *</label>
                        <input type='tel' class='form-control' id='phone' name='phone' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='date_of_birth' class='form-label'>Date of Birth *</label>
                        <input type='date' class='form-control' id='date_of_birth' name='date_of_birth' required>
                    </div>
                </div>
                
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='gender' class='form-label'>Gender *</label>
                        <select class='form-select' id='gender' name='gender' required>
                            <option value=''>Select Gender</option>
                            <option value='male'>Male</option>
                            <option value='female'>Female</option>
                            <option value='other'>Other</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='address' class='form-label'>Address</label>
                        <textarea class='form-control' id='address' name='address' rows='3'></textarea>
                    </div>
                </div>
                
                <div class='d-grid d-md-block'>
                    <button type='submit' class='btn btn-primary'>Create Patient</button>
                </div>
            </form>
        </div>
    </div>