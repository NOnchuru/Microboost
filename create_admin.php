<?php
require_once 'includes/db_connect.php';

$full_name = "System Administrator";
$phone = "+254700000000";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";
$status = "active";

// Clear any existing test account with this phone number
$conn->query("DELETE FROM USERS WHERE phone_number = '$phone'");

$stmt = $conn->prepare("INSERT INTO USERS (full_name, phone_number, password, role, account_status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $full_name, $phone, $password, $role, $status);

if ($stmt->execute()) {
    echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'>";
    echo "<h2 style='color:green;'>Admin Account Created Successfully!</h2>";
    echo "<p><strong>Phone:</strong> +254700000000</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<a href='login.php' style='display:inline-block; padding:10px 20px; background:#2E7D32; color:white; text-decoration:none; border-radius:5px;'>Go to Login Page</a>";
    echo "</div>";
} else {
    echo "Error creating admin: " . $stmt->error;
}
?>