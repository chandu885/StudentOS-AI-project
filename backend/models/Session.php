<?php
// backend/models/Session.php

require_once __DIR__ . '/../config/database.php';

class Session {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $sessionToken, $ipAddress, $userAgent, $expiresAt) {
        $stmt = $this->db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, last_activity, expires_at) 
             VALUES (?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->bind_param("issss", $userId, $sessionToken, $ipAddress, $userAgent, $expiresAt);
        return $stmt->execute();
    }

    public function findByToken($sessionToken) {
        $stmt = $this->db->prepare("SELECT * FROM user_sessions WHERE session_token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $sessionToken);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function updateActivity($sessionToken) {
        $stmt = $this->db->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?");
        $stmt->bind_param("s", $sessionToken);
        return $stmt->execute();
    }

    public function invalidate($sessionToken) {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $sessionToken);
        return $stmt->execute();
    }

    public function invalidateAll($userId, $exceptSessionToken = null) {
        if ($exceptSessionToken) {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token != ?");
            $stmt->bind_param("is", $userId, $exceptSessionToken);
        } else {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
        }
        return $stmt->execute();
    }

    public function cleanExpired() {
        return $this->db->query("DELETE FROM user_sessions WHERE expires_at <= NOW()");
    }
}
