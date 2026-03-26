<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

$search_query = "";
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

$sql = "SELECT 
            s.student_id, 
            s.full_name, 
            a.task_score,
            a.report_score,
            a.proj_mgmt_score, 
            a.time_mgmt_score, 
            a.final_mark
        FROM assessments a
        LEFT JOIN students s ON a.student_id = s.student_id
        WHERE 1=1";

$types = "";
$params = [];

if ($search_query !== "") {
    $sql .= " AND (s.student_id LIKE ? OR s.full_name LIKE ?)";
    $types .= "ss";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Assessment Results</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; padding: 30px; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 15px; margin-top: 0; }
        .search-box { margin-bottom: 25px; display: flex; gap: 10px; }
        input[type="text"] { padding: 10px; width: 300px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        button { padding: 10px 20px; background-color: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background-color 0.3s; }
        button:hover { background-color: #2980b9; }
        .btn-clear { background-color: #95a5a6; text-decoration: none; display: inline-block; }
        .btn-clear:hover { background-color: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background-color: #fff; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #333; font-weight: 600; }
        tr:hover { background-color: #f1f8ff; }
        .total-score { font-weight: bold; color: #e74c3c; font-size: 16px; }
        .no-data { text-align: center; padding: 30px; color: #7f8c8d; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Internship Assessment Results</h2>
        
        <form class="search-box" method="GET" action="view_results.php">
            <input type="text" name="search" placeholder="Search by Student ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit">Search</button>
            <a href="view_results.php" class="btn-clear"><button type="button" class="btn-clear">Clear Filter</button></a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Task Score</th>
                    <th>Report Score</th>
                    <th>Project Mgmt</th>
                    <th>Time Mgmt</th>
                    <th>Final Mark</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['task_score'] ?? '0.00'); ?></td>
                            <td><?php echo htmlspecialchars($row['report_score'] ?? '0.00'); ?></td>
                            <td><?php echo htmlspecialchars($row['proj_mgmt_score'] ?? '0.00'); ?></td>
                            <td><?php echo htmlspecialchars($row['time_mgmt_score'] ?? '0.00'); ?></td>
                            <td class="total-score"><?php echo htmlspecialchars($row['final_mark'] ?? '0.00'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No assessment records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php 
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close(); 
?>