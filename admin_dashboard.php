<?php
include 'includes/header.php';
require_role('admin');

$farmerCount = $conn->query("SELECT COUNT(*) c FROM farmers")->fetch_assoc()['c'];
$officerCount = $conn->query("SELECT COUNT(*) c FROM loan_officers")->fetch_assoc()['c'];
$deviceCount = $conn->query("SELECT COUNT(*) c FROM iot_devices WHERE status='online'")->fetch_assoc()['c'];
$totalDevices = $conn->query("SELECT COUNT(*) c FROM iot_devices")->fetch_assoc()['c'];
$readingsToday = $conn->query("SELECT COUNT(*) c FROM sensor_readings WHERE DATE(recorded_at)=CURDATE()")->fetch_assoc()['c'];
$pendingCount = $conn->query("SELECT COUNT(*) c FROM users WHERE account_status='pending'")->fetch_assoc()['c'];

$pending = $conn->query("SELECT * FROM users WHERE account_status='pending' ORDER BY created_at DESC LIMIT 5");
$recentDevices = $conn->query(
    "SELECT d.*, u.full_name FROM iot_devices d JOIN farmers f ON f.id=d.farmer_id JOIN users u ON u.id=f.user_id ORDER BY d.id DESC LIMIT 5"
);
?>

<div class="container-fluid bg-light py-4" style="min-height: 80vh;">
    <div class="container-fluid px-4">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h2 class="fw-bold m-0"><i class="fa-solid fa-server text-dark me-2"></i>System Command Center</h2>
            <div class="text-end">
                <span class="text-muted small d-block">Server Time: <?php echo date('Y-m-d H:i'); ?></span>
                <span class="badge bg-success"><span class="system-status-indicator bg-white me-1"></span> All Systems Operational</span>
            </div>
        </div>

        <?php render_flashes(); ?>

        <div class="row g-4">
            <div class="col-lg-2 col-md-3">
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-dark text-white py-3"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-bars-staggered me-2"></i>Menu</h6></div>
                    <div class="list-group list-group-flush admin-sidebar">
                        <a href="admin_dashboard.php" class="list-group-item active"><i class="fa-solid fa-gauge me-2"></i> Overview</a>
                        <a href="admin_users.php" class="list-group-item"><i class="fa-solid fa-users me-2"></i> User Management</a>
                        <a href="admin_people.php?type=farmer" class="list-group-item"><i class="fa-solid fa-tractor me-2"></i> Farmers &amp; Officers</a>
                        <a href="admin_devices.php" class="list-group-item"><i class="fa-solid fa-microchip me-2"></i> IoT Devices</a>
                        <a href="admin_data.php" class="list-group-item"><i class="fa-solid fa-database me-2"></i> Data Manager</a>
                        <a href="admin_approvals.php" class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-user-check me-2"></i> Approvals</span>
                            <span class="badge bg-danger rounded-pill"><?php echo $pendingCount; ?></span>
                        </a>
                        <a href="admin_reports.php" class="list-group-item"><i class="fa-solid fa-chart-column me-2"></i> System Reports</a>
                        <a href="admin_profile.php" class="list-group-item"><i class="fa-solid fa-user-gear me-2"></i> My Profile</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-10 col-md-9">
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><p class="text-muted mb-1 text-uppercase small fw-bold">Total Farmers</p><h3 class="fw-bold mb-0 text-dark"><?php echo $farmerCount; ?></h3></div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success"><i class="fa-solid fa-tractor fa-lg"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><p class="text-muted mb-1 text-uppercase small fw-bold">Loan Officers</p><h3 class="fw-bold mb-0 text-dark"><?php echo $officerCount; ?></h3></div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary"><i class="fa-solid fa-building-columns fa-lg"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><p class="text-muted mb-1 text-uppercase small fw-bold">Online IoT Nodes</p><h3 class="fw-bold mb-0 text-dark"><?php echo $deviceCount; ?>/<?php echo $totalDevices; ?></h3></div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info"><i class="fa-solid fa-wifi fa-lg"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><p class="text-muted mb-1 text-uppercase small fw-bold">Readings Today</p><h3 class="fw-bold mb-0 text-dark"><?php echo $readingsToday; ?></h3></div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning"><i class="fa-solid fa-cloud-arrow-up fa-lg"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                                <h5 class="mb-0 fw-bold">Action Required: Pending Registrations</h5>
                                <a href="admin_approvals.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-muted small text-uppercase">
                                            <tr><th>User Name</th><th>Role</th><th>Phone Number</th><th>Registered At</th><th class="text-end">Actions</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($pending->num_rows === 0): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-4">No pending registrations.</td></tr>
                                        <?php endif; ?>
                                        <?php while ($p = $pending->fetch_assoc()): ?>
                                            <tr>
                                                <td><div class="d-flex align-items-center"><div class="bg-secondary bg-opacity-10 rounded-circle p-2 me-2"><i class="fa-solid fa-user text-secondary"></i></div><span class="fw-bold"><?php echo h($p['full_name']); ?></span></div></td>
                                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success"><?php echo h($p['role']); ?></span></td>
                                                <td><?php echo h($p['phone_number']); ?></td>
                                                <td><?php echo h($p['created_at']); ?></td>
                                                <td class="text-end">
                                                    <a href="admin_approvals.php?approve=<?php echo $p['id']; ?>" class="btn btn-sm btn-success me-1" title="Approve"><i class="fa-solid fa-check"></i></a>
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

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 border-bottom-0"><h5 class="mb-0 fw-bold">Recently Registered Devices</h5></div>
                            <div class="card-body pt-0">
                                <ul class="list-group list-group-flush small">
                                <?php if ($recentDevices->num_rows === 0): ?>
                                    <li class="list-group-item px-0 text-muted">No devices registered yet.</li>
                                <?php endif; ?>
                                <?php while ($d = $recentDevices->fetch_assoc()): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?php echo h($d['mac_address']); ?></div>
                                            <?php echo h($d['full_name']); ?> &middot; <span class="badge bg-<?php echo $d['status']=='online'?'success':($d['status']=='maintenance'?'warning text-dark':'secondary'); ?>"><?php echo h($d['status']); ?></span>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                                </ul>
                                <div class="d-grid mt-3"><a href="admin_devices.php" class="btn btn-light btn-sm text-primary fw-bold">Manage All Devices</a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
