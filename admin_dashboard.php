<?php
include 'includes/header.php';
require_role('admin');

$farmerCount = $conn->query("SELECT COUNT(*) c FROM farmers")->fetch_assoc()['c'];
$officerCount = $conn->query("SELECT COUNT(*) c FROM loan_officers")->fetch_assoc()['c'];
$deviceCount = $conn->query("SELECT COUNT(*) c FROM iot_devices d WHERE d.status<>'maintenance' AND " . DEVICE_SECS_SQL . " <= " . (DEVICE_OFFLINE_MINUTES * 60))->fetch_assoc()['c'];
$maintDevices = $conn->query("SELECT COUNT(*) c FROM iot_devices WHERE status='maintenance'")->fetch_assoc()['c'];
$totalDevices = $conn->query("SELECT COUNT(*) c FROM iot_devices")->fetch_assoc()['c'];
$readingsToday = $conn->query("SELECT COUNT(*) c FROM sensor_readings WHERE DATE(recorded_at)=CURDATE()")->fetch_assoc()['c'];
$criticalFarmers = $conn->query("SELECT COUNT(DISTINCT gs.farmer_id) c FROM crop_health_classifications chc JOIN sensor_readings sr ON sr.id=chc.reading_id JOIN growing_seasons gs ON gs.id=sr.season_id WHERE chc.health_status='Critical' AND sr.recorded_at > (NOW() - INTERVAL 24 HOUR)")->fetch_assoc()['c'];
$pendingCount = $conn->query("SELECT COUNT(*) c FROM users WHERE account_status='pending'")->fetch_assoc()['c'];

$pending = $conn->query("SELECT * FROM users WHERE account_status='pending' ORDER BY created_at DESC LIMIT 5");
$offlineDevices = max(0, $totalDevices - $deviceCount - $maintDevices);
$recentDevices = $conn->query(
    "SELECT d.*, " . DEVICE_SECS_SQL . " AS secs_since, u.full_name FROM iot_devices d JOIN farmers f ON f.id=d.farmer_id JOIN users u ON u.id=f.user_id ORDER BY d.id DESC LIMIT 4"
);
?>


<div class="page-head">
    <div>
        <h2><i class="fa-solid fa-gauge me-2"></i>Overview</h2>
        <p class="sub">What needs your attention right now.</p>
    </div>
    <div class="text-end">
        <span class="text-muted small d-block mb-1">Server time: <?php echo date('Y-m-d H:i'); ?></span>
        <?php if ($offlineDevices > 0): ?>
            <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i><?php echo $offlineDevices; ?> device<?php echo $offlineDevices === 1 ? '' : 's'; ?> offline</span>
        <?php else: ?>
            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>All devices reporting</span>
        <?php endif; ?>
    </div>
</div>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['Farmers', $farmerCount, 'fa-tractor', 'success', 'admin_people.php?type=farmer'],
        ['Loan officers', $officerCount, 'fa-building-columns', 'primary', 'admin_people.php?type=officer'],
        ['Online devices', $deviceCount . ' / ' . $totalDevices, 'fa-wifi', 'info', 'admin_devices.php'],
        ['Readings today', $readingsToday, 'fa-cloud-arrow-up', 'warning', 'admin_data.php?tab=readings'],
    ];
    foreach ($kpis as [$label, $value, $icon, $tone, $link]): ?>
    <div class="col-6 col-xl-3">
        <a href="<?php echo $link; ?>" class="text-decoration-none text-reset">
            <div class="card kpi h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="kpi-label"><?php echo $label; ?></p>
                        <p class="kpi-value"><?php echo $value; ?></p>
                    </div>
                    <div class="kpi-icon bg-<?php echo $tone; ?> bg-opacity-10 text-<?php echo $tone; ?>"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Pending registrations</span>
                <a href="admin_approvals.php" class="btn btn-sm btn-outline-secondary">View all<?php echo $pendingCount > 5 ? ' (' . $pendingCount . ')' : ''; ?></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Name</th><th>Role</th><th>Phone</th><th>Registered</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($pending->num_rows === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No pending registrations.</td></tr>
                        <?php endif; ?>
                        <?php while ($p = $pending->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($p['full_name']); ?></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?php echo h($p['role']); ?></span></td>
                                <td><?php echo h($p['phone_number']); ?></td>
                                <td class="text-muted"><?php echo h(substr($p['created_at'], 0, 10)); ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="admin_approvals.php?approve=<?php echo $p['id']; ?>" class="btn btn-sm btn-success" title="Approve"><i class="fa-solid fa-check"></i></a>
                                    <a href="admin_approvals.php?reject=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Reject"><i class="fa-solid fa-xmark"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header fw-bold">Needs attention</div>
            <div class="card-body p-0">
                <div class="attn-row">
                    <span class="attn-count <?php echo $criticalFarmers ? 'text-danger' : 'text-muted'; ?>"><?php echo $criticalFarmers; ?></span>
                    <div class="attn-text">Farmers with a Critical reading in the last 24 hours<br><a href="admin_data.php?tab=health">View crop health records</a></div>
                </div>
                <div class="attn-row">
                    <span class="attn-count <?php echo $offlineDevices ? 'text-warning' : 'text-muted'; ?>"><?php echo $offlineDevices; ?></span>
                    <div class="attn-text">Devices with no reading for <?php echo DEVICE_OFFLINE_MINUTES; ?> minutes<br><a href="admin_devices.php">Manage devices</a></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Recent devices</span>
                <a href="admin_devices.php" class="small">Manage</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recentDevices->num_rows === 0): ?>
                    <div class="dev-row text-muted">No devices registered yet.</div>
                <?php endif; ?>
                <?php while ($d = $recentDevices->fetch_assoc()): $ds = device_status($d['status'], $d['secs_since']); ?>
                    <div class="dev-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="mac"><?php echo h($d['mac_address']); ?></span>
                            <span class="badge <?php echo $ds['class']; ?>"><?php echo h($ds['label']); ?></span>
                        </div>
                        <div class="text-muted"><?php echo h($d['full_name']); ?> &middot; last reading <?php echo h(secs_ago($d['secs_since'])); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="assets/autorefresh.js"></script>
<script>initAutoRefresh({seconds: 10});</script>
<?php include 'includes/footer.php'; ?>
