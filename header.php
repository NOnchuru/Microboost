<?php
// Buffer output so pages can still redirect after this header has printed HTML
ob_start();
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroBoost | Smart Agriculture & Microfinance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <?php if (($_SESSION['role'] ?? null) === 'admin'): ?><link rel="stylesheet" href="assets/admin.css"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --agri-green: #2E7D32;
            --agri-light: #F1F8E9;
            --earth-brown: #5D4037;
            --gold-tier: #FFD700;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: var(--agri-green); }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: white; }
        .navbar-custom .nav-link.active { text-decoration: underline; font-weight: bold; }
        .btn-agri { background-color: var(--agri-green); color: white; }
        .btn-agri:hover { background-color: #1b5e20; color: white; }
        .card-dashboard { border-left: 4px solid var(--agri-green); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .hero-section {
            background: linear-gradient(rgba(46, 125, 50, 0.85), rgba(93, 64, 55, 0.85)), url('https://images.unsplash.com/photo-1592982537447-6f296d0542d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover;
            color: white; padding: 100px 0;
        }
        .admin-sidebar .list-group-item, .side-nav .list-group-item {
            border: none; padding: 12px 18px; color: #555; font-weight: 500; transition: all 0.2s;
        }
        .admin-sidebar .list-group-item:hover, .side-nav .list-group-item:hover {
            background-color: #e9ecef; color: var(--agri-green); border-left: 4px solid var(--agri-green);
        }
        .admin-sidebar .list-group-item.active, .side-nav .list-group-item.active {
            background-color: var(--agri-light); color: var(--agri-green);
            border-left: 4px solid var(--agri-green); font-weight: bold;
        }
    </style>
</head>
<?php $role = $_SESSION['role'] ?? null; $current = basename($_SERVER['PHP_SELF']); ?>
<body class="<?php echo $role === 'admin' ? 'admin-body' : ''; ?>">
<?php if ($role !== 'admin'): ?>
    <nav class="navbar navbar-expand-lg navbar-custom shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fa-solid fa-seedling me-2"></i>MicroBoost</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (!$role): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='index.php'?'active':''; ?>" href="index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='health_guide.php'?'active':''; ?>" href="health_guide.php"><i class="fa-solid fa-circle-info me-1"></i>Guide</a></li>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-outline-light ms-2" href="register.php">Sign Up</a></li>
                    <?php elseif ($role === 'farmer'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_dashboard.php'?'active':''; ?>" href="farmer_dashboard.php"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_seasons.php'?'active':''; ?>" href="farmer_seasons.php"><i class="fa-solid fa-calendar me-1"></i>Seasons</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_devices.php'?'active':''; ?>" href="farmer_devices.php"><i class="fa-solid fa-microchip me-1"></i>Devices</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_sensor_data.php'?'active':''; ?>" href="farmer_sensor_data.php"><i class="fa-solid fa-droplet me-1"></i>Sensor Data</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_crop_health.php'?'active':''; ?>" href="farmer_crop_health.php"><i class="fa-solid fa-leaf me-1"></i>Crop Health</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_credit_score.php'?'active':''; ?>" href="farmer_credit_score.php"><i class="fa-solid fa-star me-1"></i>Credit Score</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_advisories.php'?'active':''; ?>" href="farmer_advisories.php"><i class="fa-solid fa-robot me-1"></i>Advisories</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='farmer_profile.php'?'active':''; ?>" href="farmer_profile.php"><i class="fa-solid fa-user me-1"></i>Profile</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='health_guide.php'?'active':''; ?>" href="health_guide.php"><i class="fa-solid fa-circle-info me-1"></i>Guide</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a></li>
                    <?php elseif ($role === 'loan_officer'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='lender_dashboard.php'?'active':''; ?>" href="lender_dashboard.php"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='lender_reports.php'?'active':''; ?>" href="lender_reports.php"><i class="fa-solid fa-file-lines me-1"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='lender_profile.php'?'active':''; ?>" href="lender_profile.php"><i class="fa-solid fa-user me-1"></i>Profile</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='health_guide.php'?'active':''; ?>" href="health_guide.php"><i class="fa-solid fa-circle-info me-1"></i>Guide</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a></li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_dashboard.php'?'active':''; ?>" href="admin_dashboard.php"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_users.php'?'active':''; ?>" href="admin_users.php"><i class="fa-solid fa-users me-1"></i>Users</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_people.php'?'active':''; ?>" href="admin_people.php?type=farmer"><i class="fa-solid fa-tractor me-1"></i>Farmers/Officers</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_data.php'?'active':''; ?>" href="admin_data.php"><i class="fa-solid fa-database me-1"></i>Data</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_approvals.php'?'active':''; ?>" href="admin_approvals.php"><i class="fa-solid fa-user-check me-1"></i>Approvals</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_devices.php'?'active':''; ?>" href="admin_devices.php"><i class="fa-solid fa-microchip me-1"></i>Devices</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_reports.php'?'active':''; ?>" href="admin_reports.php"><i class="fa-solid fa-chart-column me-1"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_demo.php'?'active':''; ?>" href="admin_demo.php"><i class="fa-solid fa-flask me-1"></i>Demo</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='admin_profile.php'?'active':''; ?>" href="admin_profile.php"><i class="fa-solid fa-user me-1"></i>Profile</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current=='health_guide.php'?'active':''; ?>" href="health_guide.php"><i class="fa-solid fa-circle-info me-1"></i>Guide</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
<?php else:
    $pendingBadge = 0;
    $pr = $conn->query("SELECT COUNT(*) c FROM users WHERE account_status='pending'");
    if ($pr) { $pendingBadge = (int)$pr->fetch_assoc()['c']; }
    $adminName = $_SESSION['full_name'] ?? 'Administrator';
    $navGroups = [
        'Overview' => [
            ['admin_dashboard.php', 'fa-gauge', 'Dashboard', ['admin_dashboard.php']],
        ],
        'People' => [
            ['admin_users.php', 'fa-users', 'Users', ['admin_users.php']],
            ['admin_people.php?type=farmer', 'fa-tractor', 'Farmers & Officers', ['admin_people.php']],
            ['admin_approvals.php', 'fa-user-check', 'Approvals', ['admin_approvals.php']],
        ],
        'Monitoring' => [
            ['admin_devices.php', 'fa-microchip', 'IoT Devices', ['admin_devices.php']],
            ['admin_data.php', 'fa-database', 'Data Manager', ['admin_data.php']],
            ['admin_reports.php', 'fa-chart-column', 'System Reports', ['admin_reports.php']],
        ],
        'System' => [
            ['admin_demo.php', 'fa-flask', 'Demo &amp; Gemini', ['admin_demo.php']],
            ['health_guide.php', 'fa-circle-info', 'Crop Health Guide', ['health_guide.php']],
            ['admin_profile.php', 'fa-user-gear', 'My Profile', ['admin_profile.php']],
        ],
    ];
?>
    <header class="admin-topbar">
        <button class="btn btn-sm menu-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSide" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
        <a class="brand" href="admin_dashboard.php"><i class="fa-solid fa-seedling"></i>MicroBoost <span class="fw-normal opacity-75">Admin</span></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="who d-none d-md-inline"><i class="fa-regular fa-circle-user me-1"></i><?php echo h($adminName); ?></span>
            <a class="top-link" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </header>
    <div class="admin-shell">
        <aside class="admin-side offcanvas-lg offcanvas-start" tabindex="-1" id="adminSide" aria-label="Admin menu">
            <div class="offcanvas-header d-lg-none">
                <h6 class="offcanvas-title fw-bold">Menu</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminSide" aria-label="Close"></button>
            </div>
            <div class="admin-side-inner">
                <?php foreach ($navGroups as $groupName => $items): ?>
                    <div class="side-group">
                        <div class="side-label"><?php echo $groupName; ?></div>
                        <?php foreach ($items as [$href, $icon, $label, $files]): ?>
                            <a class="side-link <?php echo in_array($current, $files, true) ? 'active' : ''; ?>" href="<?php echo $href; ?>">
                                <i class="fa-solid <?php echo $icon; ?>"></i><span><?php echo $label; ?></span>
                                <?php if ($href === 'admin_approvals.php' && $pendingBadge > 0): ?><span class="badge rounded-pill bg-danger"><?php echo $pendingBadge; ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
        <main class="admin-main">
<?php endif; ?>
