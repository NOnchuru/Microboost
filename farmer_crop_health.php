<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

$base = "FROM crop_health_classifications chc
         JOIN sensor_readings sr ON sr.id = chc.reading_id
         JOIN growing_seasons gs ON gs.id = sr.season_id
         WHERE gs.farmer_id = $farmer_id";

$showAll = isset($_GET['all']);
$tableLimit = $showAll ? 50 : 8;

$rows = [];
$res = $conn->query("SELECT chc.health_status, chc.analyzed_at, sr.soil_moisture, sr.temperature, sr.humidity, sr.recorded_at, gs.crop_type $base ORDER BY chc.analyzed_at DESC LIMIT 50");
while ($r = $res->fetch_assoc()) $rows[] = $r;

$totalAnalysed = (int)$conn->query("SELECT COUNT(*) c $base")->fetch_assoc()['c'];
$current = $rows[0] ?? null;
$currentCls = $current ? classify_crop_health($current['soil_moisture'], $current['temperature'], $current['humidity'], $current['crop_type']) : null;

// Summary of the most recent 20 analyses, not the whole history
$recent = array_slice($rows, 0, 20);
$counts = ['Healthy' => 0, 'Monitor' => 0, 'Critical' => 0];
foreach ($recent as $r) $counts[$r['health_status']]++;
$chart = array_reverse($recent);

$tone = ['Healthy' => ['success', 'fa-circle-check'], 'Monitor' => ['warning', 'fa-triangle-exclamation'], 'Critical' => ['danger', 'fa-circle-exclamation']];
?>
<div class="container my-5" style="max-width:1000px">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-leaf text-success me-2"></i>Crop Health</h2>
            <div class="text-muted small">Latest status first, with a short recent history.</div>
        </div>
        <?php if ($current): ?><span class="text-muted small"><?php echo number_format($totalAnalysed); ?> readings analysed in total</span><?php endif; ?>
    </div>
    <?php render_flashes(); ?>

    <?php if (!$current): ?>
        <div class="card border-0 shadow-sm p-5 text-center text-muted">No readings have been analysed yet. Once a sensor reading arrives, your crop health shows here.</div>
    <?php else:
        [$colour, $icon] = $tone[$current['health_status']] ?? ['secondary', 'fa-circle-question'];
    ?>
    <div class="card border-0 shadow-sm mb-4" style="border-left:6px solid var(--bs-<?php echo $colour; ?>) !important">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-md-4 text-center text-md-start">
                    <div class="text-muted text-uppercase small fw-bold mb-1">Current status</div>
                    <div class="display-6 fw-bold text-<?php echo $colour; ?>"><i class="fa-solid <?php echo $icon; ?> me-2"></i><?php echo h($current['health_status']); ?></div>
                    <div class="text-muted small mt-1"><?php echo h($current['crop_type']); ?> &middot; <?php echo date('d M Y, H:i', strtotime($current['recorded_at'])); ?></div>
                </div>
                <div class="col-md-5">
                    <div class="row text-center g-2">
                        <div class="col-4"><div class="bg-light rounded-3 py-3"><div class="text-muted small">Soil moisture</div><div class="fs-4 fw-bold"><?php echo h($current['soil_moisture']); ?>%</div></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 py-3"><div class="text-muted small">Temperature</div><div class="fs-4 fw-bold"><?php echo h($current['temperature']); ?>°C</div></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 py-3"><div class="text-muted small">Humidity</div><div class="fs-4 fw-bold"><?php echo h($current['humidity']); ?>%</div></div></div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small">Health score</div>
                    <div class="display-6 fw-bold"><?php echo $currentCls['score']; ?><span class="fs-6 text-muted">/100</span></div>
                </div>
            </div>
            <?php if (!empty($currentCls['reasons'])): ?>
            <hr class="my-3">
            <div class="small fw-bold text-muted mb-1">Why this status</div>
            <ul class="mb-0 small"><?php foreach ($currentCls['reasons'] as $why): ?><li><?php echo h($why); ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-bold mb-3">Last <?php echo count($recent); ?> readings</div>
                    <?php foreach (['Healthy' => 'success', 'Monitor' => 'warning', 'Critical' => 'danger'] as $label => $c):
                        $pct = count($recent) ? round($counts[$label] / count($recent) * 100) : 0; ?>
                        <div class="d-flex justify-content-between small mb-1"><span><?php echo $label; ?></span><span class="text-muted"><?php echo $counts[$label]; ?></span></div>
                        <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-<?php echo $c; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-bold mb-2">Soil moisture trend</div>
                    <canvas id="moistChart" height="110"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Recent readings</span>
            <?php if ($showAll): ?><a href="farmer_crop_health.php" class="small text-decoration-none">Show fewer</a>
            <?php elseif (count($rows) > $tableLimit): ?><a href="farmer_crop_health.php?all=1" class="small text-decoration-none">Show more</a><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Time</th><th>Crop</th><th>Soil</th><th>Temp</th><th>Humidity</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($rows, 0, $tableLimit) as $r): ?>
                    <tr>
                        <td class="small"><?php echo date('d M, H:i', strtotime($r['analyzed_at'])); ?></td>
                        <td><?php echo h($r['crop_type']); ?></td>
                        <td><?php echo h($r['soil_moisture']); ?>%</td>
                        <td><?php echo h($r['temperature']); ?>°C</td>
                        <td><?php echo h($r['humidity']); ?>%</td>
                        <td><span class="badge <?php echo health_badge_class($r['health_status']); ?>"><?php echo h($r['health_status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php if ($current): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('moistChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(fn($r) => date('H:i', strtotime($r['recorded_at'])), $chart)); ?>,
        datasets: [{ label: 'Soil moisture %', data: <?php echo json_encode(array_map(fn($r) => (float)$r['soil_moisture'], $chart)); ?>,
                     borderColor: '#2E7D32', backgroundColor: 'rgba(46,125,50,.12)', fill: true, tension: .3, pointRadius: 3 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } }
});
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
