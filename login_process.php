<?php
session_start();
include('db_connect.php'); 

if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $role = $_POST['role'];

    // SQL query to verify credentials from the User table [cite: 49, 128]
    $sql = "SELECT * FROM User WHERE username = '$user' AND password_hash = '$pass' AND role = '$role'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['role'] = $row['role'];
        
        // Redirect based on role 
        if ($role == 'Admin') {
            header("Location: admin/admin_dashboard.php");
        } else {
            header("Location: assessor/assessor_dashboard.php");
        }
    } else {
        // Error message in English
        echo "<script>alert('Invalid Username, Password, or Role!'); window.location.href='index.php';</script>";
    }
}
?>