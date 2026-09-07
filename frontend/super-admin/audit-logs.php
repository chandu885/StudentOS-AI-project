<?php
// frontend/super-admin/audit-logs.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$audits = [
    ['user' => 'Super Administrator', 'action' => 'ROLE_PERMISSIONS_UPDATE', 'details' => 'Updated faculty exam permissions', 'ip' => '127.0.0.1', 'time' => '10 mins ago'],
    ['user' => 'Registrar Admin', 'action' => 'COURSE_CREATE', 'details' => 'Created B.Tech in Data Science', 'ip' => '192.168.1.15', 'time' => '45 mins ago'],
    ['user' => 'Super Administrator', 'action' => 'SYSTEM_SETTINGS_UPDATE', 'details' => 'Configured Gemini 1.5 Flash as default AI engine', 'ip' => '127.0.0.1', 'time' => '2 hours ago'],
    ['user' => 'System Worker', 'action' => 'BACKUP_CREATE', 'details' => 'Full automated database snapshot completed', 'ip' => '127.0.0.1', 'time' => '4 hours ago']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs - StudentOS AI</title>
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
                        <h1>System Audit & Compliance Logs</h1>
                        <p class="page-subtitle">Immutable audit trail of administrative modifications, permission changes, and security events</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> System Activity Journal</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Actor</th>
                                        <th>Action Event</th>
                                        <th>Modification Summary</th>
                                        <th>IP Address</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audits as $a): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($a['user']); ?></strong></td>
                                            <td><span class="badge badge-purple"><?php echo htmlspecialchars($a['action']); ?></span></td>
                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($a['details']); ?></td>
                                            <td><code><?php echo htmlspecialchars($a['ip']); ?></code></td>
                                            <td><?php echo htmlspecialchars($a['time']); ?></td>
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
