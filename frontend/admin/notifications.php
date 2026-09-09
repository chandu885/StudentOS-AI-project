<?php
// frontend/admin/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message'])) {
    $title = sanitize($_POST['title'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $target = sanitize($_POST['target'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');

    if (empty($title) || empty($message)) {
        $errorMsg = 'Notification title and message body are required.';
    } elseif ($db) {
        $roles = [];
        if ($target === 'students') $roles = [4];
        elseif ($target === 'faculty') $roles = [3];
        else $roles = [3, 4]; // Everyone

        $roleList = implode(',', $roles);
        $userRes = $db->query("SELECT id FROM users WHERE role_id IN ($roleList) AND deleted_at IS NULL");
        
        if ($userRes) {
            $recipients = $userRes->fetch_all(MYSQLI_ASSOC);
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $dispatchedCount = 0;
            if ($stmt) {
                foreach ($recipients as $u) {
                    $uId = (int)$u['id'];
                    $stmt->bind_param("isss", $uId, $title, $message, $type);
                    if ($stmt->execute()) {
                        $dispatchedCount++;
                    }
                }
                $stmt->close();
            }
            $targetLabel = $target === 'students' ? 'students' : ($target === 'faculty' ? 'faculty members' : 'students and faculty');
            $successMsg = "Broadcast notification successfully dispatched to $dispatchedCount $targetLabel!";
        } else {
            $errorMsg = 'Failed to retrieve recipient users: ' . $db->error;
        }
    }
}

// Fetch live broadcast history
$history = [];
if ($db) {
    $hRes = $db->query(
        "SELECT title, message, type, MAX(created_at) AS created_at, COUNT(DISTINCT user_id) AS recipient_count
         FROM notifications
         GROUP BY title, message, type
         ORDER BY MAX(created_at) DESC
         LIMIT 10"
    );
    if ($hRes) {
        $history = $hRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Notifications - StudentOS AI</title>
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
                        <h1>Institutional Notifications & Broadcasts</h1>
                        <p class="page-subtitle">Send real-time alerts to student and faculty dashboards</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bullhorn"></i> Dispatch New Notification</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="notifications.php">
                                <div class="form-group">
                                    <label for="bTitle">Notification Title *</label>
                                    <input type="text" name="title" id="bTitle" class="form-control" placeholder="e.g. Campus Wi-Fi Maintenance Window" required>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                    <div class="form-group">
                                        <label for="bTarget">Recipient Audience *</label>
                                        <select name="target" id="bTarget" class="form-control">
                                            <option value="all">Everyone (Students & Faculty)</option>
                                            <option value="students">Students Only</option>
                                            <option value="faculty">Faculty Only</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="bType">Alert Category</label>
                                        <select name="type" id="bType" class="form-control">
                                            <option value="info">Information (Blue)</option>
                                            <option value="warning">Important Alert (Yellow)</option>
                                            <option value="deadline">Urgent Deadline (Red)</option>
                                            <option value="success">Success / Confirmation (Green)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="bMessage">Message Body *</label>
                                    <textarea name="message" id="bMessage" class="form-control" rows="4" placeholder="Type notification broadcast message..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-paper-plane"></i> Broadcast Message
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3><i class="fas fa-history"></i> Recent Broadcast History (<?php echo count($history); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($history)): ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 32px;">
                                    No past notification broadcasts found.
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 14px;">
                                    <?php foreach ($history as $h): ?>
                                        <div style="padding: 14px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($h['title']); ?></strong>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> <?php echo (int)$h['recipient_count']; ?> Delivered
                                                </span>
                                            </div>
                                            <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($h['message']); ?>
                                            </p>
                                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
                                                <span><span class="badge badge-secondary"><?php echo htmlspecialchars(ucfirst($h['type'])); ?></span></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($h['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
