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

$scores = $conn->query(
    "SELECT acs.*, gs.crop_type, gs.start_date FROM agri_credit_scores acs
     JOIN growing_seasons gs ON gs.id = acs.season_id
     WHERE acs.farmer_id = $farmer_id ORDER BY acs.last_updated DESC"
);
$latest = $conn->query("SELECT * FROM agri_credit_scores WHERE farmer_id=$farmer_id ORDER BY last_updated DESC LIMIT 1")->fetch_assoc();
?>
<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-star text-warning me-2"></i>AgroCreditScore History</h2>
    <?php render_flashes(); ?>

    <?php if ($latest): ?>
    <div class="card border-0 shadow-sm mb-4 text-center p-4">
        <h6 class="text-muted text-uppercase fw-bold">Current Score</h6>
        <div class="display-3 fw-bold text-success"><?php echo $latest['score_value']; ?></div>
        <span class="badge <?php echo tier_badge_class($latest['tier']); ?> py-2 px-3 rounded-pill fs-6"><?php echo h($latest['tier']); ?> Tier</span>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">Recalculate Score by Season</div>
        <div class="card-body">
            <p class="text-muted small">Scores are computed from your seasonal sensor data density, average soil moisture, and crop health classifications (simulated Random Forest engine).</p>
            <?php foreach ($seasonList as $s): ?>
                <a href="farmer_credit_score.php?calculate=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success me-2 mb-2"><i class="fa-solid fa-rotate me-1"></i>Calculate for <?php echo h($s['crop_type']); ?> (<?php echo h($s['start_date']); ?>)</a>
            <?php endforeach; ?>
            <?php if (empty($seasonList)): ?><p class="text-muted">No seasons yet. <a href="farmer_seasons.php">Start a growing season</a> first.</p><?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Season</th><th>Score</th><th>Tier</th><th>Last Updated</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($scores->num_rows === 0): ?><tr><td colspan="5" class="text-center text-muted py-4">No scores computed yet.</td></tr><?php endif; ?>
                    <?php while ($s = $scores->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($s['crop_type']); ?> (<?php echo h($s['start_date']); ?>)</td>
                            <td class="fw-bold"><?php echo $s['score_value']; ?></td>
                            <td><span class="badge <?php echo tier_badge_class($s['tier']); ?>"><?php echo h($s['tier']); ?></span></td>
                            <td><?php echo h($s['last_updated']); ?></td>
                            <td class="text-end"><a href="farmer_credit_score.php?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this score record?');"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
