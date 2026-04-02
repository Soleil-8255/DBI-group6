<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Management System - Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-container">
        <h2>Internship Management System</h2>
        <p>Please enter your credentials to login.</p>
        
        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label><br>
                <input type="text" id="username" name="username" required>
            </div>
            <br>
            <div class="form-group">
                <label for="password">Password:</label><br>
                <input type="password" id="password" name="password" required>
            </div>
            <br>
            <div class="form-group">
                <label for="role">User Role:</label><br>
                <select name="role" id="role" required>
                    <option value="Admin">Administrator</option>
                    <option value="Assessor">Assessor (Lecturer/Supervisor)</option>
                </select>
            </div>
            <br>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>