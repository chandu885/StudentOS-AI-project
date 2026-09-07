<?php
// frontend/super-admin/admins.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super_admin');

$currentUserId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_change_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($targetUserId <= 0) {
            $errorMsg = 'Invalid administrator selected.';
        } elseif (empty($newPass) || empty($confirmPass)) {
            $errorMsg = 'Please complete both password fields.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'Password must be at least 8 characters in length.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'Passwords do not match.';
        } else {
            if ($conn) {
                $chk = $conn->prepare("SELECT id, email, first_name, last_name, role_id FROM users WHERE id = ? AND deleted_at IS NULL");
                $chk->bind_param("i", $targetUserId);
                $chk->execute();
                $targetUser = $chk->get_result()->fetch_assoc();

                if ($targetUser) {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $up = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $up->bind_param("si", $newHash, $targetUserId);
                    if ($up->execute()) {
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $details = "Super Admin changed password for Administrator #{$targetUserId} ({$targetUser['email']})";
                        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'ADMIN_PASSWORD_RESET', 'users', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $targetUserId, $details, $ip);
                            $aud->execute();
                        }
                        $successMsg = "Password for Administrator {$targetUser['first_name']} {$targetUser['last_name']} ({$targetUser['email']}) updated successfully!";
                    } else {
                        $errorMsg = 'Database update failed.';
                    }
                } else {
                    $errorMsg = 'Administrator not found.';
                }
            }
        }
    } elseif ($action === 'provision_admin') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 2); // 2 = Admin, 1 = Super Admin

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $errorMsg = 'Please complete all required fields for admin provisioning.';
        } elseif (strlen($password) < 8) {
            $errorMsg = 'Initial password must be at least 8 characters long.';
        } else {
            if ($conn) {
                $existChk = $conn->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
                $existChk->bind_param("s", $email);
                $existChk->execute();
                if ($existChk->get_result()->num_rows > 0) {
                    $errorMsg = 'An account with this email address already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins = $conn->prepare("INSERT INTO users (role_id, email, password_hash, first_name, last_name, is_verified, is_active) VALUES (?, ?, ?, ?, ?, 1, 1)");
                    $ins->bind_param("issss", $roleId, $email, $hash, $firstName, $lastName);
                    if ($ins->execute()) {
                        $newId = $conn->insert_id;
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $details = "Super Admin provisioned new Administrator #{$newId} ({$email})";
                        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, 'ADMIN_PROVISIONED', 'users', ?, ?, ?)");
                        if ($aud) {
                            $aud->bind_param("iiss", $currentUserId, $newId, $details, $ip);
                            $aud->execute();
                        }
                        $successMsg = "Administrator {$firstName} {$lastName} ({$email}) provisioned successfully!";
                    } else {
                        $errorMsg = 'Failed to provision admin account.';
                    }
                }
            }
        }
    }
}

// Fetch real administrators
$adminsList = [];
if ($conn) {
    $res = $conn->query("SELECT u.id, u.email, u.first_name, u.last_name, u.role_id, r.name as role_name, u.is_active, u.created_at, u.last_login_at 
                         FROM users u 
                         LEFT JOIN roles r ON u.role_id = r.id 
                         WHERE u.role_id IN (1, 2) AND u.deleted_at IS NULL 
                         ORDER BY u.role_id ASC, u.id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $adminsList[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Accounts & Privileges - StudentOS AI</title>
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
                        <h1>Administrator Accounts & Privileges</h1>
                        <p class="page-subtitle">Inspect institutional admin scopes, provision administrative staff, and manage root passwords</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openProvisionModal()">
                            <i class="fas fa-user-shield"></i> Provision New Admin
                        </button>
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

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-shield" style="color: #F59E0B;"></i> Active Administrative Staff</h3>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php echo count($adminsList); ?> Provisioned Administrators
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Email Address</th>
                                        <th>Role Group</th>
                                        <th>Access Scope</th>
                                        <th>Last Login</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminsList as $adm): ?>
                                        <?php 
                                        $isRoot = ((int)$adm['role_id'] === 1);
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo $adm['id']; ?></strong></td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-primary);">
                                                    <?php echo htmlspecialchars($adm['first_name'] . ' ' . $adm['last_name']); ?>
                                                </div>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($adm['email']); ?></code></td>
                                            <td>
                                                <span class="badge <?php echo $isRoot ? 'badge-purple' : 'badge-warning'; ?>">
                                                    <?php echo $isRoot ? 'Super Admin' : 'Admin'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 12.5px; color: var(--text-secondary);">
                                                    <?php echo $isRoot ? 'Universal Root Tier' : 'Institutional Ops'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 12.5px; color: var(--text-muted);">
                                                    <?php echo $adm['last_login_at'] ? date('M d, H:i', strtotime($adm['last_login_at'])) : 'Recent'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($adm['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Disabled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 5px 10px;"
                                                        onclick="openAdminPasswordModal('<?php echo $adm['id']; ?>', '<?php echo addslashes($adm['first_name'] . ' ' . $adm['last_name']); ?>', '<?php echo addslashes($adm['email']); ?>')">
                                                    <i class="fas fa-key" style="color: #F59E0B;"></i> Change Password
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
    <div id="adminPasswordModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 500px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-key" style="color: #F59E0B;"></i> Change Admin Password
                </h3>
                <button type="button" class="btn-close" onclick="closeAdminPasswordModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="admins.php" id="adminPasswordForm" style="padding: 24px;">
                <input type="hidden" name="action" value="admin_change_password">
                <input type="hidden" name="target_user_id" id="modalTargetAdminId" value="">

                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Target Administrator</div>
                    <div style="font-size: 14px; font-weight: 700; color: var(--text-primary);" id="modalAdminName">Name</div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;" id="modalAdminEmail">email@studentos.ai</div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="adminNewPass">New Password <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="new_password" id="adminNewPass" class="form-control has-toggle" 
                               placeholder="Minimum 8 characters" required>
                        <button type="button" class="toggle-password" onclick="togglePass('adminNewPass', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="adminConfirmPass">Confirm New Password <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-check"></i></span>
                        <input type="password" name="confirm_password" id="adminConfirmPass" class="form-control has-toggle" 
                               placeholder="Re-enter new password" required>
                        <button type="button" class="toggle-password" onclick="togglePass('adminConfirmPass', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAdminPasswordModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #F59E0B, #D97706); border: none;">
                        <i class="fas fa-lock"></i> Overwrite Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Provision Admin Modal -->
    <div id="provisionModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-plus" style="color: var(--primary);"></i> Provision New Administrator
                </h3>
                <button type="button" class="btn-close" onclick="closeProvisionModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="admins.php" id="provisionAdminForm" style="padding: 24px;">
                <input type="hidden" name="action" value="provision_admin">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label for="provFirst">First Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="first_name" id="provFirst" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label for="provLast">Last Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="last_name" id="provLast" class="form-control" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="provEmail">Institutional Admin Email <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="email" id="provEmail" class="form-control" placeholder="admin@studentos.ai" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="provRole">Administrative Tier <span style="color: var(--danger);">*</span></label>
                    <select name="role_id" id="provRole" class="form-control" required>
                        <option value="2" selected>Institutional Admin (Tier 2)</option>
                        <option value="1">Super Administrator (Tier 1 - Root)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="provPassword">Initial Temporary Password <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="password" id="provPassword" class="form-control" placeholder="Minimum 8 characters" required>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeProvisionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Provision Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openAdminPasswordModal(id, name, email) {
        document.getElementById('modalTargetAdminId').value = id;
        document.getElementById('modalAdminName').textContent = name;
        document.getElementById('modalAdminEmail').textContent = email;
        document.getElementById('adminNewPass').value = '';
        document.getElementById('adminConfirmPass').value = '';
        document.getElementById('adminPasswordModal').style.display = 'flex';
    }

    function closeAdminPasswordModal() {
        document.getElementById('adminPasswordModal').style.display = 'none';
    }

    function openProvisionModal() {
        document.getElementById('provisionModal').style.display = 'flex';
    }

    function closeProvisionModal() {
        document.getElementById('provisionModal').style.display = 'none';
    }

    function togglePass(id, btn) {
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

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAdminPasswordModal();
            closeProvisionModal();
        }
    });
    </script>
</body>
</html>
