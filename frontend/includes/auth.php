<?php
// frontend/includes/auth.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

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