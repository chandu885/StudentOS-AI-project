<?php
// frontend/faculty/students.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$students = [
    ['roll' => 'CS-2023-01', 'name' => 'Alex Morgan', 'email' => 'alex.m@college.edu', 'attendance_pct' => 92, 'submissions' => '4/4', 'grade_avg' => 'A'],
    ['roll' => 'CS-2023-02', 'name' => 'Brian Clark', 'email' => 'brian.c@college.edu', 'attendance_pct' => 71, 'submissions' => '2/4', 'grade_avg' => 'C+'],
    ['roll' => 'CS-2023-03', 'name' => 'Catherine Davis', 'email' => 'catherine.d@college.edu', 'attendance_pct' => 88, 'submissions' => '4/4', 'grade_avg' => 'A-'],
    ['roll' => 'CS-2023-04', 'name' => 'Daniel Evans', 'email' => 'daniel.e@college.edu', 'attendance_pct' => 95, 'submissions' => '4/4', 'grade_avg' => 'A+']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Students - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Enrolled Student Directory</h1>
                        <p class="page-subtitle">Track individual student engagement, attendance records, and coursework completion</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Course Roster (Database Management Systems)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Attendance</th>
                                        <th>Submissions</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $stu): 
                                        $att = $stu['attendance_pct'];
                                        $attClass = $att >= 85 ? 'success' : ($att >= 75 ? 'warning' : 'danger');
                                    ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($stu['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $attClass; ?>"><?php echo $att; ?>%</span>
                                            </td>
                                            <td><?php echo htmlspecialchars($stu['submissions']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($stu['grade_avg']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
