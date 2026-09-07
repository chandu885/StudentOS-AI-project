<?php
// frontend/student/goals.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? 'academic');
    $targetDate = sanitize($_POST['target_date'] ?? date('Y-m-d', strtotime('+30 days')));
    $progress = (int)($_POST['progress'] ?? 0);

    $res = apiCall('/tasks.php?action=goal', 'POST', [
        'title' => $title,
        'category' => $category,
        'target_date' => $targetDate,
        'progress' => $progress
    ]);
    if (!empty($res['success'])) {
        $successMsg = 'Goal created successfully!';
    } else {
        $errorMsg = 'Failed to create goal.';
    }
}

$tasksRes = apiCall('/tasks.php', 'GET');
$goals = $tasksRes['goals'] ?? [
    ['id' => 1, 'title' => 'Achieve 3.8+ SGPA in Semester 6', 'category' => 'academic', 'progress' => 85, 'target_date' => '2026-06-30'],
    ['id' => 2, 'title' => 'Complete 100 LeetCode Problems', 'category' => 'skill', 'progress' => 64, 'target_date' => '2026-05-15'],
    ['id' => 3, 'title' => 'Build Full Stack AI Project for Portfolio', 'category' => 'career', 'progress' => 90, 'target_date' => '2026-04-01']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic & Personal Goals - StudentOS AI</title>
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
                        <h1>Goals & Milestones</h1>
                        <p class="page-subtitle">Track long-term academic, technical skill, and career objectives</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addGoalModal')">
                            <i class="fas fa-plus"></i> Set New Goal
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($goals as $goal): 
                        $pct = $goal['progress'] ?? 0;
                    ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <span class="badge badge-purple"><?php echo htmlspecialchars(ucfirst($goal['category'] ?? 'Academic')); ?></span>
                                <span style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-flag"></i> Target: <?php echo date('M Y', strtotime($goal['target_date'])); ?></span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 15px; color: var(--text-primary); margin-bottom: 14px;"><?php echo htmlspecialchars($goal['title']); ?></h3>
                                
                                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px;">
                                    <span style="color: var(--text-muted);">Current Progress</span>
                                    <strong style="color: var(--primary);"><?php echo $pct; ?>%</strong>
                                </div>
                                <div class="attendance-bar" style="height: 8px;">
                                    <div class="attendance-fill" style="width: <?php echo $pct; ?>%; background: var(--primary-gradient);"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Add Goal Modal -->
    <div class="modal-backdrop" id="addGoalModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Set New Goal</h3>
                <button class="modal-close" onclick="closeModal('addGoalModal')">&times;</button>
            </div>
            <form method="POST" action="goals.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="goalTitle">Goal Title</label>
                        <input type="text" name="title" id="goalTitle" class="form-control" placeholder="e.g. Master React & Node.js" required>
                    </div>
                    <div class="form-group">
                        <label for="goalCategory">Category</label>
                        <select name="category" id="goalCategory" class="form-control">
                            <option value="academic">Academic</option>
                            <option value="skill">Skill Development</option>
                            <option value="career">Career / Internship</option>
                            <option value="personal">Personal Productivity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="goalTarget">Target Completion Date</label>
                        <input type="date" name="target_date" id="goalTarget" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="goalProgress">Initial Progress (%)</label>
                        <input type="number" name="progress" id="goalProgress" class="form-control" min="0" max="100" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addGoalModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Goal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
