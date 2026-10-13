<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

// Handle new reading submission (simulates IoT device POST /api/sensor-data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_reading') {
    $device_id = (int)$_POST['device_id'];
    $season_id = (int)$_POST['season_id'];
    $soil_moisture = (float)$_POST['soil_moisture'];
    $temperature = (float)$_POST['temperature'];
    $humidity = (float)$_POST['humidity'];

    // verify device & season belong to this farmer
    $ownsDevice = $conn->query("SELECT id FROM iot_devices WHERE id=$device_id AND farmer_id=$farmer_id")->num_rows > 0;
    $ownsSeason = $conn->query("SELECT id FROM growing_seasons WHERE id=$season_id AND farmer_id=$farmer_id")->num_rows > 0;

    if ($ownsDevice && $ownsSeason) {
        $stmt = $conn->prepare("INSERT INTO sensor_readings (device_id, season_id, soil_moisture, temperature, humidity) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iiddd", $device_id, $season_id, $soil_moisture, $temperature, $humidity);
        $stmt->execute();
        $reading_id = $conn->insert_id;

        // Auto-run crop health classification (simulated ML Flask service)
        $result = classify_crop_health($soil_moisture, $temperature, $humidity);
        $stmt2 = $conn->prepare("INSERT INTO crop_health_classifications (reading_id, health_status) VALUES (?, ?)");
        $stmt2->bind_param("is", $reading_id, $result['status']);
        $stmt2->execute();

        $conn->query("UPDATE iot_devices SET status='online' WHERE id=$device_id");

        flash_set('success', "Reading recorded. Auto-classified as: {$result['status']} (confidence {$result['confidence']}).");
    } else {
        flash_set('error', 'Invalid device or season selection.');
    }
    header("Location: farmer_sensor_data.php" . ($season_id ? "?season_id=$season_id" : ""));
    exit();
}

// Edit an existing reading (re-runs classification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_reading') {
    $rid = (int)$_POST['reading_id'];
    $m = (float)$_POST['soil_moisture']; $t = (float)$_POST['temperature']; $hu = (float)$_POST['humidity'];
    $owns = $conn->query("SELECT sr.id FROM sensor_readings sr JOIN growing_seasons gs ON gs.id=sr.season_id WHERE sr.id=$rid AND gs.farmer_id=$farmer_id")->num_rows > 0;
    if ($owns) {
        $st = $conn->prepare("UPDATE sensor_readings SET soil_moisture=?, temperature=?, humidity=? WHERE id=?");
        $st->bind_param("dddi", $m, $t, $hu, $rid);
        $st->execute();
        $c = classify_crop_health($m, $t, $hu);
        $ex = $conn->query("SELECT id FROM crop_health_classifications WHERE reading_id=$rid")->fetch_assoc();
        if ($ex) {
            $st2 = $conn->prepare("UPDATE crop_health_classifications SET health_status=?, analyzed_at=NOW() WHERE id=?");
            $st2->bind_param("si", $c['status'], $ex['id']);
        } else {
            $st2 = $conn->prepare("INSERT INTO crop_health_classifications (reading_id, health_status) VALUES (?,?)");
            $st2->bind_param("is", $rid, $c['status']);
        }
        $st2->execute();
        flash_set('success', "Reading updated and re-classified as {$c['status']}.");
    } else {
        flash_set('error', 'Reading not found.');
    }
    header("Location: farmer_sensor_data.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query(
        "DELETE sr FROM sensor_readings sr JOIN growing_seasons gs ON gs.id = sr.season_id
         WHERE sr.id=$id AND gs.farmer_id=$farmer_id"
    );
    flash_set('success', 'Reading deleted.');
    header("Location: farmer_sensor_data.php");
    exit();
}

$season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : null;
$devices = $conn->query("SELECT * FROM iot_devices WHERE farmer_id=$farmer_id");
$deviceList = []; while ($d = $devices->fetch_assoc()) $deviceList[] = $d;

$seasons = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id ORDER BY start_date DESC");
$seasonList = []; while ($s = $seasons->fetch_assoc()) $seasonList[] = $s;

$sql = "SELECT sr.*, chc.health_status FROM sensor_readings sr
        JOIN growing_seasons gs ON gs.id = sr.season_id
        LEFT JOIN crop_health_classifications chc ON chc.reading_id = sr.id
        WHERE gs.farmer_id = $farmer_id";
if ($season_id) $sql .= " AND sr.season_id = $season_id";
$sql .= " ORDER BY sr.recorded_at DESC LIMIT 100";
$readings = $conn->query($sql);
$readingRows = []; while ($r = $readings->fetch_assoc()) $readingRows[] = $r;
$chartData = array_reverse($readingRows);
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-droplet text-primary me-2"></i>Sensor Data & Trends</h2>
        <?php if (count($deviceList) && count($seasonList)): ?>
        <button class="btn btn-agri" data-bs-toggle="modal" data-bs-target="#readingModal"><i class="fa-solid fa-plus me-1"></i>Log Reading</button>
        <?php endif; ?>
    </div>
    <?php render_flashes(); ?>

    <?php if (empty($deviceList) || empty($seasonList)): ?>
        <div class="alert alert-warning">You need at least one <a href="farmer_devices.php">IoT device</a> and one <a href="farmer_seasons.php">growing season</a> before logging sensor readings.</div>
    <?php endif; ?>

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-4">
            <select name="season_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Seasons</option>
                <?php foreach ($seasonList as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $season_id==$s['id']?'selected':''; ?>><?php echo h($s['crop_type']); ?> (started <?php echo h($s['start_date']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <canvas id="trendChart" height="90"></canvas>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Recorded At</th><th>Soil Moisture</th><th>Temp</th><th>Humidity</th><th>Health</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($readingRows)): ?><tr><td colspan="6" class="text-center text-muted py-4">No readings yet.</td></tr><?php endif; ?>
                    <?php foreach ($readingRows as $r): ?>
                        <tr>
                            <td><?php echo h($r['recorded_at']); ?></td>
                            <td><?php echo h($r['soil_moisture']); ?>%</td>
                            <td><?php echo h($r['temperature']); ?>°C</td>
                            <td><?php echo h($r['humidity']); ?>%</td>
                            <td><?php if ($r['health_status']): ?><span class="badge <?php echo health_badge_class($r['health_status']); ?>"><?php echo h($r['health_status']); ?></span><?php else: ?><span class="text-muted small">pending</span><?php endif; ?></td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick='openEditReading(<?php echo json_encode(["id"=>$r["id"],"m"=>$r["soil_moisture"],"t"=>$r["temperature"],"h"=>$r["humidity"]]); ?>)'><i class="fa-solid fa-pen"></i></button> <a href="farmer_sensor_data.php?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this reading?');"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="readingModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="add_reading">
        <div class="modal-header"><h5 class="modal-title">Log Sensor Reading</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small text-muted">This simulates the ESP32 device's <code>POST /api/sensor-data</code> call.</p>
            <div class="mb-3">
                <label class="form-label">Device</label>
                <select name="device_id" class="form-select" required>
                    <?php foreach ($deviceList as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo h($d['mac_address']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Season</label>
                <select name="season_id" class="form-select" required>
                    <?php foreach ($seasonList as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo h($s['crop_type']); ?> (<?php echo h($s['start_date']); ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="row g-2">
                <div class="col-4"><label class="form-label">Soil Moisture %</label><input type="number" step="0.01" class="form-control" name="soil_moisture" required></div>
                <div class="col-4"><label class="form-label">Temp °C</label><input type="number" step="0.01" class="form-control" name="temperature" required></div>
                <div class="col-4"><label class="form-label">Humidity %</label><input type="number" step="0.01" class="form-control" name="humidity" required></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-agri" type="submit">Submit Reading</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editReadingModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="edit_reading">
        <input type="hidden" name="reading_id" id="er_id">
        <div class="modal-header"><h5 class="modal-title">Edit Reading</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-2">
            <div class="col-4"><label class="form-label">Soil Moisture %</label><input type="number" step="0.01" class="form-control" name="soil_moisture" id="er_m" required></div>
            <div class="col-4"><label class="form-label">Temp °C</label><input type="number" step="0.01" class="form-control" name="temperature" id="er_t" required></div>
            <div class="col-4"><label class="form-label">Humidity %</label><input type="number" step="0.01" class="form-control" name="humidity" id="er_h" required></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-agri" type="submit">Save</button></div>
    </form>
  </div>
</div>
<script>
function openEditReading(r) {
    document.getElementById('er_id').value = r.id;
    document.getElementById('er_m').value = r.m;
    document.getElementById('er_t').value = r.t;
    document.getElementById('er_h').value = r.h;
    new bootstrap.Modal(document.getElementById('editReadingModal')).show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?php echo json_encode(array_map(fn($r)=>date('m-d H:i', strtotime($r['recorded_at'])), $chartData)); ?>;
const moisture = <?php echo json_encode(array_map(fn($r)=>(float)$r['soil_moisture'], $chartData)); ?>;
const temp = <?php echo json_encode(array_map(fn($r)=>(float)$r['temperature'], $chartData)); ?>;
const humidity = <?php echo json_encode(array_map(fn($r)=>(float)$r['humidity'], $chartData)); ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            { label: 'Soil Moisture %', data: moisture, borderColor: '#2E7D32', tension: 0.3 },
            { label: 'Temperature °C', data: temp, borderColor: '#d32f2f', tension: 0.3 },
            { label: 'Humidity %', data: humidity, borderColor: '#0288d1', tension: 0.3 },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include 'includes/footer.php'; ?>
