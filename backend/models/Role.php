<?php
// backend/models/Role.php

require_once __DIR__ . '/../config/database.php';

class Role {
    private $db;

    // Protected core system role IDs that cannot be deleted or have their system code renamed
    const PROTECTED_ROLE_IDS = [1, 2, 3, 4];
    const PROTECTED_ROLE_NAMES = ['SUPER_ADMIN', 'ADMIN', 'FACULTY', 'STUDENT'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all roles with real-time user counts and permissions counts
     */
    public function getAllWithCounts() {
        $sql = "SELECT r.id, r.name, r.display_name, r.description, r.created_at, r.updated_at,
                       COUNT(DISTINCT u.id) AS user_count,
                       COUNT(DISTINCT rp.permission_id) AS perm_count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id AND u.deleted_at IS NULL
                LEFT JOIN role_permissions rp ON rp.role_id = r.id
                GROUP BY r.id, r.name, r.display_name, r.description, r.created_at, r.updated_at
                ORDER BY r.id ASC";
        $res = $this->db->query($sql);
        $roles = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        // Attach assigned permissions list to each role
        foreach ($roles as &$role) {
            $role['is_system'] = in_array((int)$role['id'], self::PROTECTED_ROLE_IDS, true) ||
                                 in_array(strtoupper($role['name']), self::PROTECTED_ROLE_NAMES, true);
            $role['permissions'] = $this->getRolePermissions((int)$role['id']);
        }
        unset($role);

        return $roles;
    }

    /**
     * Find single role by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        if ($role) {
            $role['is_system'] = in_array((int)$role['id'], self::PROTECTED_ROLE_IDS, true) ||
                                 in_array(strtoupper($role['name']), self::PROTECTED_ROLE_NAMES, true);
            $role['permissions'] = $this->getRolePermissions((int)$role['id']);
        }
        return $role;
    }

    /**
     * Find single role by Name
     */
    public function findByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Create a new custom role
     */
    public function createRole($name, $displayName, $description = null, array $permissionIds = []) {
        $cleanName = strtoupper(trim(preg_replace('/[^A-Za-z0-9_]/', '_', $name)));
        $cleanName = trim($cleanName, '_');
        $cleanDisplay = trim($displayName);
        $cleanDesc = $description !== null ? trim($description) : null;

        if (empty($cleanName)) {
            return ['success' => false, 'error' => 'Role identifier name is required and must contain alphanumeric characters.'];
        }
        if (empty($cleanDisplay)) {
            return ['success' => false, 'error' => 'Role display title is required.'];
        }

        // Check if role name already exists
        if ($this->findByName($cleanName)) {
            return ['success' => false, 'error' => "A role with identifier '{$cleanName}' already exists."];
        }

        $stmt = $this->db->prepare("INSERT INTO roles (name, display_name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $cleanName, $cleanDisplay, $cleanDesc);

        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to create role in database: ' . $this->db->getConnection()->error];
        }

        $newRoleId = $this->db->lastInsertId();

        // Assign permissions if provided
        if (!empty($permissionIds)) {
            $this->syncPermissions($newRoleId, $permissionIds);
        }

        return [
            'success' => true,
            'role_id' => $newRoleId,
            'role' => $this->findById($newRoleId),
            'message' => "Role '{$cleanDisplay}' created successfully!"
        ];
    }

    /**
     * Update an existing role
     */
    public function updateRole($id, $displayName, $description = null, array $permissionIds = null, $name = null) {
        $role = $this->findById($id);
        if (!$role) {
            return ['success' => false, 'error' => 'Role not found.'];
        }

        $cleanDisplay = trim($displayName);
        $cleanDesc = $description !== null ? trim($description) : null;

        if (empty($cleanDisplay)) {
            return ['success' => false, 'error' => 'Role display title cannot be empty.'];
        }

        $isSystem = in_array((int)$id, self::PROTECTED_ROLE_IDS, true);

        // Core system roles cannot have their code identifier renamed
        if (!$isSystem && !empty($name)) {
            $cleanName = strtoupper(trim(preg_replace('/[^A-Za-z0-9_]/', '_', $name)));
            $cleanName = trim($cleanName, '_');
            
            // Check for duplicate name if renaming
            $existing = $this->findByName($cleanName);
            if ($existing && (int)$existing['id'] !== (int)$id) {
                return ['success' => false, 'error' => "Role identifier '{$cleanName}' is already taken."];
            }

            $stmt = $this->db->prepare("UPDATE roles SET name = ?, display_name = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssi", $cleanName, $cleanDisplay, $cleanDesc, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE roles SET display_name = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $cleanDisplay, $cleanDesc, $id);
        }

        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Database update error: ' . $this->db->getConnection()->error];
        }

        // Sync permissions if provided
        if ($permissionIds !== null) {
            $this->syncPermissions($id, $permissionIds);
        }

        return [
            'success' => true,
            'role' => $this->findById($id),
            'message' => "Role '{$cleanDisplay}' updated successfully!"
        ];
    }

    /**
     * Delete a custom role
     */
    public function deleteRole($id) {
        $id = (int)$id;

        // Check if system protected role
        if (in_array($id, self::PROTECTED_ROLE_IDS, true)) {
            return ['success' => false, 'error' => 'System default roles (SUPER_ADMIN, ADMIN, FACULTY, STUDENT) are critical to system operations and cannot be deleted.'];
        }

        $role = $this->findById($id);
        if (!$role) {
            return ['success' => false, 'error' => 'Role not found.'];
        }

        // Check if any active users are assigned
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM users WHERE role_id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $userCount = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

        if ($userCount > 0) {
            return [
                'success' => false,
                'error' => "Cannot delete role '{$role['display_name']}'. It currently has {$userCount} active assigned user account(s). Please reassign or remove these users first."
            ];
        }

        // Delete associated role permissions
        $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Delete role
        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => "Role '{$role['display_name']}' deleted successfully."];
        }

        return ['success' => false, 'error' => 'Failed to delete role: ' . $this->db->getConnection()->error];
    }

    /**
     * Get all available permissions from the system grouped by module
     */
    public function getAllPermissions() {
        $res = $this->db->query("SELECT * FROM permissions ORDER BY module ASC, id ASC");
        $permissions = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $grouped = [];
        foreach ($permissions as $p) {
            $grouped[$p['module']][] = $p;
        }
        return ['all' => $permissions, 'grouped' => $grouped];
    }

    /**
     * Get permission IDs and details assigned to a role
     */
    public function getRolePermissions($roleId) {
        $stmt = $this->db->prepare(
            "SELECT p.* 
             FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             ORDER BY p.module ASC, p.id ASC"
        );
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Sync permissions for a role
     */
    public function syncPermissions($roleId, array $permissionIds) {
        $roleId = (int)$roleId;

        // Delete existing permissions for role
        $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();

        // Insert new permissions
        if (!empty($permissionIds)) {
            $ins = $this->db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $pId) {
                $pId = (int)$pId;
                if ($pId > 0) {
                    $ins->bind_param("ii", $roleId, $pId);
                    $ins->execute();
                }
            }
        }

        return true;
    }
}
