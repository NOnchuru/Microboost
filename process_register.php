<?php
session_start();
require_once 'includes/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $role = $_POST['role'];
    $password_plain = $_POST['password'];

    if (!in_array($role, ['farmer', 'loan_officer'])) {
        header("Location: register.php?error=" . urlencode("Invalid role selected."));
        exit();
    }
    if (strlen($password_plain) < 6) {
        header("Location: register.php?error=" . urlencode("Password must be at least 6 characters."));
        exit();
    }

    $check = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
    $check->bind_param("s", $phone);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header("Location: register.php?error=" . urlencode("Phone number already registered."));
        exit();
    }

    $password = password_hash($password_plain, PASSWORD_DEFAULT);
    $status = ($role == 'farmer') ? 'pending' : 'active';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO users (full_name, phone_number, password, role, account_status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $phone, $password, $role, $status);
        $stmt->execute();
        $user_id = $conn->insert_id;

        if ($role === 'farmer') {
            $county = trim($_POST['county'] ?? '') ?: 'Unspecified';
            $primary_crop = trim($_POST['primary_crop'] ?? '') ?: 'Unspecified';
            $farm_size = ($_POST['farm_size_acres'] !== '' ) ? (float)$_POST['farm_size_acres'] : null;

            $stmt2 = $conn->prepare("INSERT INTO farmers (user_id, county, primary_crop, farm_size_acres) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("issd", $user_id, $county, $primary_crop, $farm_size);
            $stmt2->execute();
        } else {
            $sacco_name = trim($_POST['sacco_name'] ?? '') ?: 'Unspecified SACCO';
            $employee_no = trim($_POST['employee_no'] ?? '') ?: ('EMP' . $user_id);

            $stmt2 = $conn->prepare("INSERT INTO loan_officers (user_id, sacco_name, employee_no) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $user_id, $sacco_name, $employee_no);
            $stmt2->execute();
        }

        $conn->commit();
        $msg = ($role === 'farmer')
            ? "Registration successful! Your account is pending admin approval."
            : "Registration successful! You can now log in.";
        $_SESSION['flash']['success'] = $msg;
        header("Location: login.php");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: register.php?error=" . urlencode("Registration failed: " . $e->getMessage()));
    }
    exit();
}
$conn->close();
