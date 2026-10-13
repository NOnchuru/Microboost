<?php
include 'includes/header.php';
require_role('admin');

// ---------- helpers ----------
function opts($conn, $src) {
    switch ($src) {
        case 'farmers':    $q = "SELECT f.id, u.full_name label FROM farmers f JOIN users u ON u.id=f.user_id ORDER BY u.full_name"; break;
        case 'devices':    $q = "SELECT id, mac_address label FROM iot_devices ORDER BY id"; break;
        case 'seasons':    $q = "SELECT gs.id, CONCAT(u.full_name,' - ',gs.crop_type,' (',gs.start_date,')') label FROM growing_seasons gs JOIN farmers f ON f.id=gs.farmer_id JOIN users u ON u.id=f.user_id ORDER BY gs.id DESC"; break;
        case 'readings':   $q = "SELECT id, CONCAT('Reading #',id,' - ',recorded_at) label FROM sensor_readings ORDER BY id DESC LIMIT 300"; break;
        case 'advisories': $q = "SELECT id, CONCAT('#',id,' ',LEFT(advisory_text,40)) label FROM advisories ORDER BY id DESC LIMIT 300"; break;
        default: return [];
    }
    $out = [];
    $r = $conn->query($q);
    while ($x = $r->fetch_assoc()) $out[$x['id']] = $x['label'];
    return $out;
}

function upsert_classification($conn, $reading_id, $m, $t, $h) {
    $c = classify_crop_health($m, $t, $h);
    $ex = $conn->query("SELECT id FROM crop_health_classifications WHERE reading_id=$reading_id")->fetch_assoc();
    if ($ex) {
        $s = $conn->prepare("UPDATE crop_health_classifications SET health_status=?, analyzed_at=NOW() WHERE id=?");
        $s->bind_param("si", $c['status'], $ex['id']);
    } else {
        $s = $conn->prepare("INSERT INTO crop_health_classifications (reading_id, health_status) VALUES (?,?)");
        $s->bind_param("is", $reading_id, $c['status']);
    }
    $s->execute();
    return $c['status'];
}

// ---------- entity definitions ----------
$E = [
 'seasons' => [
    'title' => 'Growing Seasons', 'icon' => 'calendar', 'table' => 'growing_seasons', 'create' => true, 'edit' => true,
    'list' => "SELECT gs.id, u.full_name farmer, gs.crop_type, gs.start_date, gs.end_date, gs.status
               FROM growing_seasons gs JOIN farmers f ON f.id=gs.farmer_id JOIN users u ON u.id=f.user_id ORDER BY gs.id DESC LIMIT 300",
    'fields' => [
        ['farmer_id', 'Farmer', 'select', 'src' => 'farmers'],
        ['crop_type', 'Crop Type', 'text'],
        ['start_date', 'Start Date', 'date'],
        ['end_date', 'End Date', 'date', 'nullable' => true],
        ['status', 'Status', 'enum', 'values' => ['active', 'completed', 'failed']],
    ]],
 'readings' => [
    'title' => 'Sensor Readings', 'icon' => 'droplet', 'table' => 'sensor_readings', 'create' => true, 'edit' => true,
    'list' => "SELECT sr.id, d.mac_address device, gs.crop_type season, sr.soil_moisture, sr.temperature, sr.humidity, sr.recorded_at, chc.health_status
               FROM sensor_readings sr JOIN iot_devices d ON d.id=sr.device_id JOIN growing_seasons gs ON gs.id=sr.season_id
               LEFT JOIN crop_health_classifications chc ON chc.reading_id=sr.id ORDER BY sr.id DESC LIMIT 300",
    'fields' => [
        ['device_id', 'Device', 'select', 'src' => 'devices'],
        ['season_id', 'Season', 'select', 'src' => 'seasons'],
        ['soil_moisture', 'Soil Moisture %', 'number'],
        ['temperature', 'Temperature °C', 'number'],
        ['humidity', 'Humidity %', 'number'],
    ]],
 'health' => [
    'title' => 'Crop Health', 'icon' => 'leaf', 'table' => 'crop_health_classifications', 'create' => true, 'edit' => true,
    'list' => "SELECT chc.id, chc.reading_id, sr.soil_moisture, sr.temperature, sr.humidity, chc.health_status, chc.analyzed_at
               FROM crop_health_classifications chc JOIN sensor_readings sr ON sr.id=chc.reading_id ORDER BY chc.id DESC LIMIT 300",
    'fields' => [
        ['reading_id', 'Sensor Reading', 'select', 'src' => 'readings'],
        ['health_status', 'Health Status', 'enum', 'values' => ['Healthy', 'Monitor', 'Critical']],
    ]],
 'scores' => [
    'title' => 'AgroCredit Scores', 'icon' => 'star', 'table' => 'agri_credit_scores', 'create' => true, 'edit' => true, 'recalc' => true,
    'list' => "SELECT acs.id, u.full_name farmer, gs.crop_type season, acs.score_value, acs.tier, acs.last_updated
               FROM agri_credit_scores acs JOIN farmers f ON f.id=acs.farmer_id JOIN users u ON u.id=f.user_id
               JOIN growing_seasons gs ON gs.id=acs.season_id ORDER BY acs.id DESC LIMIT 300",
    'fields' => [
        ['farmer_id', 'Farmer', 'select', 'src' => 'farmers'],
        ['season_id', 'Season', 'select', 'src' => 'seasons'],
        ['score_value', 'Score (0-100)', 'number'],
        ['tier', 'Tier', 'enum', 'values' => ['Bronze', 'Silver', 'Gold']],
    ]],
 'advisories' => [
    'title' => 'Advisories', 'icon' => 'robot', 'table' => 'advisories', 'create' => true, 'edit' => true,
    'list' => "SELECT a.id, u.full_name farmer, a.advisory_text, a.generated_at
               FROM advisories a JOIN farmers f ON f.id=a.farmer_id JOIN users u ON u.id=f.user_id ORDER BY a.id DESC LIMIT 300",
    'fields' => [
        ['farmer_id', 'Farmer', 'select', 'src' => 'farmers'],
        ['advisory_text', 'Advisory Text', 'textarea'],
    ]],
 'sms' => [
    'title' => 'SMS Logs', 'icon' => 'sms', 'table' => 'sms_delivery_logs', 'create' => true, 'edit' => true,
    'list' => "SELECT id, advisory_id, phone_number, delivery_status, sent_at FROM sms_delivery_logs ORDER BY id DESC LIMIT 300",
    'fields' => [
        ['advisory_id', 'Advisory', 'select', 'src' => 'advisories'],
        ['phone_number', 'Phone Number', 'text'],
        ['delivery_status', 'Delivery Status', 'enum', 'values' => ['Delivered', 'Pending', 'Failed']],
    ]],
 'reports' => [
    'title' => 'Credit Reports', 'icon' => 'file-lines', 'table' => 'agri_credit_reports', 'create' => false, 'edit' => false,
    'list' => "SELECT r.id, uo.full_name loan_officer, uf.full_name farmer, r.report_path, r.generated_at
               FROM agri_credit_reports r JOIN loan_officers lo ON lo.id=r.loan_officer_id JOIN users uo ON uo.id=lo.user_id
               JOIN farmers f ON f.id=r.farmer_id JOIN users uf ON uf.id=f.user_id ORDER BY r.id DESC LIMIT 300",
    'fields' => []],
];

$tab = $_GET['tab'] ?? 'seasons';
if (!isset($E[$tab])) $tab = 'seasons';
$cfg = $E[$tab];
$table = $cfg['table'];
$self = "admin_data.php?tab=$tab";

// ---------- CREATE / UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $vals = [];
    foreach ($cfg['fields'] as $f) {
        $val = trim($_POST[$f[0]] ?? '');
        if ($val === '' && !empty($f['nullable'])) $val = null;
        $vals[$f[0]] = $val;
    }

    if ($tab === 'sms' && $vals['phone_number'] === '') {
        flash_set('error', 'Phone number is required.');
        header("Location: $self"); exit();
    }

    if ($id > 0) {
        $set = implode(',', array_map(fn($n) => "`$n`=?", array_keys($vals)));
        $stmt = $conn->prepare("UPDATE `$table` SET $set WHERE id=?");
        $params = array_values($vals); $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($vals)) . 'i', ...$params);
        $ok = $stmt->execute();
    } else {
        $cols = implode(',', array_map(fn($n) => "`$n`", array_keys($vals)));
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $stmt = $conn->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
        $params = array_values($vals);
        $stmt->bind_param(str_repeat('s', count($vals)), ...$params);
        $ok = $stmt->execute();
        if ($ok) $id = $conn->insert_id;
    }

    if (!$ok) {
        flash_set('error', 'Save failed: ' . $stmt->error);
    } else {
        $msg = 'Saved.';
        if ($tab === 'readings') {
            $status = upsert_classification($conn, $id, (float)$vals['soil_moisture'], (float)$vals['temperature'], (float)$vals['humidity']);
            $msg = "Reading saved and re-classified as $status.";
        }
        if ($tab === 'scores') {
            $s = (int)$vals['score_value'];
            $tier = $s >= 75 ? 'Gold' : ($s >= 50 ? 'Silver' : 'Bronze');
            // keep tier consistent if the admin typed a score but left tier at default mismatch
            if (empty($_POST['keep_tier'])) {
                $conn->query("UPDATE agri_credit_scores SET tier='$tier' WHERE id=$id");
                $msg = "Score saved (tier set to $tier from score).";
            }
        }
        if ($tab === 'advisories' && !empty($_POST['send_sms']) && !($_POST['id'] > 0)) {
            $fid = (int)$vals['farmer_id'];
            $p = $conn->query("SELECT u.phone_number FROM users u JOIN farmers f ON f.user_id=u.id WHERE f.id=$fid")->fetch_assoc();
            if ($p) { send_sms_advisory($conn, $id, $p['phone_number'], $vals['advisory_text']); $msg = 'Advisory saved and SMS logged as delivered.'; }
        }
        flash_set('success', $msg);
    }
    header("Location: $self");
    exit();
}

// ---------- DELETE ----------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($tab === 'reports') {
        $r = $conn->query("SELECT report_path FROM agri_credit_reports WHERE id=$id")->fetch_assoc();
        if ($r && file_exists(__DIR__ . '/' . $r['report_path'])) @unlink(__DIR__ . '/' . $r['report_path']);
    }
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    flash_set('success', 'Record deleted.');
    header("Location: $self");
    exit();
}

// ---------- RECALCULATE SCORE ----------
if ($tab === 'scores' && isset($_GET['recalc'])) {
    $id = (int)$_GET['recalc'];
    $row = $conn->query("SELECT season_id FROM agri_credit_scores WHERE id=$id")->fetch_assoc();
    if ($row) {
        $res = compute_credit_score($conn, (int)$row['season_id']);
        $s = $conn->prepare("UPDATE agri_credit_scores SET score_value=?, tier=? WHERE id=?");
        $s->bind_param("isi", $res['score'], $res['tier'], $id);
        $s->execute();
        flash_set('success', "Recalculated: {$res['score']} ({$res['tier']}) from {$res['readings']} readings.");
    }
    header("Location: $self");
    exit();
}

// ---------- READ ----------
$rows = $conn->query($cfg['list']);
$edit = null;
if (isset($_GET['edit']) && $cfg['edit']) {
    $edit = $conn->query("SELECT * FROM `$table` WHERE id=" . (int)$_GET['edit'])->fetch_assoc();
}
$showForm = $cfg['create'] && ($edit || isset($_GET['new']));
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-database me-2"></i>Data Manager</h2>
        <?php if ($cfg['create']): ?>
            <a href="<?php echo $self; ?>&new=1" class="btn btn-agri"><i class="fa-solid fa-plus me-1"></i>Add <?php echo h($cfg['title']); ?></a>
        <?php endif; ?>
    </div>

    <ul class="nav nav-tabs mb-3">
        <?php foreach ($E as $k => $c): ?>
            <li class="nav-item"><a class="nav-link <?php echo $k === $tab ? 'active fw-bold' : 'text-success'; ?>" href="admin_data.php?tab=<?php echo $k; ?>"><i class="fa-solid fa-<?php echo $c['icon']; ?> me-1"></i><?php echo h($c['title']); ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php render_flashes(); ?>

    <?php if ($showForm): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold"><?php echo $edit ? 'Edit #' . $edit['id'] : 'New'; ?> &middot; <?php echo h($cfg['title']); ?></div>
        <div class="card-body">
            <form method="POST" action="<?php echo $self; ?>" class="row g-3">
                <input type="hidden" name="id" value="<?php echo $edit['id'] ?? 0; ?>">
                <?php foreach ($cfg['fields'] as $f):
                    [$name, $lbl, $kind] = $f; $cur = $edit[$name] ?? ''; ?>
                    <div class="col-md-<?php echo $kind === 'textarea' ? 12 : 4; ?>">
                        <label class="form-label"><?php echo h($lbl); ?></label>
                        <?php if ($kind === 'select'): ?>
                            <select class="form-select" name="<?php echo $name; ?>" required>
                                <?php foreach (opts($conn, $f['src']) as $oid => $olbl): ?>
                                    <option value="<?php echo $oid; ?>" <?php echo (string)$cur === (string)$oid ? 'selected' : ''; ?>><?php echo h($olbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($kind === 'enum'): ?>
                            <select class="form-select" name="<?php echo $name; ?>">
                                <?php foreach ($f['values'] as $ev): ?>
                                    <option <?php echo $cur === $ev ? 'selected' : ''; ?>><?php echo h($ev); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($kind === 'textarea'): ?>
                            <textarea class="form-control" rows="3" name="<?php echo $name; ?>" required><?php echo h($cur); ?></textarea>
                        <?php else: ?>
                            <input class="form-control" name="<?php echo $name; ?>" type="<?php echo $kind === 'number' ? 'number' : ($kind === 'date' ? 'date' : 'text'); ?>"
                                   <?php echo $kind === 'number' ? 'step="0.01"' : ''; ?> value="<?php echo h($cur); ?>" <?php echo empty($f['nullable']) ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($tab === 'advisories' && !$edit): ?>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="send_sms" value="1" id="sendsms" checked><label class="form-check-label" for="sendsms">Also send via SMS (logs a delivery record)</label></div></div>
                <?php endif; ?>
                <?php if ($tab === 'scores'): ?>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="keep_tier" value="1" id="keeptier"><label class="form-check-label" for="keeptier">Keep the tier exactly as selected (otherwise it is derived from the score: 75+ Gold, 50+ Silver)</label></div></div>
                <?php endif; ?>
                <div class="col-12">
                    <button class="btn btn-agri" type="submit">Save</button>
                    <a href="<?php echo $self; ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                <?php foreach ($rows->fetch_fields() as $fld): ?><th><?php echo h(ucwords(str_replace('_', ' ', $fld->name))); ?></th><?php endforeach; ?>
                <th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($rows->num_rows === 0): ?><tr><td colspan="12" class="text-center text-muted py-4">No records.</td></tr><?php endif; ?>
            <?php while ($r = $rows->fetch_assoc()): ?>
                <tr>
                    <?php foreach ($r as $col => $val): ?>
                        <td>
                        <?php
                        if ($col === 'health_status' && $val) echo "<span class='badge " . health_badge_class($val) . "'>" . h($val) . "</span>";
                        elseif ($col === 'tier' && $val) echo "<span class='badge " . tier_badge_class($val) . "'>" . h($val) . "</span>";
                        elseif ($col === 'report_path') echo "<a href='" . h($val) . "' target='_blank'>" . h($val) . "</a>";
                        elseif ($col === 'advisory_text') echo h(mb_strimwidth($val, 0, 90, '…'));
                        else echo h((string)$val);
                        ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end text-nowrap">
                        <?php if (!empty($cfg['recalc'])): ?><a href="<?php echo $self; ?>&recalc=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success" title="Recalculate"><i class="fa-solid fa-rotate"></i></a><?php endif; ?>
                        <?php if ($cfg['edit']): ?><a href="<?php echo $self; ?>&edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                        <a href="<?php echo $self; ?>&delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?');"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div></div></div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
