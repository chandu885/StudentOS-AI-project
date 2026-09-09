<?php
// frontend/faculty/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Load faculty subjects for dropdown
$facultySubjects = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, name, code FROM subjects WHERE faculty_id = ? ORDER BY name ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $facultySubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($facultySubjects)) {
        $res = $db->query("SELECT id, name, code FROM subjects ORDER BY name ASC LIMIT 20");
        if ($res) {
            $facultySubjects = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? ($facultySubjects[0]['id'] ?? 1));
    $examDateRaw = sanitize($_POST['exam_date'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 90);
    $totalMarks = (int)($_POST['total_marks'] ?? 100);
    $examType = sanitize($_POST['exam_type'] ?? 'midterm');
    $roomNumber = sanitize($_POST['room_number'] ?? 'Hall A');
    $passingMarks = (int)($_POST['passing_marks'] ?? ($totalMarks * 0.4));

    if (!empty($title) && $subjectId > 0 && !empty($examDateRaw)) {
        $examTimestamp = strtotime($examDateRaw);
        $examDate = date('Y-m-d', $examTimestamp);
        $startTime = date('H:i:s', $examTimestamp);
        $endTime = date('H:i:s', strtotime("+{$duration} minutes", $examTimestamp));

        if ($db) {
            $stmt = $db->prepare(
                "INSERT INTO exams (subject_id, faculty_id, title, exam_type, exam_date, start_time, end_time, total_marks, passing_marks, room_number, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())"
            );
            if ($stmt) {
                $stmt->bind_param("iissssiiis", $subjectId, $userId, $title, $examType, $examDate, $startTime, $endTime, $totalMarks, $passingMarks, $roomNumber);
                if ($stmt->execute()) {
                    $successMsg = 'Examination scheduled and published successfully!';
                } else {
                    $errorMsg = 'Failed to schedule exam: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } else {
        $errorMsg = 'Please provide an exam title and date.';
    }
}

$exams = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT e.id, e.title, e.exam_type, e.exam_date, e.start_time, e.end_time, e.total_marks, e.passing_marks, e.room_number, e.status,
                s.name AS subject_name, s.code AS subject_code,
                TIMESTAMPDIFF(MINUTE, e.start_time, e.end_time) AS duration_minutes
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         WHERE e.faculty_id = ? OR e.subject_id IN (SELECT id FROM subjects WHERE faculty_id = ?)
         ORDER BY e.exam_date DESC, e.start_time DESC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($exams)) {
        $res = $db->query(
            "SELECT e.id, e.title, e.exam_type, e.exam_date, e.start_time, e.end_time, e.total_marks, e.passing_marks, e.room_number, e.status,
                    s.name AS subject_name, s.code AS subject_code,
                    TIMESTAMPDIFF(MINUTE, e.start_time, e.end_time) AS duration_minutes
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             ORDER BY e.exam_date DESC, e.start_time DESC
             LIMIT 20"
        );
        if ($res) {
            $exams = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - StudentOS AI</title>
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
                        <h1>Examination Management</h1>
                        <p class="page-subtitle">Schedule exams, define assessment criteria, and link question papers</p>
                    </div>
                    <div class="header-actions">
                        <a href="questions.php" class="btn btn-secondary"><i class="fas fa-database"></i> Question Bank</a>
                        <button class="btn btn-primary" onclick="openModal('createExamModal')">
                            <i class="fas fa-plus"></i> Schedule Exam
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
                        <h3><i class="fas fa-pencil-alt"></i> Scheduled Examinations (<?php echo count($exams); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Exam Title & Subject</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Total Marks</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exams)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-info-circle"></i> No exams scheduled yet. Click "Schedule Exam" above to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($exams as $ex): 
                                            $badgeClass = 'badge-primary';
                                            if ($ex['status'] === 'completed') $badgeClass = 'badge-success';
                                            elseif ($ex['status'] === 'in_progress') $badgeClass = 'badge-warning';
                                            elseif ($ex['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                            $examTime = !empty($ex['start_time']) ? ' ' . $ex['start_time'] : ' 09:00:00';
                                            $dur = (!empty($ex['duration_minutes']) && $ex['duration_minutes'] > 0) ? (int)$ex['duration_minutes'] : 90;
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($ex['title']); ?></strong>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                        <?php echo htmlspecialchars($ex['subject_name'] ?? ''); ?>
                                                        <?php if (!empty($ex['exam_type'])): ?>
                                                            &bull; <span style="text-transform: capitalize;"><?php echo htmlspecialchars($ex['exam_type']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 500;"><?php echo date('M d, Y h:i A', strtotime($ex['exam_date'] . $examTime)); ?></span>
                                                </td>
                                                <td><?php echo $dur; ?> mins</td>
                                                <td><strong><?php echo htmlspecialchars($ex['total_marks']); ?></strong> pts</td>
                                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($ex['status']); ?></span></td>
                                                <td>
                                                    <a href="marks.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                                                        <i class="fas fa-table"></i> Enter Marks
                                                    </a>
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

    <!-- Create Exam Modal -->
    <div class="modal-backdrop" id="createExamModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Schedule Examination</h3>
                <button class="modal-close" onclick="closeModal('createExamModal')">&times;</button>
            </div>
            <form method="POST" action="exams.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="examTitle">Exam Title</label>
                        <input type="text" name="title" id="examTitle" class="form-control" placeholder="e.g. Midterm Examination 2026" required>
                    </div>
                    <div class="form-group">
                        <label for="examSub">Course</label>
                        <select name="subject_id" id="examSub" class="form-control" required>
                            <?php foreach ($facultySubjects as $sub): ?>
                                <option value="<?php echo (int)$sub['id']; ?>">
                                    <?php echo htmlspecialchars($sub['name'] . ' (' . ($sub['code'] ?? 'SUB'.$sub['id']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="examDate">Exam Date & Time</label>
                            <input type="datetime-local" name="exam_date" id="examDate" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days 10:00')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="examDur">Duration (mins)</label>
                            <input type="number" name="duration_minutes" id="examDur" class="form-control" value="90" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="examTotal">Total Marks</label>
                            <input type="number" name="total_marks" id="examTotal" class="form-control" value="100" required>
                        </div>
                        <div class="form-group">
                            <label for="examRoom">Room / Hall</label>
                            <input type="text" name="room_number" id="examRoom" class="form-control" value="Room 301" placeholder="e.g. Room 301">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createExamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Schedule Exam</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
