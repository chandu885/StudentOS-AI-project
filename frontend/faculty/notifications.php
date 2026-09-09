<?php
// frontend/faculty/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

// Auto-seed initial notifications for faculty if none exist
if ($db) {
    $chkStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ?");
    if ($chkStmt) {
        $chkStmt->bind_param("i", $userId);
        $chkStmt->execute();
        $hasNotifs = (int)$chkStmt->get_result()->fetch_assoc()['cnt'];
        $chkStmt->close();

        if ($hasNotifs === 0) {
            $insStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($insStmt) {
                $seeds = [
                    ['New Submissions Received', '5 students submitted their coursework for ER Diagram Design.', 'info', 0],
                    ['Midterm Question Paper Submission', 'Please finalize and upload Midterm examination question sets by Friday.', 'warning', 0],
                    ['Faculty Council Meeting', 'Department meeting scheduled for Wednesday 4 PM in Conference Room A.', 'info', 1]
                ];
                foreach ($seeds as $s) {
                    $insStmt->bind_param("isssi", $userId, $s[0], $s[1], $s[2], $s[3]);
                    $insStmt->execute();
                }
                $insStmt->close();
            }
        }
    }
}

// Mark all as read
if (isset($_POST['mark_all_read']) || isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    if ($db) {
        $up = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($up) {
            $up->bind_param("i", $userId);
            $up->execute();
            $up->close();
        }
    }
}

$notifications = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, title, message, type, action_url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 30");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
$unreadCount = count(array_filter($notifications, fn($n) => empty($n['is_read'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Notifications - StudentOS AI</title>
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
                        <h1>Faculty Notifications (<?php echo $unreadCount; ?> unread)</h1>
                        <p class="page-subtitle">Department updates, student submission alerts, and academic deadlines</p>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <div class="header-actions">
                            <form method="POST" action="notifications.php" style="margin: 0;">
                                <button type="submit" name="mark_all_read" value="1" class="btn btn-secondary">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <div style="display: flex; flex-direction: column;">
                            <?php if (empty($notifications)): ?>
                                <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                                    <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                    No notifications found.
                                </div>
                            <?php else: ?>
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
