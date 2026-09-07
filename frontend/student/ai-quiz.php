<?php
// frontend/student/ai-quiz.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$initialSubject = sanitize($_GET['subject'] ?? 'Database Management Systems');
$quizQuestions = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = sanitize($_POST['topic'] ?? 'General Engineering');
    $count = (int)($_POST['count'] ?? 4);
    $difficulty = sanitize($_POST['difficulty'] ?? 'medium');

    $res = apiCall('/ai.php?path=quiz', 'POST', [
        'subject_id' => 1,
        'topic' => $topic,
        'count' => $count,
        'difficulty' => $difficulty
    ]);

    if (!empty($res['questions'])) {
        $quizQuestions = $res['questions'];
    } else {
        $quizQuestions = [
            [
                'id' => 1,
                'question' => 'Which normal form is based on the concept of full functional dependency?',
                'options' => ['First Normal Form (1NF)', 'Second Normal Form (2NF)', 'Third Normal Form (3NF)', 'Boyce-Codd Normal Form (BCNF)'],
                'correct_index' => 1,
                'explanation' => '2NF requires a relation to be in 1NF and all non-key attributes to be fully functionally dependent on the primary key.'
            ],
            [
                'id' => 2,
                'question' => 'A relation is in BCNF if for every functional dependency X -> Y:',
                'options' => ['X is a superkey', 'Y is a prime attribute', 'X is a foreign key', 'Both X and Y are candidate keys'],
                'correct_index' => 0,
                'explanation' => 'BCNF is strictly stricter than 3NF: the determinant X must be a superkey in every non-trivial functional dependency.'
            ],
            [
                'id' => 3,
                'question' => 'Which of the following problems can occur in an un-normalized relational database?',
                'options' => ['Insertion anomaly', 'Deletion anomaly', 'Update anomaly', 'All of the above'],
                'correct_index' => 3,
                'explanation' => 'Unnormalized tables can suffer from insertion, deletion, and update anomalies due to redundant data.'
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Generator - StudentOS AI</title>
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
                                <?php foreach ($quizQuestions as $idx => $q): ?>
                                    <div class="quiz-question-item" style="padding: 16px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);" data-correct="<?php echo $q['correct_index']; ?>">
                                        <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                            <?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($q['question']); ?>
                                        </p>
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <?php foreach ($q['options'] as $optIdx => $opt): ?>
                                                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; font-size: 13px;">
                                                    <input type="radio" name="q_<?php echo $idx; ?>" value="<?php echo $optIdx; ?>" onchange="checkAnswer(this, <?php echo $q['correct_index']; ?>)">
                                                    <span><?php echo htmlspecialchars($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="explanation-box" style="display: none; margin-top: 12px; padding: 10px 14px; border-radius: var(--radius-md); font-size: 13px; line-height: 1.5;">
                                            <i class="fas fa-info-circle"></i> <strong>Explanation:</strong> <?php echo htmlspecialchars($q['explanation']); ?>
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
