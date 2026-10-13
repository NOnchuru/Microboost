<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-0 rounded-3">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-seedling fa-3x text-success mb-2"></i>
                        <h3 class="fw-bold">Welcome Back</h3>
                        <p class="text-muted">Sign in to your MicroBoost dashboard</p>
                    </div>
                    <?php render_flashes(); ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <?php
                            $errors = [
                                'invalid' => 'Invalid login credentials.',
                                'pending' => 'Your account is still pending admin approval.',
                                'suspended' => 'Your account has been suspended. Contact support.',
                            ];
                            echo h($errors[$_GET['error']] ?? 'Login error.');
                            ?>
                        </div>
                    <?php endif; ?>
                    <form action="process_login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-agri w-100 py-2 fw-bold">Login</button>
                    </form>
                    <div class="text-center mt-4">
                        <small>New to MicroBoost? <a href="register.php" class="text-success text-decoration-none">Create an account</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
