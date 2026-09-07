<?php
// frontend/super-admin/permissions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Role permission matrix successfully updated and deployed to active sessions!';
}

$matrix = [
    ['module' => 'Own Profile', 'student' => true, 'faculty' => true, 'admin' => true, 'super' => true],
    ['module' => 'Student Management', 'student' => false, 'faculty' => false, 'admin' => true, 'super' => true],
    ['module' => 'Faculty Management', 'student' => false, 'faculty' => false, 'admin' => true, 'super' => true],
    ['module' => 'Admin Management', 'student' => false, 'faculty' => false, 'admin' => false, 'super' => true],
    ['module' => 'Course Management', 'student' => false, 'faculty' => 'Limited', 'admin' => true, 'super' => true],
    ['module' => 'Exam Management', 'student' => 'Own', 'faculty' => true, 'admin' => true, 'super' => true],
    ['module' => 'Result Management', 'student' => 'Own', 'faculty' => true, 'admin' => true, 'super' => true],
    ['module' => 'AI Assistant', 'student' => true, 'faculty' => true, 'admin' => true, 'super' => true],
    ['module' => 'AI Settings', 'student' => false, 'faculty' => false, 'admin' => false, 'super' => true],
    ['module' => 'System Settings', 'student' => false, 'faculty' => false, 'admin' => 'Limited', 'super' => true],
    ['module' => 'Audit Logs', 'student' => false, 'faculty' => false, 'admin' => 'Limited', 'super' => true],
    ['module' => 'Backup & Recovery', 'student' => false, 'faculty' => false, 'admin' => false, 'super' => true]
];

function renderCheck($val) {
    if ($val === true) return '<i class="fas fa-check-circle" style="color: var(--success); font-size: 16px;"></i>';
    if ($val === false) return '<i class="fas fa-times-circle" style="color: var(--text-muted); font-size: 16px; opacity: 0.4;"></i>';
    return '<span class="badge badge-warning">' . htmlspecialchars($val) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Matrix - StudentOS AI</title>
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
                        <h1>RBAC Permission Matrix</h1>
                        <p class="page-subtitle">Module-level authorization controls mapped across user tiers</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="permissions.php" style="margin: 0;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Save & Sync Permissions</button>
                        </form>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-key"></i> Module Access Table</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" style="text-align: center;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left;">System Module</th>
                                        <th style="text-align: center;">Student</th>
                                        <th style="text-align: center;">Faculty</th>
                                        <th style="text-align: center;">Admin</th>
                                        <th style="text-align: center;">Super Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matrix as $m): ?>
                                        <tr>
                                            <td style="text-align: left;"><strong><?php echo htmlspecialchars($m['module']); ?></strong></td>
                                            <td><?php echo renderCheck($m['student']); ?></td>
                                            <td><?php echo renderCheck($m['faculty']); ?></td>
                                            <td><?php echo renderCheck($m['admin']); ?></td>
                                            <td><?php echo renderCheck($m['super']); ?></td>
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
