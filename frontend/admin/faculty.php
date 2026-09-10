<?php
// frontend/admin/faculty.php
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
        $delId = (int)($_POST['faculty_id'] ?? 0);
        if ($delId > 0 && $db) {
            $stmt = $db->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND role_id = 3");
            if ($stmt) {
                $stmt->bind_param("i", $delId);
                if ($stmt->execute()) {
                    $successMsg = 'Faculty member account removed successfully.';
                } else {
                    $errorMsg = 'Failed to remove faculty: ' . $db->error;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['first_name'], $_POST['email'])) {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $designation = sanitize($_POST['designation'] ?? 'Assistant Professor');
        $office = sanitize($_POST['office_location'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email) || $deptId <= 0) {
            $errorMsg = 'Please provide First Name, Last Name, Official Email, and Department.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please enter a valid email address.';
        } elseif ($db) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errorMsg = "A user account with email '$email' already exists.";
                $chk->close();
            } else {
                $chk->close();
                $pwdHash = password_hash('Faculty@123', PASSWORD_DEFAULT);
                $insUser = $db->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash, is_active, created_at, updated_at) VALUES (3, ?, ?, ?, ?, 1, NOW(), NOW())");
                if ($insUser) {
                    $insUser->bind_param("ssss", $firstName, $lastName, $email, $pwdHash);
                    if ($insUser->execute()) {
                        $newId = $db->insert_id;
                        $insUser->close();

                        $empId = 'FAC-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                        $insProf = $db->prepare("INSERT INTO faculty_profiles (user_id, employee_id, department_id, designation, office_location, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        if ($insProf) {
                            $insProf->bind_param("isisss", $newId, $empId, $deptId, $designation, $office, $phone);
                            $insProf->execute();
                            $insProf->close();
                        }
                        $successMsg = "Faculty member $firstName $lastName registered successfully! (Default Password: Faculty@123)";
                    } else {
                        $errorMsg = 'Failed to create user account: ' . $db->error;
                        $insUser->close();
                    }
                }
            }
        }
    }
}

// Fetch departments
$departments = [];
if ($db) {
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes) $departments = $dRes->fetch_all(MYSQLI_ASSOC);
}

// Fetch faculty list
$facultyList = [];
if ($db) {
    $q = "SELECT u.id, u.first_name, u.last_name, u.email, fp.phone, u.is_active,
                 fp.employee_id, fp.designation, fp.office_location,
                 COALESCE(d.name, 'General') AS dept,
                 (SELECT COUNT(*) FROM subjects s WHERE s.faculty_id = u.id) AS courses
          FROM users u
          LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
          LEFT JOIN departments d ON fp.department_id = d.id
          WHERE u.role_id = 3 AND u.deleted_at IS NULL
          ORDER BY u.id DESC";
    $fRes = $db->query($q);
    if ($fRes) $facultyList = $fRes->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management - StudentOS AI</title>
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
                        <h1>Faculty Staff Management</h1>
                        <p class="page-subtitle">Professors, teaching faculty, and departmental lecturers</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addFacultyModal')">
                            <i class="fas fa-plus"></i> Add Faculty
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
                        <h3><i class="fas fa-chalkboard-teacher"></i> Faculty Directory (<?php echo count($facultyList); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name & ID</th>
                                        <th>Official Email</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Office Room</th>
                                        <th>Active Subjects</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($facultyList)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No faculty records found. Click "Add Faculty" to register a faculty member.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($facultyList as $fac): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']); ?></strong>
                                                    <?php if (!empty($fac['employee_id'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><span class="badge badge-secondary"><?php echo htmlspecialchars($fac['employee_id']); ?></span></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($fac['email']); ?></div>
                                                    <?php if (!empty($fac['phone'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($fac['phone']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($fac['dept']); ?></td>
                                                <td><span class="badge badge-purple"><?php echo htmlspecialchars($fac['designation'] ?? 'Professor'); ?></span></td>
                                                <td><?php echo htmlspecialchars(!empty($fac['office_location']) ? $fac['office_location'] : 'Main Block'); ?></td>
                                                <td><span class="badge badge-info"><?php echo (int)$fac['courses']; ?> Subjects</span></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this faculty member?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="faculty_id" value="<?php echo (int)$fac['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Deactivate faculty">
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

    <!-- Add Faculty Modal -->
    <div class="modal-backdrop" id="addFacultyModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add Faculty Member</h3>
                <button class="modal-close" onclick="closeModal('addFacultyModal')">&times;</button>
            </div>
            <form method="POST" action="faculty.php">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="ffn">First Name *</label>
                            <input type="text" name="first_name" id="ffn" class="form-control" placeholder="Alan" required>
                        </div>
                        <div class="form-group">
                            <label for="fln">Last Name *</label>
                            <input type="text" name="last_name" id="fln" class="form-control" placeholder="Turing" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fem">Official Email *</label>
                        <input type="email" name="email" id="fem" class="form-control" placeholder="alan.turing@university.edu" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="fdept">Department *</label>
                            <select name="department_id" id="fdept" class="form-control" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fdesig">Designation</label>
                            <select name="designation" id="fdesig" class="form-control">
                                <option value="Assistant Professor">Assistant Professor</option>
                                <option value="Associate Professor">Associate Professor</option>
                                <option value="Professor">Professor</option>
                                <option value="Head of Department">Head of Department</option>
                                <option value="Visiting Lecturer">Visiting Lecturer</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="foffice">Office / Room</label>
                            <input type="text" name="office_location" id="foffice" class="form-control" placeholder="Block B 302">
                        </div>
                        <div class="form-group">
                            <label for="fphone">Phone Number</label>
                            <input type="text" name="phone" id="fphone" class="form-control" placeholder="+1 555-0199">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addFacultyModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Faculty</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
