<?php
// frontend/includes/config.php

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Session configuration
if (!headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
}

// Constants
define('BASE_PATH', realpath(__DIR__ . '/../..'));
define('API_URL', 'http://localhost/StudentOS-AI-project/backend/api');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Database configuration (for direct DB access if needed)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'studentos_ai');

// API call function
function apiCall($endpoint, $method = 'GET', $data = null) {
    $url = API_URL . $endpoint;
    
    // Check if cURL extension is available
    if (!function_exists('curl_init')) {
        $headers = "Content-Type: application/json\r\n";
        if (isset($_SESSION['auth_token'])) {
            $headers .= "Authorization: Bearer " . $_SESSION['auth_token'] . "\r\n";
        }
        if (isset($_SESSION['session_token'])) {
            $headers .= "X-Session-Token: " . $_SESSION['session_token'] . "\r\n";
        }
        $options = [
            'http' => [
                'method'        => $method,
                'header'        => $headers,
                'timeout'       => 10,
                'ignore_errors' => true
            ]
        ];
        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }
        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'API connection unavailable'];
        }
        $result = json_decode($response, true);
        return is_array($result) ? $result : ['success' => false, 'error' => 'Invalid JSON response'];
    }

    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Add headers
    $headers = ['Content-Type: application/json'];
    
    // Add auth token if available
    if (isset($_SESSION['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['auth_token'];
    }
    if (isset($_SESSION['session_token'])) {
        $headers[] = 'X-Session-Token: ' . $_SESSION['session_token'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Set method
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 400) {
        return ['success' => false, 'error' => $result['error'] ?? 'API request failed'];
    }
    
    return $result;
}