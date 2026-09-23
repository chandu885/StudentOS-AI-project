<?php
// frontend/super-admin/submissions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/models/SystemModel.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();
$sysModel = new SystemModel();

// Handle Grading / Feedback POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'grade_submission') {
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
                    $successMsg = 'Submission grade and evaluation feedback updated successfully.';
                    $sysModel->logAudit($userId, 'SUPER_ADMIN_GRADE_SUBMISSION', 'assignment_submissions', $subId, "Super Admin evaluated submission #{$subId} with marks {$marks}.");
                } else {
                    $errorMsg = 'Failed to update grade: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif ($_POST['action'] === 'delete_submission') {
        $subId = (int)($_POST['submission_id'] ?? 0);
        if ($db && $subId > 0) {
            $stmt = $db->prepare("DELETE FROM assignment_submissions WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $subId);
                if ($stmt->execute()) {
                    $successMsg = 'Submission record deleted successfully.';
                    $sysModel->logAudit($userId, 'SUPER_ADMIN_DELETE_SUBMISSION', 'assignment_submissions', $subId, "Super Admin deleted submission #{$subId}.");
                } else {
                    $errorMsg = 'Failed to delete submission: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Filters
$filterAsg = !empty($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$filterDept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$filterSem = !empty($_GET['semester']) ? trim($_GET['semester']) : null;
$filterStatus = !empty($_GET['status']) ? trim($_GET['status']) : null;
$search = !empty($_GET['q']) ? trim($_GET['q']) : null;

// Fetch departments for filter
$departments = [];
if ($db) {
    $deptRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($deptRes) {
        $departments = $deptRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch assignments list for filter
$assignmentsList = [];
if ($db) {
    $asgListRes = $db->query("SELECT a.id, a.title, s.code AS subject_code FROM assignments a JOIN subjects s ON a.subject_id = s.id WHERE a.deleted_at IS NULL ORDER BY a.id DESC");
    if ($asgListRes) {
        $assignmentsList = $asgListRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch submissions
$submissions = [];
$totalSubCount = 0;
$gradedCount = 0;
$pendingCount = 0;
$pdfAttachmentCount = 0;

if ($db) {
    $sql = "SELECT sub.id, sub.assignment_id, sub.student_id, sub.submission_text, sub.file_path,
                   sub.marks_obtained, sub.feedback, sub.submitted_at, sub.graded_at, sub.status,
                   CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                   u.email AS student_email,
                   COALESCE(sp.roll_number, sp.student_id, CONCAT('STU-', u.id)) AS roll_number,
                   COALESCE(sp.semester, a.semester, '1') AS semester,
                   COALESCE(d.name, sp.department, dept_asg.name, 'General') AS department_name,
                   COALESCE(d.code, sp.department, dept_asg.code, 'GEN') AS department_code,
                   a.title AS assignment_title, a.max_marks, a.deadline,
                   s.name AS subject_name, s.code AS subject_code,
                   CONCAT(eval.first_name, ' ', eval.last_name) AS grader_name
            FROM assignment_submissions sub
            JOIN assignments a ON sub.assignment_id = a.id
            JOIN subjects s ON a.subject_id = s.id
            JOIN users u ON sub.student_id = u.id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN departments d ON sp.department_id = d.id
            LEFT JOIN departments dept_asg ON a.department_id = dept_asg.id
            LEFT JOIN users eval ON sub.graded_by = eval.id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($filterAsg) {
        $sql .= " AND sub.assignment_id = ?";
        $params[] = $filterAsg;
        $types .= "i";
    }

    if ($filterDept) {
        $sql .= " AND (sp.department_id = ? OR a.department_id = ?)";
        $params[] = $filterDept;
        $params[] = $filterDept;
        $types .= "ii";
    }

    if ($filterSem) {
        $cleanSem = preg_replace('/[^0-9]/', '', $filterSem);
        $sql .= " AND (sp.semester = ? OR sp.semester = ? OR a.semester = ? OR a.semester = ?)";
        $params[] = $filterSem;
        $params[] = $cleanSem;
        $params[] = $filterSem;
        $params[] = $cleanSem;
        $types .= "ssss";
    }

    if ($filterStatus && in_array($filterStatus, ['submitted', 'graded', 'late', 'resubmitted'])) {
        $sql .= " AND sub.status = ?";
        $params[] = $filterStatus;
        $types .= "s";
    }

    if ($search) {
        $searchWild = "%{$search}%";
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR sp.roll_number LIKE ? OR sp.student_id LIKE ? OR a.title LIKE ? OR s.name LIKE ? OR s.code LIKE ?)";
        for ($i = 0; $i < 8; $i++) {
            $params[] = $searchWild;
            $types .= "s";
        }
    }

    $sql .= " ORDER BY sub.submitted_at DESC";

    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $submissions = $res->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        }
    } else {
        $res = $db->query($sql);
        if ($res) {
            $submissions = $res->fetch_all(MYSQLI_ASSOC);
        }
    }

    // Calculate metrics
    $totalSubCount = count($submissions);
    foreach ($submissions as $subItem) {
        if (($subItem['status'] ?? '') === 'graded') {
            $gradedCount++;
        } else {
            $pendingCount++;
        }
        if (!empty($subItem['file_path'])) {
            $pdfAttachmentCount++;
        }
    }
}

$pageTitle = 'Assignment Submissions - Super Admin - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h1><i class="fas fa-inbox" style="color: var(--primary); margin-right: 8px;"></i> Student Assignment Submissions</h1>
        <p class="page-subtitle">Super Administrator oversight of submitted coursework, student files, PDF solutions, and grading evaluations</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
        <a href="assignments.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back to Assignments
        </a>
    </div>
</div>

<!-- Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-inbox"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Submissions</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalSubCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #EF4444; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-file-pdf"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">PDF / File Submissions</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $pdfAttachmentCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-check-double"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Graded &amp; Scored</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $gradedCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Pending Evaluation</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $pendingCount; ?></div>
        </div>
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

<!-- Filters Card -->
<div class="card" style="margin-bottom: 24px; padding: 16px 20px;">
    <form method="GET" action="submissions.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1 1 200px; min-width: 180px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Search student, roll, or assignment..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="height: 38px; font-size: 13px;">
        </div>

        <div style="flex: 1 1 200px; min-width: 180px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Assignment</label>
            <select name="assignment_id" class="form-control" style="height: 38px; font-size: 13px;">
                <option value="">-- All Assignments --</option>
                <?php foreach ($assignmentsList as $asgItem): ?>
                    <option value="<?php echo (int)$asgItem['id']; ?>" <?php echo ($filterAsg === (int)$asgItem['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($asgItem['title'] . ' [' . $asgItem['subject_code'] . ']'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1 1 160px; min-width: 150px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Department</label>
            <select name="department_id" class="form-control" style="height: 38px; font-size: 13px;">
                <option value="">-- All Departments --</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo (int)$dept['id']; ?>" <?php echo ($filterDept === (int)$dept['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 0 1 130px; min-width: 110px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Semester</label>
            <select name="semester" class="form-control" style="height: 38px; font-size: 13px;">
                <option value="">-- All Sems --</option>
                <?php for ($s = 1; $s <= 8; $s++): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($filterSem === (string)$s) ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div style="flex: 0 1 130px; min-width: 120px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: block;">Status</label>
            <select name="status" class="form-control" style="height: 38px; font-size: 13px;">
                <option value="">-- All Status --</option>
                <option value="submitted" <?php echo ($filterStatus === 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                <option value="graded" <?php echo ($filterStatus === 'graded') ? 'selected' : ''; ?>>Graded</option>
                <option value="late" <?php echo ($filterStatus === 'late') ? 'selected' : ''; ?>>Late</option>
                <option value="resubmitted" <?php echo ($filterStatus === 'resubmitted') ? 'selected' : ''; ?>>Resubmitted</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px; align-self: flex-end; margin-top: 4px;">
            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-filter"></i> Apply
            </button>
            <?php if ($filterAsg || $filterDept || $filterSem || $filterStatus || $search): ?>
                <a href="submissions.php" class="btn btn-secondary" style="height: 38px; padding: 0 14px; font-size: 13px; display: inline-flex; align-items: center;" title="Clear Filters">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Submissions Data Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3><i class="fas fa-list"></i> Submissions List (<?php echo count($submissions); ?>)</h3>
        <span style="font-size: 12px; color: var(--text-muted);">Showing all received student coursework submissions</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width: 170px;">Student Name</th>
                        <th style="min-width: 120px;">Roll Number</th>
                        <th style="min-width: 110px;">Semester</th>
                        <th style="min-width: 160px;">Department</th>
                        <th style="min-width: 170px;">Assignment / Subject</th>
                        <th style="min-width: 140px;">Submission Date</th>
                        <th style="min-width: 140px;">Submitted PDF / File</th>
                        <th style="min-width: 90px;">Status</th>
                        <th style="text-align: right; min-width: 110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                No student submissions found matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): 
                            $status = strtolower($sub['status'] ?? 'submitted');
                            $isGraded = ($status === 'graded');
                            $hasFile = !empty($sub['file_path']);
                            $fileExt = $hasFile ? strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION)) : '';
                            $isPdf = ($fileExt === 'pdf');
                            $semDisplay = is_numeric($sub['semester']) ? 'Semester ' . $sub['semester'] : $sub['semester'];
                            $fileUrl = $hasFile ? storageUrl($sub['file_path']) : '';
                        ?>
                            <tr>
                                <!-- Student's Name -->
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 34px; height: 34px; border-radius: 50%; background: rgba(79, 70, 229, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                                            <?php 
                                            $initials = '';
                                            $names = explode(' ', trim($sub['student_name']));
                                            foreach (array_slice($names, 0, 2) as $n) {
                                                $initials .= strtoupper(substr($n, 0, 1));
                                            }
                                            echo htmlspecialchars($initials ?: 'ST');
                                            ?>
                                        </div>
                                        <div>
                                            <strong style="color: var(--text-primary); font-size: 13.5px; display: block;"><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                                            <span style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['student_email'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Roll Number -->
                                <td>
                                    <span class="badge badge-secondary" style="font-family: monospace; font-size: 12px; font-weight: 700; letter-spacing: 0.3px;">
                                        <?php echo htmlspecialchars($sub['roll_number']); ?>
                                    </span>
                                </td>

                                <!-- Semester -->
                                <td>
                                    <span class="badge badge-primary" style="font-size: 11.5px;">
                                        <?php echo htmlspecialchars($semDisplay); ?>
                                    </span>
                                </td>

                                <!-- Department -->
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 12.5px;">
                                        <?php echo htmlspecialchars($sub['department_name']); ?>
                                    </div>
                                    <?php if (!empty($sub['department_code'])): ?>
                                        <span class="badge badge-outline" style="font-size: 10px; margin-top: 2px;">
                                            <?php echo htmlspecialchars($sub['department_code']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Assignment / Subject -->
                                <td>
                                    <strong style="color: var(--text-primary); font-size: 13px; display: block;">
                                        <?php echo htmlspecialchars($sub['assignment_title']); ?>
                                    </strong>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        <span class="badge badge-dark" style="font-size: 10px;"><?php echo htmlspecialchars($sub['subject_code']); ?></span>
                                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    </div>
                                </td>

                                <!-- Submission Date -->
                                <td>
                                    <div style="font-size: 12.5px; font-weight: 500; color: var(--text-primary);">
                                        <?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);">
                                        <?php echo date('h:i A', strtotime($sub['submitted_at'])); ?>
                                    </div>
                                </td>

                                <!-- Submitted PDF / File (Directly clickable/openable) -->
                                <td>
                                    <?php if ($hasFile): ?>
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline" style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; font-size: 11.5px; font-weight: 600; color: #EF4444; border-color: #EF4444; border-radius: 6px; text-decoration: none;" title="Open PDF in new tab">
                                                <i class="<?php echo $isPdf ? 'fas fa-file-pdf' : 'fas fa-file-alt'; ?>"></i>
                                                <span><?php echo $isPdf ? 'Open PDF' : 'View File'; ?></span>
                                                <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.7;"></i>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" download class="btn btn-sm btn-outline-secondary" style="padding: 5px 8px; font-size: 11px; border-radius: 6px;" title="Download File">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php if (!empty($sub['submission_text'])): ?>
                                            <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 9px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" onclick='viewSolutionText(<?php echo json_encode($sub, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                <i class="fas fa-file-alt"></i> Text Notes
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">No File Attached</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Status & Score -->
                                <td>
                                    <?php if ($status === 'graded'): ?>
                                        <span class="badge badge-success" style="font-size: 11px;">
                                            <i class="fas fa-check-circle"></i> Graded
                                        </span>
                                        <div style="font-size: 11px; font-weight: 700; color: var(--text-primary); margin-top: 3px;">
                                            <?php echo (float)$sub['marks_obtained']; ?> / <?php echo (int)$sub['max_marks']; ?>
                                        </div>
                                    <?php elseif ($status === 'late'): ?>
                                        <span class="badge badge-danger" style="font-size: 11px;">
                                            <i class="fas fa-exclamation-circle"></i> Late
                                        </span>
                                    <?php elseif ($status === 'resubmitted'): ?>
                                        <span class="badge badge-info" style="font-size: 11px;">
                                            <i class="fas fa-sync-alt"></i> Resubmitted
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="font-size: 11px;">
                                            <i class="fas fa-clock"></i> Submitted
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                        <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 9px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" onclick='openEvaluationModal(<?php echo json_encode($sub, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="<?php echo $isGraded ? 'Edit Evaluation' : 'Evaluate & Grade'; ?>">
                                            <i class="fas fa-pen"></i> <?php echo $isGraded ? 'Edit Grade' : 'Grade'; ?>
                                        </button>
                                        <form method="POST" action="submissions.php" onsubmit="return confirm('Are you sure you want to delete this submission record?');" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_submission">
                                            <input type="hidden" name="submission_id" value="<?php echo (int)$sub['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger); padding: 4px 7px; font-size: 11px;" title="Delete Submission">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
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

<!-- Evaluation & Grading Modal -->
<div class="modal-backdrop" id="evalModal">
    <div class="modal-card" style="max-width: 620px; width: 95%;">
        <div class="modal-header">
            <h3><i class="fas fa-graduation-cap" style="color: var(--primary);"></i> Evaluate Submission</h3>
            <button type="button" class="modal-close" onclick="closeModal('evalModal')">&times;</button>
        </div>
        <form method="POST" action="submissions.php">
            <input type="hidden" name="action" value="grade_submission">
            <input type="hidden" name="submission_id" id="evalSubId" value="">

            <div class="modal-body">
                <!-- Student & Coursework Summary -->
                <div style="background: var(--bg-primary); padding: 14px 16px; border-radius: var(--radius-md); margin-bottom: 16px; border: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12.5px;">
                        <div>
                            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Student Name</span>
                            <div style="font-weight: 700; color: var(--text-primary);" id="evalStudentName">-</div>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Roll Number</span>
                            <div style="font-weight: 600; font-family: monospace;" id="evalRollNumber">-</div>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Department &amp; Sem</span>
                            <div style="font-weight: 500;" id="evalDeptSem">-</div>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Assignment Title</span>
                            <div style="font-weight: 600; color: var(--text-primary);" id="evalAsgTitle">-</div>
                        </div>
                    </div>
                </div>

                <!-- Attached PDF / File Button -->
                <div class="form-group" id="evalFileWrapper" style="display: none;">
                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Submitted Document / PDF</label>
                    <div style="display: flex; gap: 10px; align-items: center; padding: 10px 14px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md);">
                        <i class="fas fa-file-pdf" style="font-size: 24px; color: #EF4444;"></i>
                        <div style="flex: 1;">
                            <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);" id="evalFileName">Submitted PDF</div>
                            <div style="font-size: 11px; color: var(--text-muted);">Click button below to open and review the submitted document</div>
                        </div>
                        <a href="#" target="_blank" rel="noopener noreferrer" id="evalFileOpenBtn" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;">
                            <i class="fas fa-external-link-alt"></i> Open PDF
                        </a>
                    </div>
                </div>

                <!-- Written Submission Text (if any) -->
                <div class="form-group" id="evalTextWrapper" style="display: none;">
                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Student Written Notes / Solution</label>
                    <div id="evalTextContent" style="padding: 12px 14px; background: var(--bg-primary); border-radius: var(--radius-md); font-size: 12.5px; color: var(--text-secondary); max-height: 180px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; border: 1px solid var(--border-color, rgba(255,255,255,0.08));"></div>
                </div>

                <!-- Marks & Feedback -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label for="evalMarks">Marks Obtained (out of <span id="evalMaxMarksSpan">50</span>) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.5" name="marks" id="evalMarks" class="form-control" required min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label>Submission Timestamp</label>
                        <div id="evalSubmittedAt" style="padding: 10px 14px; font-size: 12.5px; color: var(--text-muted); background: var(--bg-primary); border-radius: var(--radius-md);">—</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="evalFeedback">Evaluation Feedback / Comments</label>
                    <textarea name="feedback" id="evalFeedback" class="form-control" rows="3" placeholder="Provide constructive feedback, notes, or remarks for the student..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('evalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Evaluation</button>
            </div>
        </form>
    </div>
</div>

<!-- Solution Text Modal -->
<div class="modal-backdrop" id="textModal">
    <div class="modal-card" style="max-width: 580px; width: 95%;">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt" style="color: var(--primary);"></i> Solution Text &amp; Notes</h3>
            <button type="button" class="modal-close" onclick="closeModal('textModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 12px;">
                <strong id="textModalStudent" style="font-size: 14px; color: var(--text-primary);"></strong>
                <div id="textModalAsg" style="font-size: 12px; color: var(--text-muted);"></div>
            </div>
            <div id="textModalBody" style="padding: 14px; background: var(--bg-primary); border-radius: var(--radius-md); font-size: 13px; line-height: 1.5; color: var(--text-secondary); max-height: 350px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('textModal')">Close</button>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../components/footer.php'; ?>
</main>
</div>

<script src="../assets/js/utils.js"></script>
<script src="../assets/js/notifications.js"></script>
<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.style.opacity = '';
            modal.style.pointerEvents = '';
            document.body.style.overflow = '';
        }
    }

    function openEvaluationModal(sub) {
        document.getElementById('evalSubId').value = sub.id;
        document.getElementById('evalStudentName').textContent = sub.student_name + (sub.student_email ? ' (' + sub.student_email + ')' : '');
        document.getElementById('evalRollNumber').textContent = sub.roll_number || 'N/A';
        document.getElementById('evalDeptSem').textContent = (sub.department_code || sub.department_name) + ' • Sem ' + sub.semester;
        document.getElementById('evalAsgTitle').textContent = sub.assignment_title + ' (' + sub.subject_code + ')';
        document.getElementById('evalMaxMarksSpan').textContent = sub.max_marks;
        document.getElementById('evalMarks').max = sub.max_marks;
        document.getElementById('evalMarks').value = sub.marks_obtained !== null ? sub.marks_obtained : '';
        document.getElementById('evalFeedback').value = sub.feedback || '';
        document.getElementById('evalSubmittedAt').textContent = sub.submitted_at || '—';

        // File handling
        const fileWrap = document.getElementById('evalFileWrapper');
        const fileBtn = document.getElementById('evalFileOpenBtn');
        const fileName = document.getElementById('evalFileName');
        if (sub.file_path) {
            const cleanPath = sub.file_path.replace(/^\/+/, '');
            let url = '';
            if (cleanPath.startsWith('http://') || cleanPath.startsWith('https://')) {
                url = cleanPath;
            } else {
                url = '../../storage/' + cleanPath.replace(/^storage\//, '');
            }
            fileBtn.href = url;
            const parts = sub.file_path.split('/');
            fileName.textContent = parts[parts.length - 1];
            fileWrap.style.display = 'block';
        } else {
            fileWrap.style.display = 'none';
        }

        // Text handling
        const textWrap = document.getElementById('evalTextWrapper');
        const textContent = document.getElementById('evalTextContent');
        if (sub.submission_text && sub.submission_text.trim()) {
            textContent.textContent = sub.submission_text;
            textWrap.style.display = 'block';
        } else {
            textWrap.style.display = 'none';
        }

        openModal('evalModal');
    }

    function viewSolutionText(sub) {
        document.getElementById('textModalStudent').textContent = sub.student_name + ' (' + sub.roll_number + ')';
        document.getElementById('textModalAsg').textContent = sub.assignment_title + ' [' + sub.subject_code + ']';
        document.getElementById('textModalBody').textContent = sub.submission_text || 'No written response recorded.';
        openModal('textModal');
    }

    window.addEventListener('click', function(e) {
        ['evalModal', 'textModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && e.target === el) {
                closeModal(id);
            }
        });
    });

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ['evalModal', 'textModal'].forEach(id => {
                closeModal(id);
            });
        }
    });
</script>
</body>
</html>
