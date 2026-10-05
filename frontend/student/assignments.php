<?php
// frontend/student/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$successMsg = '';
$errorMsg = '';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asgId = (int)($_POST['assignment_id'] ?? 0);
    $text = sanitize($_POST['submission_text'] ?? '');
    
    // File upload handling if present
    $filePath = null;
    if (!empty($_FILES['submission_file']['name'])) {
        $uploadDir = BASE_PATH . '/storage/assignments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'zip', 'png', 'jpg', 'jpeg'];
        if (!in_array($ext, $allowedExts)) {
            $errorMsg = 'Invalid file type. Allowed formats: PDF, DOC, DOCX, TXT, ZIP, PNG, JPG.';
        } elseif ($_FILES['submission_file']['size'] > 15 * 1024 * 1024) {
            $errorMsg = 'Uploaded file exceeds the maximum 15MB size limit.';
        } else {
            $fileName = time() . '_' . uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetFile)) {
                $filePath = 'assignments/' . $fileName;
            } else {
                $errorMsg = 'Failed to upload attachment. Please try again.';
            }
        }
    }

    if (empty($errorMsg)) {
        if (!$db) {
            $errorMsg = 'Database connection issue. Please verify database server status.';
        } elseif ($asgId <= 0) {
            $errorMsg = 'Invalid assignment selection. Please choose an assignment to submit.';
        } elseif (empty($text) && empty($filePath)) {
            $errorMsg = 'Please provide written notes/solution text or upload a file for your submission.';
        } else {
            // Fetch deadline to determine if submission is on-time or late
            $asgCheck = $db->prepare("SELECT deadline, title FROM assignments WHERE id = ? AND deleted_at IS NULL");
            $asgMeta = null;
            if ($asgCheck) {
                $asgCheck->bind_param("i", $asgId);
                $asgCheck->execute();
                $asgMeta = $asgCheck->get_result()->fetch_assoc();
                $asgCheck->close();
            }

            if (!$asgMeta) {
                $errorMsg = 'The selected assignment does not exist or has been removed.';
            } else {
                $deadline = $asgMeta['deadline'] ?? null;
                $asgTitle = $asgMeta['title'] ?? 'Assignment';
                $isLate = ($deadline && strtotime($deadline) < time());
                $subStatus = $isLate ? 'late' : 'submitted';

                // Check if previous submission exists
                $checkStmt = $db->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
                if ($checkStmt) {
                    $checkStmt->bind_param("ii", $asgId, $userId);
                    $checkStmt->execute();
                    $existing = $checkStmt->get_result()->fetch_assoc();
                    $checkStmt->close();

                    if ($existing) {
                        // Update submission
                        if ($filePath) {
                            $upStmt = $db->prepare("UPDATE assignment_submissions SET submission_text = ?, file_path = ?, status = 'resubmitted', submitted_at = NOW() WHERE id = ?");
                            $upStmt->bind_param("ssi", $text, $filePath, $existing['id']);
                        } else {
                            $upStmt = $db->prepare("UPDATE assignment_submissions SET submission_text = ?, status = 'resubmitted', submitted_at = NOW() WHERE id = ?");
                            $upStmt->bind_param("si", $text, $existing['id']);
                        }
                        if ($upStmt && $upStmt->execute()) {
                            $successMsg = 'Assignment resubmitted successfully!';
                            $upStmt->close();
                        } else {
                            $errorMsg = 'Failed to update assignment submission in database.';
                        }
                    } else {
                        // Insert new submission
                        $insStmt = $db->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, status, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        if ($insStmt) {
                            $insStmt->bind_param("iisss", $asgId, $userId, $text, $filePath, $subStatus);
                            if ($insStmt->execute()) {
                                $successMsg = 'Assignment submitted successfully!';
                            } else {
                                $errorMsg = 'Failed to record assignment submission in database.';
                            }
                            $insStmt->close();
                        }
                    }
                }
            }
        }
    }
}

$assignments = [];
$studentSem = '1';
$studentDeptId = null;
$studentDeptCode = '';
$studentDeptName = '';

if ($db && $userId > 0) {
    $spStmt = $db->prepare(
        "SELECT sp.semester, sp.department_id, d.code AS dept_code, d.name AS dept_name 
         FROM student_profiles sp 
         LEFT JOIN departments d ON sp.department_id = d.id 
         WHERE sp.user_id = ?"
    );
    if ($spStmt) {
        $spStmt->bind_param("i", $userId);
        $spStmt->execute();
        $spRow = $spStmt->get_result()->fetch_assoc();
        if ($spRow) {
            $studentSem = (string)($spRow['semester'] ?? '1');
            $studentDeptId = !empty($spRow['department_id']) ? (int)$spRow['department_id'] : null;
            $studentDeptCode = $spRow['dept_code'] ?? '';
            $studentDeptName = $spRow['dept_name'] ?? '';
        }
        $spStmt->close();
    }

    if ($studentDeptId !== null) {
        $stmt = $db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    COALESCE(d.name, dept_s.name, 'Academics') AS department_name,
                    COALESCE(d.code, dept_s.code, 'ACAD') AS department_code,
                    COALESCE(a.semester, s.semester) AS assignment_semester,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Instructor') AS faculty_name,
                    sub.id AS submission_id, sub.status AS submission_status, sub.marks_obtained AS sub_marks, sub.feedback, sub.submitted_at, sub.file_path AS submission_file,
                    CASE 
                        WHEN sub.id IS NOT NULL THEN COALESCE(sub.status, 'submitted')
                        WHEN a.deadline < NOW() THEN 'overdue'
                        ELSE 'pending'
                    END AS computed_status
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             LEFT JOIN departments d ON a.department_id = d.id
             LEFT JOIN departments dept_s ON s.department_id = dept_s.id
             LEFT JOIN users u ON a.faculty_id = u.id
             LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
             WHERE a.deleted_at IS NULL
               AND COALESCE(a.semester, s.semester) = ?
               AND (
                   a.department_id = ?
                   OR s.department_id = ?
                   OR a.subject_id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
                   OR a.department_id IS NULL
               )
             ORDER BY a.deadline DESC"
        );
        if ($stmt) {
            $stmt->bind_param("isiii", $userId, $studentSem, $studentDeptId, $studentDeptId, $userId);
            $stmt->execute();
            $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        $stmt = $db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    COALESCE(d.name, dept_s.name, 'Academics') AS department_name,
                    COALESCE(d.code, dept_s.code, 'ACAD') AS department_code,
                    COALESCE(a.semester, s.semester) AS assignment_semester,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Instructor') AS faculty_name,
                    sub.id AS submission_id, sub.status AS submission_status, sub.marks_obtained AS sub_marks, sub.feedback, sub.submitted_at, sub.file_path AS submission_file,
                    CASE 
                        WHEN sub.id IS NOT NULL THEN COALESCE(sub.status, 'submitted')
                        WHEN a.deadline < NOW() THEN 'overdue'
                        ELSE 'pending'
                    END AS computed_status
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             LEFT JOIN departments d ON a.department_id = d.id
             LEFT JOIN departments dept_s ON s.department_id = dept_s.id
             LEFT JOIN users u ON a.faculty_id = u.id
             LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
             WHERE a.deleted_at IS NULL
               AND COALESCE(a.semester, s.semester) = ?
             ORDER BY a.deadline DESC"
        );
        if ($stmt) {
            $stmt->bind_param("is", $userId, $studentSem);
            $stmt->execute();
            $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>
<?php
$pageTitle = 'Assignments - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header">
                    <div>
                        <div class="page-header-title-row">
                            <h1>Coursework &amp; Assignments</h1>
                            <span class="badge badge-primary header-badge">
                                <i class="fas fa-graduation-cap"></i> Semester <?php echo htmlspecialchars($studentSem); ?>
                            </span>
                            <?php if (!empty($studentDeptCode)): ?>
                                <span class="badge badge-info header-badge">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($studentDeptCode); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="page-subtitle">Tasks &amp; problem statements assigned for your semester, with PDF problem sheets and coursework submission</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header table-card-header">
                        <h3><i class="fas fa-file-alt"></i> All Coursework Assignments</h3>
                        <span class="table-counter"><?php echo count($assignments); ?> Registered</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title &amp; Subject</th>
                                        <th>Target Scope</th>
                                        <th>Problem PDF</th>
                                        <th>Due Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assignments)): ?>
                                        <tr>
                                            <td colspan="8" class="empty-state-cell">
                                                <i class="fas fa-file-signature empty-state-icon"></i>
                                                <strong class="empty-state-title">No Coursework Assigned Yet for Semester <?php echo htmlspecialchars($studentSem); ?></strong>
                                                <p class="empty-state-sub">You are all caught up! Assignments published for your semester and subjects will appear here.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assignments as $asg): 
                                            $subStatus = strtolower($asg['submission_status'] ?? ($asg['computed_status'] ?? 'pending'));
                                            $hasSubmitted = !empty($asg['submission_id']) || in_array($subStatus, ['submitted', 'late', 'graded', 'resubmitted']);
                                            $overdue = isOverdue($asg['deadline']) && !$hasSubmitted;
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong class="asg-title"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                    <div class="asg-subject"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                                    <?php if (!empty($asg['description'])): ?>
                                                        <div class="asg-desc">
                                                            <?php echo htmlspecialchars($asg['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="asg-badges">
                                                        <span class="badge badge-primary asg-scope-badge">Sem <?php echo htmlspecialchars($asg['assignment_semester']); ?></span>
                                                        <span class="badge badge-info asg-scope-badge"><?php echo htmlspecialchars($asg['department_code']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($asg['attachment_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars(storageUrl($asg['attachment_path'])); ?>" target="_blank" class="btn btn-outline btn-sm asg-pdf-btn" title="View Problem Statement PDF">
                                                            <i class="fas fa-file-pdf"></i> View PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="asg-text-only">Text Only</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="asg-deadline <?php echo $overdue ? 'overdue' : ''; ?>">
                                                        <?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?>
                                                    </span>
                                                    <?php if ($overdue): ?>
                                                        <span class="badge badge-danger asg-overdue-tag">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($asg['max_marks'] ?? 100); ?> pts</td>
                                                <td>
                                                    <?php 
                                                     if ($subStatus === 'graded') echo '<span class="badge badge-success">Graded</span>';
                                                    elseif ($subStatus === 'submitted') echo '<span class="badge badge-info">Submitted</span>';
                                                    elseif ($subStatus === 'resubmitted') echo '<span class="badge badge-info">Resubmitted</span>';
                                                    elseif ($subStatus === 'late') echo '<span class="badge badge-warning">Late</span>';
                                                    elseif ($overdue) echo '<span class="badge badge-danger">Missing</span>';
                                                    else echo '<span class="badge badge-warning">Pending</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo ($asg['sub_marks'] !== null) ? "<strong>{$asg['sub_marks']}</strong> / {$asg['max_marks']}" : '—'; ?>
                                                </td>
                                                <td>
                                                    <div class="asg-actions">
                                                        <?php if ($hasSubmitted): ?>
                                                            <button class="btn btn-secondary btn-sm asg-btn" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                                <i class="fas fa-redo"></i> Resubmit
                                                            </button>
                                                            <?php if (!empty($asg['submission_file'])): ?>
                                                                <a href="<?php echo htmlspecialchars(storageUrl($asg['submission_file'])); ?>" target="_blank" class="btn btn-outline btn-sm asg-view-btn" title="View Submitted Work">
                                                                    <i class="fas fa-file-download"></i> View Submission
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <button class="btn btn-primary btn-sm asg-btn" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                                <i class="fas fa-upload"></i> Submit
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
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

    <!-- Submit Assignment Modal -->
    <div class="modal-backdrop" id="submitModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="submitModalTitle">Submit Assignment</h3>
                <button class="modal-close" onclick="closeModal('submitModal')">&times;</button>
            </div>
            <form method="POST" action="assignments.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="submitAsgId">
                    <div class="form-group">
                        <label for="submission_text" class="form-label-bold">Submission Notes / Written Response</label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="5" placeholder="Enter answers, solution notes, GitHub repository link, or explanations..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="submission_file" class="form-label-bold">Attach File (PDF, DOC, DOCX, ZIP, TXT, PNG, JPG up to 15MB)</label>
                        <input type="file" name="submission_file" id="submission_file" class="form-control" accept=".pdf,.doc,.docx,.txt,.zip,.png,.jpg,.jpeg">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('submitModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Work</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script>
    function openSubmitModal(id, title) {
        document.getElementById('submitAsgId').value = id;
        document.getElementById('submitModalTitle').textContent = 'Submit: ' + title;
        document.getElementById('submission_text').value = '';
        document.getElementById('submission_file').value = '';
        openModal('submitModal');
    }
    </script>
</body>
</html>
