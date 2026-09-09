<?php
// frontend/faculty/marks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Fetch exams list
$examsList = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT e.id, e.title, e.subject_id, e.total_marks, s.name AS subject_name, s.code AS subject_code
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         WHERE e.faculty_id = ? OR e.subject_id IN (SELECT id FROM subjects WHERE faculty_id = ?)
         ORDER BY e.exam_date DESC, e.title ASC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $examsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($examsList)) {
        $res = $db->query(
            "SELECT e.id, e.title, e.subject_id, e.total_marks, s.name AS subject_name, s.code AS subject_code
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             ORDER BY e.exam_date DESC, e.title ASC LIMIT 20"
        );
        if ($res) {
            $examsList = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$selectedExamId = (int)($_POST['exam_id'] ?? $_GET['exam_id'] ?? ($examsList[0]['id'] ?? 0));
$currentExam = null;
foreach ($examsList as $ex) {
    if ($ex['id'] == $selectedExamId) {
        $currentExam = $ex;
        break;
    }
}
if (!$currentExam && !empty($examsList)) {
    $currentExam = $examsList[0];
    $selectedExamId = (int)$currentExam['id'];
}
$examTotalMarks = (float)($currentExam['total_marks'] ?? 100);
$subjectId = (int)($currentExam['subject_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $submittedMarks = $_POST['marks'] ?? [];
    $savedCount = 0;
    if ($db && $selectedExamId > 0 && !empty($submittedMarks)) {
        foreach ($submittedMarks as $studentId => $mark) {
            if ($mark === '' || $mark === null) continue;
            $studentId = (int)$studentId;
            $markVal = max(0, min($examTotalMarks, (float)$mark));
            
            $pct = $examTotalMarks > 0 ? ($markVal / $examTotalMarks) * 100 : 0;
            if ($pct >= 90) $grade = 'A+';
            elseif ($pct >= 80) $grade = 'A';
            elseif ($pct >= 70) $grade = 'B+';
            elseif ($pct >= 60) $grade = 'B';
            elseif ($pct >= 50) $grade = 'C';
            elseif ($pct >= 40) $grade = 'D';
            else $grade = 'F';

            $remarks = "Grade {$grade} achieved with score " . round($pct, 1) . "%";

            $chk = $db->prepare("SELECT id FROM results WHERE exam_id = ? AND student_id = ?");
            if ($chk) {
                $chk->bind_param("ii", $selectedExamId, $studentId);
                $chk->execute();
                $existing = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($existing) {
                    $up = $db->prepare("UPDATE results SET marks_obtained = ?, total_marks = ?, grade = ?, remarks = ?, published_at = NOW() WHERE id = ?");
                    if ($up) {
                        $up->bind_param("ddssi", $markVal, $examTotalMarks, $grade, $remarks, $existing['id']);
                        $up->execute();
                        $up->close();
                    }
                } else {
                    $ins = $db->prepare("INSERT INTO results (exam_id, student_id, subject_id, marks_obtained, total_marks, grade, remarks, published_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    if ($ins) {
                        $ins->bind_param("iiiddss", $selectedExamId, $studentId, $subjectId, $markVal, $examTotalMarks, $grade, $remarks);
                        $ins->execute();
                        $ins->close();
                    }
                }
                $savedCount++;
            }
        }
        $successMsg = "Exam marks and grading records successfully saved for {$savedCount} students!";
    }
}

$students = [];
if ($db && $selectedExamId > 0) {
    if ($subjectId > 0) {
        $stmt = $db->prepare(
            "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                    COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                    r.marks_obtained, r.grade
             FROM student_subjects ss
             JOIN users u ON ss.student_id = u.id
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN results r ON r.student_id = u.id AND r.exam_id = ?
             WHERE ss.subject_id = ? AND u.deleted_at IS NULL
             ORDER BY roll ASC, u.first_name ASC"
        );
        if ($stmt) {
            $stmt->bind_param("ii", $selectedExamId, $subjectId);
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    if (empty($students)) {
        $stmt = $db->prepare(
            "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                    COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                    r.marks_obtained, r.grade
             FROM users u
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN results r ON r.student_id = u.id AND r.exam_id = ?
             WHERE u.role_id = 4 AND u.deleted_at IS NULL
             ORDER BY u.first_name ASC
             LIMIT 15"
        );
        if ($stmt) {
            $stmt->bind_param("i", $selectedExamId);
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook & Marks Entry - StudentOS AI</title>
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
                        <h1>Marks Entry & Gradebook</h1>
                        <p class="page-subtitle">Record and publish marks for continuous internal assessments and examinations</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="marks.php">
                    <input type="hidden" name="exam_id" value="<?php echo (int)$selectedExamId; ?>">
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="examSelect">Assessment / Exam</label>
                                    <select id="examSelect" class="form-control" onchange="window.location.href='marks.php?exam_id='+this.value;">
                                        <?php if (empty($examsList)): ?>
                                            <option value="">No examinations found</option>
                                        <?php else: ?>
                                            <?php foreach ($examsList as $ex): ?>
                                                <option value="<?php echo (int)$ex['id']; ?>" <?php echo $ex['id'] == $selectedExamId ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ex['title'] . ' - ' . ($ex['subject_name'] ?? 'Subject') . ' (Max: ' . $ex['total_marks'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Total Marks / Scale</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(($currentExam['subject_name'] ?? 'General') . ' | Max: ' . $examTotalMarks . ' pts'); ?>" disabled style="opacity: 0.85;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calculator"></i> Student Scores Sheet (<?php echo count($students); ?> students)</h3>
                            <button type="submit" name="save_marks" value="1" class="btn btn-primary"><i class="fas fa-save"></i> Save All Marks</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>Student Name</th>
                                            <th>Marks Obtained (out of <?php echo (int)$examTotalMarks; ?>)</th>
                                            <th>Grade Equivalent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                    <i class="fas fa-info-circle"></i> No enrolled students found for this examination.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $stu): 
                                                $g = $stu['grade'] ?? '';
                                                $badgeClass = 'badge-secondary';
                                                if (in_array($g, ['A+', 'A'])) $badgeClass = 'badge-success';
                                                elseif (in_array($g, ['B+', 'B'])) $badgeClass = 'badge-primary';
                                                elseif (in_array($g, ['C', 'D'])) $badgeClass = 'badge-warning';
                                                elseif ($g === 'F') $badgeClass = 'badge-danger';
                                            ?>
                                                <tr>
                                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                    <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                                    <td style="width: 220px;">
                                                        <input type="number" step="0.5" name="marks[<?php echo $stu['id']; ?>]" class="form-control" value="<?php echo isset($stu['marks_obtained']) && $stu['marks_obtained'] !== null ? htmlspecialchars($stu['marks_obtained']) : ''; ?>" min="0" max="<?php echo (int)$examTotalMarks; ?>" placeholder="0.0" style="width: 120px; height: 36px;">
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo htmlspecialchars($g ?: 'Pending'); ?>
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
                </form>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
