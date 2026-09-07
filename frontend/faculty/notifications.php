<?php
// frontend/faculty/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$notifRes = apiCall('/notifications.php', 'GET');
$notifications = $notifRes['notifications'] ?? [
    ['id' => 1, 'title' => 'New Submissions Received', 'message' => '5 students submitted their coursework for ER Diagram Design.', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
    ['id' => 2, 'title' => 'Midterm Question Paper Submission', 'message' => 'Please finalize and upload Midterm examination question sets by Friday.', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['id' => 3, 'title' => 'Faculty Council Meeting', 'message' => 'Department meeting scheduled for Wednesday 4 PM in Conference Room A.', 'is_read' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))]
];
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
                        <h1>Faculty Notifications</h1>
                        <p class="page-subtitle">Department updates, student submission alerts, and academic deadlines</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="padding: 0;">
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
