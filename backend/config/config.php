<?php
// backend/config/config.php

class Config {
    private static $instance = null;
    private $settings = [];

    private function __construct() {
        $this->loadDefaults();
        $this->loadEnvironment();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadDefaults() {
        $baseDir = realpath(__DIR__ . '/../..');
        $this->settings = [
            'app_name' => 'StudentOS AI',
            'app_env' => 'development',
            'app_url' => 'http://localhost/StudentOS-AI-project',
            'base_path' => $baseDir,
            'storage_path' => $baseDir . '/storage',
            'upload_path' => $baseDir . '/storage/uploads',
            'max_file_size' => 20 * 1024 * 1024, // 20 MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'zip'],
            'debug' => true,
            
            // Database
            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_pass' => '',
            'db_name' => 'studentos_ai',
            'db_port' => 3306,
            'db_charset' => 'utf8mb4',
            
            // Auth & Security
            'jwt_secret' => 'StudentOS_AI_Super_Secure_JWT_Key_2026_Enterprise_Security',
            'jwt_expiry' => 86400 * 7, // 7 days
            'session_timeout' => 86400,
            'session_lifetime' => 86400,
            'max_login_attempts' => 5,
            'lockout_time' => 900, // 15 mins
            
            // AI Configuration
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-flash',
            'enable_rag' => true,
            
            // Mail settings
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_user' => '',
            'smtp_pass' => '',
            'mail_from' => 'noreply@studentos.ai',
            'mail_from_name' => 'StudentOS AI'
        ];
    }

    private function loadEnvironment() {
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = strtolower(trim($key));
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $this->settings[$key] = $value;
                }
            }
        }
    }

    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    public function set($key, $value) {
        $this->settings[$key] = $value;
    }

    public function all() {
        return $this->settings;
    }
}
