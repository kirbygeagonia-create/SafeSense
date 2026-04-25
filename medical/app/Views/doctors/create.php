<div class='d-flex justify-content-between align-items-center mb-4'>
    <h1>Add New Doctor</h1>
    <a href='/doctors' class='btn btn-secondary'>Back to Doctors</a>
</div>

<div class='card'>
    <div class='card-body'>
        <form method='post' action='/doctors/store'>
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
                    <label for='specialization' class='form-label'>Specialization *</label>
                    <input type='text' class='form-control' id='specialization' name='specialization' required>
                </div>
            </div>
            
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <label for='license_number' class='form-label'>License Number *</label>
                    <input type='text' class='form-control' id='license_number' name='license_number' required>
                </div>
                <div class='col-md-6 mb-3'>
                    <label for='address' class='form-label'>Address</label>
                    <textarea class='form-control' id='address' name='address' rows='3'></textarea>
                </div>
            </div>
            
            <div class='d-grid d-md-block'>
                <button type='submit' class='btn btn-primary'>Create Doctor</button>
            </div>
        </form>
    </div>
</div>