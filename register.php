<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-white text-center py-4 border-0">
                    <h3 class="fw-bold" style="color: var(--agri-green);">Join MicroBoost</h3>
                    <p class="text-muted mb-0">Create your account to start monitoring or lending.</p>
                </div>
                <div class="card-body p-4">
                    <?php render_flashes(); ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
                    <?php endif; ?>
                    <form action="process_register.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone_number" class="form-control" placeholder="+254700000000" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Role</label>
                                <select name="role" id="roleSelect" class="form-select" required onchange="toggleRoleFields()">
                                    <option value="farmer">Smallholder Farmer</option>
                                    <option value="loan_officer">SACCO Loan Officer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>

                        <div id="farmerFields" class="border rounded-3 p-3 mt-3 bg-light">
                            <h6 class="fw-bold text-muted">Farmer Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">County</label>
                                    <input type="text" name="county" class="form-control" placeholder="e.g. Kiambu">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Primary Crop</label>
                                    <input type="text" name="primary_crop" class="form-control" placeholder="e.g. Maize">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Farm Size (acres)</label>
                                    <input type="number" step="0.01" name="farm_size_acres" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div id="officerFields" class="border rounded-3 p-3 mt-3 bg-light d-none">
                            <h6 class="fw-bold text-muted">Loan Officer Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">SACCO Name</label>
                                    <input type="text" name="sacco_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Employee No.</label>
                                    <input type="text" name="employee_no" class="form-control">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-agri w-100 py-2 fw-bold mt-4">Register Account</button>
                    </form>
                    <div class="text-center mt-3">
                        <small>Already have an account? <a href="login.php" class="text-success text-decoration-none">Login here</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRoleFields() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('farmerFields').classList.toggle('d-none', role !== 'farmer');
    document.getElementById('officerFields').classList.toggle('d-none', role !== 'loan_officer');
}
</script>

<?php include 'includes/footer.php'; ?>
