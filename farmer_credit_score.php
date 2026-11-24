<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM agri_credit_scores WHERE id=$did AND farmer_id=$farmer_id");
    flash_set('success', 'Score record deleted.');
    header("Location: farmer_credit_score.php");
    exit();
}

if (isset($_GET['calculate'])) {
    $season_id = (int)$_GET['calculate'];
    $owns = $conn->query("SELECT id FROM growing_seasons WHERE id=$season_id AND farmer_id=$farmer_id")->num_rows > 0;
    if ($owns) {
        $result = compute_credit_score($conn, $season_id);
        $existing = $conn->query("SELECT id FROM agri_credit_scores WHERE farmer_id=$farmer_id AND season_id=$season_id")->fetch_assoc();
        if ($existing) {
            $stmt = $conn->prepare("UPDATE agri_credit_scores SET score_value=?, tier=? WHERE id=?");
            $stmt->bind_param("isi", $result['score'], $result['tier'], $existing['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO agri_credit_scores (farmer_id, season_id, score_value, tier) VALUES (?,?,?,?)");
            $stmt->bind_param("iiis", $farmer_id, $season_id, $result['score'], $result['tier']);
        }
        $stmt->execute();
        flash_set('success', "AgroCreditScore computed: {$result['score']} ({$result['tier']} tier) from {$result['readings']} readings.");
    } else {
        flash_set('error', 'Invalid season.');
    }
    header("Location: farmer_credit_score.php");
    exit();
}

$seasons = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id ORDER BY start_date DESC");
$seasonList = []; while ($s = $seasons->fetch_assoc()) $seasonList[] = $s;

// Keep the stored score in step with the latest readings, then show it
$focus = null;
foreach ($seasonList as $s) { if ($s['status'] === 'active') { $focus = $s; break; } }
if (!$focus && $seasonList) $focus = $seasonList[0];
$live = null;
if ($focus) {
    update_credit_score($conn, $farmer_id, (int)$focus['id']);
    $live = compute_credit_score($conn, (int)$focus['id']);
}

$latestReading = null;
if ($focus) {
    $latestReading = $conn->query("SELECT * FROM sensor_readings WHERE season_id=" . (int)$focus['id'] . " ORDER BY recorded_at DESC LIMIT 1")->fetch_assoc();
}
$cls = $latestReading ? classify_crop_health($latestReading['soil_moisture'], $latestReading['temperature'], $latestReading['humidity'], $focus['crop_type']) : null;

$scores = $conn->query(
    "SELECT acs.*, gs.crop_type, gs.start_date FROM agri_credit_scores acs
     JOIN growing_seasons gs ON gs.id = acs.season_id
     WHERE acs.farmer_id = $farmer_id ORDER BY acs.last_updated DESC LIMIT 5"
);
$tierColour = ['Gold' => '#f2b705', 'Silver' => '#8a94a0', 'Bronze' => '#b5651d'];
?>
<div class="container my-5" style="max-width:1000px">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-star text-warning me-2"></i>AgroCreditScore</h2>
            <div class="text-muted small">Updates automatically whenever a new reading arrives.</div>
        </div>
        <a href="export.php?type=my_scores" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</a>
    </div>
    <?php render_flashes(); ?>

    <?php if ($live && $live['readings'] > 0): $tc = $tierColour[$live['tier']] ?? '#888'; ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100 text-center p-4">
                <div class="text-muted text-uppercase small fw-bold">Current score</div>
                <div style="font-size:4.5rem;line-height:1.1;font-weight:800;color:<?php echo $tc; ?>"><?php echo $live['score']; ?></div>
                <div class="mx-auto mb-3" style="width:100%;max-width:280px">
                    <div class="progress" style="height:10px"><div class="progress-bar" style="width:<?php echo $live['score']; ?>%;background:<?php echo $tc; ?>"></div></div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:.7rem"><span>0</span><span>Bronze &lt;50</span><span>Silver 50-74</span><span>Gold 75+</span></div>
                </div>
                <div><span class="badge <?php echo tier_badge_class($live['tier']); ?> py-2 px-3 rounded-pill fs-6"><?php echo h($live['tier']); ?> Tier</span></div>
                <div class="text-muted small mt-3"><?php echo h($focus['crop_type']); ?> &middot; started <?php echo h($focus['start_date']); ?> &middot; <?php echo (int)$live['readings']; ?> readings</div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">Current reading</span>
                        <?php if ($cls): ?><span class="badge <?php echo health_badge_class($cls['status']); ?>"><?php echo h($cls['status']); ?></span><?php endif; ?>
                    </div>
                    <?php if ($latestReading): ?>
                    <div class="row text-center g-2">
                        <div class="col-4"><div class="bg-light rounded-3 py-2"><div class="text-muted small">Soil moisture</div><div class="fs-5 fw-bold"><?php echo h($latestReading['soil_moisture']); ?>%</div></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 py-2"><div class="text-muted small">Temperature</div><div class="fs-5 fw-bold"><?php echo h($latestReading['temperature']); ?>°C</div></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 py-2"><div class="text-muted small">Humidity</div><div class="fs-5 fw-bold"><?php echo h($latestReading['humidity']); ?>%</div></div></div>
                    </div>
                    <div class="text-muted small mt-2">Recorded <?php echo date('d M Y, H:i', strtotime($latestReading['recorded_at'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="fw-bold mb-3">How the score is made up</div>
                    <?php foreach ([['Crop health', $live['parts']['health'], 55], ['Reporting consistency', $live['parts']['consistency'], 25], ['Soil suitability', $live['parts']['soil'], 20]] as [$lab, $pts, $max]): ?>
                        <div class="d-flex justify-content-between small mb-1"><span><?php echo $lab; ?></span><span class="text-muted"><?php echo $pts; ?> / <?php echo $max; ?></span></div>
                        <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-success" style="width:<?php echo round($pts / $max * 100); ?>%"></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm p-5 text-center text-muted mb-4">
        No score yet. Start a growing season, register a device and send a reading, and your score appears here.
        <div class="mt-2"><a href="farmer_seasons.php">Go to Seasons</a></div>
    </div>
    <?php endif; ?>

    <?php if ($scores->num_rows > 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">Score by season</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Season</th><th>Score</th><th>Tier</th><th>Last updated</th><th></th></tr></thead>
                <tbody>
                <?php while ($s = $scores->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($s['crop_type']); ?> (<?php echo h($s['start_date']); ?>)</td>
                        <td class="fw-bold"><?php echo $s['score_value']; ?></td>
                        <td><span class="badge <?php echo tier_badge_class($s['tier']); ?>"><?php echo h($s['tier']); ?></span></td>
                        <td class="small"><?php echo date('d M Y, H:i', strtotime($s['last_updated'])); ?></td>
                        <td class="text-end"><a href="farmer_credit_score.php?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this score record?');"><i class="fa-solid fa-trash"></i></a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
