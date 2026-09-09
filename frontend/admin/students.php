<?php
// frontend/admin/students.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['student_id'] ?? 0);
        if ($delId > 0 && $db) {
            $stmt = $db->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND role_id = 4");
            if ($stmt) {
                $stmt->bind_param("i", $delId);
                if ($stmt->execute()) {
                    $successMsg = 'Student account deactivated successfully.';
                } else {
                    $errorMsg = 'Failed to deactivate student: ' . $db->error;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['first_name'], $_POST['email'])) {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $rollNumber = sanitize($_POST['roll_number'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        $semester = sanitize($_POST['semester'] ?? '1');
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email) || empty($rollNumber) || $deptId <= 0) {
            $errorMsg = 'First Name, Last Name, Email, Roll Number, and Department are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please enter a valid institutional email address.';
        } elseif ($db) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errorMsg = "A user account with email '$email' already exists.";
                $chk->close();
            } else {
                $chk->close();
                // Resolve fallback course if not selected
                if ($courseId <= 0) {
                    $cRow = $db->query("SELECT id FROM courses WHERE department_id = $deptId LIMIT 1")->fetch_assoc();
                    $courseId = $cRow ? (int)$cRow['id'] : 1;
                }

                $pwdHash = password_hash('Student@123', PASSWORD_DEFAULT);
                $insUser = $db->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, is_active, created_at, updated_at) VALUES (4, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                if ($insUser) {
                    $insUser->bind_param("sssss", $firstName, $lastName, $email, $pwdHash, $phone);
                    if ($insUser->execute()) {
                        $newId = $db->insert_id;
                        $insUser->close();

                        $stuCode = 'STU-' . date('Y') . '-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                        $insProf = $db->prepare("INSERT INTO student_profiles (user_id, student_id, department_id, course_id, semester, section, roll_number, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'A', ?, ?, NOW(), NOW())");
                        if ($insProf) {
                            $insProf->bind_param("isiisss", $newId, $stuCode, $deptId, $courseId, $semester, $rollNumber, $phone);
                            $insProf->execute();
                            $insProf->close();
                        }
                        $successMsg = "Student $firstName $lastName registered successfully! (Default Password: Student@123)";
                    } else {
                        $errorMsg = 'Failed to create user account: ' . $db->error;
                        $insUser->close();
                    }
                }
            }
        }
    }
}

// Fetch departments & courses for modal
$departments = [];
$courses = [];
if ($db) {
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes) $departments = $dRes->fetch_all(MYSQLI_ASSOC);

    $cRes = $db->query("SELECT id, name, code, department_id FROM courses WHERE status = 'active' ORDER BY name ASC");
    if ($cRes) $courses = $cRes->fetch_all(MYSQLI_ASSOC);
}

// Fetch live students
$students = [];
if ($db) {
    $q = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active,
                 COALESCE(sp.student_id, CONCAT('STU-', u.id)) AS student_id,
                 COALESCE(sp.roll_number, sp.student_id, CONCAT('R-', u.id)) AS roll,
                 COALESCE(sp.semester, '1') AS semester,
                 COALESCE(sp.section, 'A') AS section,
                 COALESCE(d.name, 'General') AS dept,
                 COALESCE(c.name, 'Undergraduate') AS course_name,
                 (SELECT ROUND(AVG(r.marks_obtained) / 10.0, 2) FROM results r WHERE r.student_id = u.id) AS cgpa
          FROM users u
          LEFT JOIN student_profiles sp ON sp.user_id = u.id
          LEFT JOIN departments d ON sp.department_id = d.id
          LEFT JOIN courses c ON sp.course_id = c.id
          WHERE u.role_id = 4 AND u.deleted_at IS NULL
          ORDER BY u.id DESC";
    $sRes = $db->query($q);
    if ($sRes) {
        $students = $sRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - StudentOS AI</title>
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
                        <h1>Student Records Management</h1>
                        <p class="page-subtitle">Enrolled students master directory across all academic departments and cohorts</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addStudentModal')">
                            <i class="fas fa-user-plus"></i> Add New Student
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
                        <h3><i class="fas fa-users"></i> All Registered Students (<?php echo count($students); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Contact Email</th>
                                        <th>Department & Program</th>
                                        <th>Semester</th>
                                        <th>Avg GPA</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No students registered yet. Click "Add New Student" to enroll one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $stu): 
                                            $gpaDisplay = !empty($stu['cgpa']) ? number_format((float)$stu['cgpa'], 2) : '—';
                                            $isActive = (int)$stu['is_active'] === 1;
                                        ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($stu['student_id']); ?></div>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($stu['email']); ?></div>
                                                    <?php if (!empty($stu['phone'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($stu['phone']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($stu['dept']); ?></div>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($stu['course_name']); ?></div>
                                                </td>
                                                <td><span class="badge badge-purple">Sem <?php echo htmlspecialchars($stu['semester']); ?> (<?php echo htmlspecialchars($stu['section']); ?>)</span></td>
                                                <td><strong style="color: var(--success);"><?php echo $gpaDisplay; ?></strong></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isActive ? 'success' : 'danger'; ?>">
                                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Deactivate this student account?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="student_id" value="<?php echo (int)$stu['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Deactivate student">
                                                            <i class="fas fa-user-slash"></i>
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

    <!-- Add Student Modal -->
    <div class="modal-backdrop" id="addStudentModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="fn">First Name *</label>
                            <input type="text" name="first_name" id="fn" class="form-control" placeholder="John" required>
                        </div>
                        <div class="form-group">
                            <label for="ln">Last Name *</label>
                            <input type="text" name="last_name" id="ln" class="form-control" placeholder="Doe" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="em">Institutional Email *</label>
                            <input type="email" name="email" id="em" class="form-control" placeholder="john.doe@university.edu" required>
                        </div>
                        <div class="form-group">
                            <label for="rn">Roll Number *</label>
                            <input type="text" name="roll_number" id="rn" class="form-control" placeholder="e.g. CS-2026-050" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="deptSelect">Department *</label>
                            <select name="department_id" id="deptSelect" class="form-control" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>">
                                        <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="courseSelect">Program / Course</label>
                            <select name="course_id" id="courseSelect" class="form-control">
                                <option value="">Auto / Default Program</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="sem">Enrolling Semester</label>
                            <input type="number" name="semester" id="sem" class="form-control" value="1" min="1" max="8">
                        </div>
                        <div class="form-group">
                            <label for="ph">Phone Number</label>
                            <input type="text" name="phone" id="ph" class="form-control" placeholder="+1 555-0182">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Student</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
