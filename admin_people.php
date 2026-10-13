<?php
include 'includes/header.php';
require_role('admin');

$type = ($_GET['type'] ?? 'farmer') === 'officer' ? 'officer' : 'farmer';
$isFarmer = $type === 'farmer';
$table = $isFarmer ? 'farmers' : 'loan_officers';
$role = $isFarmer ? 'farmer' : 'loan_officer';
$label = $isFarmer ? 'Farmers' : 'Loan Officers';
$self = "admin_people.php?type=$type";

// ---------- CREATE / UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($_POST['pid'] ?? 0);           // farmers.id / loan_officers.id
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $status = $_POST['account_status'];
    $password_plain = trim($_POST['password'] ?? '');

    if ($isFarmer) {
        $f1 = trim($_POST['county'] ?? '') ?: 'Unspecified';
        $f2 = trim($_POST['primary_crop'] ?? '') ?: 'Unspecified';
        $f3 = ($_POST['farm_size_acres'] ?? '') !== '' ? (float)$_POST['farm_size_acres'] : null;
    } else {
        $f1 = trim($_POST['sacco_name'] ?? '') ?: 'Unspecified SACCO';
        $f2 = trim($_POST['employee_no'] ?? '');
    }

    if ($pid > 0) {
        $row = $conn->query("SELECT user_id FROM $table WHERE id=$pid")->fetch_assoc();
        if (!$row) { flash_set('error', 'Record not found.'); header("Location: $self"); exit(); }
        $uid = (int)$row['user_id'];

        $conn->begin_transaction();
        $ok = true;
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone_number=?, account_status=? WHERE id=?");
        $stmt->bind_param("sssi", $full_name, $phone, $status, $uid);
        $ok = $ok && $stmt->execute();

        if ($password_plain !== '') {
            $hash = password_hash($password_plain, PASSWORD_DEFAULT);
            $s2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s2->bind_param("si", $hash, $uid);
            $ok = $ok && $s2->execute();
        }
        if ($isFarmer) {
            $s3 = $conn->prepare("UPDATE farmers SET county=?, primary_crop=?, farm_size_acres=? WHERE id=?");
            $s3->bind_param("ssdi", $f1, $f2, $f3, $pid);
        } else {
            if ($f2 === '') $f2 = 'EMP' . $uid;
            $s3 = $conn->prepare("UPDATE loan_officers SET sacco_name=?, employee_no=? WHERE id=?");
            $s3->bind_param("ssi", $f1, $f2, $pid);
        }
        $ok = $ok && $s3->execute();

        if ($ok) { $conn->commit(); flash_set('success', "$label record updated."); }
        else { $conn->rollback(); flash_set('error', 'Update failed (phone number may already be in use).'); }
    } else {
        if ($password_plain === '') $password_plain = 'changeme123';
        $hash = password_hash($password_plain, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO users (full_name, phone_number, password, role, account_status) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $full_name, $phone, $hash, $role, $status);
        $ok = $stmt->execute();
        if ($ok) {
            $uid = $conn->insert_id;
            if ($isFarmer) {
                $s3 = $conn->prepare("INSERT INTO farmers (user_id, county, primary_crop, farm_size_acres) VALUES (?,?,?,?)");
                $s3->bind_param("issd", $uid, $f1, $f2, $f3);
            } else {
                if ($f2 === '') $f2 = 'EMP' . $uid;
                $s3 = $conn->prepare("INSERT INTO loan_officers (user_id, sacco_name, employee_no) VALUES (?,?,?)");
                $s3->bind_param("iss", $uid, $f1, $f2);
            }
            $ok = $s3->execute();
        }
        if ($ok) { $conn->commit(); flash_set('success', "Created. Temporary password: $password_plain"); }
        else { $conn->rollback(); flash_set('error', 'Could not create record (phone number may already be registered).'); }
    }
    header("Location: $self");
    exit();
}

// ---------- DELETE ----------
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $row = $conn->query("SELECT user_id FROM $table WHERE id=$pid")->fetch_assoc();
    if ($row) {
        $conn->query("DELETE FROM users WHERE id=" . (int)$row['user_id']); // cascades to every related table
        flash_set('success', 'Record and all related data deleted.');
    }
    header("Location: $self");
    exit();
}

// ---------- READ ----------
$search = trim($_GET['q'] ?? '');
$sql = $isFarmer
    ? "SELECT t.id pid, t.county, t.primary_crop, t.farm_size_acres, u.*,
         (SELECT COUNT(*) FROM iot_devices WHERE farmer_id=t.id) devices,
         (SELECT COUNT(*) FROM growing_seasons WHERE farmer_id=t.id) seasons
       FROM farmers t JOIN users u ON u.id=t.user_id WHERE 1=1"
    : "SELECT t.id pid, t.sacco_name, t.employee_no, u.*,
         (SELECT COUNT(*) FROM agri_credit_reports WHERE loan_officer_id=t.id) reports
       FROM loan_officers t JOIN users u ON u.id=t.user_id WHERE 1=1";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (u.full_name LIKE '%$s%' OR u.phone_number LIKE '%$s%')";
}
$sql .= " ORDER BY t.id DESC";
$rows = $conn->query($sql);

$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit = $conn->query("SELECT t.*, t.id pid, u.full_name, u.phone_number, u.account_status FROM $table t JOIN users u ON u.id=t.user_id WHERE t.id=$eid")->fetch_assoc();
}
$showForm = $edit || isset($_GET['new']);
$v = fn($k, $d = '') => h($edit[$k] ?? $d);
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-<?php echo $isFarmer ? 'tractor' : 'building-columns'; ?> me-2"></i>Manage <?php echo $label; ?></h2>
        <a href="<?php echo $self; ?>&new=1" class="btn btn-agri"><i class="fa-solid fa-plus me-1"></i>Add <?php echo $isFarmer ? 'Farmer' : 'Loan Officer'; ?></a>
    </div>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?php echo $isFarmer ? 'active bg-success' : 'text-success'; ?>" href="admin_people.php?type=farmer">Farmers</a></li>
        <li class="nav-item"><a class="nav-link <?php echo !$isFarmer ? 'active bg-success' : 'text-success'; ?>" href="admin_people.php?type=officer">Loan Officers</a></li>
    </ul>
    <?php render_flashes(); ?>

    <?php if ($showForm): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold"><?php echo $edit ? 'Edit ' . h($edit['full_name']) : 'New ' . ($isFarmer ? 'Farmer' : 'Loan Officer'); ?></div>
        <div class="card-body">
            <form method="POST" action="<?php echo $self; ?>" class="row g-3">
                <input type="hidden" name="pid" value="<?php echo $edit['pid'] ?? 0; ?>">
                <div class="col-md-4"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?php echo $v('full_name'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone Number</label><input class="form-control" name="phone_number" value="<?php echo $v('phone_number'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Account Status</label>
                    <select class="form-select" name="account_status">
                        <?php foreach (['active', 'pending', 'suspended'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($edit['account_status'] ?? 'active') === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <?php if ($isFarmer): ?>
                    <div class="col-md-4"><label class="form-label">County</label><input class="form-control" name="county" value="<?php echo $v('county'); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Primary Crop</label><input class="form-control" name="primary_crop" value="<?php echo $v('primary_crop'); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Farm Size (acres)</label><input type="number" step="0.01" class="form-control" name="farm_size_acres" value="<?php echo $v('farm_size_acres'); ?>"></div>
                <?php else: ?>
                    <div class="col-md-4"><label class="form-label">SACCO Name</label><input class="form-control" name="sacco_name" value="<?php echo $v('sacco_name'); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Employee No.</label><input class="form-control" name="employee_no" value="<?php echo $v('employee_no'); ?>"></div>
                <?php endif; ?>
                <div class="col-md-4"><label class="form-label">Password <span class="text-muted small">(blank = keep / auto on create)</span></label><input type="password" class="form-control" name="password"></div>
                <div class="col-12">
                    <button class="btn btn-agri" type="submit">Save</button>
                    <a href="<?php echo $self; ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <form class="row g-2 mb-3" method="GET">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        <div class="col-md-4"><input class="form-control" name="q" placeholder="Search name or phone" value="<?php echo h($search); ?>"></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
    </form>

    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>ID</th><th>Name</th><th>Phone</th>
                <?php if ($isFarmer): ?><th>County</th><th>Crop</th><th>Acres</th><th>Devices</th><th>Seasons</th>
                <?php else: ?><th>SACCO</th><th>Employee No.</th><th>Reports</th><?php endif; ?>
                <th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($rows->num_rows === 0): ?><tr><td colspan="10" class="text-center text-muted py-4">No records found.</td></tr><?php endif; ?>
            <?php while ($r = $rows->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $r['pid']; ?></td>
                    <td class="fw-bold"><?php echo h($r['full_name']); ?></td>
                    <td><?php echo h($r['phone_number']); ?></td>
                    <?php if ($isFarmer): ?>
                        <td><?php echo h($r['county']); ?></td><td><?php echo h($r['primary_crop']); ?></td>
                        <td><?php echo h($r['farm_size_acres']); ?></td><td><?php echo $r['devices']; ?></td><td><?php echo $r['seasons']; ?></td>
                    <?php else: ?>
                        <td><?php echo h($r['sacco_name']); ?></td><td><?php echo h($r['employee_no']); ?></td><td><?php echo $r['reports']; ?></td>
                    <?php endif; ?>
                    <td><span class="badge bg-<?php echo ['active' => 'success', 'pending' => 'warning text-dark', 'suspended' => 'danger'][$r['account_status']] ?? 'secondary'; ?>"><?php echo h($r['account_status']); ?></span></td>
                    <td class="text-end">
                        <a href="<?php echo $self; ?>&edit=<?php echo $r['pid']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <a href="<?php echo $self; ?>&delete=<?php echo $r['pid']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this account and ALL related data?');"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div></div></div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
