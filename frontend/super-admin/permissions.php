<?php
// frontend/super-admin/permissions.php
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
$conn = getDbConnection();

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
        $action = $_POST['action'] ?? 'save_matrix';

        // 1. SAVE COMPLETE PERMISSION MATRIX
        if ($action === 'save_matrix') {
            $rawMatrix = $_POST['matrix'] ?? [];
            $allRoles = $roleModel->getAllWithCounts();
            $matrixToSync = [];

            foreach ($allRoles as $r) {
                $rId = (int)$r['id'];
                if ($rId === 1) continue; // Super admin always maintains universal access

                $assignedPerms = isset($rawMatrix[$rId]) && is_array($rawMatrix[$rId]) 
                    ? array_map('intval', array_keys($rawMatrix[$rId])) 
                    : [];
                $matrixToSync[$rId] = $assignedPerms;
            }

            $res = $roleModel->syncRolePermissionsMatrix($matrixToSync);
            if ($res['success']) {
                $successMsg = 'Institution permission matrix successfully updated and deployed! Permissions are live across all active user sessions.';
                $sysModel->logAudit(
                    $userId, 
                    'SUPER_ADMIN_SYNC_PERMISSIONS', 
                    'permissions', 
                    0, 
                    'Synchronized institution RBAC permission matrix for faculty, administrators, and students.'
                );
            } else {
                $errorMsg = $res['error'];
            }
        }
        // 2. SAVE PERMISSIONS FOR A SINGLE ROLE
        elseif ($action === 'save_role_permissions') {
            $targetRoleId = (int)($_POST['role_id'] ?? 0);
            $selectedPerms = isset($_POST['permissions']) && is_array($_POST['permissions']) 
                ? array_map('intval', $_POST['permissions']) 
                : [];

            if ($targetRoleId > 0) {
                if ($targetRoleId === 1) {
                    $errorMsg = 'Super Administrator permissions cannot be restricted; root universal access is permanently enforced.';
                } else {
                    $res = $roleModel->syncPermissions($targetRoleId, $selectedPerms);
                    if ($res) {
                        $roleData = $roleModel->findById($targetRoleId);
                        $roleTitle = $roleData['display_name'] ?? "Role #{$targetRoleId}";
                        $successMsg = "Permissions for '{$roleTitle}' successfully updated with " . count($selectedPerms) . " active permission(s)!";
                        $sysModel->logAudit(
                            $userId,
                            'SUPER_ADMIN_UPDATE_ROLE_PERMS',
                            'role_permissions',
                            $targetRoleId,
                            "Updated permissions for {$roleTitle} (granted: " . count($selectedPerms) . ")."
                        );
                    } else {
                        $errorMsg = 'Failed to update role permissions in database.';
                    }
                }
            }
        }
        // 3. CREATE NEW CUSTOM PERMISSION
        elseif ($action === 'create_permission') {
            $module = sanitize($_POST['module'] ?? 'general');
            $slug = sanitize($_POST['slug'] ?? '');
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');

            $res = $roleModel->createPermission($module, $slug, $name, $description);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_CREATE_PERMISSION', 'permissions', $res['id'] ?? 0, "Created permission '{$name}' [{$slug}].");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // 4. EDIT EXISTING PERMISSION
        elseif ($action === 'edit_permission') {
            $permId = (int)($_POST['permission_id'] ?? 0);
            $module = sanitize($_POST['module'] ?? 'general');
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');

            $res = $roleModel->updatePermission($permId, $module, $name, $description);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_UPDATE_PERMISSION', 'permissions', $permId, "Updated permission #{$permId} '{$name}'.");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // 5. DELETE CUSTOM PERMISSION
        elseif ($action === 'delete_permission') {
            $permId = (int)($_POST['permission_id'] ?? 0);
            $res = $roleModel->deletePermission($permId);
            if ($res['success']) {
                $successMsg = $res['message'];
                $sysModel->logAudit($userId, 'SUPER_ADMIN_DELETE_PERMISSION', 'permissions', $permId, "Deleted custom permission #{$permId}.");
            } else {
                $errorMsg = $res['error'];
            }
        }
        // 6. RESTORE RECOMMENDED DEFAULTS FOR A ROLE
        elseif ($action === 'reset_defaults') {
            $resetRoleId = (int)($_POST['role_id'] ?? 0);
            if ($resetRoleId > 0 && $conn) {
                $defaultsMap = [
                    // Admin: Everything except raw system full control and direct ai settings
                    2 => ['users.manage', 'academic.manage', 'students.manage', 'faculty.manage', 'attendance.mark', 'assignments.manage', 'exams.manage', 'ai.use', 'logs.view', 'backup.manage', 'departments.manage', 'schedules.manage', 'results.manage', 'reports.view'],
                    // Faculty: Teaching, grading, attendance, coursework, exams, AI tools
                    3 => ['attendance.mark', 'assignments.manage', 'exams.manage', 'ai.use', 'schedules.manage', 'results.manage'],
                    // Student: AI use
                    4 => ['ai.use']
                ];

                if (isset($defaultsMap[$resetRoleId])) {
                    $slugs = $defaultsMap[$resetRoleId];
                    $inPlaceholders = "'" . implode("','", array_map([$conn, 'real_escape_string'], $slugs)) . "'";
                    $pRes = $conn->query("SELECT id FROM permissions WHERE slug IN ($inPlaceholders)");
                    $pIds = [];
                    if ($pRes) {
                        while ($r = $pRes->fetch_assoc()) {
                            $pIds[] = (int)$r['id'];
                        }
                    }
                    $roleModel->syncPermissions($resetRoleId, $pIds);
                    $roleData = $roleModel->findById($resetRoleId);
                    $roleTitle = $roleData['display_name'] ?? "Role #{$resetRoleId}";
                    $successMsg = "Standard recommended permissions restored for '{$roleTitle}' (" . count($pIds) . " permissions enabled).";
                    $sysModel->logAudit($userId, 'SUPER_ADMIN_RESET_PERMISSIONS', 'roles', $resetRoleId, "Restored default permissions for {$roleTitle}.");
                }
            }
        }
    }
}

// Fetch all permissions grouped by module
$allPermissionsData = $roleModel->getAllPermissions();
$allPermissions = $allPermissionsData['all'] ?? [];
$permissionsGrouped = $allPermissionsData['grouped'] ?? [];

// Fetch all roles
$roles = $roleModel->getAllWithCounts();

// Build permission assignment map: $rolePermMap[role_id][perm_id] = true
$rolePermMap = [];
if ($conn) {
    $rpRes = $conn->query("SELECT role_id, permission_id FROM role_permissions");
    if ($rpRes) {
        while ($row = $rpRes->fetch_assoc()) {
            $rolePermMap[(int)$row['role_id']][(int)$row['permission_id']] = true;
        }
    }
}

// Module display metadata
$moduleBadges = [
    'system'      => ['name' => 'System & Root', 'icon' => 'fa-shield-alt', 'color' => '#EF4444'],
    'users'       => ['name' => 'User Directory', 'icon' => 'fa-users', 'color' => '#3B82F6'],
    'academic'    => ['name' => 'Academics & Curricula', 'icon' => 'fa-university', 'color' => '#8B5CF6'],
    'students'    => ['name' => 'Student Affairs', 'icon' => 'fa-user-graduate', 'color' => '#10B981'],
    'faculty'     => ['name' => 'Faculty Affairs', 'icon' => 'fa-chalkboard-teacher', 'color' => '#F59E0B'],
    'attendance'  => ['name' => 'Attendance Management', 'icon' => 'fa-user-check', 'color' => '#06B6D4'],
    'assignments' => ['name' => 'Assignments & Coursework', 'icon' => 'fa-file-alt', 'color' => '#EC4899'],
    'exams'       => ['name' => 'Exams & Evaluations', 'icon' => 'fa-pencil-alt', 'color' => '#6366F1'],
    'ai'          => ['name' => 'Artificial Intelligence', 'icon' => 'fa-robot', 'color' => '#A855F7'],
    'logs'        => ['name' => 'Audit & Security Logs', 'icon' => 'fa-history', 'color' => '#64748B'],
    'backup'      => ['name' => 'Backup & Maintenance', 'icon' => 'fa-database', 'color' => '#D97706'],
    'reports'     => ['name' => 'Reports & Analytics', 'icon' => 'fa-chart-line', 'color' => '#14B8A6'],
];
?>
<?php
$pageTitle = 'Manage Permissions - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h1><i class="fas fa-key" style="color: var(--primary); margin-right: 8px;"></i> Master RBAC Permission Management</h1>
                        <p class="page-subtitle">Full authority control: Configure, grant, or revoke permissions across Faculty, Administrators, and Students in real time</p>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" onclick="openModal('createPermModal')" style="display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-plus-circle"></i> Add Custom Permission
                        </button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('matrixForm').submit();" style="display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-save"></i> Save All Permissions
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($errorMsg); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Authority Overview Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Super Admin Authority</div>
                            <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;">Universal (Root)</div>
                        </div>
                    </div>

                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Admin Permissions</div>
                            <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;">
                                <?php echo count($rolePermMap[2] ?? []); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/ <?php echo count($allPermissions); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Faculty Permissions</div>
                            <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;">
                                <?php echo count($rolePermMap[3] ?? []); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/ <?php echo count($allPermissions); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Student Permissions</div>
                            <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.2;">
                                <?php echo count($rolePermMap[4] ?? []); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/ <?php echo count($allPermissions); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px;">
                    <button type="button" class="tab-btn active" id="tabBtnMatrix" onclick="switchPermTab('matrix')">
                        <i class="fas fa-table-cells"></i> Institutional Permission Matrix
                    </button>
                    <button type="button" class="tab-btn" id="tabBtnRoleFocus" onclick="switchPermTab('roleFocus')">
                        <i class="fas fa-sliders"></i> Role-by-Role Management
                    </button>
                    <button type="button" class="tab-btn" id="tabBtnCatalog" onclick="switchPermTab('catalog')">
                        <i class="fas fa-list-check"></i> Permissions Catalog (<?php echo count($allPermissions); ?>)
                    </button>
                </div>

                <!-- TAB 1: INTERACTIVE PERMISSION MATRIX -->
                <div id="tabContentMatrix" class="tab-pane active">
                    <form method="POST" action="permissions.php" id="matrixForm">
                        <input type="hidden" name="action" value="save_matrix">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <div class="card">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                <div>
                                    <h3 style="margin: 0;"><i class="fas fa-th-list"></i> Role Access Control Grid</h3>
                                    <span style="font-size: 12px; color: var(--text-muted);">Toggle permissions for each role. Click 'Save All Permissions' to commit changes.</span>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="text" id="permSearch" class="form-control" placeholder="Filter permissions..." style="font-size: 12px; padding: 6px 12px; width: 220px;" oninput="filterMatrixRows()">
                                    <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="data-table" id="matrixTable" style="margin: 0;">
                                        <thead>
                                            <tr style="background: var(--bg-primary);">
                                                <th style="min-width: 280px; text-align: left; padding: 14px 18px;">
                                                    Permission &amp; Module
                                                </th>
                                                <!-- Super Admin Column -->
                                                <th style="text-align: center; width: 140px; background: rgba(239, 68, 68, 0.04);">
                                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                                        <span style="color: #EF4444; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fas fa-crown"></i> Super Admin
                                                        </span>
                                                        <span style="font-size: 10px; color: var(--text-muted);">Root Master</span>
                                                    </div>
                                                </th>
                                                <!-- Other Roles Columns -->
                                                <?php foreach ($roles as $r): 
                                                    if ((int)$r['id'] === 1) continue;
                                                    $rId = (int)$r['id'];
                                                ?>
                                                    <th style="text-align: center; width: 140px;">
                                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                            <span style="font-weight: 700; color: var(--text-primary);">
                                                                <?php echo htmlspecialchars($r['display_name']); ?>
                                                            </span>
                                                            <div style="display: flex; gap: 4px;">
                                                                <button type="button" class="btn-link-xs" onclick="toggleRoleColumn(<?php echo $rId; ?>, true)" title="Check all for <?php echo htmlspecialchars($r['display_name']); ?>">All</button>
                                                                <span style="color: var(--text-muted); font-size: 10px;">|</span>
                                                                <button type="button" class="btn-link-xs" onclick="toggleRoleColumn(<?php echo $rId; ?>, false)" title="Clear all for <?php echo htmlspecialchars($r['display_name']); ?>">None</button>
                                                            </div>
                                                        </div>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            foreach ($permissionsGrouped as $modKey => $pList): 
                                                $modMeta = $moduleBadges[$modKey] ?? ['name' => ucfirst($modKey), 'icon' => 'fa-folder', 'color' => 'var(--primary)'];
                                            ?>
                                                <!-- Module Header Row -->
                                                <tr class="module-header-row" style="background: rgba(255, 255, 255, 0.03); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                                                    <td colspan="<?php echo count($roles) + 1; ?>" style="padding: 10px 18px;">
                                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                                            <span style="font-weight: 700; font-size: 13px; color: <?php echo $modMeta['color']; ?>; display: inline-flex; align-items: center; gap: 8px;">
                                                                <i class="fas <?php echo $modMeta['icon']; ?>"></i>
                                                                <?php echo htmlspecialchars($modMeta['name']); ?> (<?php echo count($pList); ?>)
                                                            </span>
                                                            <span style="font-size: 11px; color: var(--text-muted);">
                                                                Module: <code><?php echo htmlspecialchars($modKey); ?></code>
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php foreach ($pList as $p): 
                                                    $pId = (int)$p['id'];
                                                ?>
                                                    <tr class="perm-row" data-search="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $p['slug'] . ' ' . $p['module'] . ' ' . ($p['description'] ?? ''))); ?>">
                                                        <td style="padding: 12px 18px; text-align: left;">
                                                            <div style="font-weight: 600; font-size: 13.5px; color: var(--text-primary);">
                                                                <?php echo htmlspecialchars($p['name']); ?>
                                                            </div>
                                                            <div style="font-size: 11px; color: var(--text-muted); font-family: monospace; margin-top: 2px;">
                                                                <?php echo htmlspecialchars($p['slug']); ?>
                                                            </div>
                                                            <?php if (!empty($p['description'])): ?>
                                                                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                                                                    <?php echo htmlspecialchars($p['description']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <!-- Super Admin Master Access -->
                                                        <td style="text-align: center; background: rgba(239, 68, 68, 0.04);">
                                                            <span title="Super Administrator always has root platform control" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #EF4444;">
                                                                <i class="fas fa-check"></i>
                                                            </span>
                                                        </td>
                                                        <!-- Interactive Checkboxes for Other Roles -->
                                                        <?php foreach ($roles as $r): 
                                                            if ((int)$r['id'] === 1) continue;
                                                            $rId = (int)$r['id'];
                                                            $isAssigned = isset($rolePermMap[$rId][$pId]);
                                                        ?>
                                                            <td style="text-align: center;">
                                                                <label class="custom-checkbox" style="margin: 0; cursor: pointer;">
                                                                    <input type="checkbox" 
                                                                           name="matrix[<?php echo $rId; ?>][<?php echo $pId; ?>]" 
                                                                           value="1" 
                                                                           class="role-chk-<?php echo $rId; ?> perm-chk" 
                                                                           <?php echo $isAssigned ? 'checked' : ''; ?>>
                                                                    <span class="checkmark"></span>
                                                                </label>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <span style="font-size: 12px; color: var(--text-muted);">
                                    <i class="fas fa-info-circle"></i> Permissions saved here immediately take effect for all Faculty, Administrators, and Students.
                                </span>
                                <div style="display: flex; gap: 10px;">
                                    <button type="reset" class="btn btn-secondary btn-sm" onclick="setTimeout(updateCounterBadges, 50)">Reset Form</button>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save All Permissions</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: ROLE FOCUS MANAGEMENT -->
                <div id="tabContentRoleFocus" class="tab-pane" style="display: none;">
                    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 20px;">
                        <!-- Role Selector Sidebar -->
                        <div class="card" style="height: fit-content;">
                            <div class="card-header">
                                <h3><i class="fas fa-users-cog"></i> Select User Tier</h3>
                            </div>
                            <div class="card-body" style="padding: 8px;">
                                <?php foreach ($roles as $r): 
                                    $rId = (int)$r['id'];
                                    $isSuper = ($rId === 1);
                                    $pCount = $isSuper ? count($allPermissions) : count($rolePermMap[$rId] ?? []);
                                ?>
                                    <div class="role-selector-item <?php echo $rId === 2 ? 'active' : ''; ?>" 
                                         id="roleItem-<?php echo $rId; ?>" 
                                         onclick="selectFocusRole(<?php echo $rId; ?>, '<?php echo htmlspecialchars(addslashes($r['display_name'])); ?>', <?php echo $isSuper ? 'true' : 'false'; ?>)">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($r['display_name']); ?></strong>
                                            <?php if ($isSuper): ?>
                                                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #EF4444; font-size: 10px;">Full Root</span>
                                            <?php else: ?>
                                                <span class="badge badge-primary" style="font-size: 10px;" id="roleBadge-<?php echo $rId; ?>"><?php echo $pCount; ?> perms</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                            Code: <code><?php echo htmlspecialchars($r['name']); ?></code> &bull; Users: <?php echo (int)$r['user_count']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Role Permissions Form Area -->
                        <div class="card">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <h3 id="focusRoleTitle"><i class="fas fa-shield-alt"></i> Permissions for Administrator</h3>
                                    <span style="font-size: 12px; color: var(--text-muted);" id="focusRoleDesc">Configure exact capabilities granted to administrator accounts</span>
                                </div>
                                <div id="focusRoleActions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <form method="POST" action="permissions.php" style="display: inline;" onsubmit="return confirm('Restore recommended standard permissions for this role?');">
                                        <input type="hidden" name="action" value="reset_defaults">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="role_id" id="resetRoleIdInput" value="2">
                                        <button type="submit" class="btn btn-outline btn-sm" title="Restore system recommended preset">
                                            <i class="fas fa-undo"></i> Reset Recommended
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleFocusRoleAll(true)">Select All</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleFocusRoleAll(false)">Clear All</button>
                                </div>
                            </div>
                            <form method="POST" action="permissions.php" id="focusRoleForm">
                                <input type="hidden" name="action" value="save_role_permissions">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="role_id" id="focusRoleIdInput" value="2">

                                <div class="card-body" id="focusRoleBody" style="padding: 20px;">
                                    <!-- Dynamic module permission cards rendered via JS -->
                                </div>

                                <div class="card-footer" id="focusRoleFooter" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                                    <button type="submit" class="btn btn-primary" id="saveRolePermBtn">
                                        <i class="fas fa-save"></i> Save Role Permissions
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: PERMISSIONS CATALOG (ADD / EDIT) -->
                <div id="tabContentCatalog" class="tab-pane" style="display: none;">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-list"></i> System Permissions Catalog</h3>
                                <span style="font-size: 12px; color: var(--text-muted);">Master registry of authorization capabilities across all StudentOS AI modules</span>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createPermModal')">
                                <i class="fas fa-plus"></i> New Permission
                            </button>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="data-table" style="margin: 0;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Module</th>
                                            <th>Permission Slug</th>
                                            <th>Display Title</th>
                                            <th>Description</th>
                                            <th>Granted Roles</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allPermissions as $p): 
                                            $pId = (int)$p['id'];
                                            $modKey = $p['module'];
                                            $modMeta = $moduleBadges[$modKey] ?? ['name' => ucfirst($modKey), 'color' => 'var(--primary)'];
                                            
                                            // Find roles having this permission
                                            $rolesWithThisPerm = [];
                                            foreach ($roles as $r) {
                                                if ((int)$r['id'] === 1 || isset($rolePermMap[(int)$r['id']][$pId])) {
                                                    $rolesWithThisPerm[] = $r['display_name'];
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><span style="font-size: 12px; color: var(--text-muted);">#<?php echo $pId; ?></span></td>
                                                <td>
                                                    <span class="badge" style="background: rgba(255,255,255,0.05); color: <?php echo $modMeta['color']; ?>; font-weight: 700;">
                                                        <?php echo htmlspecialchars($p['module']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <code style="font-size: 12px; color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($p['slug']); ?></code>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span style="font-size: 12.5px; color: var(--text-secondary);"><?php echo htmlspecialchars($p['description'] ?? '—'); ?></span>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                        <?php foreach ($rolesWithThisPerm as $rn): ?>
                                                            <span class="badge badge-dark" style="font-size: 10px;"><?php echo htmlspecialchars($rn); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 6px;">
                                                        <button type="button" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 11px;" 
                                                                onclick="openEditPermModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Description">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($pId > 12): ?>
                                                            <form method="POST" action="permissions.php" style="display: inline;" onsubmit="return confirm('Delete this custom permission?');">
                                                                <input type="hidden" name="action" value="delete_permission">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="permission_id" value="<?php echo $pId; ?>">
                                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger); padding: 3px 8px; font-size: 11px;" title="Delete Permission">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </form>
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
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Create Permission Modal -->
    <div class="modal-backdrop" id="createPermModal">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Custom Permission</h3>
                <button class="modal-close" onclick="closeModal('createPermModal')">&times;</button>
            </div>
            <form method="POST" action="permissions.php">
                <input type="hidden" name="action" value="create_permission">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newPermModule">Module Identifier *</label>
                        <input type="text" name="module" id="newPermModule" class="form-control" placeholder="e.g. academic, exams, reports, tasks" required>
                    </div>
                    <div class="form-group">
                        <label for="newPermSlug">Permission Code / Slug *</label>
                        <input type="text" name="slug" id="newPermSlug" class="form-control" placeholder="e.g. academic.schedules_export, results.publish" required>
                        <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 3px;">Unique dot-notated identifier (e.g. module.action)</small>
                    </div>
                    <div class="form-group">
                        <label for="newPermName">Display Title *</label>
                        <input type="text" name="name" id="newPermName" class="form-control" placeholder="e.g. Export Class Timetables" required>
                    </div>
                    <div class="form-group">
                        <label for="newPermDesc">Description</label>
                        <textarea name="description" id="newPermDesc" class="form-control" rows="3" placeholder="Explain the security scope and what access this permission grants..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createPermModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Permission</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Permission Modal -->
    <div class="modal-backdrop" id="editPermModal">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Permission</h3>
                <button class="modal-close" onclick="closeModal('editPermModal')">&times;</button>
            </div>
            <form method="POST" action="permissions.php">
                <input type="hidden" name="action" value="edit_permission">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="permission_id" id="editPermId" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editPermSlug">Permission Slug</label>
                        <input type="text" id="editPermSlug" class="form-control" disabled style="opacity: 0.7;">
                    </div>
                    <div class="form-group">
                        <label for="editPermModule">Module Identifier *</label>
                        <input type="text" name="module" id="editPermModule" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editPermName">Display Title *</label>
                        <input type="text" name="name" id="editPermName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editPermDesc">Description</label>
                        <textarea name="description" id="editPermDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editPermModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    /* Tab Buttons */
    .tab-btn {
        background: transparent;
        border: none;
        padding: 10px 18px;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        transition: all 0.2s ease;
    }
    .tab-btn:hover {
        color: var(--text-primary);
    }
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    /* Role selector in tab 2 */
    .role-selector-item {
        padding: 12px 14px;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all 0.15s ease;
        margin-bottom: 6px;
        border: 1px solid transparent;
    }
    .role-selector-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    .role-selector-item.active {
        background: rgba(79, 70, 229, 0.1);
        border-color: rgba(79, 70, 229, 0.3);
    }

    /* Custom Checkbox */
    .custom-checkbox {
        position: relative;
        display: inline-block;
        width: 18px;
        height: 18px;
    }
    .custom-checkbox input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .custom-checkbox .checkmark {
        position: absolute;
        top: 0;
        left: 0;
        height: 18px;
        width: 18px;
        background-color: rgba(255, 255, 255, 0.08);
        border: 1.5px solid var(--border-color);
        border-radius: 4px;
        transition: all 0.15s ease;
    }
    .custom-checkbox:hover input ~ .checkmark {
        border-color: var(--primary);
    }
    .custom-checkbox input:checked ~ .checkmark {
        background-color: var(--primary);
        border-color: var(--primary);
    }
    .custom-checkbox .checkmark:after {
        content: "";
        position: absolute;
        display: none;
        left: 5px;
        top: 2px;
        width: 5px;
        height: 9px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .custom-checkbox input:checked ~ .checkmark:after {
        display: block;
    }

    .btn-link-xs {
        background: none;
        border: none;
        color: var(--primary);
        font-size: 11px;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .btn-link-xs:hover {
        color: var(--primary-hover);
    }
    </style>

    <script src="../assets/js/utils.js"></script>
    <script>
    // State data passed from PHP
    const allPermissionsGrouped = <?php echo json_encode($permissionsGrouped); ?>;
    const allRoles = <?php echo json_encode($roles); ?>;
    const rolePermMap = <?php echo json_encode($rolePermMap); ?>;
    let currentFocusRoleId = 2;

    function switchPermTab(tabKey) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');

        if (tabKey === 'matrix') {
            document.getElementById('tabBtnMatrix').classList.add('active');
            document.getElementById('tabContentMatrix').style.display = 'block';
        } else if (tabKey === 'roleFocus') {
            document.getElementById('tabBtnRoleFocus').classList.add('active');
            document.getElementById('tabContentRoleFocus').style.display = 'block';
            renderFocusRole(currentFocusRoleId);
        } else if (tabKey === 'catalog') {
            document.getElementById('tabBtnCatalog').classList.add('active');
            document.getElementById('tabContentCatalog').style.display = 'block';
        }
    }

    // Toggle all checkboxes in a role column in matrix view
    function toggleRoleColumn(roleId, checkState) {
        const checkboxes = document.querySelectorAll('.role-chk-' + roleId);
        checkboxes.forEach(cb => {
            cb.checked = checkState;
        });
    }

    // Filter matrix rows by search query
    function filterMatrixRows() {
        const q = document.getElementById('permSearch').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.perm-row');
        const moduleHeaders = document.querySelectorAll('.module-header-row');

        if (!q) {
            rows.forEach(r => r.style.display = '');
            moduleHeaders.forEach(m => m.style.display = '');
            return;
        }

        rows.forEach(r => {
            const data = r.getAttribute('data-search') || '';
            r.style.display = data.includes(q) ? '' : 'none';
        });
    }

    // Select role in Tab 2 Focus View
    function selectFocusRole(roleId, displayName, isSuper) {
        currentFocusRoleId = roleId;
        document.querySelectorAll('.role-selector-item').forEach(i => i.classList.remove('active'));
        const item = document.getElementById('roleItem-' + roleId);
        if (item) item.classList.add('active');

        document.getElementById('focusRoleTitle').innerHTML = '<i class="fas fa-shield-alt"></i> Permissions for ' + displayName;
        document.getElementById('focusRoleIdInput').value = roleId;
        document.getElementById('resetRoleIdInput').value = roleId;

        const actions = document.getElementById('focusRoleActions');
        const footer = document.getElementById('focusRoleFooter');

        if (isSuper) {
            document.getElementById('focusRoleDesc').textContent = 'Root Administrator has permanent universal authority across all modules and cannot be restricted.';
            actions.style.display = 'none';
            footer.style.display = 'none';
        } else {
            document.getElementById('focusRoleDesc').textContent = 'Manage active authorization privileges for ' + displayName + ' accounts.';
            actions.style.display = 'flex';
            footer.style.display = 'flex';
        }

        renderFocusRole(roleId, isSuper);
    }

    function renderFocusRole(roleId, isSuper = false) {
        const container = document.getElementById('focusRoleBody');
        container.innerHTML = '';

        const assignedMap = rolePermMap[roleId] || {};

        for (const [moduleKey, pList] of Object.entries(allPermissionsGrouped)) {
            const modCard = document.createElement('div');
            modCard.style.cssText = 'background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px;';

            let modHeader = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 700; font-size: 14px; text-transform: uppercase; color: var(--primary); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-folder"></i> ${moduleKey}
                    </div>
                    ${!isSuper ? `<button type="button" class="btn-link-xs" onclick="toggleModulePills('${moduleKey}', true)">All</button> | <button type="button" class="btn-link-xs" onclick="toggleModulePills('${moduleKey}', false)">None</button>` : ''}
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
            `;

            let itemsHtml = '';
            pList.forEach(p => {
                const isChecked = isSuper || Boolean(assignedMap[p.id]);
                itemsHtml += `
                    <label style="display: flex; align-items: flex-start; gap: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; cursor: ${isSuper ? 'default' : 'pointer'};">
                        <input type="checkbox" name="permissions[]" value="${p.id}" class="focus-chk focus-mod-${moduleKey}" ${isChecked ? 'checked' : ''} ${isSuper ? 'disabled checked' : ''} style="margin-top: 3px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);">${p.name}</div>
                            <div style="font-size: 11px; color: var(--primary); font-family: monospace;">${p.slug}</div>
                            ${p.description ? `<div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">${p.description}</div>` : ''}
                        </div>
                    </label>
                `;
            });

            modCard.innerHTML = modHeader + itemsHtml + '</div>';
            container.appendChild(modCard);
        }
    }

    function toggleFocusRoleAll(state) {
        document.querySelectorAll('.focus-chk').forEach(cb => {
            if (!cb.disabled) cb.checked = state;
        });
    }

    function toggleModulePills(modKey, state) {
        document.querySelectorAll('.focus-mod-' + modKey).forEach(cb => {
            if (!cb.disabled) cb.checked = state;
        });
    }

    function openEditPermModal(perm) {
        document.getElementById('editPermId').value = perm.id;
        document.getElementById('editPermSlug').value = perm.slug;
        document.getElementById('editPermModule').value = perm.module;
        document.getElementById('editPermName').value = perm.name;
        document.getElementById('editPermDesc').value = perm.description || '';
        openModal('editPermModal');
    }

    // Initialize focus role view
    document.addEventListener('DOMContentLoaded', function() {
        selectFocusRole(2, 'Administrator', false);
    });
    </script>
</body>
</html>
