<?php
// frontend/super-admin/roles.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/models/Role.php';
require_once __DIR__ . '/../../backend/models/SystemModel.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$roleModel = new Role();
$sysModel = new SystemModel();

$successMsg = '';
$errorMsg = '';

// Generate CSRF token if not set
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

        // CREATE ROLE
        if ($action === 'create_role') {
            $name = trim($_POST['name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('intval', $_POST['permissions']) : [];

            $res = $roleModel->createRole($name, $displayName, $desc, $permissions);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_CREATE_ROLE', 'roles', $res['role_id'], "Created new role '{$displayName}' [{$name}] with " . count($permissions) . " permissions.");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // EDIT ROLE
        elseif ($action === 'edit_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $displayName = trim($_POST['display_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('intval', $_POST['permissions']) : [];

            $res = $roleModel->updateRole($roleId, $displayName, $desc, $permissions, $name);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_UPDATE_ROLE', 'roles', $roleId, "Updated role '{$displayName}' (#{$roleId}) with " . count($permissions) . " permissions.");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // DELETE ROLE
        elseif ($action === 'delete_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $roleToDelete = $roleModel->findById($roleId);

            $res = $roleModel->deleteRole($roleId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $deletedName = $roleToDelete['display_name'] ?? "Role #{$roleId}";
                $sysModel->logAudit($userId, 'SUPER_ADMIN_DELETE_ROLE', 'roles', $roleId, "Deleted custom role '{$deletedName}' (#{$roleId}).");
            } else {
                $errorMsg = $res['error'];
            }
        }
    }
}

// Fetch all roles dynamically
$roles = $roleModel->getAllWithCounts();
$allPermissionsData = $roleModel->getAllPermissions();
$permissionsGrouped = $allPermissionsData['grouped'] ?? [];
$totalPermissionsCount = count($allPermissionsData['all'] ?? []);

// Calculate summary stats
$totalRolesCount = count($roles);
$totalAssignedUsers = 0;
$customRolesCount = 0;
foreach ($roles as $r) {
    $totalAssignedUsers += (int)$r['user_count'];
    if (empty($r['is_system'])) {
        $customRolesCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Hierarchy & Access Control - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .role-badge-system {
            background: rgba(99, 102, 241, 0.15);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-badge-custom {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .perm-chip {
            display: inline-block;
            background: var(--bg-secondary, rgba(255,255,255,0.06));
            border: 1px solid var(--border-color, rgba(255,255,255,0.1));
            color: var(--text-secondary);
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 4px;
            margin: 2px;
        }
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            max-height: 380px;
            overflow-y: auto;
            padding: 8px 4px;
        }
        .perm-module-card {
            background: var(--bg-secondary, rgba(255,255,255,0.03));
            border: 1px solid var(--border-color, rgba(255,255,255,0.08));
            border-radius: var(--radius-md, 8px);
            padding: 10px 12px;
        }
        .perm-module-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary, #6366f1);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .perm-item-label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 12px;
            cursor: pointer;
            margin-bottom: 6px;
            color: var(--text-primary);
        }
        .perm-item-label input[type="checkbox"] {
            margin-top: 2px;
            cursor: pointer;
        }
        .stat-grid-roles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card-role {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg, 12px);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Role-Based Access Control (RBAC) Hierarchy</h1>
                        <p class="page-subtitle">Dynamic system roles, access privilege scoping, and permission matrices</p>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="permissions.php" class="btn btn-secondary">
                            <i class="fas fa-key"></i> View Permission Matrix
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-plus-circle"></i> Create New Role
                        </button>
                    </div>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                        <span><?php echo htmlspecialchars($successMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
                        <span><?php echo htmlspecialchars($errorMsg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Executive Stats -->
                <div class="stat-grid-roles">
                    <div class="stat-card-role">
                        <div class="stat-icon-wrap" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Total System Roles</div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);"><?php echo $totalRolesCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card-role">
                        <div class="stat-icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Total Assigned Users</div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);"><?php echo number_format($totalAssignedUsers); ?></div>
                        </div>
                    </div>
                    <div class="stat-card-role">
                        <div class="stat-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Security Modules</div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);"><?php echo count($permissionsGrouped); ?> Modules</div>
                        </div>
                    </div>
                    <div class="stat-card-role">
                        <div class="stat-icon-wrap" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Custom Roles</div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);"><?php echo $customRolesCount; ?> Defined</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <h3><i class="fas fa-shield-alt"></i> Configured User Roles</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div style="position: relative; min-width: 260px;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                                <input type="text" id="roleSearchInput" class="form-control" placeholder="Search roles or scope..." style="padding-left: 34px; height: 38px; font-size: 13px;" onkeyup="filterRolesTable()">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="rolesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">ID</th>
                                        <th>Role Identifier</th>
                                        <th>Display Title</th>
                                        <th>Scope & Description</th>
                                        <th style="text-align: center;">Assigned Users</th>
                                        <th style="text-align: center;">Permissions</th>
                                        <th style="text-align: right; width: 140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $r): ?>
                                        <tr class="role-row">
                                            <td><strong>#<?php echo (int)$r['id']; ?></strong></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <code style="font-weight: 600;"><?php echo htmlspecialchars($r['name']); ?></code>
                                                    <?php if (!empty($r['is_system'])): ?>
                                                        <span class="role-badge-system" title="Core system role protected from deletion">System</span>
                                                    <?php else: ?>
                                                        <span class="role-badge-custom" title="Custom institution defined role">Custom</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($r['display_name']); ?></strong>
                                            </td>
                                            <td style="color: var(--text-secondary); max-width: 380px; font-size: 13px;">
                                                <?php echo htmlspecialchars($r['description'] ?? 'Standard role scope.'); ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="users.php?role=<?php echo urlencode(strtolower(str_replace('_', '', $r['name']))); ?>" class="badge badge-info" style="text-decoration: none; padding: 5px 10px;" title="View all users with this role">
                                                    <i class="fas fa-user" style="margin-right: 4px;"></i><?php echo (int)$r['user_count']; ?> accounts
                                                </a>
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="btn btn-sm btn-secondary" onclick='viewPermissions(<?php echo json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 4px 10px; font-size: 12px;">
                                                    <i class="fas fa-key" style="margin-right: 4px;"></i> <?php echo (int)$r['perm_count']; ?> active
                                                </button>
                                            </td>
                                            <td style="text-align: right;">
                                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditModal(<?php echo json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Role Details & Permissions">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!empty($r['is_system'])): ?>
                                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="System default roles cannot be deleted" style="opacity: 0.4; cursor: not-allowed;">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick='confirmDeleteRole(<?php echo json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Delete Custom Role">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- CREATE ROLE MODAL -->
    <div class="modal-backdrop" id="createRoleModal">
        <div class="modal-card" style="max-width: 720px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color: var(--primary);"></i> Create New Role</h3>
                <button type="button" class="modal-close" onclick="closeModal('createRoleModal')">&times;</button>
            </div>
            <form method="POST" action="" id="createRoleForm">
                <input type="hidden" name="action" value="create_role">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="create_name">Role Identifier Code <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="name" id="create_name" class="form-control" placeholder="e.g. EXAM_CONTROLLER" required pattern="[A-Za-z0-9_]+" style="text-transform: uppercase;">
                            <small style="color: var(--text-muted); font-size: 11px;">Uppercase alphanumeric characters and underscores only.</small>
                        </div>
                        <div class="form-group">
                            <label for="create_display">Display Title <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="display_name" id="create_display" class="form-control" placeholder="e.g. Examination Controller" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_desc">Description & Authorization Scope</label>
                        <textarea name="description" id="create_desc" class="form-control" rows="2" placeholder="Describe the access boundaries and responsibilities for this role..."></textarea>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="margin-bottom: 0;">Assign Permissions (<?php echo $totalPermissionsCount; ?> Available)</label>
                            <div style="font-size: 12px;">
                                <a href="javascript:void(0)" onclick="toggleAllCheckboxes('createRoleForm', true)" style="color: var(--primary); margin-right: 12px;">Select All</a>
                                <a href="javascript:void(0)" onclick="toggleAllCheckboxes('createRoleForm', false)" style="color: var(--text-muted);">Deselect All</a>
                            </div>
                        </div>
                        <div class="perm-grid">
                            <?php foreach ($permissionsGrouped as $mod => $perms): ?>
                                <div class="perm-module-card">
                                    <div class="perm-module-title">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars(ucfirst($mod)); ?>
                                    </div>
                                    <?php foreach ($perms as $p): ?>
                                        <label class="perm-item-label">
                                            <input type="checkbox" name="permissions[]" value="<?php echo (int)$p['id']; ?>">
                                            <span>
                                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                <br><small style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($p['slug']); ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createRoleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT ROLE MODAL -->
    <div class="modal-backdrop" id="editRoleModal">
        <div class="modal-card" style="max-width: 720px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Role</h3>
                <button type="button" class="modal-close" onclick="closeModal('editRoleModal')">&times;</button>
            </div>
            <form method="POST" action="" id="editRoleForm">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="role_id" id="edit_role_id" value="">
                
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="edit_name">Role Identifier Code</label>
                            <input type="text" name="name" id="edit_name" class="form-control" style="text-transform: uppercase;">
                            <small id="edit_name_help" style="color: var(--text-muted); font-size: 11px;">Protected for system roles.</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_display">Display Title <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="display_name" id="edit_display" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_desc">Description & Authorization Scope</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="margin-bottom: 0;">Assigned Permissions</label>
                            <div style="font-size: 12px;">
                                <a href="javascript:void(0)" onclick="toggleAllCheckboxes('editRoleForm', true)" style="color: var(--primary); margin-right: 12px;">Select All</a>
                                <a href="javascript:void(0)" onclick="toggleAllCheckboxes('editRoleForm', false)" style="color: var(--text-muted);">Deselect All</a>
                            </div>
                        </div>
                        <div class="perm-grid">
                            <?php foreach ($permissionsGrouped as $mod => $perms): ?>
                                <div class="perm-module-card">
                                    <div class="perm-module-title">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars(ucfirst($mod)); ?>
                                    </div>
                                    <?php foreach ($perms as $p): ?>
                                        <label class="perm-item-label">
                                            <input type="checkbox" name="permissions[]" value="<?php echo (int)$p['id']; ?>" class="edit-perm-check" id="edit_perm_<?php echo (int)$p['id']; ?>">
                                            <span>
                                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                <br><small style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($p['slug']); ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editRoleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW PERMISSIONS MODAL -->
    <div class="modal-backdrop" id="viewPermModal">
        <div class="modal-card" style="max-width: 600px; width: 95%;">
            <div class="modal-header">
                <div>
                    <h3 id="viewPermTitle">Role Permissions</h3>
                    <p id="viewPermSubtitle" style="font-size: 12px; color: var(--text-muted); margin: 0;"></p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('viewPermModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewPermBody" style="max-height: 420px; overflow-y: auto;">
                <!-- Dynamically filled -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('viewPermModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div class="modal-backdrop" id="deleteRoleModal">
        <div class="modal-card" style="max-width: 480px; width: 95%;">
            <div class="modal-header">
                <h3 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Confirm Role Deletion</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteRoleModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="role_id" id="del_role_id" value="">
                
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete the custom role <strong id="del_role_name"></strong>?</p>
                    <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.15); border: 1px solid var(--warning); color: var(--warning); padding: 10px 14px; border-radius: var(--radius-md); font-size: 12px; margin-top: 12px;">
                        <i class="fas fa-info-circle"></i> Any permissions mapped directly to this role will be removed. Roles with active users cannot be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteRoleModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Yes, Delete Role</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
                modal.style.opacity = '1';
                modal.style.pointerEvents = 'auto';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.style.opacity = '';
                modal.style.pointerEvents = '';
                document.body.style.overflow = '';
            }
        }

        function openCreateModal() {
            const form = document.getElementById('createRoleForm');
            if (form) form.reset();
            openModal('createRoleModal');
        }

        function openEditModal(role) {
            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_name').value = role.name;
            document.getElementById('edit_display').value = role.display_name;
            document.getElementById('edit_desc').value = role.description || '';

            const nameInput = document.getElementById('edit_name');
            const nameHelp = document.getElementById('edit_name_help');

            if (role.is_system) {
                nameInput.readOnly = true;
                nameInput.style.backgroundColor = 'var(--bg-secondary, rgba(255,255,255,0.05))';
                nameInput.style.cursor = 'not-allowed';
                nameHelp.textContent = 'Core system identifier is protected to preserve system logic.';
                nameHelp.style.color = '#f59e0b';
            } else {
                nameInput.readOnly = false;
                nameInput.style.backgroundColor = '';
                nameInput.style.cursor = '';
                nameHelp.textContent = 'Custom role code can be updated.';
                nameHelp.style.color = 'var(--text-muted)';
            }

            // Uncheck all edit checkboxes
            const checkboxes = document.querySelectorAll('#editRoleForm .edit-perm-check');
            checkboxes.forEach(cb => cb.checked = false);

            // Check permissions that the role has
            if (role.permissions && Array.isArray(role.permissions)) {
                role.permissions.forEach(p => {
                    const cb = document.getElementById('edit_perm_' + p.id);
                    if (cb) cb.checked = true;
                });
            }

            openModal('editRoleModal');
        }

        function viewPermissions(role) {
            document.getElementById('viewPermTitle').textContent = role.display_name + ' Permissions';
            document.getElementById('viewPermSubtitle').textContent = role.name + ' (' + (role.perm_count || 0) + ' active permissions)';
            
            const body = document.getElementById('viewPermBody');
            if (!role.permissions || role.permissions.length === 0) {
                body.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted);"><i class="fas fa-lock" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>No specific permissions assigned to this role.</div>';
            } else {
                // Group by module
                const grouped = {};
                role.permissions.forEach(p => {
                    if (!grouped[p.module]) grouped[p.module] = [];
                    grouped[p.module].push(p);
                });

                let html = '<div style="display: flex; flex-direction: column; gap: 14px;">';
                for (const mod in grouped) {
                    html += '<div style="background: var(--bg-secondary, rgba(255,255,255,0.03)); border: 1px solid var(--border-color, rgba(255,255,255,0.08)); border-radius: 8px; padding: 12px 14px;">';
                    html += '<div style="font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; margin-bottom: 8px;"><i class="fas fa-shield-alt"></i> ' + mod.toUpperCase() + '</div>';
                    html += '<div style="display: flex; flex-wrap: wrap; gap: 6px;">';
                    grouped[mod].forEach(p => {
                        html += '<span class="perm-chip"><i class="fas fa-check" style="color: var(--success, #10b981); margin-right: 4px;"></i>' + p.name + ' <code style="font-size: 10px; opacity: 0.7;">(' + p.slug + ')</code></span>';
                    });
                    html += '</div></div>';
                }
                html += '</div>';
                body.innerHTML = html;
            }

            openModal('viewPermModal');
        }

        function confirmDeleteRole(role) {
            document.getElementById('del_role_id').value = role.id;
            document.getElementById('del_role_name').textContent = role.display_name + ' (' + role.name + ')';
            openModal('deleteRoleModal');
        }

        function toggleAllCheckboxes(formId, state) {
            const form = document.getElementById(formId);
            if (!form) return;
            const checkboxes = form.querySelectorAll('input[type="checkbox"][name="permissions[]"]');
            checkboxes.forEach(cb => cb.checked = state);
        }

        function filterRolesTable() {
            const query = document.getElementById('roleSearchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#rolesTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Close on backdrop click
        window.addEventListener('click', function(e) {
            ['createRoleModal', 'editRoleModal', 'viewPermModal', 'deleteRoleModal'].forEach(id => {
                const el = document.getElementById(id);
                if (el && e.target === el) {
                    closeModal(id);
                }
            });
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['createRoleModal', 'editRoleModal', 'viewPermModal', 'deleteRoleModal'].forEach(id => {
                    closeModal(id);
                });
            }
        });
    </script>
</body>
</html>
