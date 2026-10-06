<?php
session_start();
require_once 'includes/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $conn->real_escape_string($_POST['phone_number']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM USERS WHERE phone_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Route to specific dashboard based on role
            if ($user['role'] == 'farmer') header("Location: farmer_dashboard.php");
            elseif ($user['role'] == 'loan_officer') header("Location: lender_dashboard.php");
            elseif ($user['role'] == 'admin') header("Location: admin_dashboard.php");
            exit();
        }
    }
    header("Location: login.php?error=invalid");
    $stmt->close();
}
$conn->close();
?>