<?php
include 'includes/header.php';
require_role('admin');

if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $conn->query("UPDATE users SET account_status='active' WHERE id=$id");
    flash_set('success', 'Farmer approved and account activated.');
    header("Location: admin_approvals.php");
    exit();
}
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $conn->query("UPDATE users SET account_status='suspended' WHERE id=$id");
    flash_set('info', 'Registration rejected (account suspended).');
    header("Location: admin_approvals.php");
    exit();
}

$pending = $conn->query(
    "SELECT u.*, f.county, f.primary_crop, f.farm_size_acres
     FROM users u JOIN farmers f ON f.user_id = u.id
     WHERE u.account_status = 'pending' ORDER BY u.created_at ASC"
);
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-user-check me-2"></i>Pending Farmer Approvals</h2>
    <?php render_flashes(); ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Phone</th><th>County</th><th>Primary Crop</th><th>Farm Size</th><th>Registered</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($pending->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No pending registrations. All caught up!</td></tr>
                    <?php endif; ?>
                    <?php while ($p = $pending->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?php echo h($p['full_name']); ?></td>
                            <td><?php echo h($p['phone_number']); ?></td>
                            <td><?php echo h($p['county']); ?></td>
                            <td><?php echo h($p['primary_crop']); ?></td>
                            <td><?php echo h($p['farm_size_acres']); ?> acres</td>
                            <td class="small text-muted"><?php echo h($p['created_at']); ?></td>
                            <td class="text-end">
                                <a href="admin_approvals.php?approve=<?php echo $p['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this farmer?');"><i class="fa-solid fa-check"></i> Approve</a>
                                <a href="admin_approvals.php?reject=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this registration?');"><i class="fa-solid fa-xmark"></i> Reject</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
