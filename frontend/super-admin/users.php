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
    }
}

// Fetch users from database
$usersList = [];
$roleFilter = $_GET['role'] ?? 'all';

if ($conn) {
    $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.role_id, r.name as role_name, u.is_active, u.created_at, u.last_login_at 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
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
                                                    <div style="display: inline-flex; gap: 8px;">
                                                        <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 5px 10px;"
                                                                onclick="openPasswordModal('<?php echo $u['id']; ?>', '<?php echo addslashes($u['first_name'] . ' ' . $u['last_name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo addslashes($roleLabel); ?>')"
                                                                title="Change password for <?php echo htmlspecialchars($u['first_name']); ?>">
                                                            <i class="fas fa-key" style="color: #F59E0B;"></i> Change Password
                                                        </button>

                                                        <?php if ($u['id'] != $currentUserId): ?>
                                                            <form method="POST" action="users.php" style="display: inline;">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                                <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                                                <button type="submit" class="btn btn-secondary" style="font-size: 11.5px; padding: 5px 10px;" 
                                                                        title="<?php echo $u['is_active'] ? 'Deactivate Account' : 'Activate Account'; ?>">
                                                                    <i class="fas <?php echo $u['is_active'] ? 'fa-user-slash text-danger' : 'fa-user-check text-success'; ?>"></i>
                                                                </button>
                                                            </form>
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
        if (e.key === 'Escape') closePasswordModal();
    });
    </script>
</body>
</html>
