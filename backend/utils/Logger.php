<?php
// backend/utils/Logger.php

require_once __DIR__ . '/../config/config.php';

class Logger {
    private static $logFile;
    private static $enabled = true;
    
    public static function init() {
        $logPath = Config::getInstance()->get('storage_path') . '/../logs';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }
        self::$logFile = $logPath . '/app_' . date('Y-m-d') . '.log';
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    public static function debug($message, $context = []) {
        if (Config::getInstance()->get('debug')) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    private static function log($level, $message, $context = []) {
        if (!self::$enabled) {
            return;
        }
        
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[$timestamp] $level: $message$contextStr" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}