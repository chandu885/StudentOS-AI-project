<?php
// frontend/student/ai-recommendations.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();
$recommendations = [];
if ($db) {
    $stmt = $db->prepare("SELECT * FROM `ai_recommendations` WHERE `user_id` = ? OR `user_id` = 0 ORDER BY `created_at` DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cat = $row['category'] ?? 'study';
            $icon = 'brain';
            if ($cat === 'attendance') $icon = 'exclamation-triangle';
            elseif ($cat === 'assignment' || $cat === 'coursework') $icon = 'clock';
            elseif ($cat === 'skill' || $cat === 'career') $icon = 'trophy';
            elseif ($cat === 'exam') $icon = 'book-open';

            $recommendations[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['suggestion'],
                'category' => ucfirst($cat),
                'icon' => $icon,
                'priority' => $row['priority'] ?? 'medium'
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
    <title>AI Recommendations - StudentOS AI</title>
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
                        <h1><i class="fas fa-lightbulb" style="color: var(--warning);"></i> AI Personalized Recommendations</h1>
                        <p class="page-subtitle">Proactive academic insights derived from your grades, attendance, and study habits</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($recommendations)): ?>
                        <div class="card" style="text-align: center; padding: 48px;">
                            <i class="fas fa-brain" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Pending Recommendations</h3>
                            <p style="color: var(--text-muted); font-size: 14px;">Great work! As you log study sessions and complete quizzes, the AI will provide personalized study tips here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recommendations as $rec): 
                            $prio = $rec['priority'] ?? 'medium';
                            $prioClass = $prio === 'high' ? 'danger' : ($prio === 'medium' ? 'warning' : 'info');
                        ?>
                            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--<?php echo $prioClass; ?>);">
                                <div class="card-body" style="display: flex; gap: 18px; align-items: flex-start;">
                                    <div class="rec-icon" style="background: rgba(var(--<?php echo $prioClass; ?>), 0.15); color: var(--<?php echo $prioClass; ?>);">
                                        <i class="fas fa-<?php echo htmlspecialchars($rec['icon'] ?? 'star'); ?>"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                            <h3 style="font-size: 15px; color: var(--text-primary); margin: 0;"><?php echo htmlspecialchars($rec['title']); ?></h3>
                                            <div style="display: flex; gap: 8px;">
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($rec['category']); ?></span>
                                                <span class="badge badge-<?php echo $prioClass; ?>"><?php echo ucfirst($prio); ?> Priority</span>
                                            </div>
                                        </div>
                                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($rec['suggestion'] ?? $rec['description'] ?? ''); ?></p>
                                    </div>
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
