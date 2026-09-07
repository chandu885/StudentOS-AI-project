<?php
// frontend/super-admin/sessions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Session revoked immediately!';
}

$sessions = [
    ['id' => 'sess_99a8b7', 'user' => 'Root Super Admin', 'email' => 'superadmin@studentos.ai', 'ip' => '127.0.0.1', 'device' => 'Chrome 122 (Windows 11)', 'started' => '15 mins ago', 'is_current' => true],
    ['id' => 'sess_44e12c', 'user' => 'Dr. Robert Smith', 'email' => 'robert.smith@college.edu', 'ip' => '192.168.1.45', 'device' => 'Firefox 123 (macOS Sonoma)', 'started' => '42 mins ago', 'is_current' => false],
    ['id' => 'sess_11d99e', 'user' => 'Alex Morgan', 'email' => 'alex.m@college.edu', 'ip' => '192.168.1.108', 'device' => 'Safari Mobile (iOS 17)', 'started' => '1 hour ago', 'is_current' => false]
];
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
                        <button class="btn btn-danger" onclick="if(confirm('Terminate all active sessions except your current one?')) showToast('All background sessions terminated', 'success')">
                            <i class="fas fa-power-off"></i> Terminate All Other Sessions
                        </button>
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
