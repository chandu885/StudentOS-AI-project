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
        if ($delId > 0 && $db) {
            // Check student & faculty dependencies
            $stuChk = $db->query("SELECT COUNT(*) AS c FROM student_profiles WHERE department_id = $delId")->fetch_assoc()['c'] ?? 0;
            $facChk = $db->query("SELECT COUNT(*) AS c FROM faculty_profiles WHERE department_id = $delId")->fetch_assoc()['c'] ?? 0;
            if ($stuChk > 0 || $facChk > 0) {
                $errorMsg = "Cannot delete department: $stuChk student(s) and $facChk faculty member(s) are assigned to it.";
            } else {
                $del = $db->prepare("DELETE FROM departments WHERE id = ?");
                $del->bind_param("i", $delId);
                if ($del->execute()) {
                    $successMsg = 'Department deleted successfully.';
                } else {
                    $errorMsg = 'Failed to delete department: ' . $db->error;
                }
                $del->close();
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
                 (SELECT COUNT(*) FROM student_profiles sp JOIN users su ON sp.user_id = su.id WHERE sp.department_id = d.id AND su.deleted_at IS NULL) AS students_count
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
                                        <th>Enrolled Students</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departments)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
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
                                                        <i class="fas fa-user-tie" style="color: var(--primary); margin-right: 4px;"></i> <?php echo htmlspecialchars($dept['hod_name']); ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">Not Appointed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int)$dept['faculty_count']; ?> Professors</td>
                                                <td><span class="badge badge-info"><?php echo (int)$dept['students_count']; ?> Students</span></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="department_id" value="<?php echo (int)$dept['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete department">
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

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
