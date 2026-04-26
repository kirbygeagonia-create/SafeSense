    <div class='container'>
        <div class='row justify-content-center'>
            <div class='col-md-6 col-lg-4'>
                <div class='card mt-5'>
                    <div class='card-header text-center'>
                        <h3>Login to <?php echo APP_NAME; ?></h3>
                    </div>
                    <div class='card-body'>
                        <form method='post' action='/login/authenticate'>
                            <div class='mb-3'>
                                <label for='email' class='form-label'>Email address</label>
                                <input type='email' class='form-control' id='email' name='email' required>
                            </div>
                            <div class='mb-3'>
                                <label for='password' class='form-label'>Password</label>
                                <input type='password' class='form-control' id='password' name='password' required>
                            </div>
                            <div class='d-grid'>
                                <button type='submit' class='btn btn-primary'>Sign In</button>
                            </div>
                        </form>
                        
                        <div class='mt-3 text-center'>
                            <p class='text-muted'>Demo credentials:<br>
                            Email: admin@example.com<br>
                            Password: password</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>