<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $crop_type = trim($_POST['crop_type']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] !== '' ? $_POST['end_date'] : null;
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE growing_seasons SET crop_type=?, start_date=?, end_date=?, status=? WHERE id=? AND farmer_id=?");
        $stmt->bind_param("ssssii", $crop_type, $start_date, $end_date, $status, $id, $farmer_id);
        $stmt->execute();
        flash_set('success', 'Season updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO growing_seasons (farmer_id, crop_type, start_date, end_date, status) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $farmer_id, $crop_type, $start_date, $end_date, $status);
        $stmt->execute();
        flash_set('success', 'New growing season started.');
    }
    header("Location: farmer_seasons.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM growing_seasons WHERE id=$id AND farmer_id=$farmer_id");
    flash_set('success', 'Season deleted.');
    header("Location: farmer_seasons.php");
    exit();
}

$seasons = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id ORDER BY start_date DESC");
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-calendar text-success me-2"></i>Growing Seasons</h2>
        <button class="btn btn-agri" data-bs-toggle="modal" data-bs-target="#seasonModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i>New Season</button>
    </div>
    <?php render_flashes(); ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Crop</th><th>Start</th><th>End</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if ($seasons->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No growing seasons yet. Start one to begin tracking!</td></tr>
                    <?php endif; ?>
                    <?php while ($s = $seasons->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $s['id']; ?></td>
                            <td class="fw-bold"><?php echo h($s['crop_type']); ?></td>
                            <td><?php echo h($s['start_date']); ?></td>
                            <td><?php echo h($s['end_date'] ?? '—'); ?></td>
                            <td><span class="badge bg-<?php echo $s['status']=='active'?'success':($s['status']=='completed'?'secondary':'danger'); ?>"><?php echo h($s['status']); ?></span></td>
                            <td class="text-end">
                                <a href="farmer_sensor_data.php?season_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info" title="View Sensor Data"><i class="fa-solid fa-droplet"></i></a>
                                <a href="farmer_credit_score.php?season_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-warning" title="View/Calc Score"><i class="fa-solid fa-star"></i></a>
                                <button class="btn btn-sm btn-outline-primary" onclick='openEdit(<?php echo json_encode($s); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <a href="farmer_seasons.php?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this season and all related data?');"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="seasonModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="s_id">
        <div class="modal-header"><h5 class="modal-title" id="sTitle">New Season</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Crop Type</label><input class="form-control" name="crop_type" id="s_crop" required></div>
            <div class="mb-3"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" id="s_start" required></div>
            <div class="mb-3"><label class="form-label">End Date <span class="text-muted small">(optional)</span></label><input type="date" class="form-control" name="end_date" id="s_end"></div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" id="s_status">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-agri" type="submit">Save</button></div>
    </form>
  </div>
</div>

<script>
function openCreate() {
    document.getElementById('sTitle').innerText = 'New Season';
    document.getElementById('s_id').value = '';
    document.getElementById('s_crop').value = '';
    document.getElementById('s_start').value = new Date().toISOString().slice(0,10);
    document.getElementById('s_end').value = '';
    document.getElementById('s_status').value = 'active';
}
function openEdit(s) {
    new bootstrap.Modal(document.getElementById('seasonModal')).show();
    document.getElementById('sTitle').innerText = 'Edit Season #' + s.id;
    document.getElementById('s_id').value = s.id;
    document.getElementById('s_crop').value = s.crop_type;
    document.getElementById('s_start').value = s.start_date;
    document.getElementById('s_end').value = s.end_date || '';
    document.getElementById('s_status').value = s.status;
}
</script>
<?php include 'includes/footer.php'; ?>
