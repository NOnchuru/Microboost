<?php
include 'includes/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $farmer_id = (int)$_POST['farmer_id'];
    $mac = trim($_POST['mac_address']);
    $install_date = $_POST['installation_date'];
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE iot_devices SET farmer_id=?, mac_address=?, installation_date=?, status=? WHERE id=?");
        $stmt->bind_param("isssi", $farmer_id, $mac, $install_date, $status, $id);
        $stmt->execute();
        flash_set('success', 'Device updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO iot_devices (farmer_id, mac_address, installation_date, status) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $farmer_id, $mac, $install_date, $status);
        if (!$stmt->execute()) {
            flash_set('error', 'Could not add device: ' . $stmt->error);
        } else {
            flash_set('success', 'Device registered.');
        }
    }
    header("Location: admin_devices.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM iot_devices WHERE id=$id");
    flash_set('success', 'Device removed.');
    header("Location: admin_devices.php");
    exit();
}

$farmers = $conn->query("SELECT f.id, u.full_name FROM farmers f JOIN users u ON u.id=f.user_id ORDER BY u.full_name");
$farmerList = [];
while ($f = $farmers->fetch_assoc()) $farmerList[] = $f;

$devices = $conn->query(
    "SELECT d.*, u.full_name farmer_name FROM iot_devices d
     JOIN farmers f ON f.id = d.farmer_id JOIN users u ON u.id = f.user_id
     ORDER BY d.id DESC"
);
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-microchip me-2"></i>IoT Device Management</h2>
        <button class="btn btn-agri" data-bs-toggle="modal" data-bs-target="#deviceModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i>Add Device</button>
    </div>
    <?php render_flashes(); ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>ID</th><th>MAC Address</th><th>Farmer</th><th>Installed</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($d = $devices->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $d['id']; ?></td>
                            <td><code><?php echo h($d['mac_address']); ?></code></td>
                            <td><?php echo h($d['farmer_name']); ?></td>
                            <td><?php echo h($d['installation_date']); ?></td>
                            <td>
                                <?php $sc = ['online'=>'success','offline'=>'secondary','maintenance'=>'warning text-dark'][$d['status']] ?? 'secondary'; ?>
                                <span class="badge bg-<?php echo $sc; ?>"><?php echo h($d['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick='openEdit(<?php echo json_encode($d); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <a href="admin_devices.php?delete=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this device?');"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="deviceModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="d_id">
        <div class="modal-header"><h5 class="modal-title" id="dModalTitle">Add Device</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Assign to Farmer</label>
                <select name="farmer_id" id="d_farmer" class="form-select" required>
                    <?php foreach ($farmerList as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo h($f['full_name']); ?> (Farmer #<?php echo $f['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">MAC Address</label><input type="text" name="mac_address" id="d_mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF" required></div>
            <div class="mb-3"><label class="form-label">Installation Date</label><input type="date" name="installation_date" id="d_date" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" id="d_status" class="form-select">
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-agri" type="submit">Save Device</button></div>
    </form>
  </div>
</div>

<script>
function openCreate() {
    document.getElementById('dModalTitle').innerText = 'Add Device';
    document.getElementById('d_id').value = '';
    document.getElementById('d_mac').value = '';
    document.getElementById('d_date').value = new Date().toISOString().slice(0,10);
    document.getElementById('d_status').value = 'offline';
}
function openEdit(d) {
    new bootstrap.Modal(document.getElementById('deviceModal')).show();
    document.getElementById('dModalTitle').innerText = 'Edit Device #' + d.id;
    document.getElementById('d_id').value = d.id;
    document.getElementById('d_farmer').value = d.farmer_id;
    document.getElementById('d_mac').value = d.mac_address;
    document.getElementById('d_date').value = d.installation_date;
    document.getElementById('d_status').value = d.status;
}
</script>

<?php include 'includes/footer.php'; ?>
