<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Administrator Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h1>System Administration</h1>
    <p>Welcome, Admin! What would you like to do today?</p>
    <hr>
    
    <div class="menu">
        <h3>1. Student Management</h3>
        <ul>
            <li><a href="add_student.php">Add New Student</a></li>
            <li><a href="assign_internship.php">Assign Student to Assessor</a></li>
        </ul>

        <h3>2. Reports</h3>
        <ul>
            <li><a href="view_results.php">View All Assessment Results</a></li>
        </ul>

        <h3>3. Account</h3>
        <ul>
            <li><a href="../logout.php">Logout</a></li>
        </ul>
    </div>
</body>
</html>