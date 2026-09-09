<?php
// frontend/faculty/questions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Auto-seed initial questions if empty
if ($db) {
    $chkQ = $db->query("SELECT COUNT(*) as cnt FROM questions");
    if ($chkQ && $chkQ->fetch_assoc()['cnt'] == 0) {
        $db->query("INSERT INTO questions (exam_id, question_text, question_type, marks, created_at) VALUES 
            (1, 'State and prove Armstrong axioms for functional dependencies.', 'descriptive', 5, NOW()),
            (1, 'Which normal form is strictly free from transitive dependencies?', 'mcq', 2, NOW()),
            (1, 'Explain the Two-Phase Locking (2PL) protocol and prove that it guarantees conflict serializability.', 'descriptive', 10, NOW()),
            (2, 'Explain the divide-and-conquer paradigm with recurrence relation for Merge Sort.', 'descriptive', 8, NOW())");
    }
}

// Fetch exams for dropdown
$examsList = [];
if ($db) {
    $res = $db->query("SELECT e.id, e.title, s.name AS subject_name FROM exams e JOIN subjects s ON e.subject_id = s.id ORDER BY e.id ASC LIMIT 25");
    if ($res) {
        $examsList = $res->fetch_all(MYSQLI_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examId = (int)($_POST['exam_id'] ?? ($examsList[0]['id'] ?? 1));
    $questionText = sanitize($_POST['question_text'] ?? '');
    $rawType = sanitize($_POST['type'] ?? 'descriptive');
    $qType = in_array(strtolower($rawType), ['mcq', 'descriptive']) ? strtolower($rawType) : (stripos($rawType, 'mcq') !== false ? 'mcq' : 'descriptive');
    $marks = max(1, (int)($_POST['marks'] ?? 5));

    if (!empty($questionText) && $examId > 0 && $db) {
        $stmt = $db->prepare("INSERT INTO questions (exam_id, question_text, question_type, marks, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("issi", $examId, $questionText, $qType, $marks);
            if ($stmt->execute()) {
                $successMsg = 'Question added to examination question bank!';
            } else {
                $errorMsg = 'Failed to save question: ' . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $errorMsg = 'Please enter question text.';
    }
}

$questions = [];
if ($db) {
    $res = $db->query(
        "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.marks, q.created_at,
                e.title AS exam_title, s.name AS subject_name, s.code AS subject_code
         FROM questions q
         LEFT JOIN exams e ON q.exam_id = e.id
         LEFT JOIN subjects s ON e.subject_id = s.id
         ORDER BY q.id DESC"
    );
    if ($res) {
        $questions = $res->fetch_all(MYSQLI_ASSOC);
    }
}
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
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-database"></i> Stored Questions (<?php echo count($questions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>Subject / Exam</th>
                                        <th>Type</th>
                                        <th>Marks</th>
                                        <th>Created Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($questions)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-info-circle"></i> No questions found in the question bank. Click "Add Question" above to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($questions as $q): ?>
                                            <tr>
                                                <td style="max-width: 450px;">
                                                    <strong style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($q['question_text']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($q['subject_name'] ?? $q['exam_title'] ?? 'General'); ?></span>
                                                    <?php if (!empty($q['exam_title'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;"><?php echo htmlspecialchars($q['exam_title']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info" style="text-transform: uppercase; font-size: 11px;">
                                                        <?php echo htmlspecialchars($q['question_type']); ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo (int)$q['marks']; ?></strong> pts</td>
                                                <td>
                                                    <span style="font-size: 12px; color: var(--text-muted);">
                                                        <?php echo !empty($q['created_at']) ? date('M d, Y', strtotime($q['created_at'])) : 'Recent'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                        <label for="examIdSelect">Linked Exam / Course</label>
                        <select name="exam_id" id="examIdSelect" class="form-control" required>
                            <?php foreach ($examsList as $ex): ?>
                                <option value="<?php echo (int)$ex['id']; ?>">
                                    <?php echo htmlspecialchars($ex['title'] . (!empty($ex['subject_name']) ? ' (' . $ex['subject_name'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="qText">Question Text</label>
                        <textarea name="question_text" id="qText" class="form-control" rows="3" placeholder="Enter question text..." required></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="qType">Type</label>
                            <select name="type" id="qType" class="form-control">
                                <option value="descriptive">Descriptive / Theory</option>
                                <option value="mcq">Multiple Choice (MCQ)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="qMarks">Marks</label>
                            <input type="number" name="marks" id="qMarks" class="form-control" value="5" min="1" max="100">
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
