<?php
// frontend/student/ai-planner.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$generatedPlan = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = (int)($_POST['subject_id'] ?? 1);
    $examDate = sanitize($_POST['exam_date'] ?? date('Y-m-d', strtotime('+14 days')));
    $days = (int)($_POST['days'] ?? 14);

    $res = apiCall('/ai.php?path=planner', 'POST', [
        'subject_id' => $subjectId,
        'exam_date' => $examDate,
        'days' => $days
    ]);
    if (!empty($res['plan']) || !empty($res['data'])) {
        $generatedPlan = $res['plan'] ?? $res['data'];
    } else {
        $generatedPlan = [
            ['day' => 'Days 1 - 3', 'focus' => 'Foundations & Unit 1', 'tasks' => 'Review Entity-Relationship Model, Keys, and Relational Algebra. Solve 10 sample schema conversions.'],
            ['day' => 'Days 4 - 7', 'focus' => 'Functional Dependencies & Normalization', 'tasks' => 'Deep dive into 1NF, 2NF, 3NF, and BCNF. Practice decomposition with lossless join & dependency preservation.'],
            ['day' => 'Days 8 - 11', 'focus' => 'Transaction Processing & Concurrency', 'tasks' => 'Study ACID properties, Serializability, Two-Phase Locking (2PL), and Deadlock recovery algorithms.'],
            ['day' => 'Days 12 - 14', 'focus' => 'Full Mock Exams & Rapid Revision', 'tasks' => 'Solve 2 previous year midterm question papers under timed conditions (90 mins each).']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Study Planner - StudentOS AI</title>
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
                        <h1><i class="fas fa-calendar-check" style="color: var(--ai-accent);"></i> AI Personalized Study Planner</h1>
                        <p class="page-subtitle">Generate a custom day-by-day revision roadmap tailored to your target exam date and weak areas</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-sliders-h"></i> Plan Configuration</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ai-planner.php">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="subject_id">Subject</label>
                                    <select name="subject_id" id="subject_id" class="form-control">
                                        <option value="1">Database Management Systems</option>
                                        <option value="2">Data Structures & Algorithms</option>
                                        <option value="3">Operating Systems</option>
                                        <option value="4">Computer Networks</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="exam_date">Target Exam Date</label>
                                    <input type="date" name="exam_date" id="exam_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="days">Days Until Exam</label>
                                    <input type="number" name="days" id="days" class="form-control" value="14" min="3" max="60">
                                </div>
                                <button type="submit" class="btn btn-primary" style="height: 42px; justify-content: center;">
                                    <i class="fas fa-magic"></i> Generate Study Plan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($generatedPlan): ?>
                    <div class="card" style="border-color: rgba(99, 102, 241, 0.4);">
                        <div class="card-header" style="background: rgba(99, 102, 241, 0.08);">
                            <h3><i class="fas fa-map-signs" style="color: var(--primary);"></i> Recommended Revision Roadmap</h3>
                            <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="window.print()">
                                <i class="fas fa-print"></i> Export Plan
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (is_array($generatedPlan)): ?>
                                <div style="display: flex; flex-direction: column; gap: 14px;">
                                    <?php foreach ($generatedPlan as $step): ?>
                                        <div style="display: flex; gap: 16px; padding: 16px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); border-left: 4px solid var(--ai-accent);">
                                            <div style="min-width: 110px;">
                                                <span class="badge badge-purple"><?php echo htmlspecialchars($step['day'] ?? 'Phase'); ?></span>
                                            </div>
                                            <div>
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($step['focus'] ?? ''); ?></strong>
                                                <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px; line-height: 1.5;"><?php echo htmlspecialchars($step['tasks'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 16px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); line-height: 1.7; font-size: 14px; color: var(--text-primary);">
                                    <?php echo renderMarkdown($generatedPlan); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
