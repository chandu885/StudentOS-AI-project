<?php
// frontend/admin/courses.php
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
        $delId = (int)($_POST['course_id'] ?? 0);
        if ($delId > 0 && $db) {
            // Check if subjects are associated
            $chk = $db->prepare("SELECT COUNT(*) AS cnt FROM subjects WHERE course_id = ?");
            $chk->bind_param("i", $delId);
            $chk->execute();
            $cnt = $chk->get_result()->fetch_assoc()['cnt'] ?? 0;
            $chk->close();

            if ($cnt > 0) {
                $errorMsg = "Cannot delete this program because $cnt subject(s) are currently attached to it.";
            } else {
                $del = $db->prepare("DELETE FROM courses WHERE id = ?");
                $del->bind_param("i", $delId);
                if ($del->execute()) {
                    $successMsg = 'Degree program deleted successfully.';
                } else {
                    $errorMsg = 'Failed to delete program: ' . $db->error;
                }
                $del->close();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $semesters = max(1, (int)($_POST['semesters'] ?? 8));
        $duration = max(1, (int)($_POST['duration_years'] ?? ceil($semesters / 2)));
        $degreeType = sanitize($_POST['degree_type'] ?? 'Bachelor');
        $description = sanitize($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($courseId <= 0 || empty($name) || empty($code) || $deptId <= 0) {
            $errorMsg = 'Please fill in all required fields (Program ID, Name, Code, Department).';
        } elseif ($db) {
            $dupChk = $db->prepare("SELECT id FROM courses WHERE code = ? AND id != ?");
            $dupChk->bind_param("si", $code, $courseId);
            $dupChk->execute();
            if ($dupChk->get_result()->fetch_assoc()) {
                $errorMsg = "Another degree program with code '$code' already exists.";
                $dupChk->close();
            } else {
                $dupChk->close();
                $stmt = $db->prepare("UPDATE courses SET department_id = ?, code = ?, name = ?, description = ?, duration_years = ?, total_semesters = ?, degree_type = ?, status = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("isssiissi", $deptId, $code, $name, $description, $duration, $semesters, $degreeType, $status, $courseId);
                    if ($stmt->execute()) {
                        $successMsg = "Degree program '$name' ($code) updated successfully in the database!";
                    } else {
                        $errorMsg = 'Failed to update degree program: ' . $db->error;
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST['name'], $_POST['code'])) {
        $name = sanitize($_POST['name'] ?? '');
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $semesters = max(1, (int)($_POST['semesters'] ?? 8));
        $duration = max(1, (int)ceil($semesters / 2));
        $degreeType = sanitize($_POST['degree_type'] ?? 'Bachelor');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name) || empty($code) || $deptId <= 0) {
            $errorMsg = 'Please fill in all required fields (Program Name, Code, Department).';
        } elseif ($db) {
            // Check code uniqueness
            $dupChk = $db->prepare("SELECT id FROM courses WHERE code = ?");
            $dupChk->bind_param("s", $code);
            $dupChk->execute();
            if ($dupChk->get_result()->fetch_assoc()) {
                $errorMsg = "A program with code '$code' already exists.";
                $dupChk->close();
            } else {
                $dupChk->close();
                $stmt = $db->prepare("INSERT INTO courses (department_id, code, name, description, duration_years, total_semesters, degree_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
                if ($stmt) {
                    $stmt->bind_param("isssiis", $deptId, $code, $name, $description, $duration, $semesters, $degreeType);
                    if ($stmt->execute()) {
                        $successMsg = "Degree program '$name' ($code) added successfully!";
                    } else {
                        $errorMsg = 'Failed to create program: ' . $db->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch departments for dropdown
$departments = [];
if ($db) {
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes) {
        $departments = $dRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch courses
$courses = [];
if ($db) {
    $q = "SELECT c.*, d.name AS department_name,
                 (SELECT COUNT(*) FROM subjects s WHERE s.course_id = c.id) AS total_subjects
          FROM courses c
          LEFT JOIN departments d ON c.department_id = d.id
          ORDER BY c.id DESC";
    $cRes = $db->query($q);
    if ($cRes) {
        $courses = $cRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Courses & Degrees - StudentOS AI</title>
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
                        <h1>Degree Programs & Courses</h1>
                        <p class="page-subtitle">Configure undergraduate and graduate curricula and degree requirements</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                            <i class="fas fa-plus"></i> Add Degree Program
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
                        <h3><i class="fas fa-layer-group"></i> Active Programs (<?php echo count($courses); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Program Code</th>
                                        <th>Degree Name</th>
                                        <th>Department</th>
                                        <th>Degree Type</th>
                                        <th>Duration</th>
                                        <th>Semesters</th>
                                        <th>Subjects</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No degree programs found. Click "Add Degree Program" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $c): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($c['code']); ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                                    <?php if (!empty($c['description'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($c['description']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($c['department_name'] ?? 'Unassigned'); ?></td>
                                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($c['degree_type']); ?></span></td>
                                                <td><?php echo (int)$c['duration_years']; ?> Years</td>
                                                <td><?php echo (int)$c['total_semesters']; ?> Sems</td>
                                                <td><strong><?php echo (int)$c['total_subjects']; ?></strong> subjects</td>
                                                <td>
                                                    <span class="badge <?php echo ($c['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                        <?php echo ucfirst($c['status'] ?? 'active'); ?>
                                                    </span>
                                                </td>
                                                 <td>
                                                    <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--primary); margin-right: 4px;" title="Edit program details" onclick='openEditCourseModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this program?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete program">
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

    <!-- Add Course Modal -->
    <div class="modal-backdrop" id="addCourseModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add Degree Program</h3>
                <button class="modal-close" onclick="closeModal('addCourseModal')">&times;</button>
            </div>
            <form method="POST" action="courses.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="cName">Program Name *</label>
                        <input type="text" name="name" id="cName" class="form-control" placeholder="e.g. B.Tech in Computer Science" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="cCode">Program Code *</label>
                            <input type="text" name="code" id="cCode" class="form-control" placeholder="e.g. BTECH-CSE" required>
                        </div>
                        <div class="form-group">
                            <label for="cDept">Department *</label>
                            <select name="department_id" id="cDept" class="form-control" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="cSem">Total Semesters</label>
                            <input type="number" name="semesters" id="cSem" class="form-control" value="8" min="1" max="12">
                        </div>
                        <div class="form-group">
                            <label for="cType">Degree Type</label>
                            <select name="degree_type" id="cType" class="form-control">
                                <option value="Bachelor">Bachelor</option>
                                <option value="Master">Master</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Certificate">Certificate</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cDesc">Description / Specialization</label>
                        <textarea name="description" id="cDesc" class="form-control" rows="2" placeholder="Brief curriculum or program overview..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Program</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal-backdrop" id="editCourseModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary); margin-right: 8px;"></i> Edit Degree Program</h3>
                <button type="button" class="modal-close" onclick="closeModal('editCourseModal')">&times;</button>
            </div>
            <form method="POST" action="courses.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="editCourseId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editCName">Program Name *</label>
                        <input type="text" name="name" id="editCName" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="editCCode">Program Code *</label>
                            <input type="text" name="code" id="editCCode" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editCDept">Department *</label>
                            <select name="department_id" id="editCDept" class="form-control" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="editCSem">Total Semesters</label>
                            <input type="number" name="semesters" id="editCSem" class="form-control" min="1" max="12" required>
                        </div>
                        <div class="form-group">
                            <label for="editCDur">Duration (Years)</label>
                            <input type="number" name="duration_years" id="editCDur" class="form-control" min="1" max="6" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="editCType">Degree Type *</label>
                            <select name="degree_type" id="editCType" class="form-control" required>
                                <option value="Bachelor">Bachelor</option>
                                <option value="Master">Master</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Certificate">Certificate</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editCStatus">Status *</label>
                            <select name="status" id="editCStatus" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editCDesc">Description / Specialization</label>
                        <textarea name="description" id="editCDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCourseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Program in Database</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openEditCourseModal(c) {
        document.getElementById('editCourseId').value = c.id || '';
        document.getElementById('editCName').value = c.name || '';
        document.getElementById('editCCode').value = c.code || '';
        document.getElementById('editCDept').value = c.department_id || '';
        document.getElementById('editCSem').value = c.total_semesters || 8;
        document.getElementById('editCDur').value = c.duration_years || Math.ceil((c.total_semesters || 8) / 2);
        document.getElementById('editCType').value = c.degree_type || 'Bachelor';
        document.getElementById('editCStatus').value = c.status || 'active';
        document.getElementById('editCDesc').value = c.description || '';
        openModal('editCourseModal');
    }
    </script>
</body>
</html>
