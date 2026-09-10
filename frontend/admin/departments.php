<?php
// frontend/admin/departments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['department_id'] ?? 0);
        $deleteMode = sanitize($_POST['delete_mode'] ?? 'deactivate'); // 'deactivate' or 'permanent'

        if ($delId > 0 && $db) {
            // Check faculty and courses dependencies
            $depInfoStmt = $db->prepare("SELECT d.name, d.code,
                                                (SELECT COUNT(*) FROM faculty_profiles fp JOIN users fu ON fp.user_id = fu.id WHERE fp.department_id = ? AND fu.deleted_at IS NULL) AS fac_count,
                                                (SELECT COUNT(*) FROM courses c WHERE c.department_id = ? AND c.status = 'active') AS courses_count
                                         FROM departments d
                                         WHERE d.id = ?");
            $depInfoStmt->bind_param("iii", $delId, $delId, $delId);
            $depInfoStmt->execute();
            $depInfo = $depInfoStmt->get_result()->fetch_assoc();
            $depInfoStmt->close();

            $deptName = $depInfo['name'] ?? 'Department';
            $stuCount = 0;
            $facCount = (int)($depInfo['fac_count'] ?? 0);
            $coursesCount = (int)($depInfo['courses_count'] ?? 0);
            $phoneCount = (int)($depInfo['phone_count'] ?? 0);

            if ($deleteMode === 'deactivate') {
                $stmt = $db->prepare("UPDATE departments SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $delId);
                if ($stmt->execute()) {
                    $successMsg = "Department '$deptName' ($delId) has been deactivated successfully. Enrolled students ($stuCount) across section(s) '$sections' with $phoneCount phone number(s) have been safely preserved.";
                } else {
                    $errorMsg = 'Failed to deactivate department: ' . $db->error;
                }
                $stmt->close();
            } elseif ($deleteMode === 'permanent') {
                if ($stuCount > 0 || $facCount > 0) {
                    $errorMsg = "Cannot permanently delete '$deptName': It contains $stuCount student(s) across section(s) '$sections' with $phoneCount registered phone number(s), and $facCount faculty member(s). Please deactivate the department instead or reassign the students.";
                } else {
                    $del = $db->prepare("DELETE FROM departments WHERE id = ?");
                    $del->bind_param("i", $delId);
                    if ($del->execute()) {
                        $successMsg = "Department '$deptName' has been permanently deleted from the database.";
                    } else {
                        $errorMsg = 'Failed to delete department: ' . $db->error;
                    }
                    $del->close();
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $headId = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
        $description = sanitize($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($deptId <= 0 || empty($name) || empty($code)) {
            $errorMsg = 'Department ID, Name, and Code are required.';
        } elseif ($db) {
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
                    $successMsg = "Department '$name' ($code) updated successfully in the database!";
                    $stmt->close();
                } else {
                    $errorMsg = 'Failed to update department: ' . ($db->error ?? 'Database error');
                }
            }
        }
    } elseif (isset($_POST['name'], $_POST['code'])) {
        $name = sanitize($_POST['name'] ?? '');
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $headId = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            $errorMsg = 'Department Name and Code are required.';
        } elseif ($db) {
            $dup = $db->prepare("SELECT id FROM departments WHERE code = ?");
            $dup->bind_param("s", $code);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()) {
                $errorMsg = "A department with code '$code' already exists.";
                $dup->close();
            } else {
                $dup->close();
                if ($headId) {
                    $stmt = $db->prepare("INSERT INTO departments (code, name, description, head_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())");
                    $stmt->bind_param("sssi", $code, $name, $description, $headId);
                } else {
                    $stmt = $db->prepare("INSERT INTO departments (code, name, description, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
                    $stmt->bind_param("sss", $code, $name, $description);
                }
                if ($stmt && $stmt->execute()) {
                    $successMsg = "Department '$name' ($code) created successfully!";
                    $stmt->close();
                } else {
                    $errorMsg = 'Failed to save department: ' . ($db->error ?? 'Database error');
                }
            }
        }
    }
}

// Fetch faculty for HOD select
$facultyList = [];
if ($db) {
    $fRes = $db->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, fp.designation FROM users u LEFT JOIN faculty_profiles fp ON fp.user_id = u.id WHERE u.role_id = 3 AND u.deleted_at IS NULL ORDER BY u.first_name ASC");
    if ($fRes) {
        $facultyList = $fRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch departments with real metrics
$departments = [];
if ($db) {
    $q = "SELECT d.*, 
                 CONCAT(u.first_name, ' ', u.last_name) AS hod_name,
                 (SELECT COUNT(*) FROM faculty_profiles fp JOIN users fu ON fp.user_id = fu.id WHERE fp.department_id = d.id AND fu.deleted_at IS NULL) AS faculty_count,
                 (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.id AND c.status = 'active') AS courses_count
          FROM departments d
          LEFT JOIN users u ON d.head_id = u.id
          ORDER BY d.id ASC";
    $dRes = $db->query($q);
    if ($dRes) {
        $departments = $dRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - StudentOS AI</title>
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
                        <h1>Academic Departments</h1>
                        <p class="page-subtitle">Configure faculties, departmental heads, and academic divisions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addDeptModal')">
                            <i class="fas fa-plus"></i> Add Department
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
                        <h3><i class="fas fa-building"></i> Active Departments (<?php echo count($departments); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Department Name</th>
                                        <th>Head of Department (HOD)</th>
                                        <th>Faculty Staff</th>
                                        <th>Degree Programs</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departments)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No departments found. Click "Add Department" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <tr>
                                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                                    <?php if (!empty($dept['description'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($dept['description']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($dept['hod_name'])): ?>
                                                        <strong><?php echo htmlspecialchars($dept['hod_name']); ?></strong>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">Not Appointed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int)$dept['faculty_count']; ?> Professors</td>
                                                <td><span class="badge badge-info"><?php echo (int)($dept['courses_count'] ?? 0); ?> Programs</span></td>
                                                <td>
                                                    <span class="badge badge-<?php echo ($dept['status'] ?? 'active') === 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($dept['status'] ?? 'active'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--primary); margin-right: 4px;" title="Edit department details" onclick='openEditDeptModal(<?php echo htmlspecialchars(json_encode($dept), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Manage / Deactivate / Delete Department" onclick='openDeleteDeptModal(<?php echo htmlspecialchars(json_encode($dept), ENT_QUOTES, "UTF-8"); ?>)'>
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

    <!-- Add Department Modal -->
    <div class="modal-backdrop" id="addDeptModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Create New Department</h3>
                <button class="modal-close" onclick="closeModal('addDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="deptName">Department Name *</label>
                        <input type="text" name="name" id="deptName" class="form-control" placeholder="e.g. Electrical Engineering" required>
                    </div>
                    <div class="form-group">
                        <label for="deptCode">Department Code *</label>
                        <input type="text" name="code" id="deptCode" class="form-control" placeholder="e.g. EE" required>
                    </div>
                    <div class="form-group">
                        <label for="deptHod">Head of Department (HOD)</label>
                        <select name="head_id" id="deptHod" class="form-control">
                            <option value="">Select Faculty Head (Optional)</option>
                            <?php foreach ($facultyList as $fac): ?>
                                <option value="<?php echo (int)$fac['id']; ?>">
                                    <?php echo htmlspecialchars($fac['name']); ?> <?php echo !empty($fac['designation']) ? '(' . htmlspecialchars($fac['designation']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="deptDesc">Description</label>
                        <textarea name="description" id="deptDesc" class="form-control" rows="2" placeholder="Academic division scope and focus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal-backdrop" id="editDeptModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary); margin-right: 8px;"></i> Edit Department</h3>
                <button type="button" class="modal-close" onclick="closeModal('editDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="department_id" id="editDeptId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editDeptName">Department Name *</label>
                        <input type="text" name="name" id="editDeptName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editDeptCode">Department Code *</label>
                        <input type="text" name="code" id="editDeptCode" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editDeptHod">Head of Department (HOD)</label>
                        <select name="head_id" id="editDeptHod" class="form-control">
                            <option value="">Select Faculty Head (Optional)</option>
                            <?php foreach ($facultyList as $fac): ?>
                                <option value="<?php echo (int)$fac['id']; ?>">
                                    <?php echo htmlspecialchars($fac['name']); ?> <?php echo !empty($fac['designation']) ? '(' . htmlspecialchars($fac['designation']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editDeptStatus">Status *</label>
                        <select name="status" id="editDeptStatus" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editDeptDesc">Description</label>
                        <textarea name="description" id="editDeptDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Department in Database</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete / Deactivate Department Modal -->
    <div class="modal-backdrop" id="deleteDeptModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--danger); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> Department Management & Deletion
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="department_id" id="delDeptId" value="">
                
                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                        <div style="font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Target Department</div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="delDeptName">Department Name</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Code: <strong id="delDeptCode">CODE</strong></div>
                    </div>

                    <div style="background: rgba(14, 165, 233, 0.07); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 20px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-database"></i> Associated Academic Records
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12.5px;">
                            <div><strong>Degree Programs:</strong> <span id="delDeptCourses" class="badge badge-primary">0</span></div>
                            <div><strong>Faculty Staff:</strong> <span id="delDeptFaculty" class="badge badge-info">0</span></div>
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
                                        Sets department status to Inactive. Safely preserves all associated records.
                                    </div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.05); cursor: pointer;">
                                <input type="radio" name="delete_mode" value="permanent" style="margin-top: 3px;">
                                <div>
                                    <strong style="color: var(--danger); font-size: 13px;">Permanent Delete</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        Permanently removes the department record from the database. Allowed only when 0 faculty and 0 degree programs are assigned.
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
        document.getElementById('delDeptCourses').textContent = (dept.courses_count || 0) + ' Programs';
        document.getElementById('delDeptFaculty').textContent = (dept.faculty_count || 0) + ' Faculty';
        openModal('deleteDeptModal');
    }
    </script>
</body>
</html>
