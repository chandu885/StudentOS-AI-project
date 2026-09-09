<?php
// frontend/super-admin/sessions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';
$conn = getDbConnection();
$curToken = $_SESSION['session_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if ($action === 'terminate_all_others') {
        if ($conn) {
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id != ? OR session_token != ?");
            if ($stmt) {
                $stmt->bind_param("is", $userId, $curToken);
                $stmt->execute();
                $stmt->close();
            }
            $details = 'Super Admin forcibly terminated all background concurrent sessions';
            $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES (?, 'SESSIONS_TERMINATED_ALL', 'user_sessions', ?, ?)");
            if ($aud) { $aud->bind_param("iss", $userId, $details, $ip); $aud->execute(); $aud->close(); }
        }
        $successMsg = 'All concurrent sessions (except your current active device) have been terminated.';
    } elseif ($action === 'kill_session' || isset($_POST['session_id'])) {
        $sessId = (int)($_POST['session_id'] ?? 0);
        if ($conn && $sessId > 0) {
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $sessId);
                $stmt->execute();
                $stmt->close();
            }
            $details = "Super Admin invalidated user session #{$sessId}";
            $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES (?, 'SESSION_KILLED', 'user_sessions', ?, ?)");
            if ($aud) { $aud->bind_param("iss", $userId, $details, $ip); $aud->execute(); $aud->close(); }
        }
        $successMsg = 'Session revoked immediately!';
    }
}

$sessions = [];
if ($conn) {
    $sql = "SELECT s.id, s.session_token, s.ip_address, s.user_agent, s.last_activity, s.expires_at, s.created_at,
                   u.id AS user_id, u.email, CONCAT(u.first_name, ' ', u.last_name) AS full_name
            FROM user_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.last_activity DESC LIMIT 50";
    $res = $conn->query($sql);
    if ($res) {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $foundCurrent = false;
        while ($row = $res->fetch_assoc()) {
            $isCurrent = false;
            if (!$foundCurrent && (!empty($curToken) && $row['session_token'] === $curToken)) {
                $isCurrent = true;
                $foundCurrent = true;
            } elseif (!$foundCurrent && (int)$row['user_id'] === $userId && $row['ip_address'] === $clientIp) {
                $isCurrent = true;
                $foundCurrent = true;
            }
            $device = !empty($row['user_agent']) && $row['user_agent'] !== 'Unknown' ? substr($row['user_agent'], 0, 45) : 'Chrome (Windows 11)';
            $sessions[] = [
                'id' => $row['id'],
                'user' => $row['full_name'] ?: 'Root Super Admin',
                'email' => $row['email'] ?: 'superadmin@studentos.ai',
                'ip' => $row['ip_address'] ?: '127.0.0.1',
                'device' => $device,
                'started' => timeAgo($row['last_activity']),
                'is_current' => $isCurrent
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
    <title>Active Sessions - StudentOS AI</title>
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
                        <h1>Active User Sessions</h1>
                        <p class="page-subtitle">Real-time monitoring and termination of authenticated user sessions</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="sessions.php" onsubmit="return confirm('Terminate all active sessions except your current device?');" style="margin: 0;">
                            <input type="hidden" name="action" value="terminate_all_others">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-power-off"></i> Terminate All Other Sessions
                            </button>
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
                        <h3><i class="fas fa-desktop"></i> Live Authenticated Sessions</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>IP Address</th>
                                        <th>Client Browser / OS</th>
                                        <th>Logged In</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sessions)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">No active sessions found.</td>
                                        </tr>
                                    <?php else: ?>
                                    <?php foreach ($sessions as $s): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($s['user']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($s['email']); ?></div>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($s['ip']); ?></code></td>
                                            <td><?php echo htmlspecialchars($s['device']); ?></td>
                                            <td><?php echo htmlspecialchars($s['started']); ?></td>
                                            <td>
                                                <?php if ($s['is_current']): ?>
                                                    <span class="badge badge-success"><i class="fas fa-star"></i> Current Device</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$s['is_current']): ?>
                                                    <form method="POST" action="sessions.php" style="margin: 0;">
                                                        <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);">
                                                            <i class="fas fa-ban"></i> Kill Session
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: var(--text-muted);">Current</span>
                                                <?php endif; ?>
                                            </td>
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
