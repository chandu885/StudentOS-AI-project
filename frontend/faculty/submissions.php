<?php
// frontend/faculty/submissions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $subId = (int)$_POST['submission_id'];
    $marks = (float)$_POST['marks'];
    $feedback = sanitize($_POST['feedback'] ?? '');

    $res = apiCall('/assignments.php?action=grade', 'POST', [
        'submission_id' => $subId,
        'marks' => $marks,
        'feedback' => $feedback
    ]);
    $successMsg = 'Submission graded and score updated successfully!';
}

$submissions = [
    ['id' => 1, 'student_name' => 'Alex Morgan', 'roll' => 'CS-2023-01', 'assignment_title' => 'ER Diagram & Relational Schema', 'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'status' => 'graded', 'marks' => 19, 'max_marks' => 20, 'text' => 'GitHub Repo: https://github.com/alex/dbms-project'],
    ['id' => 2, 'student_name' => 'Catherine Davis', 'roll' => 'CS-2023-03', 'assignment_title' => 'ER Diagram & Relational Schema', 'submitted_at' => date('Y-m-d H:i:s', strtotime('-12 hours')), 'status' => 'pending', 'marks' => null, 'max_marks' => 20, 'text' => 'Attached normalization matrix and proof of lossless join.'],
    ['id' => 3, 'student_name' => 'Daniel Evans', 'roll' => 'CS-2023-04', 'assignment_title' => 'ER Diagram & Relational Schema', 'submitted_at' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'status' => 'pending', 'marks' => null, 'max_marks' => 20, 'text' => 'Completed all normalization exercises including multi-valued dependencies.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Submissions - StudentOS AI</title>
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
                        <h1>Student Assignment Submissions</h1>
                        <p class="page-subtitle">Review submitted student code, documents, and assign grades</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-inbox"></i> Submissions List</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Assignment</th>
                                        <th>Submission Time</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $sub): 
                                        $isGraded = ($sub['status'] ?? '') === 'graded';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['roll']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($sub['assignment_title']); ?></td>
                                            <td><?php echo date('M d, h:i A', strtotime($sub['submitted_at'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $isGraded ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($sub['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $isGraded ? "<strong>{$sub['marks']}</strong> / {$sub['max_marks']}" : '—'; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;" onclick="openGradeModal(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['student_name']); ?>', <?php echo $sub['max_marks']; ?>, '<?php echo addslashes($sub['text']); ?>')">
                                                    <i class="fas fa-pen"></i> <?php echo $isGraded ? 'Edit Grade' : 'Grade'; ?>
                                                </button>
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

    <!-- Grade Modal -->
    <div class="modal-backdrop" id="gradeModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="gradeModalTitle">Grade Submission</h3>
                <button class="modal-close" onclick="closeModal('gradeModal')">&times;</button>
            </div>
            <form method="POST" action="submissions.php">
                <input type="hidden" name="action" value="grade">
                <input type="hidden" name="submission_id" id="gradeSubId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Submission Text / Link</label>
                        <div id="gradeSubText" style="padding: 10px 14px; background: var(--bg-primary); border-radius: var(--radius-md); font-size: 13px; color: var(--text-secondary); word-break: break-all;"></div>
                    </div>
                    <div class="form-group">
                        <label for="gradeMarks">Marks (out of <span id="gradeMaxSpan">20</span>)</label>
                        <input type="number" step="0.5" name="marks" id="gradeMarks" class="form-control" required min="0">
                    </div>
                    <div class="form-group">
                        <label for="gradeFeedback">Faculty Feedback</label>
                        <textarea name="feedback" id="gradeFeedback" class="form-control" rows="3" placeholder="Optional comments for student..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('gradeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit Grade</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openGradeModal(id, studentName, maxMarks, text) {
        document.getElementById('gradeSubId').value = id;
        document.getElementById('gradeModalTitle').textContent = 'Grade: ' + studentName;
        document.getElementById('gradeMaxSpan').textContent = maxMarks;
        document.getElementById('gradeMarks').max = maxMarks;
        document.getElementById('gradeSubText').textContent = text || 'No written response attached.';
        openModal('gradeModal');
    }
    </script>
</body>
</html>
