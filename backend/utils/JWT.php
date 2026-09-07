<?php
// backend/utils/JWT.php

class JWT {
    private static $secret;
    private static $expiry;
    
    public static function init() {
        $config = Config::getInstance();
        self::$secret = $config->get('jwt_secret');
        self::$expiry = $config->get('jwt_expiry');
    }
    
    public static function generate($payload) {
        self::init();
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + self::$expiry;
        $payload['iat'] = time();
        $payloadJson = json_encode($payload);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payloadJson);
        $signature = self::signature($base64Header, $base64Payload);
        
        return $base64Header . '.' . $base64Payload . '.' . $signature;
    }
    
    public static function verify($token) {
        self::init();
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64Header, $base64Payload, $signature) = $parts;
        
        $expectedSignature = self::signature($base64Header, $base64Payload);
        if ($signature !== $expectedSignature) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    private static function signature($header, $payload) {
        $data = $header . '.' . $payload;
        return self::base64UrlEncode(hash_hmac('sha256', $data, self::$secret, true));
    }
    
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    private static function base64UrlDecode($data) {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($base64);
    }
}