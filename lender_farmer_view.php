<?php
include 'includes/header.php';
require_role('loan_officer');

$farmer_id = (int)($_GET['id'] ?? 0);
$farmer = $conn->query(
    "SELECT f.*, u.full_name, u.phone_number, u.created_at FROM farmers f JOIN users u ON u.id=f.user_id WHERE f.id=$farmer_id"
)->fetch_assoc();

if (!$farmer) {
    flash_set('error', 'Farmer not found.');
    header("Location: lender_dashboard.php");
    exit();
}

$seasons = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id ORDER BY start_date DESC");
$scores = $conn->query(
    "SELECT acs.*, gs.crop_type FROM agri_credit_scores acs JOIN growing_seasons gs ON gs.id=acs.season_id
     WHERE acs.farmer_id=$farmer_id ORDER BY acs.last_updated DESC"
);
$advisories = $conn->query("SELECT * FROM advisories WHERE farmer_id=$farmer_id ORDER BY generated_at DESC LIMIT 10");
$health = $conn->query(
    "SELECT chc.health_status, COUNT(*) c FROM crop_health_classifications chc
     JOIN sensor_readings sr ON sr.id=chc.reading_id JOIN growing_seasons gs ON gs.id=sr.season_id
     WHERE gs.farmer_id=$farmer_id GROUP BY chc.health_status"
);
$healthCounts = ['Healthy'=>0,'Monitor'=>0,'Critical'=>0];
while ($h = $health->fetch_assoc()) $healthCounts[$h['health_status']] = (int)$h['c'];
$devices = $conn->query("SELECT * FROM iot_devices WHERE farmer_id=$farmer_id");
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold m-0"><?php echo h($farmer['full_name']); ?></h2>
            <p class="text-muted mb-0"><?php echo h($farmer['phone_number']); ?> &middot; <?php echo h($farmer['county']); ?> &middot; Farmer since <?php echo h($farmer['created_at']); ?></p>
        </div>
        <div>
            <a href="lender_reports.php?generate=<?php echo $farmer_id; ?>" class="btn btn-danger"><i class="fa-regular fa-file-pdf me-1"></i>Generate Credit Report</a>
            <a href="lender_dashboard.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <?php render_flashes(); ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 text-center h-100">
                <h6 class="text-muted text-uppercase fw-bold">Primary Crop</h6>
                <h4 class="fw-bold"><?php echo h($farmer['primary_crop']); ?></h4>
                <p class="text-muted small mb-0"><?php echo h($farmer['farm_size_acres']); ?> acres</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 text-center h-100">
                <h6 class="text-muted text-uppercase fw-bold">Crop Health Record</h6>
                <div class="d-flex justify-content-around mt-2">
                    <div><span class="badge bg-success"><?php echo $healthCounts['Healthy']; ?></span><br><small>Healthy</small></div>
                    <div><span class="badge bg-warning text-dark"><?php echo $healthCounts['Monitor']; ?></span><br><small>Monitor</small></div>
                    <div><span class="badge bg-danger"><?php echo $healthCounts['Critical']; ?></span><br><small>Critical</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 text-center h-100">
                <h6 class="text-muted text-uppercase fw-bold">IoT Devices</h6>
                <h4 class="fw-bold"><?php echo $devices->num_rows; ?> registered</h4>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">AgroCreditScore History</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Season</th><th>Score</th><th>Tier</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php if ($scores->num_rows===0): ?><tr><td colspan="4" class="text-center text-muted py-3">No scores yet.</td></tr><?php endif; ?>
                        <?php while ($s = $scores->fetch_assoc()): ?>
                            <tr><td><?php echo h($s['crop_type']); ?></td><td class="fw-bold"><?php echo $s['score_value']; ?></td><td><span class="badge <?php echo tier_badge_class($s['tier']); ?>"><?php echo h($s['tier']); ?></span></td><td class="small"><?php echo h($s['last_updated']); ?></td></tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Growing Seasons</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Crop</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($seasons->num_rows===0): ?><tr><td colspan="4" class="text-center text-muted py-3">No seasons yet.</td></tr><?php endif; ?>
                        <?php while ($s = $seasons->fetch_assoc()): ?>
                            <tr><td><?php echo h($s['crop_type']); ?></td><td><?php echo h($s['start_date']); ?></td><td><?php echo h($s['end_date'] ?? '—'); ?></td><td><span class="badge bg-secondary"><?php echo h($s['status']); ?></span></td></tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-bold">Recent AI Advisories</div>
        <div class="card-body">
            <?php if ($advisories->num_rows===0): ?><p class="text-muted mb-0">No advisories issued yet.</p><?php endif; ?>
            <?php while ($a = $advisories->fetch_assoc()): ?>
                <div class="alert alert-light border mb-2"><?php echo h($a['advisory_text']); ?> <span class="small text-muted d-block"><?php echo h($a['generated_at']); ?></span></div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
