<?php
include 'includes/header.php';
require_login();
$uid = (int)$_SESSION['user_id'];

$home = ['farmer' => 'farmer_profile.php', 'loan_officer' => 'lender_profile.php', 'admin' => 'admin_profile.php'][$_SESSION['role']] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $row = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
    if (!$row || !password_verify($current, $row['password'])) {
        flash_set('error', 'Your current password is incorrect.');
    } elseif (strlen($new) < 6) {
        flash_set('error', 'The new password must be at least 6 characters.');
    } elseif ($new !== $confirm) {
        flash_set('error', 'The new password and its confirmation do not match.');
    } elseif ($new === $current) {
        flash_set('error', 'Choose a password different from the current one.');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $st->bind_param("si", $hash, $uid);
        if ($st->execute()) {
            flash_set('success', 'Password changed successfully.');
            header("Location: $home");
            exit();
        }
        flash_set('error', 'Could not change the password. Please try again.');
    }
    header("Location: change_password.php");
    exit();
}
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3"><i class="fa-solid fa-key me-2"></i>Change Password</div>
                <div class="card-body p-4">
                    <?php render_flashes(); ?>
                    <form method="POST" onsubmit="return checkMatch();">
                        <div class="mb-3"><label class="form-label">Current password</label><input type="password" class="form-control" name="current_password" required></div>
                        <div class="mb-3"><label class="form-label">New password <span class="text-muted small">(at least 6 characters)</span></label><input type="password" class="form-control" name="new_password" id="np" minlength="6" required></div>
                        <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" class="form-control" name="confirm_password" id="cp" required></div>
                        <div class="text-danger small mb-2 d-none" id="pwerr">The passwords do not match.</div>
                        <button class="btn btn-agri w-100" type="submit">Change Password</button>
                        <a href="<?php echo $home; ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function checkMatch() {
    var ok = document.getElementById('np').value === document.getElementById('cp').value;
    document.getElementById('pwerr').classList.toggle('d-none', ok);
    return ok;
}
</script>
<?php include 'includes/footer.php'; ?>
