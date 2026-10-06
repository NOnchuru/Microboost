<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-white text-center py-4 border-0">
                    <h3 class="fw-bold" style="color: var(--agri-green);">Join MicroBoost</h3>
                    <p class="text-muted mb-0">Create your account to start monitoring or lending.</p>
                </div>
                <div class="card-body p-4">
                    <form action="process_register.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="+254700000000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Role</label>
                            <select name="role" class="form-select" required>
                                <option value="farmer">Smallholder Farmer</option>
                                <option value="loan_officer">SACCO Loan Officer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-agri w-100 py-2 fw-bold">Register Account</button>
                    </form>
                    <div class="text-center mt-3">
                        <small>Already have an account? <a href="login.php" class="text-success text-decoration-none">Login here</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>