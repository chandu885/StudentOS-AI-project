<?php
// frontend/faculty/attendance_print.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$userName = trim(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''));
if (empty($userName)) {
    $userName = $_SESSION['user']['email'] ?? 'Faculty Member';
}
$userEmail = $_SESSION['user']['email'] ?? '';

$db = getDbConnection();

// Fetch active departments for selection
$departments = [];
if ($db) {
    $deptRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($deptRes) {
        $departments = $deptRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Request parameters
$selectedDeptId = isset($_REQUEST['department_id']) && $_REQUEST['department_id'] !== '' ? (int)$_REQUEST['department_id'] : 0;
$selectedSemester = isset($_REQUEST['semester']) && $_REQUEST['semester'] !== '' ? trim((string)$_REQUEST['semester']) : '';

// Date range (defaults: start of current month to today)
$defaultStartDate = date('Y-m-01');
$defaultEndDate = date('Y-m-d');

$startDate = !empty($_REQUEST['start_date']) ? sanitize($_REQUEST['start_date']) : $defaultStartDate;
$endDate = !empty($_REQUEST['end_date']) ? sanitize($_REQUEST['end_date']) : $defaultEndDate;

// Normalize dates if inverted
if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

// Selected department details
$selectedDept = null;
if ($selectedDeptId > 0) {
    foreach ($departments as $d) {
        if ((int)$d['id'] === $selectedDeptId) {
            $selectedDept = $d;
            break;
        }
    }
}

$isSelectionComplete = ($selectedDeptId > 0 && !empty($selectedSemester));

$subjects = [];
$students = [];
$classesHeldMap = [];
$attendanceMatrix = [];
$totalHeldAllSubjects = 0;
$classTotalAttended = 0;
$studentsAtRiskCount = 0;
$overallClassAvgPct = 0.0;

if ($db && $isSelectionComplete) {
    $cleanSem = preg_replace('/[^0-9]/', '', (string)$selectedSemester);

    // 1. Fetch all subjects for this Department and Semester
    $subStmt = $db->prepare(
        "SELECT s.id, s.name, s.code, s.credits, s.type, s.semester
         FROM subjects s
         WHERE s.status = 'active'
           AND s.department_id = ?
           AND (s.semester = ? OR s.semester = ? OR s.semester LIKE ?)
         ORDER BY s.code ASC, s.name ASC"
    );
    if ($subStmt) {
        $semLike = '%' . $cleanSem . '%';
        $subStmt->bind_param("isss", $selectedDeptId, $selectedSemester, $cleanSem, $semLike);
        $subStmt->execute();
        $subjects = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $subStmt->close();
    }

    // 2. Fetch all students belonging to this Department and Semester
    $deptName = $selectedDept['name'] ?? '';
    $deptCode = $selectedDept['code'] ?? '';

    $stuStmt = $db->prepare(
        "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email,
                COALESCE(sp.roll_number, sp.student_id, CONCAT('STU-', u.id)) AS roll_number,
                sp.semester,
                COALESCE(d.name, sp.department, 'General') AS department_name,
                COALESCE(d.code, sp.department, 'GEN') AS department_code
         FROM users u
         JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN departments d ON sp.department_id = d.id
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
               OR sp.semester LIKE ?
           )
         ORDER BY sp.roll_number ASC, u.first_name ASC"
    );

    if ($stuStmt) {
        $stuStmt->bind_param(
            "isssss",
            $selectedDeptId,
            $deptName,
            $deptCode,
            $selectedSemester,
            $cleanSem,
            $semLike
        );
        $stuStmt->execute();
        $students = $stuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stuStmt->close();
    }

    // 3. Count distinct lecture dates / classes held per subject within date range
    if (!empty($subjects)) {
        $subjectIds = array_column($subjects, 'id');
        $inSubjectIds = implode(',', array_map('intval', $subjectIds));

        $heldStmt = $db->prepare(
            "SELECT subject_id, COUNT(DISTINCT date) AS held_count
             FROM attendance
             WHERE subject_id IN ($inSubjectIds)
               AND date BETWEEN ? AND ?
             GROUP BY subject_id"
        );
        if ($heldStmt) {
            $heldStmt->bind_param("ss", $startDate, $endDate);
            $heldStmt->execute();
            $heldRes = $heldStmt->get_result();
            while ($r = $heldRes->fetch_assoc()) {
                $classesHeldMap[(int)$r['subject_id']] = (int)$r['held_count'];
            }
            $heldStmt->close();
        }

        foreach ($subjects as $s) {
            $sId = (int)$s['id'];
            $totalHeldAllSubjects += ($classesHeldMap[$sId] ?? 0);
        }
    }

    // 4. Query student attendance aggregated per subject in date range
    if (!empty($students) && !empty($subjects)) {
        $studentIds = array_column($students, 'id');
        $inStudentIds = implode(',', array_map('intval', $studentIds));
        $inSubjectIds = implode(',', array_map('intval', array_column($subjects, 'id')));

        $attStmt = $db->prepare(
            "SELECT student_id, subject_id,
                    COUNT(CASE WHEN LOWER(status) IN ('present', 'late') THEN 1 END) AS attended_count,
                    COUNT(CASE WHEN LOWER(status) = 'absent' THEN 1 END) AS absent_count,
                    COUNT(*) AS total_recorded
             FROM attendance
             WHERE student_id IN ($inStudentIds)
               AND subject_id IN ($inSubjectIds)
               AND date BETWEEN ? AND ?
             GROUP BY student_id, subject_id"
        );
        if ($attStmt) {
            $attStmt->bind_param("ss", $startDate, $endDate);
            $attStmt->execute();
            $attRes = $attStmt->get_result();
            while ($r = $attRes->fetch_assoc()) {
                $stuId = (int)$r['student_id'];
                $subId = (int)$r['subject_id'];
                $attendanceMatrix[$stuId][$subId] = [
                    'attended' => (int)$r['attended_count'],
                    'absent' => (int)$r['absent_count'],
                    'total' => (int)$r['total_recorded']
                ];
            }
            $attStmt->close();
        }

        // Calculate student metrics and totals
        $sumOfStudentPcts = 0;
        foreach ($students as &$stu) {
            $sId = (int)$stu['id'];
            $stuAttended = 0;
            $stuHeld = 0;

            foreach ($subjects as $sub) {
                $subjId = (int)$sub['id'];
                $held = $classesHeldMap[$subjId] ?? 0;
                $att = $attendanceMatrix[$sId][$subjId]['attended'] ?? 0;

                // Fallback: If classes held was 0 but attendance recorded, use total recorded
                if ($held === 0 && !empty($attendanceMatrix[$sId][$subjId]['total'])) {
                    $held = $attendanceMatrix[$sId][$subjId]['total'];
                }

                $stuAttended += $att;
                $stuHeld += $held;
            }

            $stu['total_attended'] = $stuAttended;
            $stu['total_held'] = $stuHeld;
            $stu['percentage'] = ($stuHeld > 0) ? round(($stuAttended / $stuHeld) * 100, 1) : 0.0;
            $stu['is_shortage'] = ($stuHeld > 0 && $stu['percentage'] < 75.0);

            if ($stu['is_shortage']) {
                $studentsAtRiskCount++;
            }
            $sumOfStudentPcts += $stu['percentage'];
            $classTotalAttended += $stuAttended;
        }
        unset($stu); // break reference

        if (count($students) > 0) {
            $overallClassAvgPct = round($sumOfStudentPcts / count($students), 1);
        }
    }
}

$pageTitle = 'Attendance Print & Consolidated Register - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>

<!-- Print-Specific Stylesheet and Overrides -->
<style>
/* Screen Display Styles */
.print-only {
    display: none !important;
}

.report-filter-card {
    background: var(--bg-surface, #1e293b);
    border: 1px solid var(--border-color, rgba(255,255,255,0.08));
    border-radius: var(--radius-lg, 12px);
    padding: 22px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.preset-chip {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 11.5px;
    color: var(--text-secondary, #cbd5e1);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}
.preset-chip:hover, .preset-chip.active {
    background: var(--primary, #4f46e5);
    color: #ffffff;
    border-color: var(--primary, #4f46e5);
}

.attendance-table-wrapper {
    overflow-x: auto;
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--border-color, rgba(255,255,255,0.08));
    background: var(--bg-surface, #1e293b);
}

.att-matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

.att-matrix-table th {
    background: rgba(0, 0, 0, 0.25);
    color: var(--text-muted, #94a3b8);
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 2px solid var(--border-color, rgba(255,255,255,0.12));
    white-space: nowrap;
}

.att-matrix-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.06));
    vertical-align: middle;
}

.att-matrix-table tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}

.att-matrix-table tfoot td {
    background: rgba(0, 0, 0, 0.35);
    font-weight: 700;
    border-top: 2px solid var(--border-color, rgba(255,255,255,0.15));
}

.cell-stat {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 60px;
}
.cell-stat-fraction {
    font-weight: 700;
    color: var(--text-primary, #ffffff);
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}
.cell-stat-pct {
    font-size: 10.5px;
    color: var(--text-muted, #94a3b8);
    margin-top: 1px;
}

.pct-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.3px;
}
.pct-badge-good {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.pct-badge-warn {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.pct-badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* ========================================================= */
/* CSS @media print: High-Fidelity Paper / PDF Print Layout   */
/* ========================================================= */
@media print {
    @page {
        size: landscape;
        margin: 10mm 10mm 12mm 10mm;
    }

    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        font-size: 9.5pt !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Hide Web UI chrome and navigation elements */
    .sidebar,
    .sidebar-overlay,
    .topbar,
    .site-header,
    .page-header,
    .report-filter-card,
    .metrics-summary-grid,
    .no-print,
    .btn,
    .footer,
    nav,
    aside {
        display: none !important;
    }

    /* Make main content full width with no margins */
    .dashboard-layout,
    .dashboard-main,
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .card {
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .print-only {
        display: block !important;
    }

    /* Print Institutional Header */
    .print-institute-header {
        border-bottom: 2px solid #000;
        padding-bottom: 8px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .print-institute-title {
        font-size: 16pt;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
        color: #000;
    }
    .print-sub-title {
        font-size: 11pt;
        font-weight: 700;
        margin-top: 3px;
        color: #222;
    }
    .print-meta-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 6px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        padding: 8px 12px;
        margin-bottom: 12px;
        font-size: 9pt;
    }
    .print-meta-item strong {
        color: #1e293b;
    }

    /* Attendance Table Styling for Print */
    .attendance-table-wrapper {
        border: none !important;
        background: transparent !important;
        overflow: visible !important;
    }
    .att-matrix-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 8.5pt !important;
    }
    .att-matrix-table th {
        background: #f1f5f9 !important;
        color: #000000 !important;
        border: 1px solid #334155 !important;
        padding: 5px 6px !important;
        text-align: center !important;
        font-size: 8.5pt !important;
        font-weight: 700 !important;
    }
    .att-matrix-table td {
        border: 1px solid #475569 !important;
        padding: 4px 6px !important;
        color: #000000 !important;
        background: #ffffff !important;
    }
    .att-matrix-table tr {
        page-break-inside: avoid !important;
    }
    thead {
        display: table-header-group !important;
    }
    tfoot {
        display: table-footer-group !important;
    }
    .att-matrix-table tfoot td {
        background: #f8fafc !important;
        border-top: 2px solid #000000 !important;
        font-weight: bold !important;
    }

    .cell-stat-fraction {
        color: #000 !important;
        font-size: 8.5pt !important;
    }
    .cell-stat-pct {
        color: #475569 !important;
        font-size: 7.5pt !important;
    }

    .pct-badge {
        border: 1px solid #000 !important;
        background: transparent !important;
        color: #000 !important;
        padding: 1px 4px !important;
        font-size: 8.5pt !important;
    }

    /* Signatures Section at Document Bottom */
    .print-signature-section {
        margin-top: 35px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        page-break-inside: avoid;
    }
    .print-signature-box {
        text-align: center;
        border-top: 1px dashed #333;
        padding-top: 8px;
        font-size: 9pt;
        font-weight: 600;
    }
}
</style>

<div class="main-content-inner">

    <!-- Screen Header -->
    <div class="page-header no-print" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1><i class="fas fa-print" style="color: var(--primary); margin-right: 8px;"></i> Attendance Print &amp; Consolidated Register</h1>
            <p class="page-subtitle">Select Department, Semester, and Date Range to generate and print student attendance across all subjects</p>
        </div>
        <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($isSelectionComplete && !empty($students)): ?>
                <button type="button" class="btn btn-primary" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-print"></i> Print Attendance Sheet
                </button>
                <button type="button" class="btn btn-secondary" onclick="exportAttendanceToCSV()" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-excel"></i> Export CSV
                </button>
            <?php endif; ?>
            <a href="attendance.php<?php echo ($selectedDeptId > 0 && !empty($selectedSemester)) ? '?department_id=' . $selectedDeptId . '&semester=' . urlencode($selectedSemester) : ''; ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-arrow-left"></i> Daily Register
            </a>
        </div>
    </div>

    <!-- Printable Official Institutional Header (Visible only when Printing) -->
    <div class="print-only">
        <div class="print-institute-header">
            <div>
                <h1 class="print-institute-title">StudentOS AI &bull; Academic Portal</h1>
                <div class="print-sub-title">OFFICIAL CONSOLIDATED ATTENDANCE REGISTER &amp; ELIGIBILITY REPORT</div>
            </div>
            <div style="text-align: right; font-size: 8.5pt;">
                <div><strong>Generation Date:</strong> <?php echo date('d-M-Y H:i A'); ?></div>
                <div><strong>Faculty:</strong> <?php echo htmlspecialchars($userName); ?></div>
            </div>
        </div>

        <?php if ($isSelectionComplete && $selectedDept): ?>
            <div class="print-meta-grid">
                <div class="print-meta-item">
                    <strong>Department:</strong> <?php echo htmlspecialchars($selectedDept['name'] . ' (' . $selectedDept['code'] . ')'); ?>
                </div>
                <div class="print-meta-item">
                    <strong>Semester:</strong> Semester <?php echo htmlspecialchars($selectedSemester); ?>
                </div>
                <div class="print-meta-item">
                    <strong>Date Range:</strong> <?php echo date('d M Y', strtotime($startDate)); ?> &ndash; <?php echo date('d M Y', strtotime($endDate)); ?>
                </div>
                <div class="print-meta-item">
                    <strong>Enrolled Students:</strong> <?php echo count($students); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filter Card: Department, Semester, Date Range Selection -->
    <div class="report-filter-card no-print">
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-filter" style="margin-right: 6px; color: var(--primary);"></i> Attendance Criteria &amp; Date Range</span>
            <span style="font-size: 11.5px; font-weight: normal; color: var(--text-muted);">
                Mandatory: Department, Semester &amp; Date Range
            </span>
        </div>

        <form id="attendancePrintForm" method="GET" action="attendance_print.php">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">

                <!-- Department Selector -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_dept" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        Department <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="department_id" id="filter_dept" class="form-control" required style="height: 40px; font-size: 13px;">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo (int)$dept['id']; ?>" <?php echo ((int)$selectedDeptId === (int)$dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semester Selector -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_sem" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        Semester <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="semester" id="filter_sem" class="form-control" required style="height: 40px; font-size: 13px;">
                        <option value="">-- Select Semester --</option>
                        <?php for ($s = 1; $s <= 8; $s++): ?>
                            <option value="<?php echo $s; ?>" <?php echo ((string)$selectedSemester === (string)$s) ? 'selected' : ''; ?>>
                                Semester <?php echo $s; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Start Date -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_start" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        Start Date <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" name="start_date" id="filter_start" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" required style="height: 40px; font-size: 13px;">
                </div>

                <!-- End Date -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_end" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        End Date <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" name="end_date" id="filter_end" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" required style="height: 40px; font-size: 13px;">
                </div>

                <!-- Action Submit Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="height: 40px; display: inline-flex; align-items: center; gap: 6px; padding: 0 18px; white-space: nowrap;">
                        <i class="fas fa-search"></i> Generate Sheet
                    </button>
                    <a href="attendance_print.php" class="btn btn-secondary" style="height: 40px; display: inline-flex; align-items: center; justify-content: center; padding: 0 14px;" title="Reset selection">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Date Range Presets -->
            <div style="margin-top: 14px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Date Range Presets:</span>
                <span class="preset-chip" onclick="applyDatePreset('this_month')">This Month</span>
                <span class="preset-chip" onclick="applyDatePreset('last_30')">Last 30 Days</span>
                <span class="preset-chip" onclick="applyDatePreset('last_90')">Last 90 Days (Term)</span>
                <span class="preset-chip" onclick="applyDatePreset('ytd')">Year to Date</span>
            </div>
        </form>
    </div>

    <?php if (!$isSelectionComplete): ?>
        <!-- Selection Prompt State -->
        <div class="card no-print" style="padding: 50px 24px; text-align: center; color: var(--text-muted); border-radius: var(--radius-lg);">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(79, 70, 229, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 18px;">
                <i class="fas fa-print"></i>
            </div>
            <h3 style="color: var(--text-primary); margin-bottom: 8px;">Ready to Print Class Attendance</h3>
            <p style="font-size: 14px; margin: 0 auto 20px; max-width: 580px; line-height: 1.6;">
                Please select the <strong>Department</strong>, <strong>Semester</strong>, and <strong>Date Range</strong> above, then click <strong>"Generate Sheet"</strong>.
                The system will calculate attendance across all registered course subjects and render a complete register with Roll Number, Name, All Subjects, Total, and Percentage.
            </p>
        </div>

    <?php elseif (empty($subjects)): ?>
        <!-- No Subjects Warning -->
        <div class="card no-print" style="padding: 40px 24px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: var(--warning); margin-bottom: 14px;"></i>
            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Course Subjects Found</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); margin: 0 auto; max-width: 540px;">
                There are currently no active subjects registered for <strong><?php echo htmlspecialchars($selectedDept['name'] ?? 'Department'); ?></strong> in <strong>Semester <?php echo htmlspecialchars($selectedSemester); ?></strong>.
                Please check the curriculum configuration or select a different semester.
            </p>
        </div>

    <?php elseif (empty($students)): ?>
        <!-- No Students Warning -->
        <div class="card no-print" style="padding: 40px 24px; text-align: center;">
            <i class="fas fa-users-slash" style="font-size: 40px; color: var(--warning); margin-bottom: 14px;"></i>
            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Students Enrolled</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); margin: 0 auto; max-width: 540px;">
                No active students were found enrolled in <strong><?php echo htmlspecialchars($selectedDept['name'] ?? 'Department'); ?></strong>, <strong>Semester <?php echo htmlspecialchars($selectedSemester); ?></strong>.
            </p>
        </div>

    <?php else: ?>
        <!-- KPI Summary Cards (Screen Only) -->
        <div class="metrics-summary-grid no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
            <div class="card" style="padding: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Enrolled Students</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo count($students); ?></div>
                </div>
            </div>

            <div class="card" style="padding: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0EA5E9; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Course Subjects</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo count($subjects); ?></div>
                </div>
            </div>

            <div class="card" style="padding: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Classes Held</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalHeldAllSubjects; ?></div>
                </div>
            </div>

            <div class="card" style="padding: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Class Average</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $overallClassAvgPct; ?>%</div>
                </div>
            </div>

            <div class="card" style="padding: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #EF4444; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Attendance Shortage (&lt;75%)</div>
                    <div style="font-size: 22px; font-weight: 800; color: <?php echo $studentsAtRiskCount > 0 ? '#ef4444' : 'var(--text-primary)'; ?>; line-height: 1.2;">
                        <?php echo $studentsAtRiskCount; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Register Table Card -->
        <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
            
            <!-- Table Header Bar (Screen Only) -->
            <div class="no-print" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08)); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-table" style="color: var(--primary);"></i>
                        Consolidated Attendance Sheet &mdash; <?php echo htmlspecialchars($selectedDept['code'] ?? 'Class'); ?> Sem <?php echo htmlspecialchars($selectedSemester); ?>
                    </h3>
                    <span style="font-size: 12px; color: var(--text-muted);">
                        Period: <?php echo date('M d, Y', strtotime($startDate)); ?> &ndash; <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </span>
                </div>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <!-- Live Search in Table -->
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 10px; top: 11px; font-size: 12px; color: var(--text-muted);"></i>
                        <input type="text" id="tableSearchInput" onkeyup="filterAttendanceTable()" placeholder="Search student or roll..." class="form-control" style="height: 34px; padding-left: 30px; font-size: 12px; width: 200px;">
                    </div>

                    <!-- Threshold Filter -->
                    <select id="statusFilterSelect" onchange="filterAttendanceTable()" class="form-control" style="height: 34px; font-size: 12px; width: 150px;">
                        <option value="all">All Students</option>
                        <option value="eligible">Eligible (&ge; 75%)</option>
                        <option value="shortage">Shortage (&lt; 75%)</option>
                    </select>
                </div>
            </div>

            <!-- The Main Consolidated Attendance Table -->
            <div class="attendance-table-wrapper">
                <table class="att-matrix-table" id="consolidatedAttendanceTable">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="min-width: 120px;">Roll Number</th>
                            <th style="min-width: 180px;">Student Name</th>
                            
                            <!-- Dynamic Subject Columns -->
                            <?php foreach ($subjects as $sub): ?>
                                <th style="text-align: center; min-width: 110px;" title="<?php echo htmlspecialchars($sub['name'] . ' (' . $sub['credits'] . ' Credits)'); ?>">
                                    <div style="font-weight: 800; color: var(--text-primary); font-size: 12px;"><?php echo htmlspecialchars($sub['code']); ?></div>
                                    <div style="font-size: 10px; font-weight: normal; opacity: 0.8; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>

                            <th style="text-align: center; min-width: 100px; background: rgba(79, 70, 229, 0.1);">Total</th>
                            <th style="text-align: center; min-width: 90px; background: rgba(79, 70, 229, 0.15);">Percentage</th>
                            <th style="text-align: center; min-width: 90px;" class="no-print">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowIndex = 1;
                        foreach ($students as $stu): 
                            $stuId = (int)$stu['id'];
                            $stuTotalAtt = (int)$stu['total_attended'];
                            $stuTotalHeld = (int)$stu['total_held'];
                            $stuPct = (float)$stu['percentage'];
                            $isShortage = !empty($stu['is_shortage']);

                            $pctBadgeClass = ($stuPct >= 75.0) ? 'pct-badge-good' : (($stuPct >= 60.0) ? 'pct-badge-warn' : 'pct-badge-danger');
                            $statusLabel = ($stuPct >= 75.0) ? 'Eligible' : 'Shortage';
                            $statusFilterKey = ($stuPct >= 75.0) ? 'eligible' : 'shortage';
                        ?>
                            <tr class="attendance-row" data-status="<?php echo $statusFilterKey; ?>" data-search="<?php echo htmlspecialchars(strtolower($stu['name'] . ' ' . $stu['roll_number'])); ?>">
                                <td style="text-align: center; color: var(--text-muted); font-size: 11px;"><?php echo $rowIndex++; ?></td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12.5px; color: var(--text-primary); letter-spacing: 0.3px;">
                                        <?php echo htmlspecialchars($stu['roll_number']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($stu['name']); ?>
                                    </span>
                                </td>

                                <!-- Subject-wise Attendance Cells -->
                                <?php foreach ($subjects as $sub): 
                                    $subId = (int)$sub['id'];
                                    $held = $classesHeldMap[$subId] ?? 0;
                                    $att = $attendanceMatrix[$stuId][$subId]['attended'] ?? 0;

                                    if ($held === 0 && !empty($attendanceMatrix[$stuId][$subId]['total'])) {
                                        $held = $attendanceMatrix[$stuId][$subId]['total'];
                                    }

                                    $subPct = ($held > 0) ? round(($att / $held) * 100, 1) : 0.0;
                                ?>
                                    <td style="text-align: center;">
                                        <?php if ($held > 0): ?>
                                            <div class="cell-stat">
                                                <span class="cell-stat-fraction"><?php echo $att; ?> / <?php echo $held; ?></span>
                                                <span class="cell-stat-pct" style="color: <?php echo $subPct >= 75 ? '#10b981' : ($subPct >= 60 ? '#f59e0b' : '#ef4444'); ?>;">
                                                    <?php echo $subPct; ?>%
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 11px;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <!-- Total Classes Attended / Held -->
                                <td style="text-align: center; font-weight: 700;">
                                    <div class="cell-stat">
                                        <span class="cell-stat-fraction" style="font-size: 13.5px;"><?php echo $stuTotalAtt; ?> / <?php echo $stuTotalHeld; ?></span>
                                    </div>
                                </td>

                                <!-- Overall Percentage Badge -->
                                <td style="text-align: center;">
                                    <span class="pct-badge <?php echo $pctBadgeClass; ?>">
                                        <?php echo number_format($stuPct, 1); ?>%
                                    </span>
                                </td>

                                <!-- Eligibility / Status (Screen Only) -->
                                <td style="text-align: center;" class="no-print">
                                    <?php if ($stuPct >= 75.0): ?>
                                        <span class="badge badge-success" style="font-size: 10.5px; padding: 3px 8px;">
                                            <i class="fas fa-check-circle"></i> Eligible
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="font-size: 10.5px; padding: 3px 8px;" title="Attendance below 75%">
                                            <i class="fas fa-exclamation-triangle"></i> Shortage
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; padding-right: 14px;">Classes Conducted / Class Average:</td>
                            <?php foreach ($subjects as $sub): 
                                $subId = (int)$sub['id'];
                                $held = $classesHeldMap[$subId] ?? 0;

                                // Compute subject average across all students
                                $totalSubAtt = 0;
                                foreach ($students as $stu) {
                                    $sId = (int)$stu['id'];
                                    $totalSubAtt += ($attendanceMatrix[$sId][$subId]['attended'] ?? 0);
                                }
                                $subAvgPct = (count($students) > 0 && $held > 0) ? round(($totalSubAtt / (count($students) * $held)) * 100, 1) : 0;
                            ?>
                                <td style="text-align: center;">
                                    <div class="cell-stat">
                                        <span class="cell-stat-fraction"><?php echo $held; ?> held</span>
                                        <span class="cell-stat-pct"><?php echo $subAvgPct; ?>% avg</span>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td style="text-align: center;">
                                <span class="cell-stat-fraction"><?php echo $totalHeldAllSubjects; ?> Total</span>
                            </td>
                            <td style="text-align: center;">
                                <span class="pct-badge <?php echo $overallClassAvgPct >= 75.0 ? 'pct-badge-good' : 'pct-badge-warn'; ?>">
                                    <?php echo number_format($overallClassAvgPct, 1); ?>%
                                </span>
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Official Signatures Block (Visible only on Print) -->
        <div class="print-only">
            <div class="print-signature-section">
                <div class="print-signature-box">
                    Subject Faculty / Class In-Charge<br>
                    <span style="font-size: 8pt; font-weight: normal; color: #555;"><?php echo htmlspecialchars($userName); ?></span>
                </div>
                <div class="print-signature-box">
                    Head of Department (H.O.D.)<br>
                    <span style="font-size: 8pt; font-weight: normal; color: #555;">Dept. of <?php echo htmlspecialchars($selectedDept['name'] ?? 'Academics'); ?></span>
                </div>
                <div class="print-signature-box">
                    Principal / Academic Dean<br>
                    <span style="font-size: 8pt; font-weight: normal; color: #555;">Office of Academic Affairs</span>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include_once __DIR__ . '/../components/footer.php'; ?>

<!-- Client-Side Scripts for Print, Presets, Filters, and CSV Export -->
<script>
// Apply Date Presets
function applyDatePreset(preset) {
    const today = new Date();
    const endInput = document.getElementById('filter_end');
    const startInput = document.getElementById('filter_start');
    if (!endInput || !startInput) return;

    endInput.value = today.toISOString().split('T')[0];

    if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startInput.value = firstDay.toISOString().split('T')[0];
    } else if (preset === 'last_30') {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        startInput.value = d.toISOString().split('T')[0];
    } else if (preset === 'last_90') {
        const d = new Date();
        d.setDate(d.getDate() - 90);
        startInput.value = d.toISOString().split('T')[0];
    } else if (preset === 'ytd') {
        const firstDayOfYear = new Date(today.getFullYear(), 0, 1);
        startInput.value = firstDayOfYear.toISOString().split('T')[0];
    }

    // Update active chip styling
    const chips = document.querySelectorAll('.preset-chip');
    chips.forEach(c => c.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// Live Search & Threshold Filter
function filterAttendanceTable() {
    const searchInput = document.getElementById('tableSearchInput');
    const statusSelect = document.getElementById('statusFilterSelect');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const statusFilter = statusSelect ? statusSelect.value : 'all';

    const rows = document.querySelectorAll('.attendance-row');
    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchesQuery = !query || rowSearch.includes(query);
        const matchesStatus = (statusFilter === 'all') || (rowStatus === statusFilter);

        if (matchesQuery && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Export Consolidated Attendance Table to CSV
function exportAttendanceToCSV() {
    const table = document.getElementById('consolidatedAttendanceTable');
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        // Skip hidden rows if filtered
        if (row.style.display === 'none') return;

        let rowData = [];
        const cols = row.querySelectorAll('th, td');
        cols.forEach((col, index) => {
            // Skip the action/status column on screen
            if (col.classList.contains('no-print')) return;

            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
            // Escape double quotes
            text = text.replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    });

    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    const deptCode = "<?php echo htmlspecialchars($selectedDept['code'] ?? 'Class'); ?>";
    const sem = "<?php echo htmlspecialchars($selectedSemester ?? 'Sem'); ?>";
    const dateStr = "<?php echo htmlspecialchars($startDate . '_to_' . $endDate); ?>";

    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `attendance_register_${deptCode}_Sem${sem}_${dateStr}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
