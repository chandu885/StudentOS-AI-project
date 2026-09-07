<?php
// frontend/super-admin/roles.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$roles = [
    ['id' => 1, 'name' => 'SUPER_ADMIN', 'display' => 'Super Administrator', 'desc' => 'Universal system access, security, AI settings, and root audit controls.', 'users' => 2],
    ['id' => 2, 'name' => 'ADMIN', 'display' => 'Academic Administrator', 'desc' => 'Department oversight, course scheduling, and student roster operations.', 'users' => 12],
    ['id' => 3, 'name' => 'FACULTY', 'display' => 'Faculty Professor', 'desc' => 'Course instruction, attendance registers, assignment evaluation, and exams.', 'users' => 84],
    ['id' => 4, 'name' => 'STUDENT', 'display' => 'Student Learner', 'desc' => 'Coursework submissions, study tracking, examinations, and AI study tools.', 'users' => 1238]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Hierarchy - StudentOS AI</title>
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
                        <h1>Role-Based Access Control (RBAC) Hierarchy</h1>
                        <p class="page-subtitle">Standardized user roles, access authorization tiers, and privilege scope</p>
                    </div>
                    <div class="header-actions">
                        <a href="permissions.php" class="btn btn-primary"><i class="fas fa-key"></i> View Permission Matrix</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-tag"></i> System Roles</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Role ID</th>
                                        <th>Role Identifier</th>
                                        <th>Display Title</th>
                                        <th>Description & Scope</th>
                                        <th>Assigned Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $r): ?>
                                        <tr>
                                            <td><strong>#<?php echo $r['id']; ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($r['name']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($r['display']); ?></strong></td>
                                            <td style="color: var(--text-secondary); max-width: 420px; font-size: 13px;"><?php echo htmlspecialchars($r['desc']); ?></td>
                                            <td><span class="badge badge-info"><?php echo $r['users']; ?> accounts</span></td>
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
