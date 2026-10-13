<?php
include 'includes/header.php';
require_role('admin');

// ---- CREATE / UPDATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $role = $_POST['role'];
    $status = $_POST['account_status'];
    $password_plain = trim($_POST['password'] ?? '');

    if ($id > 0) {
        if ($password_plain !== '') {
            $hash = password_hash($password_plain, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, phone_number=?, role=?, account_status=?, password=? WHERE id=?");
            $stmt->bind_param("sssssi", $full_name, $phone, $role, $status, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, phone_number=?, role=?, account_status=? WHERE id=?");
            $stmt->bind_param("ssssi", $full_name, $phone, $role, $status, $id);
        }
        if (!$stmt->execute()) {
            flash_set('error', 'Could not update user (phone number may already be in use).');
            header("Location: admin_users.php");
            exit();
        }

        // Ensure a role-specific record exists if role changed to farmer/loan_officer/admin
        if ($role === 'farmer' && !get_farmer_id_by_user($conn, $id)) {
            $conn->query("INSERT INTO farmers (user_id, county, primary_crop) VALUES ($id, 'Unspecified', 'Unspecified')");
        }
        if ($role === 'loan_officer' && !get_loan_officer_id_by_user($conn, $id)) {
            $conn->query("INSERT INTO loan_officers (user_id, sacco_name, employee_no) VALUES ($id, 'Unspecified SACCO', 'EMP$id')");
        }
        if ($role === 'admin') {
            $r = $conn->query("SELECT id FROM administrators WHERE user_id=$id");
            if ($r->num_rows === 0) $conn->query("INSERT INTO administrators (user_id) VALUES ($id)");
        }

        flash_set('success', 'User updated successfully.');
    } else {
        if ($password_plain === '') $password_plain = 'changeme123';
        $hash = password_hash($password_plain, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, phone_number, password, role, account_status) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $full_name, $phone, $hash, $role, $status);
        if (!$stmt->execute()) {
            flash_set('error', 'Could not create user (phone number may already be registered).');
            header("Location: admin_users.php");
            exit();
        }
        $new_id = $conn->insert_id;

        if ($role === 'farmer') {
            $conn->query("INSERT INTO farmers (user_id, county, primary_crop) VALUES ($new_id, 'Unspecified', 'Unspecified')");
        } elseif ($role === 'loan_officer') {
            $conn->query("INSERT INTO loan_officers (user_id, sacco_name, employee_no) VALUES ($new_id, 'Unspecified SACCO', 'EMP$new_id')");
        } elseif ($role === 'admin') {
            $conn->query("INSERT INTO administrators (user_id) VALUES ($new_id)");
        }
        flash_set('success', "User created. Temporary password: " . h($password_plain));
    }
    header("Location: admin_users.php");
    exit();
}

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        flash_set('error', 'You cannot delete your own account.');
    } else {
        $conn->query("DELETE FROM users WHERE id=$id"); // cascades to role tables
        flash_set('success', 'User deleted.');
    }
    header("Location: admin_users.php");
    exit();
}

// ---- Toggle suspend/activate quick action ----
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $r = $conn->query("SELECT account_status FROM users WHERE id=$id")->fetch_assoc();
    if ($r) {
        $new_status = $r['account_status'] === 'suspended' ? 'active' : 'suspended';
        $conn->query("UPDATE users SET account_status='$new_status' WHERE id=$id");
        flash_set('success', "Account status changed to $new_status.");
    }
    header("Location: admin_users.php");
    exit();
}

$roleFilter = $_GET['role'] ?? '';
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE 1=1";
if ($roleFilter) $sql .= " AND role='" . $conn->real_escape_string($roleFilter) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (full_name LIKE '%$s%' OR phone_number LIKE '%$s%')";
}
$sql .= " ORDER BY id DESC";
$users = $conn->query($sql);

$editUser = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editUser = $conn->query("SELECT * FROM users WHERE id=$eid")->fetch_assoc();
}
?>
<div class="container-fluid bg-light py-4">
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-users me-2"></i>User Management</h2>
        <button class="btn btn-agri" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openCreate()">
            <i class="fa-solid fa-plus me-1"></i>Add User
        </button>
    </div>

    <?php render_flashes(); ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-2" method="GET">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="Search name or phone" value="<?php echo h($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="farmer" <?php echo $roleFilter=='farmer'?'selected':''; ?>>Farmer</option>
                        <option value="loan_officer" <?php echo $roleFilter=='loan_officer'?'selected':''; ?>>Loan Officer</option>
                        <option value="admin" <?php echo $roleFilter=='admin'?'selected':''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>ID</th><th>Name</th><th>Phone</th><th>Role</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td class="fw-bold"><?php echo h($u['full_name']); ?></td>
                            <td><?php echo h($u['phone_number']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo h($u['role']); ?></span></td>
                            <td>
                                <?php
                                $sc = ['active'=>'success','pending'=>'warning text-dark','suspended'=>'danger'][$u['account_status']] ?? 'secondary';
                                echo "<span class='badge bg-$sc'>" . h($u['account_status']) . "</span>";
                                ?>
                            </td>
                            <td class="small text-muted"><?php echo h($u['created_at']); ?></td>
                            <td class="text-end">
                                <a href="admin_users.php?edit=<?php echo $u['id']; ?>#userModal" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick='openEdit(<?php echo json_encode($u); ?>)'><i class="fa-solid fa-pen"></i></a>
                                <a href="admin_users.php?toggle_status=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-power-off"></i></a>
                                <a href="admin_users.php?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user permanently? This removes all related records.');"><i class="fa-solid fa-trash"></i></a>
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Add User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" id="f_full_name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Phone Number</label><input type="text" name="phone_number" id="f_phone" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" id="f_role" class="form-select" required>
                    <option value="farmer">Farmer</option>
                    <option value="loan_officer">Loan Officer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Account Status</label>
                <select name="account_status" id="f_status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <span class="text-muted small">(leave blank to keep unchanged / auto-generate on create)</span></label>
                <input type="password" name="password" id="f_password" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-agri">Save User</button>
        </div>
    </form>
  </div>
</div>

<script>
function openCreate() {
    document.getElementById('modalTitle').innerText = 'Add User';
    document.getElementById('f_id').value = '';
    document.getElementById('f_full_name').value = '';
    document.getElementById('f_phone').value = '';
    document.getElementById('f_role').value = 'farmer';
    document.getElementById('f_status').value = 'active';
    document.getElementById('f_password').value = '';
}
function openEdit(u) {
    document.getElementById('modalTitle').innerText = 'Edit User #' + u.id;
    document.getElementById('f_id').value = u.id;
    document.getElementById('f_full_name').value = u.full_name;
    document.getElementById('f_phone').value = u.phone_number;
    document.getElementById('f_role').value = u.role;
    document.getElementById('f_status').value = u.account_status;
    document.getElementById('f_password').value = '';
}
<?php if ($editUser): ?>
document.addEventListener('DOMContentLoaded', function() {
    openEdit(<?php echo json_encode($editUser); ?>);
    new bootstrap.Modal(document.getElementById('userModal')).show();
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
