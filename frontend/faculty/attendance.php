<?php
// frontend/faculty/attendance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Fetch subjects for faculty
$facultySubjects = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, name, code, semester FROM subjects WHERE faculty_id = ? ORDER BY name ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $facultySubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($facultySubjects)) {
        $res = $db->query("SELECT id, name, code, semester FROM subjects ORDER BY name ASC LIMIT 10");
        if ($res) {
            $facultySubjects = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$selectedSubjectId = (int)($_POST['subject_id'] ?? $_GET['subject_id'] ?? ($facultySubjects[0]['id'] ?? 1));
$selectedDate = sanitize($_POST['date'] ?? $_GET['date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $records = $_POST['status'] ?? [];
    if ($db && $selectedSubjectId > 0 && !empty($records)) {
        $savedCount = 0;
        foreach ($records as $studentId => $status) {
            $studentId = (int)$studentId;
            $status = in_array(strtolower($status), ['present', 'absent', 'late']) ? strtolower($status) : 'present';
            
            $chk = $db->prepare("SELECT id FROM attendance WHERE subject_id = ? AND student_id = ? AND date = ?");
            if ($chk) {
                $chk->bind_param("iis", $selectedSubjectId, $studentId, $selectedDate);
                $chk->execute();
                $existing = $chk->get_result()->fetch_assoc();
                $chk->close();
                
                if ($existing) {
                    $up = $db->prepare("UPDATE attendance SET status = ?, faculty_id = ? WHERE id = ?");
                    if ($up) {
                        $up->bind_param("sii", $status, $userId, $existing['id']);
                        $up->execute();
                        $up->close();
                    }
                } else {
                    $ins = $db->prepare("INSERT INTO attendance (subject_id, student_id, faculty_id, date, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    if ($ins) {
                        $ins->bind_param("iiiss", $selectedSubjectId, $studentId, $userId, $selectedDate, $status);
                        $ins->execute();
                        $ins->close();
                    }
                }
                $savedCount++;
            }
        }
        $successMsg = "Attendance recorded successfully for {$savedCount} students on {$selectedDate}!";
    }
}

$students = [];
if ($db && $selectedSubjectId > 0) {
    $stmt = $db->prepare(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                COALESCE(att.status, 'present') AS attendance_status
         FROM student_subjects ss
         JOIN users u ON ss.student_id = u.id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN attendance att ON att.student_id = u.id AND att.subject_id = ? AND att.date = ?
         WHERE ss.subject_id = ? AND u.deleted_at IS NULL
         ORDER BY roll ASC, u.first_name ASC"
    );
    if ($stmt) {
        $stmt->bind_param("isi", $selectedSubjectId, $selectedDate, $selectedSubjectId);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    // If no students enrolled specifically in this subject, show enrolled students from the same course/department or all students
    if (empty($students)) {
        $stmt = $db->prepare(
            "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                    COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                    COALESCE(att.status, 'present') AS attendance_status
             FROM users u
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN attendance att ON att.student_id = u.id AND att.subject_id = ? AND att.date = ?
             WHERE u.role_id = 4 AND u.deleted_at IS NULL
             LIMIT 10"
        );
        if ($stmt) {
            $stmt->bind_param("is", $selectedSubjectId, $selectedDate);
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
    <title>Mark Attendance - StudentOS AI</title>
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
                        <h1>Class Attendance Register</h1>
                        <p class="page-subtitle">Mark daily attendance for enrolled lecture and laboratory sessions</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="attendance.php">
                    <input type="hidden" name="subject_id" value="<?php echo (int)$selectedSubjectId; ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="subject_id_select">Select Course</label>
                                    <select id="subject_id_select" class="form-control" onchange="window.location.href='attendance.php?subject_id='+this.value+'&date='+document.getElementById('date').value;">
                                        <?php if (empty($facultySubjects)): ?>
                                            <option value="">No subjects assigned</option>
                                        <?php else: ?>
                                            <?php foreach ($facultySubjects as $sub): ?>
                                                <option value="<?php echo (int)$sub['id']; ?>" <?php echo $sub['id'] == $selectedSubjectId ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sub['name'] . ' (' . ($sub['code'] ?? 'SUB' . $sub['id']) . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="date">Class Date</label>
                                    <input type="date" name="date_picker" id="date" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="window.location.href='attendance.php?subject_id='+document.getElementById('subject_id_select').value+'&date='+this.value;">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" style="height: 42px;" onclick="markAllPresent()">
                                        <i class="fas fa-check-double"></i> Mark All Present
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-clipboard-check"></i> Student Roster (<?php echo count($students); ?> students)</h3>
                            <button type="submit" name="save_attendance" value="1" class="btn btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>Student Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                    <i class="fas fa-info-circle"></i> No students found for this subject.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $stu): ?>
                                                <tr>
                                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                    <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                                    <td>
                                                        <div style="display: flex; gap: 16px;">
                                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                                <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="present" <?php echo ($stu['attendance_status'] === 'present') ? 'checked' : ''; ?> class="att-radio-present">
                                                                <span style="color: var(--success); font-weight: 500;">Present</span>
                                                            </label>
                                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                                <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="absent" <?php echo ($stu['attendance_status'] === 'absent') ? 'checked' : ''; ?> class="att-radio-absent">
                                                                <span style="color: var(--danger); font-weight: 500;">Absent</span>
                                                            </label>
                                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                                <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="late" <?php echo ($stu['attendance_status'] === 'late') ? 'checked' : ''; ?>>
                                                                <span style="color: var(--warning); font-weight: 500;">Late</span>
                                                            </label>
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
                </form>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function markAllPresent() {
        document.querySelectorAll('.att-radio-present').forEach(r => r.checked = true);
        showToast('All students marked present', 'info');
    }
    </script>
</body>
</html>
