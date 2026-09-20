<?php
// frontend/super-admin/departments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$currentUserId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $submittedCsrf)) {
        $errorMsg = 'Security validation failed (CSRF mismatch). Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($action === 'create') {
            $name = sanitize($_POST['name'] ?? '');
            $code = strtoupper(sanitize($_POST['code'] ?? ''));
            $headId = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
            $description = sanitize($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if (empty($name) || empty($code)) {
                $errorMsg = 'Department Name and Department Code are required.';
            } elseif ($db) {
                // Check duplicate code
                $dup = $db->prepare("SELECT id FROM departments WHERE code = ?");
                $dup->bind_param("s", $code);
                $dup->execute();
                if ($dup->get_result()->fetch_assoc()) {
                    $errorMsg = "A department with code '$code' already exists in the system.";
                    $dup->close();
                } else {
                    $dup->close();
                    if ($headId) {
                        $stmt = $db->prepare("INSERT INTO departments (code, name, description, head_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                        $stmt->bind_param("sssis", $code, $name, $description, $headId, $status);
                    } else {
                        $stmt = $db->prepare("INSERT INTO departments (code, name, description, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                        $stmt->bind_param("ssss", $code, $name, $description, $status);
                    }

                    if ($stmt && $stmt->execute()) {
                        $newDeptId = $db->insert_id;
                        $stmt->close();

                        // Audit Log
                        $details = "Super Admin created department '$name' ($code), ID #$newDeptId";
                        $aud = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'DEPARTMENT_CREATED', 'departments', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $newDeptId, $details, $ip);
                            $aud->execute();
                            $aud->close();
                        }

                        $successMsg = "Department '$name' ($code) has been established successfully!";
                    } else {
                        $errorMsg = 'Failed to create department: ' . ($db->error ?? 'Database error');
                    }
                }
            }
        } elseif ($action === 'edit') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $code = strtoupper(sanitize($_POST['code'] ?? ''));
            $headId = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
            $description = sanitize($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($deptId <= 0 || empty($name) || empty($code)) {
                $errorMsg = 'Department ID, Name, and Code are required.';
            } elseif ($db) {
                // Check duplicate code
                $dup = $db->prepare("SELECT id FROM departments WHERE code = ? AND id != ?");
                $dup->bind_param("si", $code, $deptId);
                $dup->execute();
                if ($dup->get_result()->fetch_assoc()) {
                    $errorMsg = "Another department with code '$code' already exists.";
                    $dup->close();
                } else {
                    $dup->close();
                    if ($headId) {
                        $stmt = $db->prepare("UPDATE departments SET name = ?, code = ?, head_id = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("sssisi", $name, $code, $headId, $description, $status, $deptId);
                    } else {
                        $stmt = $db->prepare("UPDATE departments SET name = ?, code = ?, head_id = NULL, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("ssssi", $name, $code, $description, $status, $deptId);
                    }

                    if ($stmt && $stmt->execute()) {
                        $stmt->close();

                        // Audit Log
                        $details = "Super Admin updated department #$deptId: '$name' ($code), Status: $status";
                        $aud = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'DEPARTMENT_UPDATED', 'departments', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $deptId, $details, $ip);
                            $aud->execute();
                            $aud->close();
                        }

                        $successMsg = "Department '$name' ($code) details updated successfully!";
                    } else {
                        $errorMsg = 'Failed to update department: ' . ($db->error ?? 'Database error');
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? 'active');
            if ($deptId > 0 && in_array($newStatus, ['active', 'inactive']) && $db) {
                $stmt = $db->prepare("UPDATE departments SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $newStatus, $deptId);
                if ($stmt->execute()) {
                    $stmt->close();

                    $details = "Super Admin changed department #$deptId status to " . strtoupper($newStatus);
                    $aud = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'DEPARTMENT_STATUS_TOGGLED', 'departments', ?, ?, ?)");
                    if ($aud) {
                        $aud->bind_param("iiss", $currentUserId, $deptId, $details, $ip);
                        $aud->execute();
                        $aud->close();
                    }

                    $successMsg = "Department status changed to " . strtoupper($newStatus) . " successfully.";
                } else {
                    $errorMsg = 'Failed to toggle status: ' . $db->error;
                }
            }
        } elseif ($action === 'delete') {
            $delId = (int)($_POST['department_id'] ?? 0);
            $deleteMode = sanitize($_POST['delete_mode'] ?? 'deactivate'); // 'deactivate' or 'permanent'

            if ($delId > 0 && $db) {
                // Dependency check
                $depInfoStmt = $db->prepare(
                    "SELECT d.name, d.code,
                            (SELECT COUNT(*) FROM faculty_profiles fp JOIN users fu ON fp.user_id = fu.id WHERE fp.department_id = ? AND fu.deleted_at IS NULL) AS fac_count,
                            (SELECT COUNT(*) FROM student_profiles sp JOIN users su ON sp.user_id = su.id WHERE sp.department_id = ? AND su.deleted_at IS NULL) AS stu_count,
                            (SELECT COUNT(*) FROM courses c WHERE c.department_id = ? AND c.status = 'active') AS courses_count
                     FROM departments d
                     WHERE d.id = ?"
                );
                $depInfoStmt->bind_param("iiii", $delId, $delId, $delId, $delId);
                $depInfoStmt->execute();
                $depInfo = $depInfoStmt->get_result()->fetch_assoc();
                $depInfoStmt->close();

                $deptName = $depInfo['name'] ?? 'Department';
                $facCount = (int)($depInfo['fac_count'] ?? 0);
                $stuCount = (int)($depInfo['stu_count'] ?? 0);
                $coursesCount = (int)($depInfo['courses_count'] ?? 0);

                if ($deleteMode === 'deactivate') {
                    $stmt = $db->prepare("UPDATE departments SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $delId);
                    if ($stmt->execute()) {
                        $stmt->close();

                        // Audit Log
                        $details = "Super Admin deactivated department '$deptName' (#$delId). Preserved: $stuCount student(s), $facCount faculty.";
                        $aud = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'DEPARTMENT_DEACTIVATED', 'departments', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $delId, $details, $ip);
                            $aud->execute();
                            $aud->close();
                        }

                        $successMsg = "Department '$deptName' (#$delId) deactivated. Enrolled students ($stuCount) and faculty ($facCount) remain safely archived.";
                    } else {
                        $errorMsg = 'Failed to deactivate department: ' . $db->error;
                    }
                } elseif ($deleteMode === 'permanent') {
                    if ($stuCount > 0 || $facCount > 0 || $coursesCount > 0) {
                        $errorMsg = "Cannot permanently delete '$deptName': It contains $stuCount student(s), $facCount faculty member(s), and $coursesCount active program(s). Please deactivate the department instead or reassign its members.";
                    } else {
                        $del = $db->prepare("DELETE FROM departments WHERE id = ?");
                        $del->bind_param("i", $delId);
                        if ($del->execute()) {
                            $del->close();

                            $details = "Super Admin permanently deleted department '$deptName' (#$delId)";
                            $aud = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'DEPARTMENT_PERMANENTLY_DELETED', 'departments', ?, ?, ?)");
                            if ($aud) {
                                $aud->bind_param("iiss", $currentUserId, $delId, $details, $ip);
                                $aud->execute();
                                $aud->close();
                            }

                            $successMsg = "Department '$deptName' has been permanently deleted from the database.";
                        } else {
                            $errorMsg = 'Failed to delete department: ' . $db->error;
                        }
                    }
                }
            }
        }
    }
}

// Fetch faculty for HOD select (Faculty or Admin)
$facultyList = [];
if ($db) {
    $fRes = $db->query(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, COALESCE(fp.designation, 'Professor / Faculty') AS designation, r.name AS role_name
         FROM users u 
         LEFT JOIN faculty_profiles fp ON fp.user_id = u.id 
         LEFT JOIN roles r ON u.role_id = r.id
         WHERE u.role_id IN (2, 3) AND u.deleted_at IS NULL 
         ORDER BY u.first_name ASC"
    );
    if ($fRes) {
        $facultyList = $fRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch departments with real-time metrics
$departments = [];
$totalDepts = 0;
$activeDepts = 0;
$inactiveDepts = 0;
$totalStudentsEnrolled = 0;
$totalFacultyAssigned = 0;
$totalDegreePrograms = 0;

$statusFilter = $_GET['status'] ?? 'all';

if ($db) {
    $q = "SELECT d.*, 
                 CONCAT(u.first_name, ' ', u.last_name) AS hod_name,
                 u.email AS hod_email,
                 (SELECT COUNT(*) FROM faculty_profiles fp JOIN users fu ON fp.user_id = fu.id WHERE fp.department_id = d.id AND fu.deleted_at IS NULL) AS faculty_count,
                 (SELECT COUNT(*) FROM student_profiles sp JOIN users su ON sp.user_id = su.id WHERE sp.department_id = d.id AND su.deleted_at IS NULL) AS student_count,
                 (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.id AND c.status = 'active') AS courses_count,
                 (SELECT COUNT(*) FROM subjects sub WHERE sub.department_id = d.id) AS subjects_count
          FROM departments d
          LEFT JOIN users u ON d.head_id = u.id ";

    if ($statusFilter === 'active') {
        $q .= "WHERE d.status = 'active' ";
    } elseif ($statusFilter === 'inactive') {
        $q .= "WHERE d.status = 'inactive' ";
    }

    $q .= "ORDER BY d.id ASC";
    $dRes = $db->query($q);
    if ($dRes) {
        $departments = $dRes->fetch_all(MYSQLI_ASSOC);
    }

    // Compute KPI stats across all departments
    $statRes = $db->query(
        "SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
         FROM departments"
    );
    if ($statRes && $row = $statRes->fetch_assoc()) {
        $totalDepts = (int)$row['total_count'];
        $activeDepts = (int)$row['active_count'];
        $inactiveDepts = (int)$row['inactive_count'];
    }

    $stuRes = $db->query("SELECT COUNT(*) AS cnt FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.deleted_at IS NULL AND sp.department_id IS NOT NULL");
    if ($stuRes && $row = $stuRes->fetch_assoc()) {
        $totalStudentsEnrolled = (int)$row['cnt'];
    }

    $facRes = $db->query("SELECT COUNT(*) AS cnt FROM faculty_profiles fp JOIN users u ON fp.user_id = u.id WHERE u.deleted_at IS NULL AND fp.department_id IS NOT NULL");
    if ($facRes && $row = $facRes->fetch_assoc()) {
        $totalFacultyAssigned = (int)$row['cnt'];
    }

    $cRes = $db->query("SELECT COUNT(*) AS cnt FROM courses WHERE status = 'active'");
    if ($cRes && $row = $cRes->fetch_assoc()) {
        $totalDegreePrograms = (int)$row['cnt'];
    }
}
?>
<?php
$pageTitle = 'Academic Departments Governance - Super Admin';
include_once __DIR__ . '/../components/header.php';
?>

                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-building" style="color: var(--primary);"></i> Academic Departments Governance</h1>
                        <p class="page-subtitle">Configure faculties, assign department heads (HODs), track student enrollments & program offerings</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addDeptModal')">
                            <i class="fas fa-plus-circle"></i> Create New Department
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 16px;"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 16px;"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- KPI Metric Summary Grid -->
                <div class="stats-grid" style="margin-bottom: 24px;">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalDepts; ?></span>
                            <span class="stat-label">Total Departments</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $activeDepts; ?></span>
                            <span class="stat-label">Active Catalogs</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--danger);"><i class="fas fa-pause-circle"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $inactiveDepts; ?></span>
                            <span class="stat-label">Inactive / Suspended</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #8B5CF6;"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalStudentsEnrolled); ?></span>
                            <span class="stat-label">Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent);"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalFacultyAssigned); ?></span>
                            <span class="stat-label">Faculty Appointed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($totalDegreePrograms); ?></span>
                            <span class="stat-label">Degree Programs</span>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Status Filter:</span>
                        <a href="departments.php?status=all" class="btn <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">
                            All (<?php echo $totalDepts; ?>)
                        </a>
                        <a href="departments.php?status=active" class="btn <?php echo $statusFilter === 'active' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i> Active (<?php echo $activeDepts; ?>)
                        </a>
                        <a href="departments.php?status=inactive" class="btn <?php echo $statusFilter === 'inactive' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">
                            <i class="fas fa-pause-circle" style="color: var(--danger);"></i> Inactive (<?php echo $inactiveDepts; ?>)
                        </a>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="position: relative; width: 260px;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"></i>
                            <input type="text" id="deptSearchInput" class="form-control" placeholder="Search by name, code, HOD..." style="padding-left: 32px; font-size: 12.5px;" onkeyup="filterDepartmentsTable()">
                        </div>
                    </div>
                </div>

                <!-- Departments Master Table Card -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-network-wired" style="color: var(--primary);"></i> Institutional Department Registry (<?php echo count($departments); ?>)</h3>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Active Academic Divisions
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="departmentsTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Department Name</th>
                                        <th>Head of Department (HOD)</th>
                                        <th>Students</th>
                                        <th>Faculty Staff</th>
                                        <th>Programs</th>
                                        <th>Subjects</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departments)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                                <i class="fas fa-building" style="font-size: 28px; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                                                No departments found matching current filter. Click "Create New Department" to register one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <?php 
                                            $isActive = ($dept['status'] ?? 'active') === 'active';
                                            ?>
                                            <tr class="dept-row">
                                                <td>
                                                    <span class="badge badge-primary" style="font-size: 12px; font-weight: 700; letter-spacing: 0.5px;">
                                                        <?php echo htmlspecialchars($dept['code']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 13.5px;" class="dept-name-cell">
                                                        <?php echo htmlspecialchars($dept['name']); ?>
                                                    </div>
                                                    <?php if (!empty($dept['description'])): ?>
                                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px; max-width: 320px; line-height: 1.3;">
                                                            <?php echo htmlspecialchars($dept['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="dept-hod-cell">
                                                    <?php if (!empty($dept['hod_name'])): ?>
                                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 13px;">
                                                            <?php echo htmlspecialchars($dept['hod_name']); ?>
                                                        </div>
                                                        <?php if (!empty($dept['hod_email'])): ?>
                                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                                <code><?php echo htmlspecialchars($dept['hod_email']); ?></code>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic; font-size: 12px;">
                                                            <i class="fas fa-user-clock" style="margin-right: 4px;"></i> Not Appointed
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #8B5CF6; border: 1px solid rgba(139, 92, 246, 0.3); font-size: 11.5px;">
                                                        <i class="fas fa-user-graduate" style="margin-right: 3px;"></i> <?php echo (int)($dept['student_count'] ?? 0); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: rgba(14, 165, 233, 0.12); color: var(--info); border: 1px solid rgba(14, 165, 233, 0.3); font-size: 11.5px;">
                                                        <i class="fas fa-chalkboard-teacher" style="margin-right: 3px;"></i> <?php echo (int)($dept['faculty_count'] ?? 0); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-outline" style="font-size: 11px;">
                                                        <?php echo (int)($dept['courses_count'] ?? 0); ?> Programs
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-outline" style="font-size: 11px;">
                                                        <?php echo (int)($dept['subjects_count'] ?? 0); ?> Subjects
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="departments.php" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="department_id" value="<?php echo (int)$dept['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="badge badge-<?php echo $isActive ? 'success' : 'danger'; ?>" style="cursor: pointer; border: none; font-size: 11px; padding: 4px 10px;" title="Click to toggle status">
                                                            <i class="fas <?php echo $isActive ? 'fa-check' : 'fa-ban'; ?>"></i> <?php echo ucfirst($dept['status'] ?? 'active'); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 4px 9px; margin-right: 4px;" title="Edit Department" onclick='openEditDeptModal(<?php echo htmlspecialchars(json_encode($dept), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        <i class="fas fa-edit" style="color: var(--primary);"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 4px 9px; color: var(--danger); border-color: rgba(239, 68, 68, 0.4);" title="Deactivate or Delete" onclick='openDeleteDeptModal(<?php echo htmlspecialchars(json_encode($dept), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
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

    <!-- Create Department Modal -->
    <div class="modal-backdrop" id="addDeptModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 540px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-building" style="color: var(--primary);"></i> Create New Academic Department
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('addDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body" style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group" style="margin: 0;">
                            <label for="addDeptName" style="font-weight: 600;">Department Name *</label>
                            <input type="text" name="name" id="addDeptName" class="form-control" placeholder="e.g. Electrical & Computer Engineering" required>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="addDeptCode" style="font-weight: 600;">Code *</label>
                            <input type="text" name="code" id="addDeptCode" class="form-control" placeholder="e.g. ECE" style="text-transform: uppercase;" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDeptHod" style="font-weight: 600;">Appoint Head of Department (HOD)</label>
                        <select name="head_id" id="addDeptHod" class="form-control">
                            <option value="">-- No HOD Appointed Initially --</option>
                            <?php foreach ($facultyList as $fac): ?>
                                <option value="<?php echo (int)$fac['id']; ?>">
                                    <?php echo htmlspecialchars($fac['name']); ?> &bull; <?php echo htmlspecialchars($fac['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted); font-size: 11.5px; display: block; margin-top: 4px;">
                            Select an appointed senior faculty member or academic administrator to lead the department.
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDeptStatus" style="font-weight: 600;">Catalog Status *</label>
                        <select name="status" id="addDeptStatus" class="form-control" required>
                            <option value="active" selected>Active (Visible for student registration & admissions)</option>
                            <option value="inactive">Inactive (Suspended from registration catalog)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="addDeptDesc" style="font-weight: 600;">Academic Scope & Description</label>
                        <textarea name="description" id="addDeptDesc" class="form-control" rows="3" placeholder="Brief outline of research interests, degree programs, curriculum focus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Save & Register Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal-backdrop" id="editDeptModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 540px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit" style="color: var(--primary);"></i> Edit Department Details
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('editDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="department_id" id="editDeptId" value="">

                <div class="modal-body" style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group" style="margin: 0;">
                            <label for="editDeptName" style="font-weight: 600;">Department Name *</label>
                            <input type="text" name="name" id="editDeptName" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="editDeptCode" style="font-weight: 600;">Code *</label>
                            <input type="text" name="code" id="editDeptCode" class="form-control" style="text-transform: uppercase;" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDeptHod" style="font-weight: 600;">Head of Department (HOD)</label>
                        <select name="head_id" id="editDeptHod" class="form-control">
                            <option value="">-- No HOD Appointed --</option>
                            <?php foreach ($facultyList as $fac): ?>
                                <option value="<?php echo (int)$fac['id']; ?>">
                                    <?php echo htmlspecialchars($fac['name']); ?> &bull; <?php echo htmlspecialchars($fac['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDeptStatus" style="font-weight: 600;">Status *</label>
                        <select name="status" id="editDeptStatus" class="form-control" required>
                            <option value="active">Active (Available for enrollment & faculty assignment)</option>
                            <option value="inactive">Inactive (Suspended)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="editDeptDesc" style="font-weight: 600;">Description</label>
                        <textarea name="description" id="editDeptDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deactivate / Delete Department Modal -->
    <div class="modal-backdrop" id="deleteDeptModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--danger); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> Department Management & Deletion
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="department_id" id="delDeptId" value="">
                
                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Department Profile</div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="delDeptName">Department Name</div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">Code: <strong id="delDeptCode">CODE</strong></div>
                    </div>

                    <div style="background: rgba(14, 165, 233, 0.07); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-database"></i> Associated Active Entities
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 12px;">
                            <div><strong>Students:</strong> <span id="delDeptStudents" class="badge badge-primary">0</span></div>
                            <div><strong>Faculty:</strong> <span id="delDeptFaculty" class="badge badge-info">0</span></div>
                            <div><strong>Programs:</strong> <span id="delDeptCourses" class="badge badge-outline">0</span></div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Select Action / Deletion Mode:</label>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.05); cursor: pointer;">
                                <input type="radio" name="delete_mode" value="deactivate" checked style="margin-top: 3px;">
                                <div>
                                    <strong style="color: var(--success); font-size: 13px;">Deactivate Department (Recommended)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        Marks status as Inactive. Suspends student registration while preserving all existing marks, records, and faculty.
                                    </div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.05); cursor: pointer;">
                                <input type="radio" name="delete_mode" value="permanent" style="margin-top: 3px;">
                                <div>
                                    <strong style="color: var(--danger); font-size: 13px;">Permanent Delete (Purge)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        Completely deletes the department from MySQL. Allowed only if 0 students, 0 faculty, and 0 programs are attached.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Confirm Action
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openEditDeptModal(dept) {
        document.getElementById('editDeptId').value = dept.id || '';
        document.getElementById('editDeptName').value = dept.name || '';
        document.getElementById('editDeptCode').value = dept.code || '';
        document.getElementById('editDeptHod').value = dept.head_id || '';
        document.getElementById('editDeptStatus').value = dept.status || 'active';
        document.getElementById('editDeptDesc').value = dept.description || '';
        openModal('editDeptModal');
    }

    function openDeleteDeptModal(dept) {
        document.getElementById('delDeptId').value = dept.id || '';
        document.getElementById('delDeptName').textContent = dept.name || 'Department';
        document.getElementById('delDeptCode').textContent = dept.code || '';
        document.getElementById('delDeptStudents').textContent = (dept.student_count || 0) + ' Students';
        document.getElementById('delDeptFaculty').textContent = (dept.faculty_count || 0) + ' Faculty';
        document.getElementById('delDeptCourses').textContent = (dept.courses_count || 0) + ' Programs';
        openModal('deleteDeptModal');
    }

    function filterDepartmentsTable() {
        const query = (document.getElementById('deptSearchInput').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#departmentsTable tbody tr.dept-row');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (!query || text.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>
