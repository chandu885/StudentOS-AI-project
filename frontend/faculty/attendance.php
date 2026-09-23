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

// Fetch all active departments
$departments = [];
if ($db) {
    $deptRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($deptRes) {
        $departments = $deptRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch all active subjects with department information
$allSubjects = [];
if ($db) {
    $subRes = $db->query(
        "SELECT s.id, s.name, s.code, s.semester, s.department_id, s.faculty_id,
                d.name AS department_name, d.code AS department_code
         FROM subjects s
         JOIN departments d ON s.department_id = d.id
         WHERE s.status = 'active'
         ORDER BY s.semester ASC, s.name ASC"
    );
    if ($subRes) {
        $allSubjects = $subRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Extract parameters from GET or POST (do not pre-select automatically)
$selectedDeptId = isset($_REQUEST['department_id']) && $_REQUEST['department_id'] !== '' ? (int)$_REQUEST['department_id'] : 0;
$selectedSemester = isset($_REQUEST['semester']) && $_REQUEST['semester'] !== '' ? trim((string)$_REQUEST['semester']) : '';
$selectedSubjectId = isset($_REQUEST['subject_id']) && $_REQUEST['subject_id'] !== '' ? (int)$_REQUEST['subject_id'] : 0;
$selectedDate = sanitize($_REQUEST['date'] ?? $_REQUEST['date_picker'] ?? date('Y-m-d'));

// Filter subjects available for the selected department & semester
$matchingSubjects = [];
if ($selectedDeptId > 0 && !empty($selectedSemester)) {
    foreach ($allSubjects as $s) {
        if ((int)$s['department_id'] === $selectedDeptId && (string)$s['semester'] === (string)$selectedSemester) {
            $matchingSubjects[] = $s;
        }
    }
}

// Ensure selectedSubjectId is valid for current dept & sem
if ($selectedSubjectId > 0 && !in_array($selectedSubjectId, array_column($matchingSubjects, 'id'))) {
    $selectedSubjectId = 0;
}

// Check if all three criteria (Department, Semester, Subject) are selected
$isSelectionComplete = ($selectedDeptId > 0 && !empty($selectedSemester) && $selectedSubjectId > 0);

// Find current selected subject details
$currentSubject = null;
if ($selectedSubjectId > 0) {
    foreach ($allSubjects as $s) {
        if ((int)$s['id'] === $selectedSubjectId) {
            $currentSubject = $s;
            break;
        }
    }
}

// Handle POST: Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $records = $_POST['status'] ?? [];
    $remarksList = $_POST['remarks'] ?? [];

    if ($db && $isSelectionComplete && !empty($records)) {
        $savedCount = 0;
        foreach ($records as $studentId => $status) {
            $studentId = (int)$studentId;
            $status = in_array(strtolower($status), ['present', 'absent', 'late', 'excused']) ? strtolower($status) : 'present';
            $remark = !empty($remarksList[$studentId]) ? sanitize($remarksList[$studentId]) : null;

            $stmt = $db->prepare(
                "INSERT INTO attendance (subject_id, student_id, faculty_id, date, status, remarks, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), faculty_id = VALUES(faculty_id)"
            );
            if ($stmt) {
                $stmt->bind_param("iiisss", $selectedSubjectId, $studentId, $userId, $selectedDate, $status, $remark);
                if ($stmt->execute()) {
                    $savedCount++;
                }
                $stmt->close();
            }
        }
        $formattedDate = date('M d, Y', strtotime($selectedDate));
        $successMsg = "Attendance recorded successfully for {$savedCount} students on {$formattedDate}!";
    } elseif (!$isSelectionComplete) {
        $errorMsg = "Please ensure Department, Semester, and Subject are all selected before saving attendance.";
    } elseif (empty($records)) {
        $errorMsg = "No student attendance records were marked.";
    }
}

// Query students belonging to that specific Department and Semester
$students = [];
if ($db && $isSelectionComplete) {
    $cleanSem = preg_replace('/[^0-9]/', '', (string)$selectedSemester);

    $selectedDeptName = '';
    $selectedDeptCode = '';
    foreach ($departments as $d) {
        if ((int)$d['id'] === $selectedDeptId) {
            $selectedDeptName = $d['name'];
            $selectedDeptCode = $d['code'];
            break;
        }
    }

    $stmt = $db->prepare(
        "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email,
                COALESCE(sp.roll_number, sp.student_id, CONCAT('STU-', u.id)) AS roll,
                sp.semester, 
                COALESCE(d.name, sp.department, 'General') AS department_name,
                COALESCE(d.code, sp.department, 'GEN') AS department_code,
                att.status AS attendance_status,
                att.remarks
         FROM users u
         JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN departments d ON sp.department_id = d.id
         LEFT JOIN attendance att ON att.student_id = u.id AND att.subject_id = ? AND att.date = ?
         WHERE u.role_id = 4 
           AND u.deleted_at IS NULL
           AND (
               sp.department_id = ? 
               OR sp.department = ? 
               OR sp.department = ?
           )
           AND (
               sp.semester = ? 
               OR sp.semester = ?
           )
         ORDER BY sp.roll_number ASC, u.first_name ASC"
    );

    if ($stmt) {
        $stmt->bind_param(
            "isissss",
            $selectedSubjectId,
            $selectedDate,
            $selectedDeptId,
            $selectedDeptName,
            $selectedDeptCode,
            $selectedSemester,
            $cleanSem
        );
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Compute live counts
$totalStudents = count($students);
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
foreach ($students as $stu) {
    $st = strtolower($stu['attendance_status'] ?? 'present');
    if ($st === 'present') $presentCount++;
    elseif ($st === 'absent') $absentCount++;
    elseif ($st === 'late') $lateCount++;
}

$pageTitle = 'Class Attendance Register - Faculty - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div>
        <h1><i class="fas fa-clipboard-check" style="color: var(--primary); margin-right: 8px;"></i> Class Attendance Register</h1>
        <p class="page-subtitle">Select Department, Semester, and Subject to display students belonging to that specific class and record attendance</p>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
        <span><?php echo htmlspecialchars($successMsg); ?></span>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
        <span><?php echo htmlspecialchars($errorMsg); ?></span>
    </div>
<?php endif; ?>

<!-- Class Selection Card: Department, Semester, Subject, Date -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; letter-spacing: 0.5px;">
        <i class="fas fa-sliders-h" style="margin-right: 6px;"></i> Class &amp; Subject Selection
    </div>
    <form id="attendanceFilterForm" method="GET" action="attendance.php">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
            <!-- Department Selection -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="att_department" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    Department <span style="color: var(--danger);">*</span>
                </label>
                <select name="department_id" id="att_department" class="form-control" onchange="onDepartmentOrSemesterChange()" required style="height: 40px; font-size: 13px;">
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int)$dept['id']; ?>" <?php echo ((int)$selectedDeptId === (int)$dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Semester Selection -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="att_semester" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    Semester <span style="color: var(--danger);">*</span>
                </label>
                <select name="semester" id="att_semester" class="form-control" onchange="onDepartmentOrSemesterChange()" required style="height: 40px; font-size: 13px;">
                    <option value="">-- Select Semester --</option>
                    <?php for ($s = 1; $s <= 8; $s++): ?>
                        <option value="<?php echo $s; ?>" <?php echo ((string)$selectedSemester === (string)$s) ? 'selected' : ''; ?>>
                            Semester <?php echo $s; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Subject Selection -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="att_subject" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    Course / Subject <span style="color: var(--danger);">*</span>
                </label>
                <select name="subject_id" id="att_subject" class="form-control" onchange="onSubjectSelect(this)" required style="height: 40px; font-size: 13px;" <?php echo (empty($selectedDeptId) || empty($selectedSemester)) ? 'disabled' : ''; ?>>
                    <?php if (empty($selectedDeptId)): ?>
                        <option value="">-- Select Department first --</option>
                    <?php elseif (empty($selectedSemester)): ?>
                        <option value="">-- Select Semester first --</option>
                    <?php elseif (empty($matchingSubjects)): ?>
                        <option value="">-- No subjects found in this Dept &amp; Sem --</option>
                    <?php else: ?>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($matchingSubjects as $sub): ?>
                            <option value="<?php echo (int)$sub['id']; ?>" <?php echo ((int)$selectedSubjectId === (int)$sub['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($sub['code'] ? $sub['code'] . ' - ' : '') . $sub['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Class Date -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="att_date" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    Class Date <span style="color: var(--danger);">*</span>
                </label>
                <input type="date" name="date" id="att_date" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="onDateChange()" style="height: 40px; font-size: 13px;" required>
            </div>

            <!-- Load Button -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 40px; display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; white-space: nowrap;">
                    <i class="fas fa-sync-alt"></i> Load Roster
                </button>
                <a href="attendance.php" class="btn btn-secondary" style="height: 40px; display: inline-flex; align-items: center; justify-content: center; padding: 0 12px;" title="Reset selection">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </div>
    </form>

    <?php if ($isSelectionComplete && $currentSubject): ?>
        <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.08)); display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 12.5px;">
                <span class="badge badge-info" style="font-size: 12px; padding: 4px 10px;">
                    <i class="fas fa-building" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($currentSubject['department_name'] ?? 'Department'); ?>
                </span>
                <span class="badge badge-primary" style="font-size: 12px; padding: 4px 10px;">
                    <i class="fas fa-layer-group" style="margin-right: 4px;"></i> Semester <?php echo htmlspecialchars($selectedSemester); ?>
                </span>
                <span class="badge badge-secondary" style="font-size: 12px; padding: 4px 10px; font-weight: 700;">
                    <i class="fas fa-book" style="margin-right: 4px;"></i> <?php echo htmlspecialchars(($currentSubject['code'] ? $currentSubject['code'] . ' - ' : '') . $currentSubject['name']); ?>
                </span>
                <span class="badge badge-outline" style="font-size: 12px; padding: 4px 10px;">
                    <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> <?php echo date('M d, Y', strtotime($selectedDate)); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Attendance Form & Student Roster -->
<?php if (!$isSelectionComplete): ?>
    <!-- Informative Selection Prompt when not all criteria are selected -->
    <div class="card" style="padding: 48px 24px; text-align: center; color: var(--text-muted);">
        <i class="fas fa-user-check" style="font-size: 44px; margin-bottom: 16px; opacity: 0.35; color: var(--primary);"></i>
        <h3 style="color: var(--text-primary); margin-bottom: 8px;">Select Department, Semester &amp; Subject</h3>
        <p style="font-size: 13.5px; margin: 0 auto 20px; max-width: 560px; line-height: 1.6;">
            Once you choose the <strong>Department</strong>, <strong>Semester</strong>, and <strong>Course/Subject</strong> above, the system will display the students belonging specifically to that class so you can record attendance.
        </p>
        <div style="display: inline-flex; flex-wrap: wrap; gap: 12px; justify-content: center; font-size: 12px;">
            <span class="badge <?php echo $selectedDeptId > 0 ? 'badge-success' : 'badge-secondary'; ?>" style="padding: 6px 12px;">
                <i class="fas <?php echo $selectedDeptId > 0 ? 'fa-check' : 'fa-circle'; ?>" style="margin-right: 4px;"></i> 1. Department <?php echo $selectedDeptId > 0 ? 'Selected' : 'Required'; ?>
            </span>
            <span class="badge <?php echo !empty($selectedSemester) ? 'badge-success' : 'badge-secondary'; ?>" style="padding: 6px 12px;">
                <i class="fas <?php echo !empty($selectedSemester) ? 'fa-check' : 'fa-circle'; ?>" style="margin-right: 4px;"></i> 2. Semester <?php echo !empty($selectedSemester) ? 'Selected' : 'Required'; ?>
            </span>
            <span class="badge <?php echo $selectedSubjectId > 0 ? 'badge-success' : 'badge-secondary'; ?>" style="padding: 6px 12px;">
                <i class="fas <?php echo $selectedSubjectId > 0 ? 'fa-check' : 'fa-circle'; ?>" style="margin-right: 4px;"></i> 3. Subject <?php echo $selectedSubjectId > 0 ? 'Selected' : 'Required'; ?>
            </span>
        </div>
    </div>
<?php elseif (empty($matchingSubjects)): ?>
    <!-- Department and Semester selected but no subjects exist -->
    <div class="card" style="padding: 40px 24px; text-align: center;">
        <i class="fas fa-exclamation-triangle" style="font-size: 38px; color: var(--warning); margin-bottom: 14px;"></i>
        <h3 style="color: var(--text-primary); margin-bottom: 6px;">No Subjects Found</h3>
        <p style="font-size: 13.5px; color: var(--text-muted); margin: 0 auto; max-width: 540px;">
            No active course subjects are registered for the selected Department and Semester. Please select another semester or configure the subject in curriculum management first.
        </p>
    </div>
<?php else: ?>
    <!-- Live Attendance Overview Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Enrolled Students</div>
                <div style="font-size: 20px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalStudents; ?></div>
            </div>
        </div>

        <div class="card" style="padding: 14px 18px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.12); color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Marked Present</div>
                <div id="statPresentCount" style="font-size: 20px; font-weight: 800; color: var(--success); line-height: 1.2;"><?php echo $presentCount; ?></div>
            </div>
        </div>

        <div class="card" style="padding: 14px 18px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-times"></i>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Marked Absent</div>
                <div id="statAbsentCount" style="font-size: 20px; font-weight: 800; color: var(--danger); line-height: 1.2;"><?php echo $absentCount; ?></div>
            </div>
        </div>

        <div class="card" style="padding: 14px 18px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: var(--warning); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Marked Late</div>
                <div id="statLateCount" style="font-size: 20px; font-weight: 800; color: var(--warning); line-height: 1.2;"><?php echo $lateCount; ?></div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form method="POST" action="attendance.php" id="markAttendanceForm">
        <input type="hidden" name="department_id" value="<?php echo (int)$selectedDeptId; ?>">
        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>">
        <input type="hidden" name="subject_id" value="<?php echo (int)$selectedSubjectId; ?>">
        <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
        <input type="hidden" name="save_attendance" value="1">

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; padding: 16px 20px;">
                <div>
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-users"></i> Students in <?php echo htmlspecialchars($currentSubject['department_code'] ?? 'Cohort'); ?> - Semester <?php echo htmlspecialchars($selectedSemester); ?> (<?php echo $totalStudents; ?>)
                    </h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Subject: <strong><?php echo htmlspecialchars(($currentSubject['code'] ? $currentSubject['code'] . ' - ' : '') . $currentSubject['name']); ?></strong> &bull; Date: <strong><?php echo date('M d, Y', strtotime($selectedDate)); ?></strong>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('present')" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-check-double" style="color: var(--success);"></i> Mark All Present
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('absent')" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user-times" style="color: var(--danger);"></i> Mark All Absent
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px;">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Roll Number</th>
                                <th style="min-width: 200px;">Student Name</th>
                                <th style="width: 160px;">Department &amp; Sem</th>
                                <th style="min-width: 250px;">Attendance Status</th>
                                <th style="min-width: 220px;">Remarks / Notes (Optional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--text-muted);">
                                        <i class="fas fa-user-slash" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                                        No registered students found belonging to <strong><?php echo htmlspecialchars($selectedDeptName ?: 'selected department'); ?></strong> in <strong>Semester <?php echo htmlspecialchars($selectedSemester); ?></strong>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $stu): 
                                    $currStatus = !empty($stu['attendance_status']) ? strtolower($stu['attendance_status']) : 'present';
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-secondary" style="font-family: monospace; font-size: 12px; font-weight: 700;">
                                                <?php echo htmlspecialchars($stu['roll']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(79, 70, 229, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0;">
                                                    <?php 
                                                    $in = '';
                                                    $words = explode(' ', trim($stu['name']));
                                                    foreach (array_slice($words, 0, 2) as $w) {
                                                        $in .= strtoupper(substr($w, 0, 1));
                                                    }
                                                    echo htmlspecialchars($in ?: 'ST');
                                                    ?>
                                                </div>
                                                <div>
                                                    <strong style="color: var(--text-primary); font-size: 13.5px;"><?php echo htmlspecialchars($stu['name']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($stu['email'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline" style="font-size: 11px;">
                                                <?php echo htmlspecialchars($stu['department_code'] ?? 'GEN'); ?> &bull; Sem <?php echo htmlspecialchars($stu['semester'] ?? $selectedSemester); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 18px; align-items: center;">
                                                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0;">
                                                    <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="present" <?php echo ($currStatus === 'present') ? 'checked' : ''; ?> class="att-radio att-radio-present" onchange="updateLiveCounters()" style="accent-color: var(--success); width: 16px; height: 16px; cursor: pointer;">
                                                    <span style="color: var(--success); font-weight: 600; font-size: 13px;">Present</span>
                                                </label>
                                                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0;">
                                                    <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="absent" <?php echo ($currStatus === 'absent') ? 'checked' : ''; ?> class="att-radio att-radio-absent" onchange="updateLiveCounters()" style="accent-color: var(--danger); width: 16px; height: 16px; cursor: pointer;">
                                                    <span style="color: var(--danger); font-weight: 600; font-size: 13px;">Absent</span>
                                                </label>
                                                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0;">
                                                    <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="late" <?php echo ($currStatus === 'late') ? 'checked' : ''; ?> class="att-radio att-radio-late" onchange="updateLiveCounters()" style="accent-color: var(--warning); width: 16px; height: 16px; cursor: pointer;">
                                                    <span style="color: var(--warning); font-weight: 600; font-size: 13px;">Late</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[<?php echo $stu['id']; ?>]" class="form-control" placeholder="Optional remark (e.g. sick leave, late 10m)..." value="<?php echo htmlspecialchars($stu['remarks'] ?? ''); ?>" style="height: 32px; font-size: 12px;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card-footer" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                    <span style="font-size: 12px; color: var(--text-muted);">
                        Ready to save attendance for <?php echo $totalStudents; ?> students.
                    </span>
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 24px; font-size: 13px;">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

<script src="../assets/js/utils.js"></script>
<script src="../assets/js/notifications.js"></script>
<script>
// Catalog of all subjects for dynamic client-side filtering
const allSubjects = <?php echo json_encode($allSubjects); ?>;

function onDepartmentOrSemesterChange() {
    const deptSelect = document.getElementById('att_department');
    const semSelect = document.getElementById('att_semester');
    const subjSelect = document.getElementById('att_subject');

    const dept = deptSelect ? deptSelect.value : '';
    const sem = semSelect ? semSelect.value : '';

    if (!subjSelect) return;
    subjSelect.innerHTML = '';

    if (!dept) {
        subjSelect.innerHTML = '<option value="">-- Select Department first --</option>';
        subjSelect.disabled = true;
        return;
    }

    if (!sem) {
        subjSelect.innerHTML = '<option value="">-- Select Semester first --</option>';
        subjSelect.disabled = true;
        return;
    }

    // Filter matching subjects
    const matches = allSubjects.filter(s => String(s.department_id) === String(dept) && String(s.semester) === String(sem));

    if (matches.length === 0) {
        subjSelect.innerHTML = '<option value="">-- No subjects found in this Dept &amp; Sem --</option>';
        subjSelect.disabled = true;
    } else {
        subjSelect.disabled = false;
        let html = '<option value="">-- Select Subject --</option>';
        matches.forEach(sub => {
            const prefix = sub.code ? sub.code + ' - ' : '';
            html += `<option value="${sub.id}">${prefix}${sub.name}</option>`;
        });
        subjSelect.innerHTML = html;
    }
}

function onSubjectSelect(selectEl) {
    if (selectEl && selectEl.value) {
        // Automatically submit the filter form to load the roster once a subject is selected
        document.getElementById('attendanceFilterForm').submit();
    }
}

function onDateChange() {
    const dept = document.getElementById('att_department').value;
    const sem = document.getElementById('att_semester').value;
    const sub = document.getElementById('att_subject').value;
    if (dept && sem && sub) {
        document.getElementById('attendanceFilterForm').submit();
    }
}

function markAll(type) {
    if (type === 'present') {
        document.querySelectorAll('.att-radio-present').forEach(r => r.checked = true);
        if (typeof showToast === 'function') showToast('Marked all students as Present', 'info');
    } else if (type === 'absent') {
        document.querySelectorAll('.att-radio-absent').forEach(r => r.checked = true);
        if (typeof showToast === 'function') showToast('Marked all students as Absent', 'warning');
    } else if (type === 'late') {
        document.querySelectorAll('.att-radio-late').forEach(r => r.checked = true);
        if (typeof showToast === 'function') showToast('Marked all students as Late', 'info');
    }
    updateLiveCounters();
}

function updateLiveCounters() {
    let p = 0, a = 0, l = 0;
    document.querySelectorAll('.att-radio:checked').forEach(r => {
        if (r.value === 'present') p++;
        else if (r.value === 'absent') a++;
        else if (r.value === 'late') l++;
    });
    const elP = document.getElementById('statPresentCount');
    const elA = document.getElementById('statAbsentCount');
    const elL = document.getElementById('statLateCount');
    if (elP) elP.textContent = p;
    if (elA) elA.textContent = a;
    if (elL) elL.textContent = l;
}
</script>
</body>
</html>
