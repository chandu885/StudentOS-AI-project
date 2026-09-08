<?php
// frontend/student/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$db = getDbConnection();
$notices = [];
if ($db) {
    $res = $db->query("SELECT n.*, d.name as department_name FROM `notices` n LEFT JOIN `departments` d ON n.department_id = d.id WHERE n.target_role IN ('all', 'student', 'STUDENT') OR n.target_role IS NULL ORDER BY n.created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $notices[] = [
                'title' => $row['title'],
                'category' => $row['department_name'] ?? 'College Administration',
                'date' => $row['created_at'],
                'priority' => ($row['priority'] === 'high') ? 'urgent' : 'general',
                'content' => $row['content']
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
    <title>Notices & Circulars - StudentOS AI</title>
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
                        <h1>Official Notices & Circulars</h1>
                        <p class="page-subtitle">College notifications, administrative updates, and department circulars</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($notices)): ?>
                        <div class="card" style="text-align: center; padding: 48px;">
                            <i class="fas fa-bullhorn" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Notices Available</h3>
                            <p style="color: var(--text-muted); font-size: 14px;">There are no active administrative circulars or department announcements at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $not): 
                            $isUrgent = ($not['priority'] ?? '') === 'urgent';
                        ?>
                            <div class="card" style="margin-bottom: 0;">
                                <div class="card-header">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="badge <?php echo $isUrgent ? 'badge-danger' : 'badge-primary'; ?>">
                                            <?php echo $isUrgent ? 'Urgent Notice' : 'Circular'; ?>
                                        </span>
                                        <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($not['category']); ?></span>
                                    </div>
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($not['date'])); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h3 style="font-size: 16px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($not['title']); ?></h3>
                                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($not['content'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
