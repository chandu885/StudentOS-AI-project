<?php
// frontend/super-admin/login-logs.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$logs = [
    ['email' => 'superadmin@studentos.ai', 'ip' => '127.0.0.1', 'device' => 'Chrome 122 (Windows)', 'status' => 'SUCCESS', 'time' => '15 mins ago'],
    ['email' => 'robert.smith@college.edu', 'ip' => '192.168.1.45', 'device' => 'Firefox 123 (macOS)', 'status' => 'SUCCESS', 'time' => '42 mins ago'],
    ['email' => 'unknown.hacker@evil.org', 'ip' => '203.0.113.19', 'device' => 'Python-urllib/3.10', 'status' => 'FAILED', 'time' => '1 hour ago'],
    ['email' => 'alex.m@college.edu', 'ip' => '192.168.1.108', 'device' => 'Safari Mobile (iOS)', 'status' => 'SUCCESS', 'time' => '1 hour ago']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Security Logs - StudentOS AI</title>
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
                        <h1>Authentication & Login Audit Logs</h1>
                        <p class="page-subtitle">Track authorized logins and detect suspicious authentication attempts</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-lock"></i> Recent Authentication Activity</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Target Identity</th>
                                        <th>Origin IP</th>
                                        <th>Client User Agent</th>
                                        <th>Authentication Status</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): 
                                        $isSuccess = $log['status'] === 'SUCCESS';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($log['email']); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($log['ip']); ?></code></td>
                                            <td><?php echo htmlspecialchars($log['device']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $isSuccess ? 'success' : 'danger'; ?>">
                                                    <?php echo $log['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['time']); ?></td>
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
