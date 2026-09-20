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
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
                            <h1 style="margin: 0;">Coursework &amp; Assignments</h1>
                            <span class="badge badge-primary" style="font-size: 12px; padding: 4px 10px; font-weight: 600;">
                                <i class="fas fa-graduation-cap"></i> Semester <?php echo htmlspecialchars($studentSem); ?>
                            </span>
                            <?php if (!empty($studentDeptCode)): ?>
                                <span class="badge badge-info" style="font-size: 12px; padding: 4px 10px; font-weight: 600;">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($studentDeptCode); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="page-subtitle" style="margin: 0;">Tasks &amp; problem statements assigned for your semester, with PDF problem sheets and live AI assistance</p>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="btn btn-primary" style="background: linear-gradient(135deg, #4F46E5, #7C3AED); border: none; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); display: inline-flex; align-items: center; gap: 8px;" onclick="openGeneralAISolver()">
                            <i class="fas fa-robot"></i> Ask AI to Solve Any Homework
                        </button>
                        <button class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px;" onclick="openModal('aiKeyModal')" title="Configure Gemini / Groq / OpenAI API Key">
                            <i class="fas fa-key" style="color: #F59E0B;"></i> AI Key
                        </button>
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

                <!-- Quick AI Problem Solver Banner -->
                <div class="card" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.07), rgba(6, 182, 212, 0.05)); border: 1px solid rgba(79, 70, 229, 0.22); margin-bottom: 24px; border-radius: var(--radius-lg);">
                    <div class="card-body" style="padding: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                            <div style="max-width: 600px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                    <span class="badge" style="background: #4F46E5; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">
                                        <i class="fas fa-brain"></i> LIVE AI HOMEWORK ASSISTANT
                                    </span>
                                    <span style="font-size: 12px; color: var(--text-muted);">Real-time problem solving & code generation</span>
                                </div>
                                <h3 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px 0;">
                                    Stuck on an assignment problem or question?
                                </h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
                                    Click <strong>"AI Solve & Tutor"</strong> on any assignment below to get a complete step-by-step academic solution, code implementations, theoretical intuition, or direct submission assistance.
                                </p>
                            </div>
                            <div style="display: flex; gap: 8px; width: 100%; max-width: 480px;">
                                <input type="text" id="quickAiInput" class="form-control" placeholder="Ask any assignment question (e.g. 'Solve 0/1 Knapsack in Python', 'Prove BCNF decomposition')..." style="font-size: 13px; border-radius: 20px 0 0 20px;" onkeydown="if(event.key==='Enter') triggerQuickAiSolve()">
                                <button class="btn btn-primary" style="border-radius: 0 20px 20px 0; padding: 8px 18px; white-space: nowrap; background: #4F46E5; border-color: #4F46E5;" onclick="triggerQuickAiSolve()">
                                    <i class="fas fa-search"></i> Solve
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-file-alt"></i> All Coursework Assignments</h3>
                        <span style="font-size: 12px; color: var(--text-muted);"><?php echo count($assignments); ?> Registered</span>
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
                                            <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-file-signature" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                                <strong style="color: var(--text-primary);">No Coursework Assigned Yet for Semester <?php echo htmlspecialchars($studentSem); ?></strong>
                                                <p style="font-size: 13px; margin-top: 4px;">You are all caught up! Assignments published for your semester and subjects will appear here.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assignments as $asg): 
                                            $subStatus = strtolower($asg['submission_status'] ?? ($asg['computed_status'] ?? 'pending'));
                                            $hasSubmitted = !empty($asg['submission_id']) || in_array($subStatus, ['submitted', 'late', 'graded', 'resubmitted']);
                                            $overdue = isOverdue($asg['deadline']) && !$hasSubmitted;
                                            $asgJson = json_encode([
                                                'id' => (int)$asg['id'],
                                                'title' => $asg['title'],
                                                'subject_name' => $asg['subject_name'],
                                                'subject_code' => $asg['subject_code'] ?? '',
                                                'description' => $asg['description'] ?? '',
                                                'instructions' => $asg['instructions'] ?? '',
                                                'max_marks' => $asg['max_marks'] ?? 100,
                                                'deadline' => $asg['deadline'] ?? '',
                                                'attachment_path' => !empty($asg['attachment_path']) ? storageUrl($asg['attachment_path']) : null
                                            ]);
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                                    <?php if (!empty($asg['description'])): ?>
                                                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; max-width: 380px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                            <?php echo htmlspecialchars($asg['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                        <span class="badge badge-primary" style="font-size: 11px;">Sem <?php echo htmlspecialchars($asg['assignment_semester']); ?></span>
                                                        <span class="badge badge-info" style="font-size: 11px;"><?php echo htmlspecialchars($asg['department_code']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($asg['attachment_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars(storageUrl($asg['attachment_path'])); ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; color: #EF4444; border-color: #EF4444; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px;" title="View Problem Statement PDF">
                                                            <i class="fas fa-file-pdf"></i> View PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="font-size: 12px; color: var(--text-muted);">Text Only</span>
                                                    <?php endif; ?>
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
                                                    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                                        <!-- Live AI Solve Button -->
                                                        <button class="btn btn-sm" style="background: linear-gradient(135deg, #4F46E5, #7C3AED); color: #fff; border: none; padding: 6px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; border-radius: var(--radius-sm); font-weight: 600; box-shadow: 0 2px 8px rgba(79,70,229,0.3); cursor: pointer;" onclick='openAIAssignmentModal(<?php echo htmlspecialchars($asgJson, ENT_QUOTES, "UTF-8"); ?>)' title="Open AI Assignment Solver & Step-by-Step Tutor">
                                                            <i class="fas fa-robot"></i> AI Solve &amp; Tutor
                                                        </button>

                                                        <?php if ($hasSubmitted): ?>
                                                            <button class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px;" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                                <i class="fas fa-redo"></i> Resubmit
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-primary btn-sm" style="padding: 6px 14px; font-size: 12px;" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
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

    <!-- AI Assignment Solver & Tutor Modal -->
    <div class="modal-backdrop" id="aiAssignmentModal" style="display: none;">
        <div class="modal-card" style="max-width: 820px; width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #4F46E5, #7C3AED); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 12px rgba(79,70,229,0.3);">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div>
                        <h3 id="aiModalHeading" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary);">
                            AI Assignment Solver & Academic Tutor
                        </h3>
                        <span id="aiModalSubtitle" style="font-size: 12px; color: var(--text-muted);">
                            Live answers, step-by-step proofs, and full solutions
                        </span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span id="aiModelBadge" class="badge" style="background: rgba(79,70,229,0.1); color: #4F46E5; font-size: 11px; padding: 4px 8px; border-radius: 12px;">
                        <i class="fas fa-bolt"></i> Live AI Active
                    </span>
                    <button class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 11px;" onclick="openModal('aiKeyModal')" title="Set Custom API Key">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="modal-close" onclick="closeModal('aiAssignmentModal')" style="font-size: 20px; border: none; background: none; cursor: pointer;">&times;</button>
                </div>
            </div>

            <div class="modal-body" style="padding: 16px 20px; overflow-y: auto; flex: 1;">
                <!-- Problem Statement Card -->
                <div id="aiProblemBox" style="background: rgba(79, 70, 229, 0.04); border: 1px solid rgba(79, 70, 229, 0.15); border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span id="aiProblemSubject" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #4F46E5;">SUBJECT</span>
                        <span id="aiProblemMarks" style="font-size: 11px; color: var(--text-muted); font-weight: 600;">100 pts</span>
                    </div>
                    <h4 id="aiProblemTitle" style="font-size: 14px; margin: 0 0 6px 0; color: var(--text-primary);">Assignment Title</h4>
                    <p id="aiProblemDesc" style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5;">Problem description...</p>
                    <div id="aiPdfWrapper" style="margin-top: 10px;">
                        <a id="aiProblemPdfLink" href="#" target="_blank" class="btn btn-outline btn-sm" style="font-size: 11px; color: #EF4444; border-color: #EF4444; display: none; align-items: center; gap: 5px; border-radius: 6px;">
                            <i class="fas fa-file-pdf"></i> View Problem Statement PDF
                        </a>
                    </div>
                </div>

                <!-- One-Click Quick Actions -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;">
                    <button class="btn btn-outline btn-sm" style="border-radius: 16px; font-size: 12px;" onclick="executeAITask('solve')">
                        🚀 Solve Step-by-Step
                    </button>
                    <button class="btn btn-outline btn-sm" style="border-radius: 16px; font-size: 12px;" onclick="executeAITask('code')">
                        💻 Generate Code
                    </button>
                    <button class="btn btn-outline btn-sm" style="border-radius: 16px; font-size: 12px;" onclick="executeAITask('explain')">
                        💡 Explain Concepts
                    </button>
                    <button class="btn btn-outline btn-sm" style="border-radius: 16px; font-size: 12px;" onclick="executeAITask('review')">
                        🔍 Review My Draft
                    </button>
                </div>

                <!-- Live Solution Display Box -->
                <div id="aiSolutionContainer" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; min-height: 160px; max-height: 380px; overflow-y: auto; font-size: 13.5px; line-height: 1.65; color: var(--text-primary);">
                    <div id="aiEmptyPlaceholder" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                        <i class="fas fa-magic" style="font-size: 28px; margin-bottom: 10px; color: #4F46E5; opacity: 0.6; display: block;"></i>
                        <strong>Live Academic AI Ready</strong>
                        <p style="font-size: 12px; margin-top: 4px;">Click any action pill above or type a custom question below to generate a step-by-step solution.</p>
                    </div>
                    <div id="aiLoadingIndicator" style="display: none; text-align: center; padding: 36px 20px; color: #4F46E5;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 28px; margin-bottom: 12px;"></i>
                        <div style="font-weight: 600; font-size: 14px;">Synthesizing Live Academic Solution...</div>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Formulating mathematical proofs, code implementations, and verified answers</p>
                    </div>
                    <div id="aiFormattedOutput" style="display: none;"></div>
                </div>

                <!-- Interactive Question Bar -->
                <div style="margin-top: 14px; display: flex; gap: 8px;">
                    <input type="text" id="aiCustomPromptInput" class="form-control" placeholder="Ask ANY question about this assignment or request adjustments (e.g. 'Show C++ code', 'Explain step 2')..." style="font-size: 13px;" onkeydown="if(event.key==='Enter') executeAITask('ask')">
                    <button class="btn btn-primary" id="aiSendBtn" style="padding: 8px 18px; white-space: nowrap; background: #4F46E5; border-color: #4F46E5;" onclick="executeAITask('ask')">
                        <i class="fas fa-paper-plane"></i> Ask
                    </button>
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span id="aiStatusFooter" style="font-size: 11px; color: var(--text-muted);">
                    <i class="fas fa-shield-alt" style="color: var(--success);"></i> Verified Academic AI
                </span>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline btn-sm" id="copyAiBtn" onclick="copyAiOutput()" style="display: none;">
                        <i class="fas fa-copy"></i> Copy Solution
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="insertSubmissionBtn" onclick="insertSolutionIntoSubmission()" style="display: none; background: #10B981; border-color: #10B981; color: #fff;">
                        <i class="fas fa-file-import"></i> Insert into Submission Notes
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('aiAssignmentModal')">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Key Settings Modal -->
    <div class="modal-backdrop" id="aiKeyModal" style="display: none;">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fas fa-key" style="color: #F59E0B;"></i> AI Provider & API Key Configuration</h3>
                <button class="modal-close" onclick="closeModal('aiKeyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px;">
                    StudentOS AI includes a built-in reasoning engine, but you can also connect your own free <strong>Google Gemini</strong> or <strong>Groq</strong> API key for unlimited ultra-fast answers to any question.
                </p>
                <div class="form-group">
                    <label for="modalApiKey" style="font-weight: 600; font-size: 13px;">API Key (Google Gemini / Groq / OpenRouter)</label>
                    <input type="password" id="modalApiKey" class="form-control" placeholder="AQ.Ab8... or AIzaSy... or gsk_... or sk-...">
                    <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                        Get a 100% free key: <a href="https://aistudio.google.com/" target="_blank" style="color: #4F46E5; text-decoration: underline;">Google AI Studio</a> or <a href="https://console.groq.com/" target="_blank" style="color: #4F46E5; text-decoration: underline;">Groq Console</a>.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('aiKeyModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveUserApiKey()"><i class="fas fa-save"></i> Save Key</button>
            </div>
        </div>
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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label for="submission_text" style="margin: 0; font-weight: 600;">Submission Notes / Written Response</label>
                            <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 11px; border-radius: 12px; color: #4F46E5; border-color: rgba(79,70,229,0.3);" onclick="openAIFromSubmitModal()">
                                <i class="fas fa-magic"></i> Generate with AI
                            </button>
                        </div>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="6" placeholder="Enter full answer, step-by-step solution, GitHub link, or coursework response..."></textarea>
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
    let currentAsgData = null;
    let lastGeneratedSolution = '';

    function openSubmitModal(id, title) {
        document.getElementById('submitAsgId').value = id;
        document.getElementById('submitModalTitle').textContent = 'Submit: ' + title;
        openModal('submitModal');
    }

    function openAIAssignmentModal(asg) {
        currentAsgData = asg;
        document.getElementById('aiProblemSubject').textContent = asg.subject_name || 'Academic Subject';
        document.getElementById('aiProblemMarks').textContent = (asg.max_marks || 100) + ' pts';
        document.getElementById('aiProblemTitle').textContent = asg.title || 'Coursework Assignment';
        document.getElementById('aiProblemDesc').textContent = asg.description || 'No detailed problem statement provided. Use custom question bar below.';
        
        const pdfLink = document.getElementById('aiProblemPdfLink');
        if (pdfLink) {
            if (asg.attachment_path) {
                pdfLink.href = asg.attachment_path;
                pdfLink.style.display = 'inline-flex';
            } else {
                pdfLink.style.display = 'none';
            }
        }

        // Reset output box
        document.getElementById('aiEmptyPlaceholder').style.display = 'block';
        document.getElementById('aiLoadingIndicator').style.display = 'none';
        document.getElementById('aiFormattedOutput').style.display = 'none';
        document.getElementById('copyAiBtn').style.display = 'none';
        document.getElementById('insertSubmissionBtn').style.display = 'none';
        document.getElementById('aiCustomPromptInput').value = '';

        openModal('aiAssignmentModal');
    }

    function openGeneralAISolver() {
        openAIAssignmentModal({
            id: 0,
            title: 'General Coursework Problem',
            subject_name: 'Academic Question',
            description: 'Ask any question from your syllabus, homework, laboratory experiments, or exams.',
            max_marks: 100
        });
    }

    function openAIFromSubmitModal() {
        const asgId = parseInt(document.getElementById('submitAsgId').value) || 0;
        closeModal('submitModal');
        openAIAssignmentModal({
            id: asgId,
            title: document.getElementById('submitModalTitle').textContent.replace('Submit: ', ''),
            subject_name: 'Coursework',
            description: document.getElementById('submission_text').value || 'Provide complete solution for submission',
            max_marks: 100
        });
        executeAITask('solve');
    }

    function triggerQuickAiSolve() {
        const query = document.getElementById('quickAiInput').value.trim();
        if (!query) return;
        openAIAssignmentModal({
            id: 0,
            title: query,
            subject_name: 'Custom Query',
            description: query,
            max_marks: 100
        });
        document.getElementById('aiCustomPromptInput').value = query;
        executeAITask('ask');
    }

    async function executeAITask(taskType) {
        if (!currentAsgData) return;

        const customInput = document.getElementById('aiCustomPromptInput');
        const question = (taskType === 'ask') ? customInput.value.trim() : '';
        if (taskType === 'ask' && !question) {
            showToast('Please enter a question or prompt first', 'warning');
            return;
        }

        // Show loading state
        document.getElementById('aiEmptyPlaceholder').style.display = 'none';
        document.getElementById('aiLoadingIndicator').style.display = 'block';
        document.getElementById('aiFormattedOutput').style.display = 'none';
        document.getElementById('copyAiBtn').style.display = 'none';
        document.getElementById('insertSubmissionBtn').style.display = 'none';
        document.getElementById('aiSendBtn').disabled = true;

        try {
            const savedKey = localStorage.getItem('user_ai_api_key') || '';
            const draft = document.getElementById('submission_text').value || '';

            const headers = typeof getAuthHeaders === 'function' 
                ? getAuthHeaders({ 'Content-Type': 'application/json' }) 
                : { 'Content-Type': 'application/json' };

            let apiUrl = '../../backend/api/ai.php?path=assignment-assist';
            if (window.location.pathname.toLowerCase().includes('/studentos-ai-project/')) {
                apiUrl = '/StudentOS-AI-project/backend/api/ai.php?path=assignment-assist';
            }

            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify({
                    assignment_id: currentAsgData.id || 0,
                    question: question || currentAsgData.title,
                    task_type: taskType,
                    draft_text: draft,
                    api_key: savedKey
                })
            });

            document.getElementById('aiLoadingIndicator').style.display = 'none';
            document.getElementById('aiSendBtn').disabled = false;

            if (!response.ok) {
                let errMsg = 'Server error (' + response.status + ')';
                try {
                    const errData = await response.json();
                    if (errData && errData.error) errMsg = errData.error;
                } catch(e) {}
                document.getElementById('aiFormattedOutput').innerHTML = `<div class="alert alert-error" style="color:var(--danger); padding:12px;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(errMsg)}</div>`;
                document.getElementById('aiFormattedOutput').style.display = 'block';
                return;
            }

            const data = await response.json();

            if (data && data.answer) {
                lastGeneratedSolution = data.answer;
                const formattedHtml = formatAcademicMarkdown(data.answer);
                const outBox = document.getElementById('aiFormattedOutput');
                outBox.innerHTML = formattedHtml;
                outBox.style.display = 'block';

                document.getElementById('copyAiBtn').style.display = 'inline-flex';
                if (currentAsgData.id > 0) {
                    document.getElementById('insertSubmissionBtn').style.display = 'inline-flex';
                }

                if (data.provider) {
                    document.getElementById('aiModelBadge').innerHTML = `<i class="fas fa-check-circle" style="color:#10B981;"></i> ${escapeHTML(data.provider)}`;
                }
            } else if (data && data.error) {
                document.getElementById('aiFormattedOutput').innerHTML = `<div class="alert alert-error" style="color:var(--danger); padding:12px;"><i class="fas fa-exclamation-triangle"></i> ${escapeHTML(data.error)}</div>`;
                document.getElementById('aiFormattedOutput').style.display = 'block';
            } else {
                document.getElementById('aiFormattedOutput').innerHTML = '<div class="alert alert-error" style="color:var(--danger); padding:12px;">Unable to synthesize AI response. Please try rephrasing your prompt.</div>';
                document.getElementById('aiFormattedOutput').style.display = 'block';
            }
        } catch (err) {
            console.error('AI Assignment Assist Error:', err);
            document.getElementById('aiLoadingIndicator').style.display = 'none';
            document.getElementById('aiSendBtn').disabled = false;
            const msg = err && err.message ? err.message : 'Error connecting to AI service';
            document.getElementById('aiFormattedOutput').innerHTML = `<div class="alert alert-error" style="color:var(--danger); padding:12px;"><i class="fas fa-exclamation-circle"></i> ${escapeHTML(msg)}. Please verify server and network connectivity.</div>`;
            document.getElementById('aiFormattedOutput').style.display = 'block';
        }
    }

    function insertSolutionIntoSubmission() {
        if (!currentAsgData || !lastGeneratedSolution) return;
        closeModal('aiAssignmentModal');
        openSubmitModal(currentAsgData.id, currentAsgData.title);
        const subBox = document.getElementById('submission_text');
        subBox.value = lastGeneratedSolution;
        showToast('Solution copied into submission response box!', 'success');
        subBox.focus();
    }

    function copyAiOutput() {
        if (!lastGeneratedSolution) return;
        copyToClipboard(lastGeneratedSolution);
    }

    function saveUserApiKey() {
        const key = document.getElementById('modalApiKey').value.trim();
        if (key) {
            localStorage.setItem('user_ai_api_key', key);
            // Also notify backend session
            fetch('/StudentOS-AI-project/backend/api/ai.php?path=save-key', {
                method: 'POST',
                headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ api_key: key })
            }).catch(() => {});
            showToast('AI API Key configured successfully!', 'success');
        } else {
            localStorage.removeItem('user_ai_api_key');
            showToast('API Key cleared. Using built-in engine.', 'info');
        }
        closeModal('aiKeyModal');
    }

    // Markdown Parser
    function formatAcademicMarkdown(md) {
        if (!md) return '';
        let html = escapeHTML(md);

        // Code blocks with syntax copy button
        html = html.replace(/```([a-zA-Z0-9_\-\+]*)\n([\s\S]*?)```/g, function(match, lang, code) {
            const langLabel = lang ? lang.toUpperCase() : 'CODE';
            const rawCode = code.trim();
            const copyCode = rawCode.replace(/"/g, '&quot;');
            return `
                <div style="margin: 12px 0; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); background: #0F172A; color: #F8FAFC;">
                    <div style="background: #1E293B; padding: 6px 12px; font-size: 11px; font-weight: 700; color: #94A3B8; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-code"></i> ${langLabel}</span>
                        <button type="button" style="background: rgba(255,255,255,0.1); border: none; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 10px; cursor: pointer;" onclick="copyToClipboard('${copyCode}')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <pre style="padding: 12px 14px; margin: 0; font-family: monospace; font-size: 12.5px; overflow-x: auto; line-height: 1.5;">${code}</pre>
                </div>
            `;
        });

        // Inline code
        html = html.replace(/`([^`]+)`/g, '<code style="background: rgba(79,70,229,0.1); color: #4F46E5; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: monospace;">$1</code>');

        // Headers
        html = html.replace(/^###\s*(.*?)$/gm, '<h4 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 16px 0 8px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">$1</h4>');
        html = html.replace(/^##\s*(.*?)$/gm, '<h3 style="font-size: 17px; font-weight: 700; color: #4F46E5; margin: 18px 0 8px 0;">$1</h3>');
        html = html.replace(/^#\s*(.*?)$/gm, '<h2 style="font-size: 19px; font-weight: 800; color: #4F46E5; margin: 20px 0 10px 0;">$1</h2>');

        // Bold & Italics
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^\*]+)\*/g, '<em>$1</em>');

        // Bullet lists
        html = html.replace(/^[•\-\*]\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin: 4px 0 4px 12px;"><i class="fas fa-chevron-right" style="font-size:9px; color:#4F46E5; margin-top:6px; flex-shrink:0;"></i><span>$1</span></div>');

        // Numbered lists
        html = html.replace(/^(\d+)\.\s+(.*?)$/gm, '<div style="display:flex; gap:8px; margin: 4px 0 4px 12px;"><strong style="color:#4F46E5; flex-shrink:0;">$1.</strong><span>$2</span></div>');

        // Tables simple parse
        html = html.replace(/\|(.+)\|/g, function(match) {
            if (match.includes('---')) return '';
            const cols = match.split('|').map(c => c.trim()).filter(c => c.length > 0);
            return '<div style="display:flex; gap:12px; padding:6px 0; border-bottom:1px solid var(--border-color);">' + cols.map(c => `<div style="flex:1; font-size:12.5px;">${c}</div>`).join('') + '</div>';
        });

        // Double linebreaks
        html = html.replace(/\n\n/g, '<div style="height: 10px;"></div>');
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    // Populate key on page load
    document.addEventListener('DOMContentLoaded', () => {
        const saved = localStorage.getItem('user_ai_api_key');
        if (saved) {
            const input = document.getElementById('modalApiKey');
            if (input) input.value = saved;
        }
    });
    </script>
</body>
</html>
