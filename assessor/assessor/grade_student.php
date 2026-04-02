<?php
session_start();
include('../db_connect.php');

if(isset($_POST['submit_grade'])){
    $intern_id = $_POST['internship_id'];
    
    $m1 = $_POST['tasks'];
    $m2 = $_POST['health_safety'];
    $m3 = $_POST['knowledge'];
    $m4 = $_POST['report'];
    $m5 = $_POST['clarity'];
    $m6 = $_POST['lifelong'];
    $m7 = $_POST['project_mgmt'];
    $m8 = $_POST['time_mgmt'];


    $total = ($m1 + $m2 + $m3 + $m4 + $m5 + $m6 + $m7 + $m8) / 8;

    $sql = "INSERT INTO Assessment (internship_id, mark_tasks, mark_health_safety, mark_knowledge, 
            mark_report, mark_clarity, mark_lifelong, mark_project_management, mark_time_management, total_score) 
            VALUES ('$intern_id', '$m1', '$m2', '$m3', '$m4', '$m5', '$m6', '$m7', '$m8', '$total')";
    
    if(mysqli_query($conn, $sql)){
        echo "<script>alert('Assessment Submitted! Total Score: $total');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grade Student</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h2>Internship Assessment Form</h2>
    <form method="POST">
        <p>Internship ID: <input type="number" name="internship_id" required></p>
        
        <label>1. Tasks Performed (0-100):</label><br>
        <input type="number" name="tasks" min="0" max="100"><br>
        
        <label>2. Health & Safety:</label><br>
        <input type="number" name="health_safety" min="0" max="100"><br>
        
        <label>3. Technical Knowledge:</label><br>
        <input type="number" name="knowledge" min="0" max="100"><br>
        
        <label>4. Quality of Report:</label><br>
        <input type="number" name="report" min="0" max="100"><br>
        
        <label>5. Clarity of Presentation:</label><br>
        <input type="number" name="clarity" min="0" max="100"><br>
        
        <label>6. Lifelong Learning Attitude:</label><br>
        <input type="number" name="lifelong" min="0" max="100"><br>
        
        <label>7. Project Management:</label><br>
        <input type="number" name="project_mgmt" min="0" max="100"><br>
        
        <label>8. Time Management:</label><br>
        <input type="number" name="time_mgmt" min="0" max="100"><br>
        
        <br>
        <button type="submit" name="submit_grade">Submit Grades</button>
    </form>
</body>
</html>