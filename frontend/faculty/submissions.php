<?php
// frontend/faculty/submissions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $subId = (int)($_POST['submission_id'] ?? 0);
    $marks = (float)($_POST['marks'] ?? 0);
    $feedback = sanitize($_POST['feedback'] ?? '');

    if ($db && $subId > 0) {
        $stmt = $db->prepare(
            "UPDATE assignment_submissions 
             SET marks_obtained = ?, feedback = ?, graded_by = ?, status = 'graded', graded_at = NOW() 
             WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("dsii", $marks, $feedback, $userId, $subId);
            if ($stmt->execute()) {
                $successMsg = 'Submission graded and score updated successfully!';
            } else {
                $errorMsg = 'Failed to grade submission: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$submissions = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT sub.id, sub.assignment_id, sub.student_id, sub.submission_text, sub.file_path,
                sub.marks_obtained, sub.feedback, sub.submitted_at, sub.status,
                CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                a.title AS assignment_title, a.max_marks,
                s.name AS subject_name, s.code AS subject_code
         FROM assignment_submissions sub
         JOIN assignments a ON sub.assignment_id = a.id
         JOIN subjects s ON a.subject_id = s.id
         JOIN users u ON sub.student_id = u.id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE a.faculty_id = ? OR s.faculty_id = ?
         ORDER BY sub.submitted_at DESC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($submissions)) {
        $res = $db->query(
            "SELECT sub.id, sub.assignment_id, sub.student_id, sub.submission_text, sub.file_path,
                    sub.marks_obtained, sub.feedback, sub.submitted_at, sub.status,
                    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                    COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                    a.title AS assignment_title, a.max_marks,
                    s.name AS subject_name, s.code AS subject_code
             FROM assignment_submissions sub
             JOIN assignments a ON sub.assignment_id = a.id
             JOIN subjects s ON a.subject_id = s.id
             JOIN users u ON sub.student_id = u.id
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             ORDER BY sub.submitted_at DESC
             LIMIT 25"
        );
        if ($res) {
            $submissions = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}
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
                        <h3><i class="fas fa-inbox"></i> Submissions List (<?php echo count($submissions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Assignment & Subject</th>
                                        <th>Submission Time</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($submissions)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-inbox"></i> No student submissions received yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($submissions as $sub): 
                                            $isGraded = ($sub['status'] ?? '') === 'graded';
                                            $subText = $sub['submission_text'] ?? $sub['file_path'] ?? '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['roll']); ?></div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sub['assignment_title']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?></div>
                                                </td>
                                                <td><?php echo !empty($sub['submitted_at']) ? date('M d, h:i A', strtotime($sub['submitted_at'])) : 'Recent'; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isGraded ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($sub['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $isGraded ? "<strong>" . (float)$sub['marks_obtained'] . "</strong> / {$sub['max_marks']}" : '—'; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;" onclick="openGradeModal(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['student_name']); ?>', <?php echo (int)$sub['max_marks']; ?>, '<?php echo addslashes($subText); ?>')">
                                                        <i class="fas fa-pen"></i> <?php echo $isGraded ? 'Edit Grade' : 'Grade'; ?>
                                                    </button>
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
