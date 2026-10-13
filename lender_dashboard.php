<?php
include 'includes/header.php';
require_role('loan_officer');

$search = trim($_GET['q'] ?? '');
$sql = "SELECT f.*, u.full_name, u.phone_number,
        (SELECT score_value FROM agri_credit_scores WHERE farmer_id=f.id ORDER BY last_updated DESC LIMIT 1) latest_score,
        (SELECT tier FROM agri_credit_scores WHERE farmer_id=f.id ORDER BY last_updated DESC LIMIT 1) latest_tier,
        (SELECT COUNT(*) FROM iot_devices WHERE farmer_id=f.id) device_count
        FROM farmers f JOIN users u ON u.id = f.user_id
        WHERE u.account_status='active'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (u.full_name LIKE '%$s%' OR u.phone_number LIKE '%$s%' OR f.id = '" . (int)$search . "')";
}
$sql .= " ORDER BY latest_score DESC";
$farmers = $conn->query($sql);
?>
<div class="container-fluid bg-light py-4">
    <div class="container">
        <h2 class="fw-bold mb-4"><i class="fa-solid fa-building-columns text-primary me-2"></i>Lender Portal</h2>

        <?php render_flashes(); ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form class="d-flex" method="GET">
                    <input class="form-control me-2" type="search" name="q" value="<?php echo h($search); ?>" placeholder="Search farmer by ID, Name, or Phone">
                    <button class="btn btn-primary px-4" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Farmer Profiles &amp; Creditworthiness</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Farmer</th><th>County</th><th>Primary Crop</th><th>AgroCreditScore</th><th>IoT Devices</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($farmers->num_rows === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No matching farmers found.</td></tr>
                        <?php endif; ?>
                        <?php while ($f = $farmers->fetch_assoc()): ?>
                            <tr>
                                <td><span class="fw-bold"><?php echo h($f['full_name']); ?></span><br><span class="small text-muted"><?php echo h($f['phone_number']); ?></span></td>
                                <td><?php echo h($f['county']); ?></td>
                                <td><?php echo h($f['primary_crop']); ?></td>
                                <td>
                                    <?php if ($f['latest_score'] !== null): ?>
                                        <span class="badge <?php echo tier_badge_class($f['latest_tier']); ?> fs-6"><?php echo $f['latest_score']; ?> (<?php echo h($f['latest_tier']); ?>)</span>
                                    <?php else: ?><span class="text-muted small">Not scored</span><?php endif; ?>
                                </td>
                                <td><?php echo $f['device_count']; ?></td>
                                <td>
                                    <a href="lender_farmer_view.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye me-1"></i>View</a>
                                    <a href="lender_reports.php?generate=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-file-pdf me-1"></i>Report</a>
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
