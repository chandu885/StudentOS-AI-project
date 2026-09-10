<?php
// frontend/admin/semesters.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/models/Academic.php';
require_once __DIR__ . '/../../backend/models/SystemModel.php';

requireRole('admin');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$academic = new Academic();
$sysModel = new SystemModel();

$successMsg = '';
$errorMsg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Candidate preview AJAX for promotion modal
if (isset($_GET['action']) && $_GET['action'] === 'preview_candidates') {
    require_once __DIR__ . '/../../backend/models/Student.php';
    $stuModel = new Student();
    $deg = sanitize($_GET['degree'] ?? 'all');
    $sem = sanitize($_GET['from_semester'] ?? 'all');
    $opt = (!empty($_GET['only_opted_in']) && $_GET['only_opted_in'] === '1');
    $candidates = $stuModel->getPromotionCandidates($deg, $sem, $opt);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => count($candidates), 'candidates' => $candidates]);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $submittedCsrf)) {
        $errorMsg = 'Security validation failed (CSRF mismatch). Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'create_semester';

        // PROMOTE COHORT BY DEGREE & SEMESTER
        if ($action === 'promote_cohort') {
            require_once __DIR__ . '/../../backend/models/Student.php';
            $studentModel = new Student();

            $degree = sanitize($_POST['degree'] ?? 'all');
            $fromSemester = sanitize($_POST['from_semester'] ?? 'all');
            $targetSemester = sanitize($_POST['target_semester'] ?? 'next');
            $scope = sanitize($_POST['scope'] ?? 'all');
            $notes = sanitize($_POST['notes'] ?? 'Admin cohort semester promotion run');
            $onlyOptedIn = ($scope === 'opted_in');

            $res = $studentModel->promoteByDegreeAndSemester($degree, $fromSemester, $targetSemester, $userId, $notes, $onlyOptedIn);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'EXECUTE_SEMESTER_PROMOTION', 'student_profiles', 0, "Admin promoted {$res['count']} student(s) (Degree: $degree, From Sem: $fromSemester, Target: $targetSemester). Notes: $notes");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // CREATE SEMESTER
        elseif ($action === 'create_semester') {
            $data = [
                'course_id'       => (int)($_POST['course_id'] ?? 0),
                'semester_number' => (int)($_POST['semester_number'] ?? 1),
                'name'            => trim($_POST['name'] ?? ''),
                'academic_year'   => trim($_POST['academic_year'] ?? '2026-2027'),
                'start_date'      => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date'        => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'status'          => $_POST['status'] ?? 'active',
                'description'     => trim($_POST['description'] ?? '')
            ];

            $res = $academic->createSemester($data, $userId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'CREATE_SEMESTER', 'semesters', $res['semester_id'], "Admin created semester '{$data['name']}' (#{$data['semester_number']}) for Course ID #{$data['course_id']}.");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // EDIT SEMESTER
        elseif ($action === 'edit_semester') {
            $semId = (int)($_POST['semester_id'] ?? 0);
            $data = [
                'course_id'       => (int)($_POST['course_id'] ?? 0),
                'semester_number' => (int)($_POST['semester_number'] ?? 1),
                'name'            => trim($_POST['name'] ?? ''),
                'academic_year'   => trim($_POST['academic_year'] ?? '2026-2027'),
                'start_date'      => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date'        => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'status'          => $_POST['status'] ?? 'active',
                'description'     => trim($_POST['description'] ?? '')
            ];

            $res = $academic->updateSemester($semId, $data);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'UPDATE_SEMESTER', 'semesters', $semId, "Admin updated semester '{$data['name']}' (#{$semId}).");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // DELETE SEMESTER
        elseif ($action === 'delete_semester') {
            $semId = (int)($_POST['semester_id'] ?? 0);
            $sem = $academic->getSemesterById($semId);

            $res = $academic->deleteSemester($semId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $name = $sem['name'] ?? "Semester #{$semId}";
                $sysModel->logAudit($userId, 'DELETE_SEMESTER', 'semesters', $semId, "Admin deleted semester '{$name}'.");
            } else {
                $errorMsg = $res['error'];
            }
        }
    }
}

// Filters
$filterCourse = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$filterStatus = !empty($_GET['status']) ? trim($_GET['status']) : null;
$filterYear = !empty($_GET['academic_year']) ? trim($_GET['academic_year']) : null;
$searchQuery = !empty($_GET['q']) ? trim($_GET['q']) : null;

// Fetch data
$semesters = $academic->getSemesters($filterCourse, $filterStatus, $filterYear, $searchQuery);
$courses = $academic->getCourses();
$departments = $academic->getDepartments();

// Calculate stats
$totalSemesters = count($semesters);
$activeSemesters = 0;
$upcomingSemesters = 0;
$completedSemesters = 0;

foreach ($semesters as $s) {
    if ($s['status'] === 'active') $activeSemesters++;
    elseif ($s['status'] === 'upcoming') $upcomingSemesters++;
    elseif ($s['status'] === 'completed') $completedSemesters++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Terms Management - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .stat-grid-sems {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card-sem {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg, 12px);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .sem-badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .sem-badge-upcoming {
            background: rgba(99, 102, 241, 0.15);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .sem-badge-completed {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Semester & Academic Terms</h1>
                        <p class="page-subtitle">Configure semester periods, academic session milestones, and cohort terms across programs</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn btn-success" onclick="openModal('promoteCohortModal')" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-graduation-cap"></i> Promote Cohort by Degree
                        </button>
                        <a href="students.php?promotion_status=opted_in" class="btn btn-outline" style="border-color: rgba(34, 197, 94, 0.4); color: var(--success);">
                            <i class="fas fa-level-up-alt"></i> Student Promotions
                        </a>
                        <a href="subjects.php" class="btn btn-secondary">
                            <i class="fas fa-book"></i> View Subjects
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openCreateSemesterModal()">
                            <i class="fas fa-plus-circle"></i> Create Semester
                        </button>
                    </div>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 14px rgba(34, 197, 94, 0.1);">
                        <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                        <span style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($successMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                        <span style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($errorMsg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Executive Stats -->
                <div class="stat-grid-sems">
                    <div class="stat-card-sem">
                        <div class="stat-icon-wrap" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Total Semesters</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $totalSemesters; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-sem">
                        <div class="stat-icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Active Terms</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $activeSemesters; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-sem">
                        <div class="stat-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Upcoming Terms</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $upcomingSemesters; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-sem">
                        <div class="stat-icon-wrap" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Completed Terms</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $completedSemesters; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Degree & Semester Promotion Console Card -->
                <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success); background: var(--bg-card); box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
                    <div class="card-body" style="padding: 22px 26px;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px;">
                            <div>
                                <h3 style="font-size: 17px; font-weight: 700; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-graduation-cap" style="color: var(--success); font-size: 20px;"></i> Semester Promotion by Degree & Semester
                                    <span class="badge badge-success" style="font-size: 11px;">Admin & Super Admin Exclusive</span>
                                </h3>
                                <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0 0;">
                                    Select degree program and active semester to execute academic cohort promotions with instant automated notifications.
                                </p>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="openModal('promoteCohortModal')" style="font-size: 12.5px; border-color: rgba(34, 197, 94, 0.4); color: var(--success);">
                                <i class="fas fa-expand-arrows-alt"></i> Advanced Wizard
                            </button>
                        </div>

                        <form method="POST" action="semesters.php" id="inlinePromotionForm" onsubmit="return confirmCohortPromotion(this);">
                            <input type="hidden" name="action" value="promote_cohort">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: flex-end;">
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px;">
                                        <i class="fas fa-university" style="margin-right: 4px;"></i> 1. Select Degree
                                    </label>
                                    <select name="degree" id="inlinePromDegree" class="form-control" onchange="updateCandidatePreview('inline')" required>
                                        <option value="all">All Degrees & Departments</option>
                                        <?php if (!empty($departments)): ?>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?php echo htmlspecialchars($d['code']); ?>">
                                                    <?php echo htmlspecialchars($d['name'] . ' (' . $d['code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="BCA">Bachelor of Computer Applications (BCA)</option>
                                            <option value="BBA">Bachelor of Business Administration (BBA)</option>
                                            <option value="CSE">Computer Science & Engineering (CSE)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px;">
                                        <i class="fas fa-step-forward" style="margin-right: 4px;"></i> 2. Current Semester (From)
                                    </label>
                                    <select name="from_semester" id="inlinePromFromSem" class="form-control" onchange="updateCandidatePreview('inline')" required>
                                        <option value="all">All Semesters (Cohorts)</option>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px;">
                                        <i class="fas fa-arrow-alt-circle-up" style="margin-right: 4px;"></i> 3. Promote To (Target)
                                    </label>
                                    <select name="target_semester" id="inlinePromTargetSem" class="form-control" required>
                                        <option value="next">Advance to Next Semester (+1 Auto)</option>
                                        <?php for ($i = 2; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                        <option value="Graduated">Graduated / Program Completed</option>
                                    </select>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px;">
                                        <i class="fas fa-users" style="margin-right: 4px;"></i> 4. Student Scope
                                    </label>
                                    <select name="scope" id="inlinePromScope" class="form-control" onchange="updateCandidatePreview('inline')">
                                        <option value="all">All Enrolled Students</option>
                                        <option value="opted_in">Only Students with Opt-In Status</option>
                                    </select>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-success" style="width: 100%; height: 38px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700;">
                                        <i class="fas fa-level-up-alt"></i> Execute Promotion
                                    </button>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color);">
                                <div id="inlineCandidatePreview" style="font-size: 12.5px; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-user-check" style="color: var(--success);"></i>
                                    <span id="inlineCandidateCountText">Checking eligible students in roster...</span>
                                </div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">
                                    <i class="fas fa-bell" style="color: var(--warning, #f59e0b);"></i> Automatic confirmation notifications will be posted to all promoted student dashboards.
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <h3><i class="fas fa-layer-group"></i> Active Semester Ledger</h3>

                        <!-- Filter Controls -->
                        <form method="GET" action="semesters.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0;">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>" class="form-control" placeholder="Search semester or course..." style="width: 190px; height: 36px; font-size: 13px;">

                            <select name="course_id" class="form-control" style="width: 160px; height: 36px; font-size: 13px;" onchange="this.form.submit()">
                                <option value="">All Programs</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo ($filterCourse === (int)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status" class="form-control" style="width: 130px; height: 36px; font-size: 13px;" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo ($filterStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="upcoming" <?php echo ($filterStatus === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="completed" <?php echo ($filterStatus === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>

                            <?php if (!empty($searchQuery) || !empty($filterCourse) || !empty($filterStatus)): ?>
                                <a href="semesters.php" class="btn btn-sm btn-secondary" title="Clear Filters" style="height: 36px; display: flex; align-items: center;">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Term / Semester</th>
                                        <th>Program & Dept</th>
                                        <th style="text-align: center;">Sem #</th>
                                        <th>Academic Year</th>
                                        <th>Term Schedule</th>
                                        <th style="text-align: center;">Curriculum</th>
                                        <th>Status</th>
                                        <th style="text-align: right; width: 110px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($semesters)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                                <i class="fas fa-calendar-times" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                                No semesters found matching your criteria.
                                                <div style="margin-top: 10px;">
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="openCreateSemesterModal()">
                                                        <i class="fas fa-plus"></i> Create New Semester
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($semesters as $s): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                                                    <?php if (!empty($s['description'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($s['description']); ?>">
                                                            <?php echo htmlspecialchars($s['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($s['course_name']); ?></span>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($s['department_name']); ?></div>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="badge badge-secondary" style="font-weight: 700; font-size: 12px;">
                                                        Sem <?php echo (int)$s['semester_number']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($s['academic_year']); ?></code>
                                                </td>
                                                <td>
                                                    <?php if (!empty($s['start_date']) || !empty($s['end_date'])): ?>
                                                        <div style="font-size: 12px;">
                                                            <i class="fas fa-calendar" style="color: var(--text-muted); margin-right: 4px;"></i>
                                                            <?php echo !empty($s['start_date']) ? date('M d, Y', strtotime($s['start_date'])) : 'TBD'; ?>
                                                            <span style="color: var(--text-muted); margin: 0 4px;">→</span>
                                                            <?php echo !empty($s['end_date']) ? date('M d, Y', strtotime($s['end_date'])) : 'TBD'; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-size: 12px; font-style: italic;">Not scheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <a href="subjects.php?course_id=<?php echo (int)$s['course_id']; ?>&semester=<?php echo (int)$s['semester_number']; ?>" class="badge badge-info" style="text-decoration: none; padding: 4px 8px;" title="View subjects for this semester">
                                                        <i class="fas fa-book" style="margin-right: 4px;"></i><?php echo (int)$s['subject_count']; ?> subjects
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $st = $s['status'] ?? 'active';
                                                    $badgeClass = ($st === 'active') ? 'sem-badge-active' : (($st === 'upcoming') ? 'sem-badge-upcoming' : 'sem-badge-completed');
                                                    ?>
                                                    <span class="<?php echo $badgeClass; ?>"><?php echo ucfirst($st); ?></span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditSemesterModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Semester">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick='confirmDeleteSemester(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Delete Semester">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
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

    <!-- CREATE SEMESTER MODAL -->
    <div class="modal-backdrop" id="createSemesterModal">
        <div class="modal-card" style="max-width: 620px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Create New Semester</h3>
                <button type="button" class="modal-close" onclick="closeModal('createSemesterModal')">&times;</button>
            </div>
            <form method="POST" action="" id="createSemesterForm">
                <input type="hidden" name="action" value="create_semester">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_sem_course">Degree Program / Course <span style="color: var(--danger);">*</span></label>
                        <select name="course_id" id="create_sem_course" class="form-control" required onchange="autoSuggestSemName('create')">
                            <option value="">Select Degree Course...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['code']); ?>" data-max="<?php echo (int)$c['total_semesters']; ?>">
                                    <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sem_num">Semester Number <span style="color: var(--danger);">*</span></label>
                            <input type="number" name="semester_number" id="create_sem_num" class="form-control" value="1" min="1" max="12" required onchange="autoSuggestSemName('create')">
                        </div>
                        <div class="form-group">
                            <label for="create_sem_year">Academic Year <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="academic_year" id="create_sem_year" class="form-control" value="2026-2027" placeholder="e.g. 2026-2027" required onchange="autoSuggestSemName('create')">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="create_sem_name">Semester Title / Term Label <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name" id="create_sem_name" class="form-control" placeholder="e.g. Semester 1 (Fall 2026)" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sem_start">Start Date</label>
                            <input type="date" name="start_date" id="create_sem_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="create_sem_end">End Date</label>
                            <input type="date" name="end_date" id="create_sem_end" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="create_sem_status">Term Status</label>
                            <select name="status" id="create_sem_status" class="form-control">
                                <option value="active">Active (Current)</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="create_sem_desc">Academic Notes / Description</label>
                        <textarea name="description" id="create_sem_desc" class="form-control" rows="2" placeholder="Curriculum milestones, enrollment guidelines, or semester remarks..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createSemesterModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Semester</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SEMESTER MODAL -->
    <div class="modal-backdrop" id="editSemesterModal">
        <div class="modal-card" style="max-width: 620px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Semester Term</h3>
                <button type="button" class="modal-close" onclick="closeModal('editSemesterModal')">&times;</button>
            </div>
            <form method="POST" action="" id="editSemesterForm">
                <input type="hidden" name="action" value="edit_semester">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="semester_id" id="edit_sem_id" value="">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_sem_course">Degree Program / Course <span style="color: var(--danger);">*</span></label>
                        <select name="course_id" id="edit_sem_course" class="form-control" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sem_num">Semester Number <span style="color: var(--danger);">*</span></label>
                            <input type="number" name="semester_number" id="edit_sem_num" class="form-control" min="1" max="12" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_sem_year">Academic Year <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="academic_year" id="edit_sem_year" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_sem_name">Semester Title / Term Label <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name" id="edit_sem_name" class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sem_start">Start Date</label>
                            <input type="date" name="start_date" id="edit_sem_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_sem_end">End Date</label>
                            <input type="date" name="end_date" id="edit_sem_end" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_sem_status">Term Status</label>
                            <select name="status" id="edit_sem_status" class="form-control">
                                <option value="active">Active (Current)</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_sem_desc">Academic Notes / Description</label>
                        <textarea name="description" id="edit_sem_desc" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSemesterModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE SEMESTER MODAL -->
    <div class="modal-backdrop" id="deleteSemesterModal">
        <div class="modal-card" style="max-width: 480px; width: 95%;">
            <div class="modal-header">
                <h3 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Confirm Semester Deletion</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteSemesterModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_semester">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="semester_id" id="del_sem_id" value="">

                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="del_sem_name"></strong>?</p>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                        This will remove the term from the institution calendar.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteSemesterModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Semester</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PROMOTE COHORT BY DEGREE & SEMESTER MODAL -->
    <div class="modal-backdrop" id="promoteCohortModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 580px; width: 95%; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.7);">
            <div class="modal-header" style="background: rgba(34, 197, 94, 0.08); border-bottom: 1px solid rgba(34, 197, 94, 0.25); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--success); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-graduation-cap"></i> Execute Semester Promotion by Degree
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('promoteCohortModal')">&times;</button>
            </div>
            <form method="POST" action="semesters.php" onsubmit="return confirmCohortPromotion(this);">
                <input type="hidden" name="action" value="promote_cohort">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                        <div style="font-size: 13.5px; color: var(--text-primary); font-weight: 600;">
                            <i class="fas fa-shield-alt" style="color: var(--success);"></i> Role-Restricted Promotion Engine
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Only Institutional Administrators & Super Administrators can execute semester advancements. When executed, all eligible students receive a confirmation notification in their system inbox.
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                                Target Degree / Department *
                            </label>
                            <select name="degree" id="modalPromDegree" class="form-control" onchange="updateCandidatePreview('modal')" required>
                                <option value="all">All Degrees & Programs</option>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d['code']); ?>">
                                            <?php echo htmlspecialchars($d['name'] . ' (' . $d['code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="BCA">BCA (Bachelor of Computer Applications)</option>
                                    <option value="BBA">BBA (Bachelor of Business Administration)</option>
                                    <option value="CSE">CSE (Computer Science & Engineering)</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                                Current Semester (From) *
                            </label>
                            <select name="from_semester" id="modalPromFromSem" class="form-control" onchange="updateCandidatePreview('modal')" required>
                                <option value="all">All Semesters</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                                Advance To (Target Term) *
                            </label>
                            <select name="target_semester" id="modalPromTargetSem" class="form-control" required>
                                <option value="next">Advance to Next Semester (+1 Auto)</option>
                                <?php for ($i = 2; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                <?php endfor; ?>
                                <option value="Graduated">Graduated / Program Completed</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                                Promotion Scope *
                            </label>
                            <select name="scope" id="modalPromScope" class="form-control" onchange="updateCandidatePreview('modal')">
                                <option value="all">All Enrolled Students</option>
                                <option value="opted_in">Only Students with Opt-In Status</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                            Administrative Notes / Audit Remarks
                        </label>
                        <input type="text" name="notes" class="form-control" value="Cohort semester promotion authorized by Administration" required>
                    </div>

                    <!-- Live candidate preview container -->
                    <div id="modalCandidateBox" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fas fa-users" style="color: var(--primary);"></i> Candidate Roster Preview</span>
                            <span id="modalCandidateCountBadge" class="badge badge-success" style="font-size: 11px;">0 Eligible Students</span>
                        </div>
                        <div id="modalCandidateList" style="max-height: 110px; overflow-y: auto; margin-top: 8px; font-size: 12px; color: var(--text-muted); line-height: 1.6;">
                            Select degree and semester above to view eligible students.
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('promoteCohortModal')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="font-weight: 700;">
                        <i class="fas fa-check-double"></i> Confirm & Execute Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('show');
                el.style.display = 'flex';
                el.style.opacity = '1';
                el.style.pointerEvents = 'auto';
                document.body.style.overflow = 'hidden';
                if (id === 'promoteCohortModal') {
                    updateCandidatePreview('modal');
                }
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('show');
                el.style.display = 'none';
                el.style.opacity = '';
                el.style.pointerEvents = '';
                document.body.style.overflow = '';
            }
        }

        function openCreateSemesterModal() {
            const form = document.getElementById('createSemesterForm');
            if (form) form.reset();
            openModal('createSemesterModal');
        }

        function autoSuggestSemName(prefix) {
            const numEl = document.getElementById(prefix + '_sem_num');
            const yearEl = document.getElementById(prefix + '_sem_year');
            const num = numEl ? numEl.value : '1';
            const year = yearEl ? yearEl.value : '2026-2027';

            const termNameInput = document.getElementById(prefix + '_sem_name');
            if (termNameInput && (!termNameInput.value || termNameInput.value.startsWith('Semester '))) {
                const season = (parseInt(num) % 2 === 1) ? 'Fall' : 'Spring';
                const yrPart = year ? year.split('-')[0] : '';
                termNameInput.value = 'Semester ' + num + ' (' + season + (yrPart ? ' ' + yrPart : '') + ')';
            }
        }

        function openEditSemesterModal(sem) {
            document.getElementById('edit_sem_id').value = sem.id;
            document.getElementById('edit_sem_course').value = sem.course_id;
            document.getElementById('edit_sem_num').value = sem.semester_number;
            document.getElementById('edit_sem_year').value = sem.academic_year;
            document.getElementById('edit_sem_name').value = sem.name;
            document.getElementById('edit_sem_start').value = sem.start_date || '';
            document.getElementById('edit_sem_end').value = sem.end_date || '';
            document.getElementById('edit_sem_status').value = sem.status || 'active';
            document.getElementById('edit_sem_desc').value = sem.description || '';

            openModal('editSemesterModal');
        }

        function confirmDeleteSemester(sem) {
            document.getElementById('del_sem_id').value = sem.id;
            document.getElementById('del_sem_name').textContent = sem.name + ' (' + sem.course_name + ')';
            openModal('deleteSemesterModal');
        }

        // Live candidate preview for promotion console and modal
        async function updateCandidatePreview(prefix) {
            const degEl = document.getElementById(prefix === 'modal' ? 'modalPromDegree' : 'inlinePromDegree');
            const semEl = document.getElementById(prefix === 'modal' ? 'modalPromFromSem' : 'inlinePromFromSem');
            const scopeEl = document.getElementById(prefix === 'modal' ? 'modalPromScope' : 'inlinePromScope');

            if (!degEl || !semEl) return;

            const deg = degEl.value;
            const sem = semEl.value;
            const onlyOptedIn = scopeEl && scopeEl.value === 'opted_in' ? '1' : '0';

            try {
                const res = await fetch(`semesters.php?action=preview_candidates&degree=${encodeURIComponent(deg)}&from_semester=${encodeURIComponent(sem)}&only_opted_in=${onlyOptedIn}`);
                const data = await res.json();

                if (prefix === 'modal') {
                    const badge = document.getElementById('modalCandidateCountBadge');
                    const list = document.getElementById('modalCandidateList');
                    if (badge) badge.textContent = `${data.count || 0} Eligible Students`;
                    if (list) {
                        if (data.candidates && data.candidates.length > 0) {
                            list.innerHTML = data.candidates.map(c => `
                                <div style="display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px dashed var(--border-color);">
                                    <span><strong>${escapeHTML(c.first_name + ' ' + c.last_name)}</strong> (${escapeHTML(c.student_id || c.roll_number || '')})</span>
                                    <span>Dept: <strong style="color:var(--primary);">${escapeHTML(c.department)}</strong> | Sem <strong>${escapeHTML(c.semester)}</strong></span>
                                </div>
                            `).join('');
                        } else {
                            list.innerHTML = `<span style="color:var(--text-muted);"><i class="fas fa-info-circle"></i> No eligible active students found for ${escapeHTML(deg)} (Semester ${escapeHTML(sem)}).</span>`;
                        }
                    }
                } else {
                    const textEl = document.getElementById('inlineCandidateCountText');
                    if (textEl) {
                        const degLabel = deg === 'all' ? 'all departments' : deg;
                        const semLabel = sem === 'all' ? 'all semesters' : `Semester ${sem}`;
                        textEl.innerHTML = `<strong>${data.count || 0}</strong> eligible active student(s) found in <strong>${escapeHTML(degLabel)}</strong> (${escapeHTML(semLabel)}).`;
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        function confirmCohortPromotion(form) {
            const deg = form.degree ? form.degree.options[form.degree.selectedIndex].text : '';
            const sem = form.from_semester ? form.from_semester.options[form.from_semester.selectedIndex].text : '';
            const target = form.target_semester ? form.target_semester.options[form.target_semester.selectedIndex].text : '';
            return confirm(`Are you sure you want to execute semester promotion for:\n\n• Degree: ${deg}\n• Current Term: ${sem}\n• Target Term: ${target}\n\nThis will advance eligible students and dispatch confirmation notifications.`);
        }

        // Initialize candidate counter on load
        document.addEventListener('DOMContentLoaded', function() {
            updateCandidatePreview('inline');
        });

        window.addEventListener('click', function(e) {
            ['createSemesterModal', 'editSemesterModal', 'deleteSemesterModal', 'promoteCohortModal'].forEach(id => {
                const el = document.getElementById(id);
                if (el && e.target === el) {
                    closeModal(id);
                }
            });
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['createSemesterModal', 'editSemesterModal', 'deleteSemesterModal', 'promoteCohortModal'].forEach(id => {
                    closeModal(id);
                });
            }
        });
    </script>
</body>
</html>
