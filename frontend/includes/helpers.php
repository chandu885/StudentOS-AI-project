<?php
// frontend/includes/helpers.php

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function url($path = '') {
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    $base = '';
    if (isset($_SERVER['SCRIPT_NAME']) && preg_match('#^(.*?/frontend)#i', $_SERVER['SCRIPT_NAME'], $m)) {
        $base = $m[1];
    }
    $cleanPath = '/' . ltrim($path, '/');
    if ($base && strpos($cleanPath, $base) !== 0) {
        return $base . $cleanPath;
    }
    return $cleanPath;
}

function redirect($url) {
    header('Location: ' . url($url));
    exit;
}

function getDashboardUrl($roleId = null) {
    if ($roleId === null) {
        $user = $_SESSION['user'] ?? null;
        $roleId = (int)($user['role_id'] ?? 4);
    }
    $map = [
        1 => '/super-admin/dashboard.php',
        2 => '/admin/dashboard.php',
        3 => '/faculty/dashboard.php',
        4 => '/student/dashboard.php'
    ];
    return $map[(int)$roleId] ?? '/student/dashboard.php';
}


function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}

function isOverdue($datetime) {
    return strtotime($datetime) < time();
}

function getStatusBadge($status) {
    $colors = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'published' => 'success',
        'draft' => 'secondary',
        'closed' => 'dark'
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge badge-' . $color . '">' . ucfirst($status) . '</span>';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

function generateRandomColor($seed = null) {
    if ($seed) {
        srand($seed);
    }
    $colors = ['#6366F1', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#3B82F6', '#10B981'];
    $color = $colors[array_rand($colors)];
    if ($seed) {
        srand();
    }
    return $color;
}

function truncate($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $append;
}

function getDbConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Database connection error: " . $conn->connect_error);
            return null;
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function renderMarkdown($text) {
    if (empty($text)) return '';
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/^### (.*?)$/m', '<h4 style="margin-top:16px; margin-bottom:8px; color:var(--primary); font-weight:600;">$1</h4>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h3 style="margin-top:20px; margin-bottom:10px; color:var(--text-primary); font-weight:700;">$1</h3>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h2 style="margin-top:24px; margin-bottom:12px; color:var(--text-primary); font-weight:800;">$1</h2>', $html);
    $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*([^\*]+)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/^[•\-\*]\s+(.*?)$/m', '<div style="display:flex; gap:8px; margin-bottom:4px; margin-left:12px;"><i class="fas fa-check-circle" style="color:var(--ai-accent); font-size:12px; margin-top:4px;"></i><span>$1</span></div>', $html);
    $html = nl2br($html);
    return $html;
}

