<?php
require_once "config.php";

$username = 'nathan';
$email = 'nezhaathian5@gmail.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$full_name = 'Nathan';
$role = 'director';

$query = "INSERT INTO users (username, email, password, full_name, role) 
          VALUES ('$username', '$email', '$password', '$full_name', '$role')
          ON DUPLICATE KEY UPDATE 
          password = '$password',
          full_name = '$full_name',
          role = '$role'";

if (mysqli_query($conn, $query)) {
    echo "User berhasil dibuat/update! Silakan login.";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>