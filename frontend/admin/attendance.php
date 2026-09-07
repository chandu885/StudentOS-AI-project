<?php
// frontend/admin/attendance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$atRiskStudents = [
    ['roll' => 'CS-2023-02', 'name' => 'Brian Clark', 'dept' => 'Computer Science', 'subject' => 'Operating Systems', 'pct' => 71.2],
    ['roll' => 'ME-2023-11', 'name' => 'Samuel Adams', 'dept' => 'Mechanical', 'subject' => 'Thermodynamics', 'pct' => 68.4],
    ['roll' => 'EC-2023-08', 'name' => 'Olivia Pope', 'dept' => 'Electronics', 'subject' => 'Signals & Systems', 'pct' => 72.0]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Attendance - StudentOS AI</title>
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
                        <h1>Institutional Attendance Monitoring</h1>
                        <p class="page-subtitle">Track campus attendance benchmarks, class conduction rates, and students with low attendance</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">87.4%</span>
                            <span class="stat-label">Campus Attendance Average</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($atRiskStudents); ?></span>
                            <span class="stat-label">Students Below 75%</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-chalkboard"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">100%</span>
                            <span class="stat-label">Class Conduction Rate</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-times" style="color: var(--danger);"></i> Students Under 75% Attendance (Examination Alert)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Department</th>
                                        <th>Subject Deficit</th>
                                        <th>Attendance %</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($atRiskStudents as $stu): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($stu['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($stu['subject']); ?></td>
                                            <td><strong style="color: var(--danger);"><?php echo $stu['pct']; ?>%</strong></td>
                                            <td>
                                                <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="showToast('Attendance warning notification issued', 'info')">
                                                    <i class="fas fa-paper-plane"></i> Send Notice
                                                </button>
                                            </td>
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
