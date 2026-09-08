<?php
// frontend/student/ai-quiz.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();
$initialSubject = sanitize($_GET['subject'] ?? 'Database Management Systems');
$quizQuestions = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = sanitize($_POST['topic'] ?? 'General Engineering');
    $count = (int)($_POST['count'] ?? 5);
    $difficulty = sanitize($_POST['difficulty'] ?? 'medium');

    $res = apiCall('/ai.php?path=quiz', 'POST', [
        'subject_id' => 1,
        'topic' => $topic,
        'count' => $count,
        'difficulty' => $difficulty
    ]);

    if (!empty($res['questions'])) {
        $quizQuestions = $res['questions'];
    } elseif ($db) {
        // Query database ai_quiz_questions
        $qStmt = $db->query("SELECT q.* FROM `ai_quiz_questions` q JOIN `ai_quizzes` z ON q.quiz_id = z.id ORDER BY q.id ASC LIMIT $count");
        if ($qStmt && $qStmt->num_rows > 0) {
            $quizQuestions = [];
            while ($row = $qStmt->fetch_assoc()) {
                $options = json_decode($row['options_json'], true) ?: [];
                $correctIdx = array_search($row['correct_answer'], $options);
                if ($correctIdx === false) $correctIdx = 0;
                $quizQuestions[] = [
                    'id' => $row['id'],
                    'question' => $row['question_text'],
                    'options' => $options,
                    'correct_index' => $correctIdx,
                    'explanation' => $row['explanation']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Generator - StudentOS AI</title>
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
                        <h1><i class="fas fa-brain" style="color: var(--ai-accent);"></i> AI Practice Quiz Generator</h1>
                        <p class="page-subtitle">Test your subject mastery with on-demand interactive multiple-choice questions</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-sliders-h"></i> Quiz Settings</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ai-quiz.php">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="topic">Topic</label>
                                    <input type="text" name="topic" id="topic" class="form-control" value="<?php echo htmlspecialchars($initialSubject); ?>" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="difficulty">Difficulty</label>
                                    <select name="difficulty" id="difficulty" class="form-control">
                                        <option value="easy">Easy (Fundamentals)</option>
                                        <option value="medium" selected>Medium (Standard Exams)</option>
                                        <option value="hard">Hard (GATE / Synthesis)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="count">Questions</label>
                                    <select name="count" id="count" class="form-control">
                                        <option value="3">3 Questions</option>
                                        <option value="5" selected>5 Questions</option>
                                        <option value="10">10 Questions</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" style="height: 42px; justify-content: center;">
                                    <i class="fas fa-bolt"></i> Generate Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($quizQuestions): ?>
                    <div class="card" id="quizContainer">
                        <div class="card-header">
                            <h3><i class="fas fa-question-circle"></i> Practice Assessment</h3>
                            <span class="badge badge-primary"><?php echo count($quizQuestions); ?> Questions</span>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 24px;">
                                <?php foreach ($quizQuestions as $idx => $q): 
                                    $correctIdx = $q['correct_index'] ?? (isset($q['correct_answer'], $q['options']) && is_array($q['options']) ? array_search($q['correct_answer'], $q['options']) : 0);
                                    if ($correctIdx === false) $correctIdx = 0;
                                    $correctIdx = (int)$correctIdx;
                                ?>
                                    <div class="quiz-question-item" style="padding: 16px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);" data-correct="<?php echo $correctIdx; ?>">
                                        <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                            <?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($q['question'] ?? 'Question'); ?>
                                        </p>
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <?php if (!empty($q['options']) && is_array($q['options'])): ?>
                                                <?php foreach ($q['options'] as $optIdx => $opt): ?>
                                                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; font-size: 13px;">
                                                        <input type="radio" name="q_<?php echo $idx; ?>" value="<?php echo $optIdx; ?>" onchange="checkAnswer(this, <?php echo $correctIdx; ?>)">
                                                        <span><?php echo htmlspecialchars($opt); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="explanation-box" style="display: none; margin-top: 12px; padding: 10px 14px; border-radius: var(--radius-md); font-size: 13px; line-height: 1.5;">
                                            <i class="fas fa-info-circle"></i> <strong>Explanation:</strong> <?php echo htmlspecialchars($q['explanation'] ?? 'Review lecture notes for more detail.'); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
    <script>
    function checkAnswer(radio, correctIdx) {
        const parent = radio.closest('.quiz-question-item');
        const selectedIdx = parseInt(radio.value);
        const explanation = parent.querySelector('.explanation-box');
        
        // Disable other radios for this question
        parent.querySelectorAll('input[type="radio"]').forEach(r => r.disabled = true);

        explanation.style.display = 'block';
        if (selectedIdx === correctIdx) {
            radio.closest('label').style.borderColor = 'var(--success)';
            radio.closest('label').style.background = 'rgba(34, 197, 94, 0.1)';
            explanation.style.background = 'rgba(34, 197, 94, 0.15)';
            explanation.style.color = 'var(--success)';
            showToast('Correct Answer! 🎉', 'success');
        } else {
            radio.closest('label').style.borderColor = 'var(--danger)';
            radio.closest('label').style.background = 'rgba(239, 68, 68, 0.1)';
            explanation.style.background = 'rgba(239, 68, 68, 0.15)';
            explanation.style.color = 'var(--danger)';
            showToast('Incorrect! Review the explanation.', 'error');
        }
    }
    </script>
</body>
</html>
