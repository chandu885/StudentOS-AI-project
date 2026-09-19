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
        // Ensure browser session is loaded if cookie exists
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            if (isset($_COOKIE['STUDENTOS_SESSION'])) {
                session_name('STUDENTOS_SESSION');
            }
            session_start();
        }

        $token = $this->extractBearerToken();
        $sessionToken = $this->extractSessionToken();

        // Check 1: Valid JWT Bearer token
        if ($token) {
            $payload = JWT::verify($token);
            if ($payload && !empty($payload['user_id'])) {
                $user = $this->userModel->findById($payload['user_id']);
                if ($user) {
                    if (!$user['is_active']) {
                        $this->sendError('Account deactivated', 403);
                    }
                    if (!$user['is_verified']) {
                        $this->sendError('Email not verified', 403);
                    }
                    if ($sessionToken) {
                        $session = $this->validateSession($user['id'], $sessionToken);
                        if ($session) {
                            $this->updateSessionActivity($sessionToken);
                        }
                    }
                    $GLOBALS['current_user'] = $user;
                    return $user;
                }
            }

            // If token was not a valid JWT, check if it's a database session token
            $userByToken = $this->findUserBySessionToken($token);
            if ($userByToken) {
                $GLOBALS['current_user'] = $userByToken;
                return $userByToken;
            }
        }

        // Check 2: X-Session-Token header or session_token
        if ($sessionToken) {
            $userBySess = $this->findUserBySessionToken($sessionToken);
            if ($userBySess) {
                $GLOBALS['current_user'] = $userBySess;
                return $userBySess;
            }
        }

        // Check 3: Active PHP session fallback (for web portal & AJAX fetch)
        if (!empty($_SESSION['user']['id'])) {
            $user = $this->userModel->findById((int)$_SESSION['user']['id']);
            if ($user && !empty($user['is_active'])) {
                $GLOBALS['current_user'] = $user;
                return $user;
            }
        }

        // Check 4: Session auth_token or session_token stored in $_SESSION
        if (!empty($_SESSION['session_token'])) {
            $userBySess = $this->findUserBySessionToken($_SESSION['session_token']);
            if ($userBySess) {
                $GLOBALS['current_user'] = $userBySess;
                return $userBySess;
            }
        }
        if (!empty($_SESSION['auth_token'])) {
            $userByToken = $this->findUserBySessionToken($_SESSION['auth_token']);
            if ($userByToken) {
                $GLOBALS['current_user'] = $userByToken;
                return $userByToken;
            }
        }

        $this->sendError('Authorization header required', 401);
    }

    private function findUserBySessionToken($sessionToken) {
        if (empty($sessionToken)) return null;
        try {
            $stmt = $this->db->prepare(
                "SELECT u.* FROM user_sessions s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1 
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("s", $sessionToken);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($user) {
                    $this->updateSessionActivity($sessionToken);
                    return $user;
                }
            }
        } catch (Throwable $e) {}
        return null;
    }

    private function extractBearerToken() {
        $authHeader = null;
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $authHeader = $v;
                break;
            }
        }

        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;
        }

        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            return trim(substr($authHeader, 7));
        }

        return null;
    }

    private function extractSessionToken() {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'X-Session-Token') === 0) {
                return $v;
            }
        }
        return $_SERVER['HTTP_X_SESSION_TOKEN'] ?? null;
    }

    private function validateSession($userId, $sessionToken) {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_sessions 
             WHERE user_id = ? AND session_token = ? AND expires_at > NOW()"
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