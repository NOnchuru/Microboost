<?php
require_once 'includes/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone_number']);
    $role = $conn->real_escape_string($_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Set initial status based on role (farmers pending admin approval, others active for now)
    $status = ($role == 'farmer') ? 'pending' : 'active';
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO USERS (full_name, phone_number, password, role, account_status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $full_name, $phone, $password, $role, $status, $created_at);

    if ($stmt->execute()) {
        header("Location: login.php?success=registered");
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
?>