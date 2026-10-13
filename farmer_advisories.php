<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);
$user = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM advisories WHERE id=$did AND farmer_id=$farmer_id");
    flash_set('success', 'Advisory deleted.');
    header("Location: farmer_advisories.php");
    exit();
}

if (isset($_GET['generate'])) {
    $season_id = (int)$_GET['generate'];
    $season = $conn->query("SELECT * FROM growing_seasons WHERE id=$season_id AND farmer_id=$farmer_id")->fetch_assoc();
    if ($season) {
        $latestReading = $conn->query(
            "SELECT sr.*, chc.health_status FROM sensor_readings sr
             LEFT JOIN crop_health_classifications chc ON chc.reading_id = sr.id
             WHERE sr.season_id=$season_id ORDER BY sr.recorded_at DESC LIMIT 1"
        )->fetch_assoc();

        $health_status = $latestReading['health_status'] ?? 'Monitor';
        $text = generate_advisory_text(
            $health_status,
            $season['crop_type'],
            $latestReading['soil_moisture'] ?? null,
            $latestReading['temperature'] ?? null
        );

        $stmt = $conn->prepare("INSERT INTO advisories (farmer_id, advisory_text) VALUES (?, ?)");
        $stmt->bind_param("is", $farmer_id, $text);
        $stmt->execute();
        $advisory_id = $conn->insert_id;

        send_sms_advisory($conn, $advisory_id, $user['phone_number'], $text);
        flash_set('success', 'New AI advisory generated and SMS sent (simulated).');
    } else {
        flash_set('error', 'Invalid season.');
    }
    header("Location: farmer_advisories.php");
    exit();
}

$seasons = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id AND status='active' ORDER BY start_date DESC");
$seasonList = []; while ($s = $seasons->fetch_assoc()) $seasonList[] = $s;

$advisories = $conn->query(
    "SELECT a.*, sl.delivery_status, sl.sent_at FROM advisories a
     LEFT JOIN sms_delivery_logs sl ON sl.advisory_id = a.id
     WHERE a.farmer_id = $farmer_id ORDER BY a.generated_at DESC LIMIT 50"
);
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-robot text-info me-2"></i>AI Advisories</h2>
        <div class="dropdown">
            <button class="btn btn-agri dropdown-toggle" data-bs-toggle="dropdown" <?php echo empty($seasonList)?'disabled':''; ?>><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate New Advisory</button>
            <ul class="dropdown-menu">
                <?php foreach ($seasonList as $s): ?>
                    <li><a class="dropdown-item" href="farmer_advisories.php?generate=<?php echo $s['id']; ?>">For <?php echo h($s['crop_type']); ?> season</a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php render_flashes(); ?>

    <div class="row g-3">
        <?php if ($advisories->num_rows === 0): ?>
            <div class="col-12"><div class="alert alert-info">No advisories yet. Generate one from an active season above.</div></div>
        <?php endif; ?>
        <?php while ($a = $advisories->fetch_assoc()): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0dcaf0;">
                <div class="card-body">
                    <p class="mb-2"><?php echo h($a['advisory_text']); ?></p>
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span><?php echo h($a['generated_at']); ?> <a href="farmer_advisories.php?delete=<?php echo $a['id']; ?>" class="text-danger ms-2" onclick="return confirm('Delete this advisory?');"><i class="fa-solid fa-trash"></i></a></span>
                        <?php if ($a['delivery_status']): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-sms me-1"></i><?php echo h($a['delivery_status']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
