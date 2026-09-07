<?php
// backend/middleware/AuthMiddleware.php

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Session.php';

class AuthMiddleware {
    private $db;
    private $userModel;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userModel = new User();
    }
    
    public function authenticate() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            $this->sendError('Authorization header required', 401);
        }
        
        $authHeader = $headers['Authorization'];
        if (strpos($authHeader, 'Bearer ') !== 0) {
            $this->sendError('Invalid authorization header format', 401);
        }
        
        $token = substr($authHeader, 7);
        
        $payload = JWT::verify($token);
        if (!$payload) {
            $this->sendError('Invalid or expired token', 401);
        }
        
        $user = $this->userModel->findById($payload['user_id']);
        if (!$user) {
            $this->sendError('User not found', 401);
        }
        
        if (!$user['is_active']) {
            $this->sendError('Account deactivated', 403);
        }
        
        if (!$user['is_verified']) {
            $this->sendError('Email not verified', 403);
        }
        
        if (isset($headers['X-Session-Token'])) {
            $sessionToken = $headers['X-Session-Token'];
            $session = $this->validateSession($user['id'], $sessionToken);
            if (!$session) {
                $this->sendError('Invalid session', 401);
            }
            $this->updateSessionActivity($sessionToken);
        }
        
        $GLOBALS['current_user'] = $user;
        return $user;
    }
    
    private function validateSession($userId, $sessionToken) {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_sessions 
             WHERE user_id = ? AND session_token = ? AND is_active = 1 AND expires_at > NOW()"
        );
        $stmt->bind_param("is", $userId, $sessionToken);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function updateSessionActivity($sessionToken) {
        $stmt = $this->db->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?");
        $stmt->bind_param("s", $sessionToken);
        $stmt->execute();
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}

function currentUser() {
    return $GLOBALS['current_user'] ?? null;
}

function requireAuth() {
    $auth = new AuthMiddleware();
    return $auth->authenticate();
}

function requireRole($roleId) {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    if ($user['role_id'] != $roleId) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }
}

function requireAdmin() {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    if ($user['role_id'] > 2) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
}

function requireSuperAdmin() {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    if ($user['role_id'] != 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Super Admin access required']);
        exit;
    }
}