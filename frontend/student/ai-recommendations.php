<?php
// frontend/student/ai-recommendations.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$recommendations = [];

if ($db && $userId > 0) {
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
        $stmt->close();
    }

    // If no saved recommendations, dynamically synthesize from real student data
    if (empty($recommendations)) {
        // 1. Attendance checks
        $attStmt = $db->prepare(
            "SELECT s.name as subject_name,
                    ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as percentage
             FROM attendance a
             JOIN subjects s ON a.subject_id = s.id
             WHERE a.student_id = ?
             GROUP BY a.subject_id
             HAVING percentage < 75"
        );
        if ($attStmt) {
            $attStmt->bind_param("i", $userId);
            $attStmt->execute();
            $attRes = $attStmt->get_result();
            while ($ar = $attRes->fetch_assoc()) {
                $recommendations[] = [
                    'id' => 'att-' . rand(100, 999),
                    'title' => 'Attendance Warning: ' . $ar['subject_name'],
                    'description' => 'Your current attendance is ' . $ar['percentage'] . '%. Regulations require a minimum of 75% to sit for semester examinations. Attend the next consecutive lectures to recover eligibility.',
                    'category' => 'Attendance',
                    'icon' => 'exclamation-triangle',
                    'priority' => 'high'
                ];
            }
            $attStmt->close();
        }

        // 2. Urgent pending assignments
        $asgStmt = $db->prepare(
            "SELECT a.title, a.deadline, s.name as subject_name 
             FROM assignments a 
             JOIN subjects s ON a.subject_id = s.id 
             JOIN student_subjects ss ON ss.subject_id = s.id 
             WHERE ss.student_id = ? 
             AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
             ORDER BY a.deadline ASC LIMIT 2"
        );
        if ($asgStmt) {
            $asgStmt->bind_param("ii", $userId, $userId);
            $asgStmt->execute();
            $asgRes = $asgStmt->get_result();
            while ($ar = $asgRes->fetch_assoc()) {
                $diffDays = round((strtotime($ar['deadline']) - time()) / 86400);
                $recommendations[] = [
                    'id' => 'asg-' . rand(100, 999),
                    'title' => 'Assignment Due: ' . $ar['title'],
                    'description' => 'Coursework for ' . $ar['subject_name'] . ' is due in ' . max(0, (int)$diffDays) . ' day(s) (' . date('M d, h:i A', strtotime($ar['deadline'])) . '). Start drafting your submission or consult the lecture notes.',
                    'category' => 'Coursework',
                    'icon' => 'clock',
                    'priority' => ($diffDays <= 2) ? 'high' : 'medium'
                ];
            }
            $asgStmt->close();
        }

        // 3. Upcoming exams
        $exStmt = $db->prepare(
            "SELECT e.title, e.exam_date, s.name as subject_name 
             FROM exams e 
             JOIN subjects s ON e.subject_id = s.id 
             JOIN student_subjects ss ON ss.subject_id = s.id 
             WHERE ss.student_id = ? AND e.exam_date >= CURDATE() 
             ORDER BY e.exam_date ASC LIMIT 1"
        );
        if ($exStmt) {
            $exStmt->bind_param("i", $userId);
            $exStmt->execute();
            if ($er = $exStmt->get_result()->fetch_assoc()) {
                $recommendations[] = [
                    'id' => 'exam-' . rand(100, 999),
                    'title' => 'Upcoming Assessment: ' . $er['subject_name'],
                    'description' => 'Exam scheduled on ' . date('M d, Y', strtotime($er['exam_date'])) . '. Use the AI Study Planner to generate a 7-day revision roadmap or take an AI Practice Quiz.',
                    'category' => 'Exam',
                    'icon' => 'book-open',
                    'priority' => 'medium'
                ];
            }
            $exStmt->close();
        }

        // 4. Fallback baseline tip
        if (empty($recommendations)) {
            $recommendations[] = [
                'id' => 'base-1',
                'title' => 'Optimal Academic Standing',
                'description' => 'All coursework is up-to-date and attendance is healthy. Try challenging your knowledge with an AI Practice Quiz to reinforce retention.',
                'category' => 'Study',
                'icon' => 'brain',
                'priority' => 'low'
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
