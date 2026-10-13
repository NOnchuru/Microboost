<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

$history = $conn->query(
    "SELECT chc.*, sr.soil_moisture, sr.temperature, sr.humidity, sr.recorded_at, gs.crop_type
     FROM crop_health_classifications chc
     JOIN sensor_readings sr ON sr.id = chc.reading_id
     JOIN growing_seasons gs ON gs.id = sr.season_id
     WHERE gs.farmer_id = $farmer_id
     ORDER BY chc.analyzed_at DESC LIMIT 100"
);

$summary = $conn->query(
    "SELECT chc.health_status, COUNT(*) c FROM crop_health_classifications chc
     JOIN sensor_readings sr ON sr.id = chc.reading_id
     JOIN growing_seasons gs ON gs.id = sr.season_id
     WHERE gs.farmer_id=$farmer_id GROUP BY chc.health_status"
);
$counts = ['Healthy'=>0,'Monitor'=>0,'Critical'=>0];
while ($s = $summary->fetch_assoc()) $counts[$s['health_status']] = (int)$s['c'];
?>
<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-leaf text-success me-2"></i>Crop Health History</h2>
    <?php render_flashes(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3 text-center"><h6 class="text-muted">Healthy</h6><h2 class="text-success fw-bold"><?php echo $counts['Healthy']; ?></h2></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3 text-center"><h6 class="text-muted">Monitor</h6><h2 class="text-warning fw-bold"><?php echo $counts['Monitor']; ?></h2></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3 text-center"><h6 class="text-muted">Critical</h6><h2 class="text-danger fw-bold"><?php echo $counts['Critical']; ?></h2></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Analyzed At</th><th>Crop</th><th>Reading</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($history->num_rows === 0): ?><tr><td colspan="4" class="text-center text-muted py-4">No classifications yet. Log a sensor reading to trigger analysis.</td></tr><?php endif; ?>
                    <?php while ($r = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($r['analyzed_at']); ?></td>
                            <td><?php echo h($r['crop_type']); ?></td>
                            <td class="small text-muted">Moist <?php echo h($r['soil_moisture']); ?>% / Temp <?php echo h($r['temperature']); ?>°C / Hum <?php echo h($r['humidity']); ?>%</td>
                            <td><span class="badge <?php echo health_badge_class($r['health_status']); ?>"><?php echo h($r['health_status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
