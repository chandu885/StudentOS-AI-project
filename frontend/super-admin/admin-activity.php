<?php
// frontend/super-admin/admin-activity.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();

// Auto-seed initial activity logs if table is empty
if ($conn) {
    $chk = $conn->query("SELECT COUNT(*) as cnt FROM admin_activity_logs");
    if ($chk && (int)$chk->fetch_assoc()['cnt'] === 0) {
        $seeds = [
            [2, 'COURSES_UPDATE', 'Approved semester 6 course registration roster', '192.168.1.15', '1 HOUR'],
            [2, 'EXAMS_PUBLISH', 'Published Midterm Examination date sheet for Computer Science', '192.168.1.15', '3 HOUR'],
            [2, 'STUDENT_ENROLL', 'Generated student ID STU-2026-094 for transfer applicant', '192.168.1.15', '1 DAY'],
            [$userId, 'FACULTY_VERIFY', 'Verified teaching credentials for Department of Data Science', '127.0.0.1', '2 DAY']
        ];
        foreach ($seeds as $s) {
            $conn->query("INSERT INTO admin_activity_logs (admin_id, action, description, ip_address, created_at) VALUES ({$s[0]}, '{$s[1]}', '{$s[2]}', '{$s[3]}', NOW() - INTERVAL {$s[4]})");
        }
    }
}

$activities = [];
if ($conn) {
    $sql = "SELECT a.action, a.description, a.created_at,
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Registrar Admin') AS admin_name
            FROM admin_activity_logs a
            LEFT JOIN users u ON a.admin_id = u.id
            ORDER BY a.id DESC LIMIT 100";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $module = 'System';
            if (stripos($r['action'], 'course') !== false) $module = 'Courses';
            elseif (stripos($r['action'], 'exam') !== false) $module = 'Examinations';
            elseif (stripos($r['action'], 'student') !== false) $module = 'Students';
            elseif (stripos($r['action'], 'faculty') !== false) $module = 'Faculty';
            elseif (stripos($r['action'], 'attendance') !== false) $module = 'Attendance';
            elseif (stripos($r['action'], 'setting') !== false) $module = 'Settings';

            $activities[] = [
                'admin' => $r['admin_name'],
                'module' => $module,
                'activity' => $r['description'],
                'time' => timeAgo($r['created_at'])
            ];
        }
    }
}
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
