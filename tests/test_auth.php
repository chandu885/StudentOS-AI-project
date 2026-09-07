<?php
// tests/test_auth.php
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/services/AuthService.php';

$auth = new AuthService();
$res = $auth->login('student@studentos.ai', 'Student@12345', '127.0.0.1', 'PHP-CLI Test');

echo "Login Result:\n";
echo "Success: " . ($res['success'] ? 'YES' : 'NO') . "\n";
if ($res['success']) {
    echo "User: " . $res['user']['first_name'] . ' ' . $res['user']['last_name'] . "\n";
    echo "Role: " . $res['user']['role_name'] . "\n";
    echo "Token Generated: " . (!empty($res['token']) ? 'YES' : 'NO') . "\n";
} else {
    echo "Error: " . $res['error'] . "\n";
}
