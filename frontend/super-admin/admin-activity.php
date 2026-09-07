<?php
// frontend/super-admin/admin-activity.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$activities = [
    ['admin' => 'Registrar Admin', 'module' => 'Courses', 'activity' => 'Approved semester 6 course registration roster', 'time' => '1 hour ago'],
    ['admin' => 'Examination Controller', 'module' => 'Examinations', 'activity' => 'Published Midterm Examination date sheet for Computer Science', 'time' => '3 hours ago'],
    ['admin' => 'Registrar Admin', 'module' => 'Students', 'activity' => 'Generated student ID STU-2026-094 for transfer applicant', 'time' => 'Yesterday']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Activity Log - StudentOS AI</title>
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
                        <h1>Staff & Administrator Activity Tracker</h1>
                        <p class="page-subtitle">Track individual operational tasks performed by academic administrators</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-gear"></i> Administrative Operations History</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Administrator</th>
                                        <th>Target Module</th>
                                        <th>Operation Detail</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $act): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($act['admin']); ?></strong></td>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($act['module']); ?></span></td>
                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($act['activity']); ?></td>
                                            <td><?php echo htmlspecialchars($act['time']); ?></td>
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
