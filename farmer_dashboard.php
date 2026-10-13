<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

$activeSeason = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id AND status='active' ORDER BY start_date DESC LIMIT 1")->fetch_assoc();
$latestScore = $conn->query("SELECT * FROM agri_credit_scores WHERE farmer_id=$farmer_id ORDER BY last_updated DESC LIMIT 1")->fetch_assoc();
$latestReading = $conn->query(
    "SELECT sr.* FROM sensor_readings sr JOIN growing_seasons gs ON gs.id=sr.season_id
     WHERE gs.farmer_id=$farmer_id ORDER BY sr.recorded_at DESC LIMIT 1"
)->fetch_assoc();
$latestAdvisory = $conn->query("SELECT * FROM advisories WHERE farmer_id=$farmer_id ORDER BY generated_at DESC LIMIT 1")->fetch_assoc();
$deviceCount = $conn->query("SELECT COUNT(*) c FROM iot_devices WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];
?>

<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0"><i class="fa-solid fa-tractor text-success me-2"></i>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
            <span class="badge bg-<?php echo $activeSeason ? 'success' : 'secondary'; ?> p-2">
                Status: <?php echo $activeSeason ? 'Active Season (' . h($activeSeason['crop_type']) . ')' : 'No Active Season'; ?>
            </span>
        </div>

        <?php render_flashes(); ?>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card card-dashboard h-100 border-0 p-3">
                    <div class="card-body text-center">
                        <h5 class="text-muted text-uppercase fw-bold mb-3">AgroCreditScore</h5>
                        <?php if ($latestScore): ?>
                            <div class="display-3 fw-bold text-success mb-2"><?php echo $latestScore['score_value']; ?></div>
                            <span class="badge text-dark py-2 px-3 rounded-pill" style="background-color: var(--gold-tier);"><?php echo h($latestScore['tier']); ?> Tier</span>
                        <?php else: ?>
                            <div class="display-6 fw-bold text-muted mb-2">Not yet scored</div>
                            <a href="farmer_credit_score.php" class="btn btn-sm btn-outline-success">Calculate Score</a>
                        <?php endif; ?>
                        <p class="small text-muted mt-3">Computed via Random Forest classification of your seasonal IoT data.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card card-dashboard h-100 border-0 p-3">
                    <div class="card-body">
                        <h5 class="text-muted text-uppercase fw-bold mb-4">Latest Sensor Readings</h5>
                        <?php if ($latestReading): ?>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span><i class="fa-solid fa-droplet text-primary me-2"></i>Soil Moisture</span><span class="fw-bold"><?php echo h($latestReading['soil_moisture']); ?>%</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span><i class="fa-solid fa-temperature-half text-danger me-2"></i>Temperature</span><span class="fw-bold"><?php echo h($latestReading['temperature']); ?>°C</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fa-solid fa-cloud text-info me-2"></i>Humidity</span><span class="fw-bold"><?php echo h($latestReading['humidity']); ?>%</span>
                        </div>
                        <?php else: ?>
                            <p class="text-muted">No sensor data yet. You have <?php echo $deviceCount; ?> device(s) registered.</p>
                            <a href="farmer_sensor_data.php" class="btn btn-sm btn-outline-primary">Log a Reading</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card card-dashboard h-100 border-0 p-3" style="border-left-color: #0dcaf0;">
                    <div class="card-body">
                        <h5 class="text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-robot text-info me-2"></i>Latest AI Advisory</h5>
                        <?php if ($latestAdvisory): ?>
                        <div class="alert alert-info border-0 shadow-sm mb-2">
                            <?php echo h($latestAdvisory['advisory_text']); ?>
                        </div>
                        <span class="small text-muted"><?php echo h($latestAdvisory['generated_at']); ?></span>
                        <?php else: ?>
                            <p class="text-muted">No advisories yet.</p>
                            <a href="farmer_advisories.php" class="btn btn-sm btn-outline-info">Generate Advisory</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3"><a href="farmer_seasons.php" class="btn btn-outline-success w-100 py-3"><i class="fa-solid fa-calendar d-block fa-lg mb-1"></i>Manage Seasons</a></div>
            <div class="col-md-3"><a href="farmer_devices.php" class="btn btn-outline-success w-100 py-3"><i class="fa-solid fa-microchip d-block fa-lg mb-1"></i>My Devices (<?php echo $deviceCount; ?>)</a></div>
            <div class="col-md-3"><a href="farmer_crop_health.php" class="btn btn-outline-success w-100 py-3"><i class="fa-solid fa-leaf d-block fa-lg mb-1"></i>Crop Health History</a></div>
            <div class="col-md-3"><a href="farmer_profile.php" class="btn btn-outline-success w-100 py-3"><i class="fa-solid fa-user d-block fa-lg mb-1"></i>Edit Profile</a></div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
