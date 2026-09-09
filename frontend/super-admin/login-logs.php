<?php
// frontend/super-admin/login-logs.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();
$logs = [];

if ($conn) {
    $sql = "SELECT l.id, l.user_id, l.success, l.ip_address, l.user_agent, l.failure_reason, l.created_at,
                   COALESCE(u.email, 'unknown_auth_attempt') AS email,
                   CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS user_name
            FROM login_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.id DESC LIMIT 100";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $device = !empty($r['user_agent']) && $r['user_agent'] !== 'Unknown' ? substr($r['user_agent'], 0, 45) : 'Chrome (Windows)';
            $logs[] = [
                'email' => $r['email'],
                'ip' => $r['ip_address'] ?: '127.0.0.1',
                'device' => $device,
                'status' => ((int)$r['success'] === 1) ? 'SUCCESS' : 'FAILED',
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
