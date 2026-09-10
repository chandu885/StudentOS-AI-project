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
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $delId = (int)($_POST['student_id'] ?? 0);
        $deleteMode = sanitize($_POST['delete_mode'] ?? 'soft'); // 'soft' or 'permanent'

        if ($delId > 0 && $db) {
            // Get student info for feedback
            $chk = $db->prepare("SELECT u.first_name, u.last_name, u.email FROM users u WHERE u.id = ? AND u.role_id = 4");
            $chk->bind_param("i", $delId);
            $chk->execute();
            $targetStu = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$targetStu) {
                $errorMsg = 'Student account not found.';
            } else {
                $name = $targetStu['first_name'] . ' ' . $targetStu['last_name'];
                if ($deleteMode === 'soft') {
                    $stmt = $db->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0, updated_at = NOW() WHERE id = ? AND role_id = 4");
                    $stmt->bind_param("i", $delId);
                    if ($stmt->execute()) {
                        $successMsg = "Student $name ({$targetStu['email']}) has been deactivated successfully (soft delete).";
                    } else {
                        $errorMsg = 'Failed to deactivate student: ' . $db->error;
                    }
                    $stmt->close();
                } elseif ($deleteMode === 'permanent') {
                    $db->begin_transaction();
                    try {
                        $db->query("DELETE FROM user_sessions WHERE user_id = $delId");
                        $db->query("DELETE FROM notifications WHERE user_id = $delId");
                        $db->query("DELETE FROM tasks WHERE user_id = $delId");
                        $db->query("DELETE FROM student_subjects WHERE student_id = $delId");
                        $db->query("DELETE FROM assignment_submissions WHERE student_id = $delId");
                        $db->query("DELETE FROM attendance WHERE student_id = $delId");
                        $db->query("DELETE FROM performance WHERE student_id = $delId");
                        $db->query("DELETE FROM results WHERE student_id = $delId");
                        $db->query("DELETE FROM student_profiles WHERE user_id = $delId");
                        $db->query("DELETE FROM users WHERE id = $delId AND role_id = 4");

                        $db->commit();
                        $successMsg = "Student $name ({$targetStu['email']}) has been permanently deleted from the database.";
                    } catch (\Exception $e) {
                        $db->rollback();
                        $errorMsg = 'Failed to permanently delete student: ' . $e->getMessage();
                    }
                }
            }
        }
    } elseif ($action === 'edit') {
        $editId = (int)($_POST['student_id'] ?? 0);
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $rollNumber = sanitize($_POST['roll_number'] ?? '');
        $semester = sanitize($_POST['semester'] ?? '1');
        $department = strtoupper(sanitize($_POST['department'] ?? 'BCA'));
        if (!in_array($department, ['BBA', 'BCA'])) {
            $department = 'BCA';
        }
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $newPassword = $_POST['new_password'] ?? '';

        if ($editId <= 0 || empty($firstName) || empty($lastName) || empty($email) || empty($rollNumber)) {
            $errorMsg = 'First Name, Last Name, Email, and Roll Number are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please enter a valid institutional email address.';
        } elseif ($db) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
            $chk->bind_param("si", $email, $editId);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errorMsg = "Another user account with email '$email' already exists.";
                $chk->close();
            } else {
                $chk->close();
                // Update user details
                if (!empty($newPassword) && strlen($newPassword) >= 6) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upUser = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password_hash = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND role_id = 4");
                    $upUser->bind_param("ssssii", $firstName, $lastName, $email, $newHash, $isActive, $editId);
                } else {
                    $upUser = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND role_id = 4");
                    $upUser->bind_param("sssii", $firstName, $lastName, $email, $isActive, $editId);
                }

                if ($upUser && $upUser->execute()) {
                    $upUser->close();

                    // Lookup department_id
                    $dId = null;
                    $dSt = $db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
                    if ($dSt) {
                        $dSt->bind_param("s", $department);
                        $dSt->execute();
                        $dRow = $dSt->get_result()->fetch_assoc();
                        if ($dRow) $dId = (int)$dRow['id'];
                        $dSt->close();
                    }

                    // Update or insert student profile
                    $upProf = $db->prepare("UPDATE student_profiles SET department = ?, department_id = ?, roll_number = ?, semester = ?, updated_at = NOW() WHERE user_id = ?");
                    if ($upProf) {
                        $upProf->bind_param("sissi", $department, $dId, $rollNumber, $semester, $editId);
                        $upProf->execute();
                        if ($upProf->affected_rows === 0) {
                            $stuCode = 'STU-' . date('Y') . '-' . str_pad($editId, 4, '0', STR_PAD_LEFT);
                            $insProf = $db->prepare("INSERT IGNORE INTO student_profiles (user_id, student_id, department, department_id, semester, roll_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            if ($insProf) {
                                $insProf->bind_param("ississ", $editId, $stuCode, $department, $dId, $semester, $rollNumber);
                                $insProf->execute();
                                $insProf->close();
                            }
                        }
                        $upProf->close();
                    }
                    $successMsg = "Student '$firstName $lastName' updated successfully!";
                } else {
                    $errorMsg = 'Failed to update student: ' . $db->error;
                }
            }
        }
    } elseif (isset($_POST['first_name'], $_POST['email'])) {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $rollNumber = sanitize($_POST['roll_number'] ?? '');
        $semester = sanitize($_POST['semester'] ?? '1');
        $department = strtoupper(sanitize($_POST['department'] ?? 'BCA'));
        if (!in_array($department, ['BBA', 'BCA'])) {
            $department = 'BCA';
        }
        $rawPassword = $_POST['password'] ?? 'Student@123';
        if (empty($rawPassword)) {
            $rawPassword = 'Student@123';
        }

        if (empty($firstName) || empty($lastName) || empty($email) || empty($rollNumber)) {
            $errorMsg = 'First Name, Last Name, Email, and Roll Number are required.';
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
                $pwdHash = password_hash($rawPassword, PASSWORD_DEFAULT);
                $insUser = $db->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash, is_active, created_at, updated_at) VALUES (4, ?, ?, ?, ?, 1, NOW(), NOW())");
                if ($insUser) {
                    $insUser->bind_param("ssss", $firstName, $lastName, $email, $pwdHash);
                    if ($insUser->execute()) {
                        $newId = $db->insert_id;
                        $insUser->close();

                        // Lookup department_id
                        $dId = null;
                        $dSt = $db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
                        if ($dSt) {
                            $dSt->bind_param("s", $department);
                            $dSt->execute();
                            $dRow = $dSt->get_result()->fetch_assoc();
                            if ($dRow) $dId = (int)$dRow['id'];
                            $dSt->close();
                        }

                        $stuCode = 'STU-' . date('Y') . '-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                        $insProf = $db->prepare("INSERT INTO student_profiles (user_id, student_id, department, department_id, semester, roll_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        if ($insProf) {
                            $insProf->bind_param("ississ", $newId, $stuCode, $department, $dId, $semester, $rollNumber);
                            $insProf->execute();
                            $insProf->close();
                        }
                        $successMsg = "Student $firstName $lastName registered successfully! (Department: $department, Password: $rawPassword)";
                    } else {
                        $errorMsg = 'Failed to create student account: ' . $db->error;
                        $insUser->close();
                    }
                }
            }
        }
    } elseif ($action === 'promote_student') {
        $stuId = (int)($_POST['student_id'] ?? 0);
        $targetSem = sanitize($_POST['target_semester'] ?? '');
        $notes = sanitize($_POST['notes'] ?? 'Admin approved semester promotion');
        if ($stuId > 0 && $db) {
            require_once __DIR__ . '/../../backend/models/Student.php';
            $studentModel = new Student();
            $res = $studentModel->promoteStudent($stuId, $targetSem, $userId, $notes);
            if ($res['success']) {
                $successMsg = $res['message'];
            } else {
                $errorMsg = $res['error'];
            }
        }
    } elseif ($action === 'toggle_opt_in') {
        $stuId = (int)($_POST['student_id'] ?? 0);
        $state = (int)($_POST['opt_in_state'] ?? 0);
        $targetSem = sanitize($_POST['target_semester'] ?? '');
        $notes = sanitize($_POST['notes'] ?? 'Administrative opt-in update');
        if ($stuId > 0 && $db) {
            require_once __DIR__ . '/../../backend/models/Student.php';
            $studentModel = new Student();
            if ($state === 1) {
                $res = $studentModel->optInPromotion($stuId, $targetSem, $notes);
            } else {
                $res = $studentModel->optOutPromotion($stuId, $notes);
            }
            if ($res['success']) {
                $successMsg = $res['message'];
            } else {
                $errorMsg = $res['error'];
            }
        }
    } elseif ($action === 'reject_promotion') {
        $stuId = (int)($_POST['student_id'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? 'Promotion deferred by administration');
        if ($stuId > 0 && $db) {
            $stmt = $db->prepare("UPDATE student_profiles SET promotion_opt_in = 0, promotion_status = 'rejected', promotion_notes = ?, updated_at = NOW() WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $notes, $stuId);
                $stmt->execute();
                $stmt->close();
                $successMsg = "Student promotion request deferred/rejected with note: '$notes'.";
            }
        }
    } elseif ($action === 'bulk_promote') {
        $deptParam = sanitize($_POST['department'] ?? 'all');
        $fromSem = sanitize($_POST['from_semester'] ?? 'all');
        $targetSem = sanitize($_POST['target_semester'] ?? 'next');
        $scope = sanitize($_POST['scope'] ?? 'opted_in');
        $onlyOptedIn = ($scope === 'opted_in');
        $notes = sanitize($_POST['notes'] ?? 'Admin bulk semester promotion run');
        if ($db) {
            require_once __DIR__ . '/../../backend/models/Student.php';
            $studentModel = new Student();
            $res = $studentModel->promoteByDegreeAndSemester($deptParam, $fromSem, $targetSem, $userId, $notes, $onlyOptedIn);
            if ($res['success']) {
                $successMsg = $res['message'];
            } else {
                $errorMsg = $res['error'];
            }
        }
    } elseif ($action === 'toggle_promotion_window') {
        $windowStatus = sanitize($_POST['window_status'] ?? '1');
        $promYear = sanitize($_POST['academic_year'] ?? '2026-2027');
        if ($db) {
            $db->query("INSERT INTO system_settings (`key`, `value`, `description`, `updated_at`) VALUES ('semester_promotion_open', '$windowStatus', 'Status of student semester promotion window', NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
            $db->query("INSERT INTO system_settings (`key`, `value`, `description`, `updated_at`) VALUES ('semester_promotion_academic_year', '$promYear', 'Active academic year for semester promotion', NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
            $successMsg = "Semester promotion window is now " . ($windowStatus === '1' ? 'OPEN' : 'CLOSED') . " for academic cycle $promYear.";
        }
    }
}

// Fetch promotion window settings
$promWindowOpen = true;
$promYear = '2026-2027';
if ($db) {
    $setRes = $db->query("SELECT `key`, `value` FROM `system_settings` WHERE `key` IN ('semester_promotion_open', 'semester_promotion_academic_year')");
    if ($setRes) {
        while ($sRow = $setRes->fetch_assoc()) {
            if ($sRow['key'] === 'semester_promotion_open' && $sRow['value'] === '0') {
                $promWindowOpen = false;
            }
            if ($sRow['key'] === 'semester_promotion_academic_year') {
                $promYear = $sRow['value'];
            }
        }
    }
}

// Filters
$deptFilter = $_GET['department'] ?? 'all';
$promFilter = $_GET['promotion_status'] ?? 'all';

// Fetch live students with department & promotion status
$students = [];
if ($db) {
    $q = "SELECT u.id, u.first_name, u.last_name, u.email, u.is_active, u.created_at,
                 COALESCE(sp.student_id, CONCAT('STU-', u.id)) AS student_id,
                 COALESCE(sp.department, 'BCA') AS department,
                 COALESCE(sp.roll_number, sp.student_id, CONCAT('R-', u.id)) AS roll,
                 COALESCE(sp.semester, '1') AS semester,
                 COALESCE(sp.promotion_opt_in, 0) AS promotion_opt_in,
                 COALESCE(sp.promotion_status, 'not_opted') AS promotion_status,
                 sp.promotion_target_sem,
                 sp.promotion_requested_at,
                 sp.promoted_at,
                 sp.promotion_notes,
                 sp.prev_semester,
                 (SELECT ROUND(AVG(r.marks_obtained) / 10.0, 2) FROM results r WHERE r.student_id = u.id) AS cgpa
          FROM users u
          LEFT JOIN student_profiles sp ON sp.user_id = u.id
          WHERE u.role_id = 4 AND u.deleted_at IS NULL ";

    if ($deptFilter === 'BBA') {
        $q .= "AND sp.department = 'BBA' ";
    } elseif ($deptFilter === 'BCA') {
        $q .= "AND sp.department = 'BCA' ";
    }

    if ($promFilter === 'opted_in') {
        $q .= "AND sp.promotion_opt_in = 1 AND sp.promotion_status = 'opted_in' ";
    } elseif ($promFilter === 'not_opted') {
        $q .= "AND (sp.promotion_status = 'not_opted' OR sp.promotion_status IS NULL) ";
    } elseif ($promFilter === 'promoted') {
        $q .= "AND sp.promotion_status = 'promoted' ";
    } elseif ($promFilter === 'rejected') {
        $q .= "AND sp.promotion_status = 'rejected' ";
    }

    $q .= "ORDER BY u.id DESC";
    $sRes = $db->query($q);
    if ($sRes) {
        $students = $sRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Compute statistics from all active students
$totalStudents = count($students);
$optedInCount = 0;
$promotedCount = 0;
$notOptedCount = 0;

if ($db) {
    $statQ = "SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN sp.promotion_opt_in = 1 AND sp.promotion_status = 'opted_in' THEN 1 ELSE 0 END) AS opted_in_count,
        SUM(CASE WHEN sp.promotion_status = 'promoted' THEN 1 ELSE 0 END) AS promoted_count,
        SUM(CASE WHEN sp.promotion_status = 'not_opted' OR sp.promotion_status IS NULL THEN 1 ELSE 0 END) AS not_opted_count
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.role_id = 4 AND u.deleted_at IS NULL";
    $stRes = $db->query($statQ);
    if ($stRes && $sRow = $stRes->fetch_assoc()) {
        $totalAll = (int)$sRow['total_count'];
        $optedInCount = (int)$sRow['opted_in_count'];
        $promotedCount = (int)$sRow['promoted_count'];
        $notOptedCount = (int)$sRow['not_opted_count'];
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

                
                <!-- Filters & Search Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 18px;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-right: 4px;">Department:</span>
                        <a href="students.php?department=all&promotion_status=<?php echo urlencode($promFilter); ?>" class="btn <?php echo $deptFilter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">All Departments</a>
                        <a href="students.php?department=BBA&promotion_status=<?php echo urlencode($promFilter); ?>" class="btn <?php echo $deptFilter === 'BBA' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">BBA</a>
                        <a href="students.php?department=BCA&promotion_status=<?php echo urlencode($promFilter); ?>" class="btn <?php echo $deptFilter === 'BCA' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">BCA</a>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-right: 4px;">Promotion:</span>
                        <a href="students.php?department=<?php echo urlencode($deptFilter); ?>&promotion_status=all" class="btn <?php echo $promFilter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">All</a>
                        <a href="students.php?department=<?php echo urlencode($deptFilter); ?>&promotion_status=opted_in" class="btn <?php echo $promFilter === 'opted_in' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">
                            <i class="fas fa-check-circle" style="color:var(--success);"></i> Opted-In (<?php echo $optedInCount; ?>)
                        </a>
                        <a href="students.php?department=<?php echo urlencode($deptFilter); ?>&promotion_status=promoted" class="btn <?php echo $promFilter === 'promoted' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">Promoted</a>
                        <a href="students.php?department=<?php echo urlencode($deptFilter); ?>&promotion_status=not_opted" class="btn <?php echo $promFilter === 'not_opted' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 5px 12px;">Not Opted</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-user-graduate"></i> Enrolled Students Directory (<?php echo count($students); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll / Student ID</th>
                                        <th>Student Name</th>
                                        <th>Department</th>
                                        <th>Email Address</th>
                                        <th>Semester</th>
                                        <th>Promotion Opt-In</th>
                                        <th>Avg GPA</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No students found matching the selected filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $stu): 
                                            $gpaDisplay = !empty($stu['cgpa']) ? number_format((float)$stu['cgpa'], 2) : '—';
                                            $isActive = (int)$stu['is_active'] === 1;
                                            $isBBA = ($stu['department'] ?? 'BCA') === 'BBA';
                                            $pStat = $stu['promotion_status'] ?? 'not_opted';
                                            $isOptedIn = ((int)$stu['promotion_opt_in'] === 1 && $pStat === 'opted_in');
                                            $targetSem = !empty($stu['promotion_target_sem']) ? $stu['promotion_target_sem'] : ((is_numeric($stu['semester']) ? (string)((int)$stu['semester'] + 1) : '2'));
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($stu['roll']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($stu['student_id']); ?></div>
                                                </td>
                                                <td>
                                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge" style="font-weight: 700; font-size: 11px; <?php echo $isBBA ? 'background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);' : 'background: rgba(37, 99, 235, 0.15); color: var(--primary); border: 1px solid rgba(37, 99, 235, 0.3);'; ?>">
                                                        <?php echo htmlspecialchars($stu['department'] ?? 'BCA'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($stu['email']); ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge badge-purple">Sem <?php echo htmlspecialchars($stu['semester']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($isOptedIn): ?>
                                                        <span class="badge badge-success" style="font-weight: 700; font-size: 11px;">
                                                            <i class="fas fa-arrow-circle-up"></i> Opted-In (Sem <?php echo htmlspecialchars($stu['semester']); ?> &rarr; <?php echo htmlspecialchars($targetSem); ?>)
                                                        </span>
                                                    <?php elseif ($pStat === 'promoted'): ?>
                                                        <span class="badge badge-purple" style="font-weight: 700; font-size: 11px;">
                                                            <i class="fas fa-trophy"></i> Promoted
                                                        </span>
                                                    <?php elseif ($pStat === 'rejected'): ?>
                                                        <span class="badge badge-danger" style="font-weight: 700; font-size: 11px;" title="<?php echo htmlspecialchars($stu['promotion_notes'] ?? ''); ?>">
                                                            <i class="fas fa-times-circle"></i> Deferred
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary" style="font-size: 11px; opacity: 0.75;">Not Opted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong style="color: var(--success);"><?php echo $gpaDisplay; ?></strong></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isActive ? 'success' : 'danger'; ?>">
                                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: inline-flex; gap: 6px;">
                                                        <?php if ($isOptedIn): ?>
                                                            <button type="button" class="btn btn-primary" style="font-size: 11px; padding: 5px 9px; font-weight: 700;"
                                                                    title="Promote Student to Next Semester"
                                                                    onclick='openPromoteStudentModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                                                                <i class="fas fa-level-up-alt"></i> Promote
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 9px;"
                                                                title="Edit Student" 
                                                                onclick='openEditStudentModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                                                            <i class="fas fa-edit" style="color: var(--primary);"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 9px; border-color: rgba(239, 68, 68, 0.4); color: var(--danger);" 
                                                                title="Delete Student" 
                                                                onclick='openDeleteStudentModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                                                            <i class="fas fa-trash-alt"></i> Delete
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

    <!-- Add Student Modal -->
    <div class="modal-backdrop" id="addStudentModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color: var(--primary); margin-right: 8px;"></i> Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="add">
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
                            <input type="text" name="roll_number" id="rn" class="form-control" placeholder="e.g. 2026-CS-050" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="dept">Department *</label>
                            <select name="department" id="dept" class="form-control" required>
                                <option value="BCA" selected>BCA (Bachelor of Computer Applications)</option>
                                <option value="BBA">BBA (Bachelor of Business Administration)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sem">Enrolling Semester</label>
                            <input type="number" name="semester" id="sem" class="form-control" value="1" min="1" max="12">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pwd">Initial Password</label>
                        <input type="password" name="password" id="pwd" class="form-control" placeholder="Default: Student@123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal-backdrop" id="editStudentModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 540px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-edit" style="color: var(--primary);"></i> Edit Student Details
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="student_id" id="editStuId" value="">
                
                <div class="modal-body" style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="editStuFn">First Name *</label>
                            <input type="text" name="first_name" id="editStuFn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editStuLn">Last Name *</label>
                            <input type="text" name="last_name" id="editStuLn" class="form-control" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="editStuEmail">Email Address *</label>
                            <input type="email" name="email" id="editStuEmail" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editStuRoll">Roll Number *</label>
                            <input type="text" name="roll_number" id="editStuRoll" class="form-control" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="editStuDept">Department *</label>
                            <select name="department" id="editStuDept" class="form-control" required>
                                <option value="BCA">BCA (Bachelor of Computer Applications)</option>
                                <option value="BBA">BBA (Bachelor of Business Administration)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editStuSem">Current Semester</label>
                            <input type="number" name="semester" id="editStuSem" class="form-control" min="1" max="12" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editStuStatus">Account Status</label>
                        <select name="is_active" id="editStuStatus" class="form-control" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive / Deactivated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editStuPass">Reset Password (Optional)</label>
                        <input type="password" name="new_password" id="editStuPass" class="form-control" placeholder="Leave empty to keep existing password">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Student Modal -->
    <div class="modal-backdrop" id="deleteStudentModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 500px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--danger); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-slash"></i> Delete / Deactivate Student
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="student_id" id="delStuId" value="">

                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Student Profile</div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="delStuName">Student Name</div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;" id="delStuEmail">student@university.edu</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Roll: <strong id="delStuRoll">000</strong> &bull; Dept: <strong id="delStuDept" style="color: var(--primary);">BCA</strong> &bull; Semester: <strong id="delStuSem">1</strong></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Select Deletion Mode:</label>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.05); cursor: pointer;">
                                <input type="radio" name="delete_mode" value="soft" checked style="margin-top: 3px;">
                                <div>
                                    <strong style="color: var(--success); font-size: 13px;">Deactivate Student (Soft Delete - Recommended)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        Disables student login and hides account. Historical records and submissions are safely archived.
                                    </div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.05); cursor: pointer;">
                                <input type="radio" name="delete_mode" value="permanent" style="margin-top: 3px;">
                                <div>
                                    <strong style="color: var(--danger); font-size: 13px;">Permanent Delete (Cascade Cleanup)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        Permanently removes the student profile, grades, marks, and account from the database.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background: var(--danger); border: none;">
                        <i class="fas fa-trash-alt"></i> Execute Action
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Promote Individual Student Modal -->
    <div class="modal-backdrop" id="promoteStudentModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="background: rgba(37, 99, 235, 0.08); border-bottom: 1px solid rgba(37, 99, 235, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--primary); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-level-up-alt"></i> Promote Student to Next Semester
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('promoteStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="promote_student">
                <input type="hidden" name="student_id" id="promStuId" value="">

                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Student Details</div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="promStuName">Student Name</div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;" id="promStuEmail">student@university.edu</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Department: <strong id="promStuDept" style="color: var(--primary);">BCA</strong> &bull; 
                            Current Term: <strong>Semester <span id="promStuCurSem">1</span></strong>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="promTargetSem">Promote to Target Semester *</label>
                        <select name="target_semester" id="promTargetSem" class="form-control" required>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                            <?php endfor; ?>
                            <option value="Graduated">Graduated / Program Completed</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 11.5px; display: block; margin-top: 4px;">
                            Automatically advances the student's active semester and updates curriculum access.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="promNotes">Promotion Remarks / Reason</label>
                        <input type="text" name="notes" id="promNotes" class="form-control" placeholder="e.g. End-term exam cleared, eligible for progression" value="Academic promotion approved by Administration">
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('promoteStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-level-up-alt"></i> Confirm Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Promote Opted-In Students Modal -->
    <div class="modal-backdrop" id="bulkPromoteModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="background: rgba(34, 197, 94, 0.08); border-bottom: 1px solid rgba(34, 197, 94, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--success); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-users-cog"></i> Bulk Promote Opted-In Students
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('bulkPromoteModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="bulk_promote">

                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 13.5px; color: var(--text-primary); font-weight: 600;">
                            Ready to promote <span style="color: var(--success); font-weight: 800;"><?php echo $optedInCount; ?></span> student(s) who opted in!
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Each opted-in student will be advanced to their respective next semester and receive a confirmation notification.
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="bulkDept">Select Degree / Department</label>
                        <select name="department" id="bulkDept" class="form-control">
                            <option value="all">All Degrees & Departments</option>
                            <option value="BCA">BCA - Bachelor of Computer Applications</option>
                            <option value="BBA">BBA - Bachelor of Business Administration</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group" style="margin: 0;">
                            <label for="bulkFromSem">Current Semester (From)</label>
                            <select name="from_semester" id="bulkFromSem" class="form-control">
                                <option value="all">All Semesters</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label for="bulkTargetSem">Target Semester</label>
                            <select name="target_semester" id="bulkTargetSem" class="form-control">
                                <option value="next">Next Semester (+1 Auto)</option>
                                <?php for ($i = 2; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                <?php endfor; ?>
                                <option value="Graduated">Graduated</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="bulkScope">Promotion Candidate Filter</label>
                        <select name="scope" id="bulkScope" class="form-control">
                            <option value="opted_in">Only Opted-In Students (Ready Cohort)</option>
                            <option value="all">All Enrolled Students in Selected Degree/Term</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bulkNotes">Batch Promotion Notes</label>
                        <input type="text" name="notes" id="bulkNotes" class="form-control" value="Official batch semester promotion run (<?php echo htmlspecialchars($promYear); ?>)">
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkPromoteModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Execute Bulk Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Opt-In Status Modal -->
    <div class="modal-backdrop" id="toggleOptInModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 500px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid var(--border-color); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                    <i class="fas fa-sliders-h" style="color: var(--primary);"></i> Manage Student Promotion Opt-In
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('toggleOptInModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="toggle_opt_in">
                <input type="hidden" name="student_id" id="toggleStuId" value="">

                <div class="modal-body" style="padding: 24px;">
                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="toggleStuName">Student Name</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Dept: <strong id="toggleStuDept">BCA</strong> &bull; Current Term: <strong>Semester <span id="toggleStuCurSem">1</span></strong>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="toggleOptInState">Set Promotion Opt-In State *</label>
                        <select name="opt_in_state" id="toggleOptInState" class="form-control" required>
                            <option value="1">Opt-In: Student is Eligible & Ready for Promotion</option>
                            <option value="0">Not Opted-In / Withdraw Promotion Request</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="toggleTargetSem">Target Promotion Semester</label>
                        <select name="target_semester" id="toggleTargetSem" class="form-control">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="toggleNotes">Admin Notes / Remarks</label>
                        <input type="text" name="notes" id="toggleNotes" class="form-control" placeholder="e.g. In-person request granted by Dean">
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('toggleOptInModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Promotion Window Settings Modal -->
    <div class="modal-backdrop" id="promotionSettingsModal" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal-card" style="max-width: 480px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8);">
            <div class="modal-header" style="background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid var(--border-color); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                    <i class="fas fa-sliders-h" style="color: var(--primary);"></i> Promotion Window Settings
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('promotionSettingsModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="toggle_promotion_window">

                <div class="modal-body" style="padding: 24px;">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="winStatus">Promotion Opt-In Window Status *</label>
                        <select name="window_status" id="winStatus" class="form-control" required>
                            <option value="1" <?php echo $promWindowOpen ? 'selected' : ''; ?>>OPEN: Students can opt-in from dashboard</option>
                            <option value="0" <?php echo !$promWindowOpen ? 'selected' : ''; ?>>CLOSED: Promotion applications suspended</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="winYear">Academic Year *</label>
                        <input type="text" name="academic_year" id="winYear" class="form-control" value="<?php echo htmlspecialchars($promYear); ?>" required>
                        <small style="color: var(--text-muted); font-size: 11.5px; display: block; margin-top: 4px;">
                            e.g. 2026-2027
                        </small>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('promotionSettingsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openEditStudentModal(stu) {
        document.getElementById('editStuId').value = stu.id || '';
        document.getElementById('editStuFn').value = stu.first_name || '';
        document.getElementById('editStuLn').value = stu.last_name || '';
        document.getElementById('editStuEmail').value = stu.email || '';
        document.getElementById('editStuRoll').value = stu.roll || '';
        document.getElementById('editStuDept').value = stu.department || 'BCA';
        document.getElementById('editStuSem').value = stu.semester || '1';
        document.getElementById('editStuStatus').value = stu.is_active !== undefined ? stu.is_active : '1';
        document.getElementById('editStuPass').value = '';
        openModal('editStudentModal');
    }

    function openDeleteStudentModal(stu) {
        document.getElementById('delStuId').value = stu.id || '';
        document.getElementById('delStuName').textContent = (stu.first_name || '') + ' ' + (stu.last_name || '');
        document.getElementById('delStuEmail').textContent = stu.email || '';
        document.getElementById('delStuRoll').textContent = stu.roll || 'N/A';
        document.getElementById('delStuDept').textContent = stu.department || 'BCA';
        document.getElementById('delStuSem').textContent = stu.semester || '1';
        openModal('deleteStudentModal');
    }

    function openPromoteStudentModal(stu) {
        document.getElementById('promStuId').value = stu.id || '';
        document.getElementById('promStuName').textContent = (stu.first_name || '') + ' ' + (stu.last_name || '');
        document.getElementById('promStuEmail').textContent = stu.email || '';
        document.getElementById('promStuDept').textContent = stu.department || 'BCA';
        document.getElementById('promStuCurSem').textContent = stu.semester || '1';

        var cur = parseInt(stu.semester) || 1;
        var next = cur + 1;
        var sel = document.getElementById('promTargetSem');
        if (sel) {
            sel.value = String(stu.promotion_target_sem || next);
        }
        openModal('promoteStudentModal');
    }

    function openToggleOptInModal(stu) {
        document.getElementById('toggleStuId').value = stu.id || '';
        document.getElementById('toggleStuName').textContent = (stu.first_name || '') + ' ' + (stu.last_name || '');
        document.getElementById('toggleStuDept').textContent = stu.department || 'BCA';
        document.getElementById('toggleStuCurSem').textContent = stu.semester || '1';

        var cur = parseInt(stu.semester) || 1;
        var next = cur + 1;
        var stateSel = document.getElementById('toggleOptInState');
        if (stateSel) {
            stateSel.value = (parseInt(stu.promotion_opt_in) === 1 && stu.promotion_status === 'opted_in') ? '1' : '0';
        }
        var targetSel = document.getElementById('toggleTargetSem');
        if (targetSel) {
            targetSel.value = String(stu.promotion_target_sem || next);
        }
        openModal('toggleOptInModal');
    }

    function openBulkPromoteModal() {
        openModal('bulkPromoteModal');
    }
    </script>
</body>
</html>
