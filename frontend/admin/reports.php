<?php
// frontend/admin/reports.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

// Real File Exports
if (isset($_GET['export']) && $db) {
    $exportType = $_GET['export'];

    if ($exportType === 'attendance') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="institutional_attendance_report_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Record ID', 'Roll Number', 'Student Name', 'Semester', 'Subject Code', 'Subject Name', 'Date', 'Status', 'Remarks']);

        $q = "SELECT att.id, COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                     CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                     COALESCE(sp.semester, '1') AS semester,
                     s.code AS subject_code, s.name AS subject_name,
                     att.date, att.status, att.remarks
              FROM attendance att
              JOIN users u ON att.student_id = u.id
              LEFT JOIN student_profiles sp ON sp.user_id = u.id
              JOIN subjects s ON att.subject_id = s.id
              ORDER BY att.date DESC, student_name ASC";
        $res = $db->query($q);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll'],
                    $row['student_name'],
                    $row['semester'],
                    $row['subject_code'],
                    $row['subject_name'],
                    $row['date'],
                    ucfirst($row['status']),
                    $row['remarks'] ?? ''
                ]);
            }
        }
        fclose($out);
        exit;
    } elseif ($exportType === 'grades') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="semester_grades_distribution_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Result ID', 'Roll Number', 'Student Name', 'Semester', 'Exam Title', 'Subject Code', 'Subject Name', 'Marks Obtained', 'Total Marks', 'Grade', 'Remarks']);

        $q = "SELECT r.id, COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                     CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                     COALESCE(sp.semester, '1') AS semester,
                     COALESCE(e.title, 'Examination') AS exam_title,
                     s.code AS subject_code, s.name AS subject_name,
                     r.marks_obtained, r.total_marks, r.grade, r.remarks
              FROM results r
              JOIN users u ON r.student_id = u.id
              LEFT JOIN student_profiles sp ON sp.user_id = u.id
              LEFT JOIN exams e ON r.exam_id = e.id
              JOIN subjects s ON r.subject_id = s.id
              ORDER BY r.id DESC";
        $res = $db->query($q);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll'],
                    $row['student_name'],
                    $row['semester'],
                    $row['exam_title'],
                    $row['subject_code'],
                    $row['subject_name'],
                    $row['marks_obtained'],
                    $row['total_marks'],
                    $row['grade'],
                    $row['remarks'] ?? ''
                ]);
            }
        }
        fclose($out);
        exit;
    } elseif ($exportType === 'students') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_master_enrollment_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['User ID', 'Student Roll', 'First Name', 'Last Name', 'Email', 'Semester', 'Status', 'Registered Date']);

        $q = "SELECT u.id, COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                     u.first_name, u.last_name, u.email,
                     COALESCE(sp.semester, '1') AS semester,
                     IF(u.is_active = 1, 'Active', 'Inactive') AS status, u.created_at
              FROM users u
              LEFT JOIN student_profiles sp ON sp.user_id = u.id
              WHERE u.role_id = 4 AND u.deleted_at IS NULL
              ORDER BY u.id ASC";
        $res = $db->query($q);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['semester'],
                    ucfirst($row['status']),
                    $row['created_at']
                ]);
            }
        }
        fclose($out);
        exit;
    } elseif ($exportType === 'faculty') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="faculty_staff_roster_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['User ID', 'Employee ID', 'Name', 'Email', 'Department', 'Designation', 'Office Room', 'Status']);

        $q = "SELECT u.id, fp.employee_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                     u.email, COALESCE(d.name, 'General') AS dept_name,
                     fp.designation, fp.office_location, IF(u.is_active = 1, 'Active', 'Inactive') AS status
              FROM users u
              LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
              LEFT JOIN departments d ON fp.department_id = d.id
              WHERE u.role_id = 3 AND u.deleted_at IS NULL
              ORDER BY u.id ASC";
        $res = $db->query($q);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['employee_id'] ?? ('FAC-' . $row['id']),
                    $row['full_name'],
                    $row['email'],
                    $row['phone'] ?? '',
                    $row['dept_name'],
                    $row['designation'] ?? 'Professor',
                    $row['office_location'] ?? 'Main Block',
                    ucfirst($row['status'])
                ]);
            }
        }
        fclose($out);
        exit;
    }
}

// Live Summary Metrics
$totalStudents = 0;
$totalFaculty = 0;
$totalResults = 0;
$attendancePct = 85.0;

if ($db) {
    $r1 = $db->query("SELECT COUNT(*) AS c FROM users WHERE role_id = 4 AND deleted_at IS NULL");
    if ($r1) $totalStudents = (int)$r1->fetch_assoc()['c'];

    $r2 = $db->query("SELECT COUNT(*) AS c FROM users WHERE role_id = 3 AND deleted_at IS NULL");
    if ($r2) $totalFaculty = (int)$r2->fetch_assoc()['c'];

    $r3 = $db->query("SELECT COUNT(*) AS c FROM results");
    if ($r3) $totalResults = (int)$r3->fetch_assoc()['c'];

    $r4 = $db->query("SELECT (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) AS avg_att FROM attendance");
    if ($r4) {
        $val = $r4->fetch_assoc()['avg_att'];
        if ($val !== null) $attendancePct = round((float)$val, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Reports - StudentOS AI</title>
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
                        <h1>Institutional Reports & Export</h1>
                        <p class="page-subtitle">Generate live official transcripts, attendance registers, and enrollment analytics</p>
                    </div>
                </div>

                <div class="stats-grid" style="margin-bottom: 24px;">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalStudents; ?></span>
                            <span class="stat-label">Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent);"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalFaculty; ?></span>
                            <span class="stat-label">Active Faculty Members</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $attendancePct; ?>%</span>
                            <span class="stat-label">Campus Attendance Avg</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-award"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalResults; ?></span>
                            <span class="stat-label">Evaluated Records</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="margin-bottom: 16px;"><i class="fas fa-clipboard-user"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Department Attendance Report</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Comprehensive lecture-by-lecture attendance registers with eligibility audit.</p>
                            <a href="reports.php?export=attendance" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1); margin-bottom: 16px;"><i class="fas fa-chart-bar"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Semester Grade Distribution</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Semester results ledger with marks breakdown, grades, and remarks.</p>
                            <a href="reports.php?export=grades" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1); margin-bottom: 16px;"><i class="fas fa-id-card"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Student Master Enrollment</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Complete directory of all registered students, contact info, and department tags.</p>
                            <a href="reports.php?export=students" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--warning); background: rgba(234, 179, 8, 0.1); margin-bottom: 16px;"><i class="fas fa-user-tie"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Faculty & Staff Roster</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Full list of teaching staff, designations, department assignments, and room locations.</p>
                            <a href="reports.php?export=faculty" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
