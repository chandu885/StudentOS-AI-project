<?php
// frontend/student/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
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
        $ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $ext;
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetFile)) {
            $filePath = 'assignments/' . $fileName;
        }
    }

    if ($asgId > 0 && $userId > 0 && $db) {
        // Fetch deadline to determine if submission is on-time or late
        $deadline = null;
        $asgCheck = $db->prepare("SELECT deadline, title FROM assignments WHERE id = ?");
        if ($asgCheck) {
            $asgCheck->bind_param("i", $asgId);
            $asgCheck->execute();
            $asgMeta = $asgCheck->get_result()->fetch_assoc();
            $deadline = $asgMeta['deadline'] ?? null;
            $asgTitle = $asgMeta['title'] ?? 'Assignment';
            $asgCheck->close();
        }

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
                if ($upStmt->execute()) {
                    $successMsg = 'Assignment resubmitted successfully!';
                } else {
                    $errorMsg = 'Failed to update assignment submission.';
                }
                $upStmt->close();
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
    } else {
        $errorMsg = 'Invalid assignment selection or database connection issue.';
    }
}

$db = getDbConnection();
$assignments = [];

if ($db && $userId > 0) {
    $stmt = $db->prepare(
        "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                sub.id AS submission_id, sub.status AS submission_status, sub.marks_obtained AS sub_marks, sub.feedback, sub.submitted_at,
                CASE 
                    WHEN sub.id IS NOT NULL THEN COALESCE(sub.status, 'submitted')
                    WHEN a.deadline < NOW() THEN 'overdue'
                    ELSE 'pending'
                END AS computed_status
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
         WHERE ss.student_id = ?
         ORDER BY a.deadline DESC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - StudentOS AI</title>
    
    <!-- External Google Font Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CDN Resources (Font Awesome, Normalize) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    
    <!-- Application Stylesheets -->
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
                        <h1>Coursework & Assignments</h1>
                        <p class="page-subtitle">Track, submit, and review grades for your coursework</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> All Assignments</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title & Subject</th>
                                        <th>Due Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assignments)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-file-signature" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                                <strong style="color: var(--text-primary);">No Coursework Assigned Yet</strong>
                                                <p style="font-size: 13px; margin-top: 4px;">You are all caught up! Assignments published by faculty will appear here.</p>
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
                                                    <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                                </td>
                                                <td>
                                                    <span style="font-size: 13px; font-weight: 500; <?php echo $overdue ? 'color: var(--danger);' : ''; ?>">
                                                        <?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?>
                                                    </span>
                                                    <?php if ($overdue): ?>
                                                        <span class="badge badge-danger" style="margin-left: 6px;">Overdue</span>
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
                                                    <?php if ($hasSubmitted): ?>
                                                        <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                            <i class="fas fa-redo"></i> Resubmit
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-primary" style="padding: 6px 14px; font-size: 12px;" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                            <i class="fas fa-upload"></i> Submit
                                                        </button>
                                                    <?php endif; ?>
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
                        <label for="submission_text">Submission Notes / Written Response</label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="4" placeholder="Enter links, GitHub URL, or answer text..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="submission_file">Attach File (PDF, ZIP, DOCX up to 10MB)</label>
                        <input type="file" name="submission_file" id="submission_file" class="form-control">
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
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openSubmitModal(id, title) {
        document.getElementById('submitAsgId').value = id;
        document.getElementById('submitModalTitle').textContent = 'Submit: ' + title;
        openModal('submitModal');
    }
    </script>
</body>
</html>
