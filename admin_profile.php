<?php
include 'includes/header.php';
require_role('admin');
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $department = trim($_POST['department'] ?? '');
    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone_number=? WHERE id=?");
    $stmt->bind_param("ssi", $full_name, $phone, $uid);
    $stmt->execute();
    $conn->query("UPDATE administrators SET department='" . $conn->real_escape_string($department) . "' WHERE user_id=$uid");

    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt2->bind_param("si", $hash, $uid);
        $stmt2->execute();
    }
    $_SESSION['full_name'] = $full_name;
    flash_set('success', 'Profile updated.');
    header("Location: admin_profile.php");
    exit();
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$admin = $conn->query("SELECT * FROM administrators WHERE user_id=$uid")->fetch_assoc();
if (!$admin) { $conn->query("INSERT INTO administrators (user_id) VALUES ($uid)"); $admin = ['department'=>'']; }
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3"><i class="fa-solid fa-user-gear me-2"></i>Admin Profile</div>
                <div class="card-body p-4">
                    <?php render_flashes(); ?>
                    <form method="POST">
                        <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?php echo h($user['full_name']); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone_number" value="<?php echo h($user['phone_number']); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Department</label><input class="form-control" name="department" value="<?php echo h($admin['department'] ?? ''); ?>"></div>
                        <div class="mb-3"><label class="form-label">New Password <span class="text-muted small">(optional)</span></label><input type="password" class="form-control" name="password"></div>
                        <button class="btn btn-agri w-100" type="submit">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
