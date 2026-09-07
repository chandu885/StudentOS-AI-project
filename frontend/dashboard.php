<?php
// frontend/dashboard.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Require authentication
requireAuth();

// Get user role and redirect to appropriate dashboard
$user = $_SESSION['user'];
$roleId = $user['role_id'];

$redirectMap = [
    1 => '/super-admin/dashboard.php',
    2 => '/admin/dashboard.php',
    3 => '/faculty/dashboard.php',
    4 => '/student/dashboard.php'
];

if (isset($redirectMap[$roleId])) {
    redirect($redirectMap[$roleId]);
}

// Fallback
redirect('/login.php');