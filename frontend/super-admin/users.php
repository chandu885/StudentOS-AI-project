<?php
// frontend/super-admin/users.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super_admin');

$currentUserId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();
$successMsg = '';
$errorMsg = '';

// Handle Password Change by Super Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_change_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($targetUserId <= 0) {
            $errorMsg = 'Invalid target user selected.';
        } elseif (empty($newPass) || empty($confirmPass)) {
            $errorMsg = 'Please provide both new password and confirmation.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'Password must be at least 8 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'Passwords do not match. Please verify.';
        } else {
            if ($conn) {
                $chk = $conn->prepare("SELECT u.id, u.email, u.first_name, u.last_name, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.deleted_at IS NULL");
                $chk->bind_param("i", $targetUserId);
                $chk->execute();
                $targetUser = $chk->get_result()->fetch_assoc();

                if ($targetUser) {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $up = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $up->bind_param("si", $newHash, $targetUserId);
                    if ($up->execute()) {
                        // Audit log
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $details = "Super Admin reset password for User #{$targetUserId} ({$targetUser['first_name']} {$targetUser['last_name']}, {$targetUser['email']}, Role: {$targetUser['role_name']})";
                        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'SUPER_ADMIN_PASSWORD_RESET', 'users', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $targetUserId, $details, $ip);
                            $aud->execute();
                        }

                        $successMsg = "Password for {$targetUser['first_name']} {$targetUser['last_name']} ({$targetUser['email']}) has been successfully updated!";
                    } else {
                        $errorMsg = 'Database update failed. Please try again.';
                    }
                } else {
                    $errorMsg = 'Selected user was not found in the database.';
                }
            } else {
                $errorMsg = 'Database connection error.';
            }
        }
    } elseif ($action === 'toggle_status') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 1);
        if ($targetUserId > 0 && $conn) {
            $stmt = $conn->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $newStatus, $targetUserId);
            $stmt->execute();
            $successMsg = 'User account status updated successfully!';
        }
    } elseif ($action === 'update_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 4);
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($targetUserId <= 0 || empty($firstName) || empty($lastName) || empty($email)) {
            $errorMsg = 'Please complete all required fields (First Name, Last Name, Email).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please provide a valid email address.';
        } else {
            if ($conn) {
                $dup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
                $dup->bind_param("si", $email, $targetUserId);
                $dup->execute();
                if ($dup->get_result()->fetch_assoc()) {
                    $errorMsg = "Another user account with email '$email' already exists.";
                    $dup->close();
                } else {
                    $dup->close();
                    $up = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    if ($up) {
                        $up->bind_param("ssssiii", $firstName, $lastName, $email, $phone, $roleId, $isActive, $targetUserId);
                        if ($up->execute()) {
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                            $details = "Super Admin updated user #{$targetUserId} ({$email}, Role: {$roleId})";
                            $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'USER_UPDATED', 'users', ?, ?, ?)");
                            if ($aud) {
                                $aud->bind_param("iiss", $currentUserId, $targetUserId, $details, $ip);
                                $aud->execute();
                            }
                            $successMsg = "User {$firstName} {$lastName} ({$email}) updated successfully in the database!";
                        } else {
                            $errorMsg = 'Database update failed: ' . $conn->error;
                        }
                        $up->close();
                    }
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $deleteMode = sanitize($_POST['delete_mode'] ?? 'soft'); // 'soft' or 'permanent'

        if ($targetUserId <= 0) {
            $errorMsg = 'Invalid user selected for deletion.';
        } elseif ($targetUserId === $currentUserId) {
            $errorMsg = 'You cannot delete your own Super Administrator account.';
        } elseif ($conn) {
            // Retrieve user and profile data (phone, department, section) before delete
            $uStmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role_id, r.name AS role_name,
                                            COALESCE(sp.phone, u.phone) AS phone,
                                            sp.section, sp.student_id,
                                            d.name AS department_name, d.code AS dept_code
                                     FROM users u
                                     LEFT JOIN roles r ON u.role_id = r.id
                                     LEFT JOIN student_profiles sp ON sp.user_id = u.id
                                     LEFT JOIN departments d ON sp.department_id = d.id
                                     WHERE u.id = ?");
            $uStmt->bind_param("i", $targetUserId);
            $uStmt->execute();
            $targetUser = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            if (!$targetUser) {
                $errorMsg = 'User record not found in the database.';
            } else {
                $userName = $targetUser['first_name'] . ' ' . $targetUser['last_name'];
                $userEmail = $targetUser['email'];
                $roleName = $targetUser['role_name'] ?? 'User';
                $phone = !empty($targetUser['phone']) ? $targetUser['phone'] : 'N/A';
                $dept = !empty($targetUser['dept_code']) ? $targetUser['dept_code'] : (!empty($targetUser['department_name']) ? $targetUser['department_name'] : 'N/A');
                $section = !empty($targetUser['section']) ? $targetUser['section'] : 'N/A';

                if ($deleteMode === 'soft') {
                    // Soft delete: deactivates and marks deleted_at timestamp, preserving phone, department, section
                    $up = $conn->prepare("UPDATE users SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
                    $up->bind_param("i", $targetUserId);
                    if ($up->execute()) {
                        // Audit log
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $details = "Super Admin soft-deleted user #{$targetUserId} ({$userName}, Role: {$roleName}, Phone: {$phone}, Dept: {$dept}, Sec: {$section})";
                        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'USER_SOFT_DELETED', 'users', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $targetUserId, $details, $ip);
                            $aud->execute();
                        }
                        $successMsg = "User {$userName} ({$userEmail}) deactivated (soft-deleted). Phone ({$phone}), Department ({$dept}), and Section ({$section}) records are safely archived.";
                    } else {
                        $errorMsg = 'Failed to deactivate user: ' . $conn->error;
                    }
                    $up->close();
                } elseif ($deleteMode === 'permanent') {
                    // Permanent delete: clean cascade inside a transaction
                    $conn->begin_transaction();
                    try {
                        // Clean up child tables
                        $conn->query("DELETE FROM user_sessions WHERE user_id = $targetUserId");
                        $conn->query("DELETE FROM notifications WHERE user_id = $targetUserId");
                        $conn->query("DELETE FROM tasks WHERE user_id = $targetUserId");
                        $conn->query("DELETE FROM student_subjects WHERE student_id = $targetUserId");
                        $conn->query("DELETE FROM assignment_submissions WHERE student_id = $targetUserId");
                        $conn->query("DELETE FROM attendance WHERE student_id = $targetUserId");
                        $conn->query("DELETE FROM performance WHERE student_id = $targetUserId");
                        $conn->query("DELETE FROM results WHERE student_id = $targetUserId");
                        $conn->query("DELETE FROM student_profiles WHERE user_id = $targetUserId");
                        $conn->query("DELETE FROM faculty_profiles WHERE user_id = $targetUserId");
                        $conn->query("DELETE FROM users WHERE id = $targetUserId");

                        $conn->commit();

                        // Audit log
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $details = "Super Admin permanently deleted user #{$targetUserId} ({$userName}, Email: {$userEmail}, Role: {$roleName}, Phone: {$phone}, Dept: {$dept}, Sec: {$section})";
                        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'USER_PERMANENTLY_DELETED', 'users', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $targetUserId, $details, $ip);
                            $aud->execute();
                        }

                        $successMsg = "User {$userName} ({$userEmail}) and all associated records (Phone: {$phone}, Department: {$dept}, Section: {$section}) have been permanently deleted from the database.";
                    } catch (\Exception $e) {
                        $conn->rollback();
                        $errorMsg = 'Failed to permanently delete user: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch users from database
$usersList = [];
$roleFilter = $_GET['role'] ?? 'all';

if ($conn) {
    $sql = "SELECT u.id, u.email, u.first_name, u.last_name, 
                   COALESCE(sp.phone, u.phone) AS phone, 
                   u.role_id, r.name as role_name, u.is_active, u.created_at, u.last_login_at,
                   sp.student_id, sp.section, sp.semester, sp.roll_number,
                   d.name as department_name, d.code as dept_code
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN departments d ON sp.department_id = d.id
            WHERE u.deleted_at IS NULL ";
    
    if ($roleFilter === 'faculty') {
        $sql .= "AND u.role_id = 3 ";
    } elseif ($roleFilter === 'admin') {
        $sql .= "AND u.role_id = 2 ";
    } elseif ($roleFilter === 'super_admin') {
        $sql .= "AND u.role_id = 1 ";
    } elseif ($roleFilter === 'student') {
        $sql .= "AND u.role_id = 4 ";
    }
    
    $sql .= "ORDER BY u.role_id ASC, u.id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usersList[] = $row;
        }
    }
}

$totalUsers = count($usersList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Users Master - StudentOS AI</title>
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
                        <h1>Global User Master & Credential Authority</h1>
                        <p class="page-subtitle">Central identity governance • Inspect accounts and change passwords for Faculty & Administrators</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Pills -->
                <div class="task-filter-group">
                    <a href="users.php?role=all" class="task-filter-btn <?php echo $roleFilter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> All Accounts (<?php echo $totalUsers; ?>)
                    </a>
                    <a href="users.php?role=admin" class="task-filter-btn <?php echo $roleFilter === 'admin' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i> Administrators
                    </a>
                    <a href="users.php?role=faculty" class="task-filter-btn <?php echo $roleFilter === 'faculty' ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> Faculty Staff
                    </a>
                    <a href="users.php?role=student" class="task-filter-btn <?php echo $roleFilter === 'student' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                    <a href="users.php?role=super_admin" class="task-filter-btn <?php echo $roleFilter === 'super_admin' ? 'active' : ''; ?>">
                        <i class="fas fa-crown"></i> Super Admins
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-gear"></i> Registered System Accounts</h3>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            Showing <?php echo count($usersList); ?> active users
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Full Name</th>
                                        <th>Email Address</th>
                                        <th>Role Group</th>
                                        <th>Created Date</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Credential Governance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usersList)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                                No users found matching current filter.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usersList as $u): ?>
                                            <?php 
                                            $roleId = (int)$u['role_id'];
                                            $roleBadgeClass = 'badge-primary';
                                            $roleLabel = $u['role_name'] ?? 'User';
                                            if ($roleId === 1) { $roleBadgeClass = 'badge-purple'; $roleLabel = 'Super Admin'; }
                                            elseif ($roleId === 2) { $roleBadgeClass = 'badge-warning'; $roleLabel = 'Admin'; }
                                            elseif ($roleId === 3) { $roleBadgeClass = 'badge-success'; $roleLabel = 'Faculty'; }
                                            elseif ($roleId === 4) { $roleBadgeClass = 'badge-primary'; $roleLabel = 'Student'; }
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $u['id']; ?></strong></td>
                                                <td>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                                    </div>
                                                    <?php if ($roleId === 4): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                                            <?php if (!empty($u['dept_code'])): ?>
                                                                <span class="badge badge-primary" style="font-size: 9.5px; padding: 1px 6px;" title="Department"><?php echo htmlspecialchars($u['dept_code']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($u['section'])): ?>
                                                                <span class="badge badge-info" style="font-size: 9.5px; padding: 1px 6px;" title="Class Section">Sec <?php echo htmlspecialchars($u['section']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($u['phone'])): ?>
                                                                <span style="color: var(--text-secondary); font-size: 10.5px;" title="Phone Contact"><i class="fas fa-phone-alt" style="font-size: 9px; color: var(--primary);"></i> <?php echo htmlspecialchars($u['phone']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif (!empty($u['phone'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                                                            <i class="fas fa-phone-alt" style="font-size: 9px; color: var(--primary);"></i> <?php echo htmlspecialchars($u['phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($u['email']); ?></code></td>
                                                <td>
                                                    <span class="badge <?php echo $roleBadgeClass; ?>">
                                                        <?php echo htmlspecialchars($roleLabel); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($u['is_active']): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Deactivated</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: inline-flex; gap: 6px;">
                                                        <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 8px; color: var(--info);"
                                                                onclick='openViewUserModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)'
                                                                title="View complete profile details for <?php echo htmlspecialchars($u['first_name']); ?>">
                                                            <i class="fas fa-eye"></i> Details
                                                        </button>

                                                        <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 8px;"
                                                                onclick='openEditUserModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)'
                                                                title="Edit details for <?php echo htmlspecialchars($u['first_name']); ?>">
                                                            <i class="fas fa-edit" style="color: var(--primary);"></i> Edit
                                                        </button>

                                                        <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 8px;"
                                                                onclick="openPasswordModal('<?php echo $u['id']; ?>', '<?php echo addslashes($u['first_name'] . ' ' . $u['last_name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo addslashes($roleLabel); ?>')"
                                                                title="Change password for <?php echo htmlspecialchars($u['first_name']); ?>">
                                                            <i class="fas fa-key" style="color: #F59E0B;"></i> Pass
                                                        </button>

                                                        <?php if ($u['id'] != $currentUserId): ?>
                                                            <form method="POST" action="users.php" style="display: inline;">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                                <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                                                <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 5px 8px;" 
                                                                        title="<?php echo $u['is_active'] ? 'Deactivate Account' : 'Activate Account'; ?>">
                                                                    <i class="fas <?php echo $u['is_active'] ? 'fa-user-slash text-danger' : 'fa-user-check text-success'; ?>"></i>
                                                                </button>
                                                            </form>

                                                            <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 5px 8px; border-color: rgba(239, 68, 68, 0.4); color: var(--danger);"
                                                                    onclick='openDeleteUserModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)'
                                                                    title="Delete / Deactivate User">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
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

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 500px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-shield-alt" style="color: #F59E0B;"></i> Super Admin Password Reset
                </h3>
                <button type="button" class="btn-close" onclick="closePasswordModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="users.php" id="adminResetForm" style="padding: 24px;">
                <input type="hidden" name="action" value="admin_change_password">
                <input type="hidden" name="target_user_id" id="modalTargetUserId" value="">

                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Target Account</div>
                    <div style="font-size: 14px; font-weight: 700; color: var(--text-primary);" id="modalTargetName">Name</div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;" id="modalTargetEmail">email@studentos.ai</div>
                    <div style="margin-top: 6px;">
                        <span class="badge badge-purple" id="modalTargetRole">Role</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="modalNewPassword">New Password <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="new_password" id="modalNewPassword" class="form-control has-toggle" 
                               placeholder="Minimum 8 characters" required>
                        <button type="button" class="toggle-password" onclick="toggleModalPass('modalNewPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="modalConfirmPassword">Confirm New Password <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-check"></i></span>
                        <input type="password" name="confirm_password" id="modalConfirmPassword" class="form-control has-toggle" 
                               placeholder="Re-type new password" required>
                        <button type="button" class="toggle-password" onclick="toggleModalPass('modalConfirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #F59E0B, #D97706); border: none;">
                        <i class="fas fa-lock"></i> Overwrite Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-edit" style="color: var(--primary);"></i> Edit User Account
                </h3>
                <button type="button" class="btn-close" onclick="closeEditUserModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="users.php" id="editUserForm" style="padding: 24px;">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="target_user_id" id="editUserId" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label for="editUserFn">First Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="first_name" id="editUserFn" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editUserLn">Last Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="last_name" id="editUserLn" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="editUserEmail">Email Address <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="email" id="editUserEmail" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="editUserPhone">Phone Number</label>
                    <input type="text" name="phone" id="editUserPhone" class="form-control" placeholder="+1 555-0182">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="editUserRole">System Role <span style="color: var(--danger);">*</span></label>
                        <select name="role_id" id="editUserRole" class="form-control" required>
                            <option value="1">Super Admin</option>
                            <option value="2">Admin</option>
                            <option value="3">Faculty</option>
                            <option value="4">Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editUserStatus">Account Status <span style="color: var(--danger);">*</span></label>
                        <select name="is_active" id="editUserStatus" class="form-control" required>
                            <option value="1">Active</option>
                            <option value="0">Deactivated</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes to Database
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Details Modal -->
    <div id="viewUserModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-id-card" style="color: var(--primary);"></i> User Profile & Contact Details
                </h3>
                <button type="button" class="btn-close" onclick="closeViewUserModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; color: #fff;" id="vAvatar">
                        U
                    </div>
                    <div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);" id="vFullName">User Name</div>
                        <div style="font-size: 12.5px; color: var(--text-muted);" id="vEmail">user@studentos.ai</div>
                    </div>
                    <div style="margin-left: auto;">
                        <span class="badge badge-primary" id="vRoleBadge">Role</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; font-size: 13px;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px 12px;">
                        <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; display: block;">User ID</span>
                        <strong id="vUserId" style="color: var(--text-primary);">#0</strong>
                    </div>
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px 12px;">
                        <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; display: block;">Status</span>
                        <span id="vStatus" class="badge badge-success">Active</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px 12px; grid-column: span 2;">
                        <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; display: block;">Phone Number</span>
                        <strong id="vPhone" style="color: var(--primary);">Not Provided</strong>
                    </div>
                </div>

                <div id="vStudentSection" style="background: rgba(14, 165, 233, 0.05); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px; display: none;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-graduation-cap"></i> Academic Enrollment Details
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12.5px;">
                        <div><strong style="color: var(--text-muted);">Department:</strong> <span id="vDept" style="color: var(--text-primary); font-weight: 600;">N/A</span></div>
                        <div><strong style="color: var(--text-muted);">Section:</strong> <span id="vClassSection" class="badge badge-info">Sec A</span></div>
                        <div><strong style="color: var(--text-muted);">Student ID:</strong> <span id="vStudentId" style="color: var(--text-primary);">N/A</span></div>
                        <div><strong style="color: var(--text-muted);">Roll Number:</strong> <span id="vRollNumber" style="color: var(--text-primary);">N/A</span></div>
                    </div>
                </div>

                <div style="font-size: 11.5px; color: var(--text-muted); display: flex; justify-content: space-between; padding-top: 8px;">
                    <span>Created: <span id="vCreatedAt">N/A</span></span>
                    <span>Last Login: <span id="vLastLogin">Never</span></span>
                </div>
            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeViewUserModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--danger); margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-slash"></i> User Account Deletion & Deactivation
                </h3>
                <button type="button" class="btn-close" onclick="closeDeleteUserModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="users.php" style="padding: 24px;">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="target_user_id" id="delUserId" value="">

                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">Target Account</div>
                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);" id="delUserName">User Name</div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;" id="delUserEmail">user@studentos.ai</div>
                    <div style="margin-top: 6px;">
                        <span class="badge badge-purple" id="delUserRole">Role</span>
                    </div>
                </div>

                <div id="delStudentInfoBox" style="background: rgba(14, 165, 233, 0.07); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px; display: none;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-database"></i> Student Profile & Contact Linkages
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12.5px;">
                        <div><strong>Phone:</strong> <span id="delUserPhone" style="color: var(--text-primary);">N/A</span></div>
                        <div><strong>Section:</strong> <span id="delUserSection" class="badge badge-info">Sec A</span></div>
                        <div style="grid-column: span 2;"><strong>Department:</strong> <span id="delUserDept" style="color: var(--text-primary); font-weight: 600;">N/A</span></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Select Deletion Mode:</label>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.05); cursor: pointer;">
                            <input type="radio" name="delete_mode" value="soft" checked style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--success); font-size: 13px;">Deactivate Account (Soft Delete - Recommended)</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Disables user login immediately. Safely preserves phone number, department affiliation, section, and all historical academic data.
                                </div>
                            </div>
                        </label>

                        <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.05); cursor: pointer;">
                            <input type="radio" name="delete_mode" value="permanent" style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--danger); font-size: 13px;">Permanent Delete (Cascade Cleanup)</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    Permanently purges student profile (releasing phone, department, section), attendance, marks, tasks, and user credentials.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background: var(--danger); border: none;">
                        <i class="fas fa-trash-alt"></i> Execute Action
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openPasswordModal(id, name, email, role) {
        document.getElementById('modalTargetUserId').value = id;
        document.getElementById('modalTargetName').textContent = name;
        document.getElementById('modalTargetEmail').textContent = email;
        document.getElementById('modalTargetRole').textContent = role;
        document.getElementById('modalNewPassword').value = '';
        document.getElementById('modalConfirmPassword').value = '';
        const modal = document.getElementById('passwordModal');
        modal.style.display = 'flex';
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }

    function openEditUserModal(u) {
        document.getElementById('editUserId').value = u.id || '';
        document.getElementById('editUserFn').value = u.first_name || '';
        document.getElementById('editUserLn').value = u.last_name || '';
        document.getElementById('editUserEmail').value = u.email || '';
        document.getElementById('editUserPhone').value = u.phone || '';
        document.getElementById('editUserRole').value = u.role_id || 4;
        document.getElementById('editUserStatus').value = (u.is_active !== undefined) ? u.is_active : 1;
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }

    function openViewUserModal(u) {
        document.getElementById('vAvatar').textContent = (u.first_name ? u.first_name.charAt(0) : 'U').toUpperCase();
        document.getElementById('vFullName').textContent = (u.first_name || '') + ' ' + (u.last_name || '');
        document.getElementById('vEmail').textContent = u.email || '';
        document.getElementById('vUserId').textContent = '#' + (u.id || '');
        document.getElementById('vRoleBadge').textContent = u.role_name || 'User';
        document.getElementById('vStatus').textContent = (u.is_active == 1) ? 'Active' : 'Deactivated';
        document.getElementById('vStatus').className = (u.is_active == 1) ? 'badge badge-success' : 'badge badge-danger';
        document.getElementById('vPhone').textContent = u.phone ? u.phone : 'Not Provided';
        document.getElementById('vCreatedAt').textContent = u.created_at ? u.created_at.substring(0, 10) : 'N/A';
        document.getElementById('vLastLogin').textContent = u.last_login_at ? u.last_login_at : 'Never';

        const stuSec = document.getElementById('vStudentSection');
        if (u.role_id == 4) {
            stuSec.style.display = 'block';
            document.getElementById('vDept').textContent = (u.dept_code ? u.dept_code + ' - ' : '') + (u.department_name || 'Not Assigned');
            document.getElementById('vClassSection').textContent = 'Sec ' + (u.section || 'A');
            document.getElementById('vStudentId').textContent = u.student_id || 'N/A';
            document.getElementById('vRollNumber').textContent = u.roll_number || 'N/A';
        } else {
            stuSec.style.display = 'none';
        }
        document.getElementById('viewUserModal').style.display = 'flex';
    }

    function closeViewUserModal() {
        document.getElementById('viewUserModal').style.display = 'none';
    }

    function openDeleteUserModal(u) {
        document.getElementById('delUserId').value = u.id || '';
        document.getElementById('delUserName').textContent = (u.first_name || '') + ' ' + (u.last_name || '');
        document.getElementById('delUserEmail').textContent = u.email || '';
        document.getElementById('delUserRole').textContent = u.role_name || 'User';

        const box = document.getElementById('delStudentInfoBox');
        if (u.role_id == 4) {
            box.style.display = 'block';
            document.getElementById('delUserPhone').textContent = u.phone || 'Not Registered';
            document.getElementById('delUserSection').textContent = 'Sec ' + (u.section || 'A');
            document.getElementById('delUserDept').textContent = (u.dept_code ? u.dept_code + ' - ' : '') + (u.department_name || 'Not Assigned');
        } else {
            if (u.phone) {
                box.style.display = 'block';
                document.getElementById('delUserPhone').textContent = u.phone;
                document.getElementById('delUserSection').textContent = 'N/A';
                document.getElementById('delUserDept').textContent = 'N/A';
            } else {
                box.style.display = 'none';
            }
        }
        document.getElementById('deleteUserModal').style.display = 'flex';
    }

    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').style.display = 'none';
    }

    function toggleModalPass(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    document.getElementById('adminResetForm')?.addEventListener('submit', function(e) {
        const p1 = document.getElementById('modalNewPassword').value;
        const p2 = document.getElementById('modalConfirmPassword').value;
        if (p1 !== p2) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        if (p1.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long.');
            return false;
        }
    });

    // Close modal on escape key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePasswordModal();
            closeEditUserModal();
            closeViewUserModal();
            closeDeleteUserModal();
        }
    });
    </script>
</body>
</html>
