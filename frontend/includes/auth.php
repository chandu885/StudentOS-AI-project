<?php
// frontend/includes/auth.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

function requireAuth() {
    if (!isLoggedIn()) {
        redirect('/login.php');
        exit;
    }
}

function requireRole($role) {
    requireAuth();
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        redirect('/login.php');
        exit;
    }
    
    $roleMap = [
        'super_admin' => 1,
        'super-admin' => 1,
        'admin' => 2,
        'faculty' => 3,
        'student' => 4
    ];
    
    $requiredRoleId = $roleMap[$role] ?? 0;
    
    // Exact match
    if ((int)$user['role_id'] === $requiredRoleId) {
        return true;
    }

    // Super Admin has universal access
    if ((int)$user['role_id'] === 1) {
        return true;
    }

    // If role doesn't match, redirect to their home portal
    $redirectMap = [
        1 => '/super-admin/dashboard.php',
        2 => '/admin/dashboard.php',
        3 => '/faculty/dashboard.php',
        4 => '/student/dashboard.php'
    ];
    $target = $redirectMap[(int)$user['role_id']] ?? '/dashboard.php';
    redirect($target);
    exit;
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['auth_token']) && !empty($_SESSION['user']);
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function hasRole($role) {
    $user = getCurrentUser();
    if (!$user) return false;
    $roleMap = ['super_admin' => 1, 'super-admin' => 1, 'admin' => 2, 'faculty' => 3, 'student' => 4];
    $id = $roleMap[$role] ?? 0;
    return (int)$user['role_id'] === $id;
}

function hasPermission($permissionSlug) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Super Admin has full universal control over all settings and modules
    if ((int)$user['role_id'] === 1) {
        return true;
    }

    $roleId = (int)$user['role_id'];
    $db = function_exists('getDbConnection') ? getDbConnection() : null;
    if (!$db && class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    }
    if ($db) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cnt 
             FROM role_permissions rp 
             JOIN permissions p ON rp.permission_id = p.id 
             WHERE rp.role_id = ? AND (p.slug = ? OR p.slug = 'system.full_control')"
        );
        if ($stmt) {
            $stmt->bind_param("is", $roleId, $permissionSlug);
            $stmt->execute();
            $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();
            return $cnt > 0;
        }
    }
    return false;
}

function requirePermission($permissionSlug) {
    requireAuth();
    if (!hasPermission($permissionSlug)) {
        http_response_code(403);
        $user = getCurrentUser();
        $redirectMap = [
            1 => '/super-admin/dashboard.php',
            2 => '/admin/dashboard.php',
            3 => '/faculty/dashboard.php',
            4 => '/student/dashboard.php'
        ];
        $target = $redirectMap[(int)($user['role_id'] ?? 4)] ?? '/dashboard.php';
        redirect($target . '?error=' . urlencode("Access Denied: Missing permission '$permissionSlug'"));
        exit;
    }
    return true;
}

function getUserPermissions($roleId) {
    $db = function_exists('getDbConnection') ? getDbConnection() : null;
    if (!$db && class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    }
    if (!$db) return [];
    $stmt = $db->prepare(
        "SELECT p.id, p.slug, p.name, p.module, p.description 
         FROM role_permissions rp 
         JOIN permissions p ON rp.permission_id = p.id 
         WHERE rp.role_id = ? 
         ORDER BY p.module ASC, p.name ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $perms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $perms;
}

function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['session_token'])) {
        $st = $_SESSION['session_token'];
        if (function_exists('getDbConnection')) {
            try {
                $db = getDbConnection();
                if ($db) {
                    $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $st);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } catch (Throwable $e) {
                // Silently ignore to ensure logout always succeeds
            }
        }
        try {
            apiCall('/auth.php?path=logout', 'POST');
        } catch (Throwable $e) {
            // Silently ignore to ensure logout always succeeds
        }
    }
    $_SESSION = [];
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}