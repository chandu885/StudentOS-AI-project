<?php
// frontend/super-admin/subjects.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/models/Academic.php';
require_once __DIR__ . '/../../backend/models/SystemModel.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$academic = new Academic();
$sysModel = new SystemModel();
$conn = getDbConnection();

$successMsg = '';
$errorMsg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $submittedCsrf)) {
        $errorMsg = 'Security validation failed (CSRF mismatch). Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'create_subject';

        if ($action === 'create_subject') {
            $data = [
                'course_id'     => (int)($_POST['course_id'] ?? 0),
                'department_id' => (int)($_POST['department_id'] ?? 0),
                'faculty_id'    => !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null,
                'code'          => trim($_POST['code'] ?? ''),
                'name'          => trim($_POST['name'] ?? ''),
                'semester'      => trim((string)($_POST['semester'] ?? '1')),
                'credits'       => (int)($_POST['credits'] ?? 3),
                'type'          => $_POST['type'] ?? 'core',
                'syllabus'      => trim($_POST['syllabus'] ?? ''),
                'status'        => $_POST['status'] ?? 'active'
            ];

            $res = $academic->createSubject($data, $userId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_CREATE_SUBJECT', 'subjects', $res['subject_id'], "Super Admin created subject '{$data['name']}' [{$data['code']}].");
            } else {
                $errorMsg = $res['error'];
            }
        } elseif ($action === 'edit_subject') {
            $subId = (int)($_POST['subject_id'] ?? 0);
            $data = [
                'course_id'     => (int)($_POST['course_id'] ?? 0),
                'department_id' => (int)($_POST['department_id'] ?? 0),
                'faculty_id'    => !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null,
                'code'          => trim($_POST['code'] ?? ''),
                'name'          => trim($_POST['name'] ?? ''),
                'semester'      => trim((string)($_POST['semester'] ?? '1')),
                'credits'       => (int)($_POST['credits'] ?? 3),
                'type'          => $_POST['type'] ?? 'core',
                'syllabus'      => trim($_POST['syllabus'] ?? ''),
                'status'        => $_POST['status'] ?? 'active'
            ];

            $res = $academic->updateSubject($subId, $data);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_UPDATE_SUBJECT', 'subjects', $subId, "Super Admin updated subject '{$data['name']}' [{$data['code']}].");
            } else {
                $errorMsg = $res['error'];
            }
        } elseif ($action === 'delete_subject') {
            $subId = (int)($_POST['subject_id'] ?? 0);
            $sub = $academic->getSubjectById($subId);

            $res = $academic->deleteSubject($subId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $subName = $sub['name'] ?? "Subject #{$subId}";
                $sysModel->logAudit($userId, 'SUPER_ADMIN_DELETE_SUBJECT', 'subjects', $subId, "Super Admin deleted/deactivated subject '{$subName}'.");
            } else {
                $errorMsg = $res['error'];
            }
        }
    }
}

// Filters
$filterCourse = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$filterSem = !empty($_GET['semester']) ? trim($_GET['semester']) : null;
$filterDept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$searchQuery = !empty($_GET['q']) ? trim($_GET['q']) : null;

$subjects = $academic->getSubjects($filterCourse, $filterSem, $filterDept, $searchQuery);
$departments = $academic->getDepartments();
$courses = $academic->getCourses();

// Faculty members
$facultyList = [];
if ($conn) {
    $facRes = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role_id = 3 AND deleted_at IS NULL ORDER BY first_name ASC");
    if ($facRes) {
        $facultyList = $facRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Stats
$totalSubjectsCount = count($subjects);
$activeSubjectsCount = 0;
$totalCreditsSum = 0;
$facultyAssignedSet = [];

foreach ($subjects as $s) {
    if (($s['status'] ?? 'active') === 'active') {
        $activeSubjectsCount++;
    }
    $totalCreditsSum += (int)($s['credits'] ?? 0);
    if (!empty($s['faculty_id'])) {
        $facultyAssignedSet[$s['faculty_id']] = true;
    }
}
$assignedFacultyCount = count($facultyAssignedSet);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject & Curriculum Management - Super Admin - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .type-badge-core {
            background: rgba(99, 102, 241, 0.15);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .type-badge-elective {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .type-badge-lab {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .stat-grid-subjects {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card-sub {
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
                        <h1>Institutional Subject Catalog</h1>
                        <p class="page-subtitle">Super Admin control of academic subjects, department curricula, faculty allocations, and credit schemas</p>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="semesters.php" class="btn btn-secondary">
                            <i class="fas fa-calendar-alt"></i> Manage Semesters
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openCreateSubjectModal()">
                            <i class="fas fa-plus-circle"></i> Create Subject
                        </button>
                    </div>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                        <span><?php echo htmlspecialchars($successMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
                        <span><?php echo htmlspecialchars($errorMsg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="stat-grid-subjects">
                    <div class="stat-card-sub">
                        <div class="stat-icon-wrap" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Total Subjects</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $totalSubjectsCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-sub">
                        <div class="stat-icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Active Catalog</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $activeSubjectsCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-sub">
                        <div class="stat-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                            <i class="fas fa-award"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Total Credits</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $totalCreditsSum; ?> pts</div>
                        </div>
                    </div>
                    <div class="stat-card-sub">
                        <div class="stat-icon-wrap" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Assigned Faculty</div>
                            <div style="font-size: 22px; font-weight: 700; color: var(--text-primary);"><?php echo $assignedFacultyCount; ?> Professors</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <h3><i class="fas fa-book"></i> Active Master Subject Catalog</h3>
                        
                        <!-- Filter Bar -->
                        <form method="GET" action="subjects.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0;">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>" class="form-control" placeholder="Search code or name..." style="width: 190px; height: 36px; font-size: 13px;">
                            
                            <select name="course_id" class="form-control" style="width: 150px; height: 36px; font-size: 13px;" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo ($filterCourse === (int)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="semester" class="form-control" style="width: 130px; height: 36px; font-size: 13px;" onchange="this.form.submit()">
                                <option value="">All Sems</option>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?php echo $s; ?>" <?php echo ($filterSem === (string)$s) ? 'selected' : ''; ?>>
                                        Semester <?php echo $s; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>

                            <?php if (!empty($searchQuery) || !empty($filterCourse) || !empty($filterSem)): ?>
                                <a href="subjects.php" class="btn btn-sm btn-secondary" title="Clear Filters" style="height: 36px; display: flex; align-items: center;">
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
                                        <th style="width: 100px;">Code</th>
                                        <th>Subject Name</th>
                                        <th>Course & Dept</th>
                                        <th>Semester</th>
                                        <th>Credits</th>
                                        <th>Type</th>
                                        <th>Assigned Instructor</th>
                                        <th>Status</th>
                                        <th style="text-align: right; width: 110px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($subjects)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                                <i class="fas fa-book-open" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                                No subjects found in the catalog.
                                                <div style="margin-top: 10px;">
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="openCreateSubjectModal()">
                                                        <i class="fas fa-plus"></i> Create Subject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($subjects as $s): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-secondary" style="font-family: monospace; font-size: 12px; font-weight: 700;">
                                                        <?php echo htmlspecialchars($s['code']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                                                    <?php if (!empty($s['syllabus'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($s['syllabus']); ?>">
                                                            <?php echo htmlspecialchars($s['syllabus']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($s['course_name'] ?? 'N/A'); ?></span>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($s['department_name'] ?? ''); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info" style="font-size: 11px;">
                                                        <?php 
                                                        $semDisplay = is_numeric($s['semester']) ? 'Semester ' . $s['semester'] : $s['semester'];
                                                        echo htmlspecialchars($semDisplay); 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo (int)$s['credits']; ?></strong> pts
                                                </td>
                                                <td>
                                                    <?php 
                                                    $type = strtolower($s['type'] ?? 'core');
                                                    $class = 'type-badge-' . ($type === 'lab' ? 'lab' : ($type === 'elective' ? 'elective' : 'core'));
                                                    ?>
                                                    <span class="<?php echo $class; ?>"><?php echo ucfirst($type); ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($s['faculty_first'])): ?>
                                                        <div style="display: flex; align-items: center; gap: 6px;">
                                                            <i class="fas fa-user-tie" style="color: var(--primary); font-size: 12px;"></i>
                                                            <div>
                                                                <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($s['faculty_first'] . ' ' . $s['faculty_last']); ?></span>
                                                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($s['faculty_email'] ?? ''); ?></div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-size: 12px; font-style: italic;">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (($s['status'] ?? 'active') === 'active'): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditSubjectModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Subject">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick='confirmDeleteSubject(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Delete Subject">
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

    <!-- CREATE SUBJECT MODAL -->
    <div class="modal-backdrop" id="createSubjectModal">
        <div class="modal-card" style="max-width: 680px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add New Subject</h3>
                <button type="button" class="modal-close" onclick="closeModal('createSubjectModal')">&times;</button>
            </div>
            <form method="POST" action="" id="createSubjectForm">
                <input type="hidden" name="action" value="create_subject">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sub_name">Subject Title <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="name" id="create_sub_name" class="form-control" placeholder="e.g. Distributed Operating Systems" required>
                        </div>
                        <div class="form-group">
                            <label for="create_sub_code">Course Code <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="code" id="create_sub_code" class="form-control" placeholder="e.g. CS506" required style="text-transform: uppercase;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sub_course">Degree Course / Program <span style="color: var(--danger);">*</span></label>
                            <select name="course_id" id="create_sub_course" class="form-control" required onchange="syncDepartment('create')">
                                <option value="">Select Course...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" data-dept="<?php echo (int)$c['department_id']; ?>">
                                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_sub_dept">Department</label>
                            <select name="department_id" id="create_sub_dept" class="form-control">
                                <option value="">Auto-assigned from Course</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sub_sem">Semester <span style="color: var(--danger);">*</span></label>
                            <select name="semester" id="create_sub_sem" class="form-control" required>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?php echo $s; ?>">Semester <?php echo $s; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_sub_credits">Credits <span style="color: var(--danger);">*</span></label>
                            <input type="number" name="credits" id="create_sub_credits" class="form-control" value="4" min="1" max="10" required>
                        </div>
                        <div class="form-group">
                            <label for="create_sub_type">Subject Type</label>
                            <select name="type" id="create_sub_type" class="form-control">
                                <option value="core">Core Theory</option>
                                <option value="elective">Elective</option>
                                <option value="lab">Practical / Lab</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="create_sub_faculty">Assigned Faculty Instructor</label>
                            <select name="faculty_id" id="create_sub_faculty" class="form-control">
                                <option value="">-- Unassigned (Select later) --</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <option value="<?php echo (int)$fac['id']; ?>">
                                        <?php echo htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_sub_status">Status</label>
                            <select name="status" id="create_sub_status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="create_sub_syllabus">Syllabus Outline & Topics</label>
                        <textarea name="syllabus" id="create_sub_syllabus" class="form-control" rows="3" placeholder="Module 1: Foundations, Module 2: Architecture, Module 3: Advanced Applications..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SUBJECT MODAL -->
    <div class="modal-backdrop" id="editSubjectModal">
        <div class="modal-card" style="max-width: 680px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Subject</h3>
                <button type="button" class="modal-close" onclick="closeModal('editSubjectModal')">&times;</button>
            </div>
            <form method="POST" action="" id="editSubjectForm">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="subject_id" id="edit_sub_id" value="">

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sub_name">Subject Title <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="name" id="edit_sub_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_sub_code">Course Code <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="code" id="edit_sub_code" class="form-control" required style="text-transform: uppercase;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sub_course">Degree Course / Program <span style="color: var(--danger);">*</span></label>
                            <select name="course_id" id="edit_sub_course" class="form-control" required onchange="syncDepartment('edit')">
                                <option value="">Select Course...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" data-dept="<?php echo (int)$c['department_id']; ?>">
                                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_sub_dept">Department</label>
                            <select name="department_id" id="edit_sub_dept" class="form-control">
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sub_sem">Semester <span style="color: var(--danger);">*</span></label>
                            <select name="semester" id="edit_sub_sem" class="form-control" required>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?php echo $s; ?>">Semester <?php echo $s; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_sub_credits">Credits <span style="color: var(--danger);">*</span></label>
                            <input type="number" name="credits" id="edit_sub_credits" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_sub_type">Subject Type</label>
                            <select name="type" id="edit_sub_type" class="form-control">
                                <option value="core">Core Theory</option>
                                <option value="elective">Elective</option>
                                <option value="lab">Practical / Lab</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="edit_sub_faculty">Assigned Faculty Instructor</label>
                            <select name="faculty_id" id="edit_sub_faculty" class="form-control">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <option value="<?php echo (int)$fac['id']; ?>">
                                        <?php echo htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_sub_status">Status</label>
                            <select name="status" id="edit_sub_status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_sub_syllabus">Syllabus Outline & Topics</label>
                        <textarea name="syllabus" id="edit_sub_syllabus" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <div class="modal-backdrop" id="deleteSubjectModal">
        <div class="modal-card" style="max-width: 480px; width: 95%;">
            <div class="modal-header">
                <h3 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteSubjectModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_subject">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="subject_id" id="del_sub_id" value="">

                <div class="modal-body">
                    <p>Are you sure you want to delete subject <strong id="del_sub_name"></strong>?</p>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                        If students have already enrolled in this subject, it will be marked inactive to safeguard academic transcripts.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Subject</button>
                </div>
            </form>
        </div>
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

        function openCreateSubjectModal() {
            const form = document.getElementById('createSubjectForm');
            if (form) form.reset();
            openModal('createSubjectModal');
        }

        function syncDepartment(mode) {
            const courseSelect = document.getElementById(mode + '_sub_course');
            const deptSelect = document.getElementById(mode + '_sub_dept');
            if (!courseSelect || !deptSelect) return;

            const selectedOption = courseSelect.options[courseSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.dept) {
                deptSelect.value = selectedOption.dataset.dept;
            }
        }

        function openEditSubjectModal(sub) {
            document.getElementById('edit_sub_id').value = sub.id;
            document.getElementById('edit_sub_name').value = sub.name;
            document.getElementById('edit_sub_code').value = sub.code;
            document.getElementById('edit_sub_course').value = sub.course_id;
            document.getElementById('edit_sub_dept').value = sub.department_id;
            
            let cleanSem = (sub.semester || '1').replace(/[^0-9]/g, '');
            document.getElementById('edit_sub_sem').value = cleanSem || '1';
            
            document.getElementById('edit_sub_credits').value = sub.credits;
            document.getElementById('edit_sub_type').value = (sub.type || 'core').toLowerCase();
            document.getElementById('edit_sub_faculty').value = sub.faculty_id || '';
            document.getElementById('edit_sub_status').value = sub.status || 'active';
            document.getElementById('edit_sub_syllabus').value = sub.syllabus || '';

            openModal('editSubjectModal');
        }

        function confirmDeleteSubject(sub) {
            document.getElementById('del_sub_id').value = sub.id;
            document.getElementById('del_sub_name').textContent = sub.name + ' (' + sub.code + ')';
            openModal('deleteSubjectModal');
        }

        window.addEventListener('click', function(e) {
            ['createSubjectModal', 'editSubjectModal', 'deleteSubjectModal'].forEach(id => {
                const el = document.getElementById(id);
                if (el && e.target === el) {
                    closeModal(id);
                }
            });
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['createSubjectModal', 'editSubjectModal', 'deleteSubjectModal'].forEach(id => {
                    closeModal(id);
                });
            }
        });
    </script>
</body>
</html>
