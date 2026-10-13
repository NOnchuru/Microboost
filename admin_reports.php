<?php
include 'includes/header.php';
require_role('admin');

$counts = [];
foreach (['farmers'=>'farmers','loan_officers'=>'loan_officers','iot_devices'=>'iot_devices',
          'sensor_readings'=>'sensor_readings','advisories'=>'advisories',
          'agri_credit_scores'=>'agri_credit_scores','agri_credit_reports'=>'agri_credit_reports'] as $k=>$t) {
    $r = $conn->query("SELECT COUNT(*) c FROM $t")->fetch_assoc();
    $counts[$k] = (int)$r['c'];
}

$byTier = $conn->query("SELECT tier, COUNT(*) c FROM agri_credit_scores GROUP BY tier");
$tiers = ['Gold'=>0,'Silver'=>0,'Bronze'=>0];
while ($t = $byTier->fetch_assoc()) $tiers[$t['tier']] = (int)$t['c'];

$byHealth = $conn->query("SELECT health_status, COUNT(*) c FROM crop_health_classifications GROUP BY health_status");
$health = ['Healthy'=>0,'Monitor'=>0,'Critical'=>0];
while ($h = $byHealth->fetch_assoc()) $health[$h['health_status']] = (int)$h['c'];

$byCounty = $conn->query("SELECT county, COUNT(*) c FROM farmers GROUP BY county ORDER BY c DESC LIMIT 10");
$smsStats = $conn->query("SELECT delivery_status, COUNT(*) c FROM sms_delivery_logs GROUP BY delivery_status");
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-chart-column me-2"></i>System Reports</h2>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Total Farmers', $counts['farmers'], 'tractor', 'success'],
            ['Loan Officers', $counts['loan_officers'], 'building-columns', 'primary'],
            ['IoT Devices', $counts['iot_devices'], 'microchip', 'info'],
            ['Sensor Readings', $counts['sensor_readings'], 'droplet', 'secondary'],
            ['Advisories Sent', $counts['advisories'], 'robot', 'warning'],
            ['Credit Scores', $counts['agri_credit_scores'], 'star', 'dark'],
            ['Credit Reports', $counts['agri_credit_reports'], 'file-lines', 'danger'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted mb-1 text-uppercase small fw-bold"><?php echo $c[0]; ?></p><h3 class="fw-bold mb-0"><?php echo $c[1]; ?></h3></div>
                    <div class="bg-<?php echo $c[3]; ?> bg-opacity-10 p-3 rounded-circle text-<?php echo $c[3]; ?>"><i class="fa-solid fa-<?php echo $c[2]; ?> fa-lg"></i></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">AgroCreditScore Tier Distribution</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span>🥇 Gold</span><span class="fw-bold"><?php echo $tiers['Gold']; ?></span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>🥈 Silver</span><span class="fw-bold"><?php echo $tiers['Silver']; ?></span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>🥉 Bronze</span><span class="fw-bold"><?php echo $tiers['Bronze']; ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Crop Health Classifications</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span class="text-success">Healthy</span><span class="fw-bold"><?php echo $health['Healthy']; ?></span></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-warning">Monitor</span><span class="fw-bold"><?php echo $health['Monitor']; ?></span></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-danger">Critical</span><span class="fw-bold"><?php echo $health['Critical']; ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">SMS Delivery Status</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                    <?php while ($s = $smsStats->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between"><span><?php echo h($s['delivery_status']); ?></span><span class="fw-bold"><?php echo $s['c']; ?></span></li>
                    <?php endwhile; ?>
                    <?php if ($smsStats->num_rows === 0): ?><li class="list-group-item text-muted">No SMS sent yet.</li><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-bold">Farmers by County</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>County</th><th>Farmer Count</th></tr></thead>
                <tbody>
                <?php while ($c = $byCounty->fetch_assoc()): ?>
                    <tr><td><?php echo h($c['county']); ?></td><td><?php echo $c['c']; ?></td></tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
