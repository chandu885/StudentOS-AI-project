<?php
// frontend/faculty/questions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Question added to examination question bank!';
}

$questions = [
    ['id' => 1, 'text' => 'State and prove Armstrong axioms for functional dependencies.', 'subject' => 'DBMS', 'type' => 'Theory', 'marks' => 5, 'difficulty' => 'Medium'],
    ['id' => 2, 'text' => 'Which normal form is strictly free from transitive dependencies?', 'subject' => 'DBMS', 'type' => 'MCQ', 'marks' => 2, 'difficulty' => 'Easy'],
    ['id' => 3, 'text' => 'Explain the Two-Phase Locking (2PL) protocol and prove that it guarantees conflict serializability.', 'subject' => 'DBMS', 'type' => 'Theory', 'marks' => 10, 'difficulty' => 'Hard']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank - StudentOS AI</title>
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
                        <h1>Examination Question Bank</h1>
                        <p class="page-subtitle">Curate and organize reusable exam questions, rubrics, and MCQs</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addQuestionModal')">
                            <i class="fas fa-plus"></i> Add Question
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-database"></i> Stored Questions</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Marks</th>
                                        <th>Difficulty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $q): ?>
                                        <tr>
                                            <td style="max-width: 450px;">
                                                <strong style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($q['text']); ?></strong>
                                            </td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($q['subject']); ?></span></td>
                                            <td><?php echo htmlspecialchars($q['type']); ?></td>
                                            <td><strong><?php echo $q['marks']; ?></strong> pts</td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($q['difficulty']) === 'hard' ? 'danger' : (strtolower($q['difficulty']) === 'medium' ? 'warning' : 'success'); ?>">
                                                    <?php echo htmlspecialchars($q['difficulty']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Add Question Modal -->
    <div class="modal-backdrop" id="addQuestionModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add Question to Bank</h3>
                <button class="modal-close" onclick="closeModal('addQuestionModal')">&times;</button>
            </div>
            <form method="POST" action="questions.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="qText">Question Text</label>
                        <textarea name="question_text" id="qText" class="form-control" rows="3" placeholder="Enter question..." required></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="qType">Type</label>
                            <select name="type" id="qType" class="form-control">
                                <option value="Theory">Descriptive / Theory</option>
                                <option value="MCQ">Multiple Choice (MCQ)</option>
                                <option value="Coding">Coding / Practical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="qMarks">Marks</label>
                            <input type="number" name="marks" id="qMarks" class="form-control" value="5" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
