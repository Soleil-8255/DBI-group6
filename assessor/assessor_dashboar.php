<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Assessor') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assessor Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h1>Assessor Panel</h1>
    <p>Welcome, Assessor! Please select an action:</p>
    <hr>
    <ul>
        <li><a href="grade_student.php">Grade Student Internship</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</body>
</html>