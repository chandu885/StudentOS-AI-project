<?php
// frontend/includes/session.php

// Session management
function startSecureSession() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            ini_set('session.cookie_samesite', 'Strict');
            session_name('STUDENTOS_SESSION');
        }
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function createCSRFField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Render standardized Logout Button HTML
 *
 * @param string $extraClass
 * @param string $label
 * @return string HTML
 */
function renderLogoutButton($extraClass = 'btn btn-danger btn-logout-action', $label = 'Log Out') {
    $logoutUrl = function_exists('url') ? url('/logout.php') : '/logout.php';
    return '<a href="' . htmlspecialchars($logoutUrl) . '" class="' . htmlspecialchars($extraClass) . '" role="button" title="Sign Out of Session"'
         . ' onclick="try{localStorage.removeItem(\'auth_token\');localStorage.removeItem(\'session_token\');sessionStorage.removeItem(\'auth_token\');sessionStorage.removeItem(\'session_token\');}catch(e){}">'
         . '<i class="fas fa-sign-out-alt"></i> <span>' . htmlspecialchars($label) . '</span></a>';
}

/**
 * Render standardized Login / Opt-in Button HTML
 *
 * @param string $extraClass
 * @param string $label
 * @param string $targetUrl
 * @return string HTML
 */
function renderLoginButton($extraClass = 'btn btn-primary btn-login-action', $label = 'Sign In', $targetUrl = '/login.php') {
    $loginUrl = function_exists('url') ? url($targetUrl) : $targetUrl;
    return '<a href="' . htmlspecialchars($loginUrl) . '" class="' . htmlspecialchars($extraClass) . '" role="button" title="' . htmlspecialchars($label) . '">'
         . '<i class="fas fa-sign-in-alt"></i> <span>' . htmlspecialchars($label) . '</span></a>';
}

// Start session
startSecureSession();