<?php
include 'includes/header.php';
require_role('loan_officer');
$uid = $_SESSION['user_id'];
$officer_id = get_loan_officer_id_by_user($conn, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $sacco_name = trim($_POST['sacco_name']);
    $employee_no = trim($_POST['employee_no']);

    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone_number=? WHERE id=?");
    $stmt->bind_param("ssi", $full_name, $phone, $uid);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE loan_officers SET sacco_name=?, employee_no=? WHERE id=?");
    $stmt2->bind_param("ssi", $sacco_name, $employee_no, $officer_id);
    $stmt2->execute();

    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt3 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt3->bind_param("si", $hash, $uid);
        $stmt3->execute();
    }

    $_SESSION['full_name'] = $full_name;
    flash_set('success', 'Profile updated.');
    header("Location: lender_profile.php");
    exit();
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$officer = $conn->query("SELECT * FROM loan_officers WHERE id=$officer_id")->fetch_assoc();
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3"><i class="fa-solid fa-user me-2"></i>My Profile</div>
                <div class="card-body p-4">
                    <?php render_flashes(); ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?php echo h($user['full_name']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone_number" value="<?php echo h($user['phone_number']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label">SACCO Name</label><input class="form-control" name="sacco_name" value="<?php echo h($officer['sacco_name']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Employee No.</label><input class="form-control" name="employee_no" value="<?php echo h($officer['employee_no']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label">New Password <span class="text-muted small">(optional)</span></label><input type="password" class="form-control" name="password"></div>
                        </div>
                        <button class="btn btn-agri w-100 mt-4" type="submit">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
