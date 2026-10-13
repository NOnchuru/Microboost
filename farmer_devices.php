<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $mac = trim($_POST['mac_address']);
    $install_date = $_POST['installation_date'];
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE iot_devices SET mac_address=?, installation_date=?, status=? WHERE id=? AND farmer_id=?");
        $stmt->bind_param("sssii", $mac, $install_date, $status, $id, $farmer_id);
        $stmt->execute();
        flash_set('success', 'Device updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO iot_devices (farmer_id, mac_address, installation_date, status) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $farmer_id, $mac, $install_date, $status);
        if (!$stmt->execute()) {
            flash_set('error', 'Could not register device (MAC address may already exist).');
        } else {
            flash_set('success', 'Device registered.');
        }
    }
    header("Location: farmer_devices.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM iot_devices WHERE id=$id AND farmer_id=$farmer_id");
    flash_set('success', 'Device removed.');
    header("Location: farmer_devices.php");
    exit();
}

$devices = $conn->query("SELECT * FROM iot_devices WHERE farmer_id=$farmer_id ORDER BY id DESC");
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-microchip text-success me-2"></i>My IoT Devices</h2>
        <button class="btn btn-agri" data-bs-toggle="modal" data-bs-target="#deviceModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i>Register Device</button>
    </div>
    <?php render_flashes(); ?>

    <div class="row g-3">
        <?php if ($devices->num_rows === 0): ?>
            <div class="col-12"><div class="alert alert-info">You have no IoT devices registered yet. Register your ESP32 sensor node's MAC address to start collecting farm data.</div></div>
        <?php endif; ?>
        <?php while ($d = $devices->fetch_assoc()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="fw-bold"><i class="fa-solid fa-wifi me-2"></i>Node #<?php echo $d['id']; ?></h5>
                        <span class="badge bg-<?php echo $d['status']=='online'?'success':($d['status']=='maintenance'?'warning text-dark':'secondary'); ?>"><?php echo h($d['status']); ?></span>
                    </div>
                    <p class="mb-1"><strong>MAC:</strong> <code><?php echo h($d['mac_address']); ?></code></p>
                    <p class="text-muted small mb-3">Installed: <?php echo h($d['installation_date']); ?></p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-fill" onclick='openEdit(<?php echo json_encode($d); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                        <a href="farmer_seasons.php" class="btn btn-sm btn-outline-info flex-fill"><i class="fa-solid fa-calendar"></i> Assign Season</a>
                        <a href="farmer_devices.php?delete=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this device?');"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div class="modal fade" id="deviceModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="d_id">
        <div class="modal-header"><h5 class="modal-title" id="dTitle">Register Device</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">MAC Address</label><input class="form-control" name="mac_address" id="d_mac" placeholder="AA:BB:CC:DD:EE:FF" required></div>
            <div class="mb-3"><label class="form-label">Installation Date</label><input type="date" class="form-control" name="installation_date" id="d_date" required></div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" id="d_status">
                    <option value="offline">Offline</option>
                    <option value="online">Online</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-agri" type="submit">Save</button></div>
    </form>
  </div>
</div>

<script>
function openCreate() {
    document.getElementById('dTitle').innerText = 'Register Device';
    document.getElementById('d_id').value = '';
    document.getElementById('d_mac').value = '';
    document.getElementById('d_date').value = new Date().toISOString().slice(0,10);
    document.getElementById('d_status').value = 'offline';
}
function openEdit(d) {
    new bootstrap.Modal(document.getElementById('deviceModal')).show();
    document.getElementById('dTitle').innerText = 'Edit Device #' + d.id;
    document.getElementById('d_id').value = d.id;
    document.getElementById('d_mac').value = d.mac_address;
    document.getElementById('d_date').value = d.installation_date;
    document.getElementById('d_status').value = d.status;
}
</script>
<?php include 'includes/footer.php'; ?>
