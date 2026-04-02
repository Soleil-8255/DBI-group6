<?php
session_start();
include('../db_connect.php');

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$sql = "SELECT s.student_name, i.company_name, a.total_score, a.comments 
        FROM Assessment a
        JOIN Internship i ON a.internship_id = i.internship_id
        JOIN Student s ON i.student_id = s.student_id";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View Assessment Results</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h2>Student Internship Results</h2>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Company</th>
                <th>Total Score</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo $row['student_name']; ?></td>
                <td><?php echo $row['company_name']; ?></td>
                <td><?php echo number_format($row['total_score'], 2); ?></td>
                <td><?php echo $row['comments']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <br>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</body>
</html>