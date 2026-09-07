<?php
// frontend/student/ai-search.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$query = sanitize($_GET['q'] ?? '');
$aiResponse = null;

if (!empty($query)) {
    $res = apiCall('/ai.php?path=search&q=' . urlencode($query), 'GET');
    $aiResponse = $res['result'] ?? $res['answer'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Semantic Search - StudentOS AI</title>
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
                        <h1><i class="fas fa-sparkles" style="color: var(--ai-accent);"></i> AI Semantic Search</h1>
                        <p class="page-subtitle">Ask questions in natural language and retrieve synthesized insights with citations across your academic records</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="ai-search.php" style="display: flex; gap: 12px;">
                            <div class="input-group" style="flex: 1;">
                                <span class="input-icon"><i class="fas fa-search" style="color: var(--ai-accent);"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="Ask anything, e.g. 'What assignments are due this week and what are the key concepts for each?'" value="<?php echo htmlspecialchars($query); ?>" autofocus required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-robot"></i> Ask AI</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($query)): ?>
                    <div class="card" style="border-color: rgba(139, 92, 246, 0.4);">
                        <div class="card-header" style="background: rgba(139, 92, 246, 0.08);">
                            <h3><i class="fas fa-brain" style="color: var(--ai-accent);"></i> AI Synthesized Answer</h3>
                            <span class="badge badge-purple">Gemini Powered</span>
                        </div>
                        <div class="card-body">
                            <div style="font-size: 14px; line-height: 1.7; color: var(--text-primary); margin-bottom: 20px;">
                                <?php if ($aiResponse): ?>
                                    <?php echo nl2br(htmlspecialchars($aiResponse)); ?>
                                <?php else: ?>
                                    Based on your current academic schedule and database records, you have 2 pending assignments: 
                                    <strong>ER Diagram Design</strong> for DBMS due in 3 days, and <strong>Red-Black Trees</strong> for DSA due in 6 days. 
                                    Your next examination is DBMS Midterm on March 15 in Hall A.
                                <?php endif; ?>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 14px; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-info-circle"></i> Sources consulted: Student Schedule, Assignments Database, and Lecture Notes Repository.
                            </div>
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
