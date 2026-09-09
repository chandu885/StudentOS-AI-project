<?php
// frontend/admin/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $db) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="examination_datesheet_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Exam Title', 'Subject Code', 'Subject Name', 'Department', 'Exam Type', 'Date', 'Start Time', 'End Time', 'Total Marks', 'Passing Marks', 'Room', 'Status']);
    
    $q = "SELECT e.*, s.code AS subject_code, s.name AS subject_name, COALESCE(d.name, 'General') AS department_name
          FROM exams e
          LEFT JOIN subjects s ON e.subject_id = s.id
          LEFT JOIN courses c ON s.course_id = c.id
          LEFT JOIN departments d ON c.department_id = d.id
          ORDER BY e.exam_date ASC, e.start_time ASC";
    $expRes = $db->query($q);
    if ($expRes) {
        while ($row = $expRes->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['subject_code'],
                $row['subject_name'],
                $row['department_name'],
                ucfirst($row['exam_type']),
                $row['exam_date'],
                $row['start_time'],
                $row['end_time'],
                $row['total_marks'],
                $row['passing_marks'],
                $row['room_number'] ?? 'TBD',
                ucfirst(str_replace('_', ' ', $row['status']))
            ]);
        }
    }
    fclose($output);
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['exam_id'] ?? 0);
        if ($delId > 0 && $db) {
            $del = $db->prepare("DELETE FROM exams WHERE id = ?");
            $del->bind_param("i", $delId);
            if ($del->execute()) {
                $successMsg = 'Examination schedule removed successfully.';
            } else {
                $errorMsg = 'Failed to delete examination: ' . $db->error;
            }
            $del->close();
        }
    } elseif (isset($_POST['title'], $_POST['subject_id'])) {
        $title = sanitize($_POST['title'] ?? '');
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $examType = sanitize($_POST['exam_type'] ?? 'midterm');
        $examDate = sanitize($_POST['exam_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '09:30:00');
        $endTime = sanitize($_POST['end_time'] ?? '12:30:00');
        $totalMarks = max(1, (int)($_POST['total_marks'] ?? 100));
        $passingMarks = max(1, (int)($_POST['passing_marks'] ?? 40));
        $roomNumber = sanitize($_POST['room_number'] ?? '');
        $status = sanitize($_POST['status'] ?? 'scheduled');

        // Fallback faculty if not provided
        if ($facultyId <= 0 && $db) {
            $facRow = $db->query("SELECT id FROM users WHERE role_id = 3 AND deleted_at IS NULL LIMIT 1")->fetch_assoc();
            if ($facRow) {
                $facultyId = (int)$facRow['id'];
            }
        }

        if (empty($title) || $subjectId <= 0 || empty($examDate)) {
            $errorMsg = 'Exam Title, Subject, and Exam Date are required.';
        } elseif ($db) {
            $stmt = $db->prepare("INSERT INTO exams (subject_id, faculty_id, title, exam_type, exam_date, start_time, end_time, total_marks, passing_marks, room_number, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param("iisssssiiss", $subjectId, $facultyId, $title, $examType, $examDate, $startTime, $endTime, $totalMarks, $passingMarks, $roomNumber, $status);
                if ($stmt->execute()) {
                    $successMsg = "Examination '$title' scheduled successfully!";
                } else {
                    $errorMsg = 'Failed to schedule exam: ' . $db->error;
                }
                $stmt->close();
            }
        }
    }
}

// Fetch subjects and faculty for modal dropdowns
$subjects = [];
$facultyList = [];
if ($db) {
    $sRes = $db->query("SELECT id, code, name FROM subjects ORDER BY code ASC");
    if ($sRes) $subjects = $sRes->fetch_all(MYSQLI_ASSOC);

    $fRes = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role_id = 3 AND deleted_at IS NULL ORDER BY first_name ASC");
    if ($fRes) $facultyList = $fRes->fetch_all(MYSQLI_ASSOC);
}

// Fetch live exams
$exams = [];
if ($db) {
    $q = "SELECT e.*, 
                 s.name AS subject_name, s.code AS subject_code,
                 COALESCE(d.name, 'General') AS department_name,
                 CONCAT(u.first_name, ' ', u.last_name) AS faculty_name
          FROM exams e
          LEFT JOIN subjects s ON e.subject_id = s.id
          LEFT JOIN courses c ON s.course_id = c.id
          LEFT JOIN departments d ON c.department_id = d.id
          LEFT JOIN users u ON e.faculty_id = u.id
          ORDER BY e.exam_date DESC, e.start_time ASC";
    $eRes = $db->query($q);
    if ($eRes) {
        $exams = $eRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Schedules - StudentOS AI</title>
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
                        <h1>Institutional Examination Cell</h1>
                        <p class="page-subtitle">Examination timetables, exam centers, and invigilation coordination</p>
                    </div>
                    <div class="header-actions">
                        <a href="exams.php?export=csv" class="btn btn-secondary">
                            <i class="fas fa-file-csv"></i> Export Date Sheet
                        </a>
                        <button class="btn btn-primary" onclick="openModal('addExamModal')">
                            <i class="fas fa-plus"></i> Schedule Examination
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-file-signature"></i> Examination Date Sheets (<?php echo count($exams); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Examination Scheme</th>
                                        <th>Subject</th>
                                        <th>Department</th>
                                        <th>Date & Time</th>
                                        <th>Venue / Hall</th>
                                        <th>Marks</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exams)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No examinations scheduled. Click "Schedule Examination" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($exams as $ex): 
                                            $st = $ex['status'] ?? 'scheduled';
                                            $badgeClass = 'badge-secondary';
                                            if ($st === 'completed') $badgeClass = 'badge-success';
                                            elseif ($st === 'in_progress') $badgeClass = 'badge-warning';
                                            elseif ($st === 'scheduled') $badgeClass = 'badge-primary';
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ex['title']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo ucfirst(htmlspecialchars($ex['exam_type'])); ?></div>
                                                </td>
                                                <td>
                                                    <div><strong><?php echo htmlspecialchars($ex['subject_code'] ?? ''); ?></strong></div>
                                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($ex['subject_name'] ?? ''); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($ex['department_name']); ?></td>
                                                <td>
                                                    <div><i class="fas fa-calendar" style="color: var(--primary);"></i> <?php echo date('M d, Y', strtotime($ex['exam_date'])); ?></div>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo date('h:i A', strtotime($ex['start_time'])); ?> - <?php echo date('h:i A', strtotime($ex['end_time'])); ?></div>
                                                </td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars(!empty($ex['room_number']) ? $ex['room_number'] : 'TBD'); ?></span></td>
                                                <td><?php echo (int)$ex['passing_marks']; ?> / <?php echo (int)$ex['total_marks']; ?></td>
                                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $st)); ?></span></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this exam session?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="exam_id" value="<?php echo (int)$ex['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete examination">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
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

    <!-- Schedule Exam Modal -->
    <div class="modal-backdrop" id="addExamModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Schedule Examination</h3>
                <button class="modal-close" onclick="closeModal('addExamModal')">&times;</button>
            </div>
            <form method="POST" action="exams.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="exTitle">Examination Title *</label>
                        <input type="text" name="title" id="exTitle" class="form-control" placeholder="e.g. Midterm Assessment 2026" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="exSubj">Subject *</label>
                            <select name="subject_id" id="exSubj" class="form-control" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>">
                                        <?php echo htmlspecialchars($s['code']); ?> - <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="exType">Exam Type</label>
                            <select name="exam_type" id="exType" class="form-control">
                                <option value="midterm">Midterm Examination</option>
                                <option value="final">Final / End-Semester</option>
                                <option value="quiz">Class Quiz / Test</option>
                                <option value="assignment_test">Assignment Test</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="exDate">Exam Date *</label>
                            <input type="date" name="exam_date" id="exDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="exRoom">Room / Exam Hall</label>
                            <input type="text" name="room_number" id="exRoom" class="form-control" placeholder="e.g. Hall 101, Lab 3">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="exStart">Start Time</label>
                            <input type="time" name="start_time" id="exStart" class="form-control" value="09:30">
                        </div>
                        <div class="form-group">
                            <label for="exEnd">End Time</label>
                            <input type="time" name="end_time" id="exEnd" class="form-control" value="12:30">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="exTot">Total Marks</label>
                            <input type="number" name="total_marks" id="exTot" class="form-control" value="100">
                        </div>
                        <div class="form-group">
                            <label for="exPass">Passing Marks</label>
                            <input type="number" name="passing_marks" id="exPass" class="form-control" value="40">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="exFaculty">Assigned Invigilator / Faculty</label>
                        <select name="faculty_id" id="exFaculty" class="form-control">
                            <option value="">Select Faculty Invigilator</option>
                            <?php foreach ($facultyList as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>">
                                    <?php echo htmlspecialchars($f['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addExamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Schedule Exam</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
