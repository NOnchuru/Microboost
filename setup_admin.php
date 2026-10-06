<?php
require_once 'includes/db_connect.php';

$phone = '+254700000000';
$password_plain = 'admin123';
// Generates a valid hash specifically compatible with your PHP installation
$hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
$full_name = 'System Administrator';
$role = 'admin';
$status = 'active';

// Remove any existing user with this phone number to avoid duplicate errors
$conn->query("DELETE FROM USERS WHERE phone_number = '$phone'");

// Insert the new admin account
$stmt = $conn->prepare("INSERT INTO USERS (full_name, phone_number, password, role, account_status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $full_name, $phone, $hashed_password, $role, $status);

if ($stmt->execute()) {
    echo "<div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h2 style='color: #2E7D32;'>Admin Account Successfully Created!</h2>";
    echo "<p><strong>Phone:</strong> $phone</p>";
    echo "<p><strong>Password:</strong> $password_plain</p>";
    echo "<br><a href='login.php' style='padding: 12px 24px; background: #2E7D32; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Login Page</a>";
    echo "</div>";
} else {
    echo "Error inserting record: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>