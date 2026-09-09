<?php
// frontend/admin/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

$totalStudents = 0;
$totalFaculty = 0;
$totalDepts = 0;
$totalCourses = 0;
$recentStudents = [];

if ($db) {
    // Total Students
    $r = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role_id = 4 AND deleted_at IS NULL");
    if ($r) $totalStudents = (int)$r->fetch_assoc()['cnt'];

    // Total Faculty
    $r = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role_id = 3 AND deleted_at IS NULL");
    if ($r) $totalFaculty = (int)$r->fetch_assoc()['cnt'];

    // Total Departments
    $r = $db->query("SELECT COUNT(*) AS cnt FROM departments WHERE status = 'active'");
    if ($r) $totalDepts = (int)$r->fetch_assoc()['cnt'];

    // Total Courses
    $r = $db->query("SELECT COUNT(*) AS cnt FROM courses WHERE status = 'active'");
    if ($r) $totalCourses = (int)$r->fetch_assoc()['cnt'];

    // Recent Students
    $res = $db->query(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, IF(u.is_active = 1, 'active', 'inactive') AS status, u.created_at,
                COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                COALESCE(d.name, 'General Academics') AS dept,
                COALESCE(CONCAT('Semester ', sp.semester), 'Semester 1') AS sem
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN departments d ON sp.department_id = d.id
         WHERE u.role_id = 4 AND u.deleted_at IS NULL
         ORDER BY u.id DESC
         LIMIT 8"
    );
    if ($res) {
        $recentStudents = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Administration - StudentOS AI</title>
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
                <div class="welcome-section">
                    <div>
                        <h1>Academic Administration</h1>
                        <p class="welcome-subtitle">Institution Overview • Campus Operations & Department Management</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalStudents); ?></span>
                            <span class="stat-label">Total Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent);"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalFaculty); ?></span>
                            <span class="stat-label">Faculty Members</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalDepts); ?></span>
                            <span class="stat-label">Departments</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalCourses); ?></span>
                            <span class="stat-label">Degree Programs</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-check"></i> Recently Registered Students</h3>
                        <a href="students.php" class="link">View All Students</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Full Name</th>
                                        <th>Department</th>
                                        <th>Current Semester</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentStudents)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-users"></i> No registered students found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentStudents as $stu): 
                                            $st = strtolower($stu['status'] ?? 'active');
                                            $stBadge = ($st === 'active') ? 'badge-success' : 'badge-secondary';
                                        ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($stu['dept']); ?></td>
                                                <td><?php echo htmlspecialchars($stu['sem']); ?></td>
                                                <td><span class="badge <?php echo $stBadge; ?>"><?php echo ucfirst($st); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
