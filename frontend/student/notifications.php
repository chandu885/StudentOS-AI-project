<?php
// frontend/student/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];

$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'read_all') {
    if ($db) {
        $upStmt = $db->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `user_id` = ?");
        if ($upStmt) {
            $upStmt->bind_param("i", $userId);
            $upStmt->execute();
        }
    }
    apiCall('/notifications.php?action=read-all', 'POST');
    redirect('/student/notifications.php');
}

$notifications = [];
if ($db) {
    $stmt = $db->prepare("SELECT * FROM `notifications` WHERE `user_id` = ? OR `user_id` = 0 ORDER BY `created_at` DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - StudentOS AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Notifications Center</h1>
                        <p class="page-subtitle">Academic announcements, submission reminders, and system alerts</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="notifications.php" style="margin: 0;">
                            <input type="hidden" name="action" value="read_all">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <?php if (!empty($notifications)): ?>
                            <div style="display: flex; flex-direction: column;">
                                <?php foreach ($notifications as $n): 
                                    $isUnread = empty($n['is_read']);
                                ?>
                                    <div style="display: flex; align-items: flex-start; gap: 16px; padding: 18px 24px; border-bottom: 1px solid var(--border-color); background: <?php echo $isUnread ? 'rgba(99, 102, 241, 0.05)' : 'transparent'; ?>;">
                                        <div style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $isUnread ? 'var(--primary)' : 'var(--bg-input)'; ?>; color: <?php echo $isUnread ? '#fff' : 'var(--text-muted)'; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($n['title']); ?></strong>
                                                <span style="font-size: 11px; color: var(--text-muted);"><?php echo timeAgo($n['created_at']); ?></span>
                                            </div>
                                            <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin: 0;"><?php echo htmlspecialchars($n['message']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <p>You are all caught up! No new notifications.</p>
                            </div>
                        <?php endif; ?>
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
