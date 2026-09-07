<?php
// backend/models/User.php

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data) {
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $this->db->prepare(
            "INSERT INTO users (role_id, email, password_hash, first_name, last_name, is_verified, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "issssii",
            $data['role_id'],
            $data['email'],
            $passwordHash,
            $data['first_name'],
            $data['last_name'],
            $data['is_verified'] ?? 0,
            $data['is_active'] ?? 1
        );
        
        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }
        return false;
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function update($id, $data) {
        $fields = [];
        $types = "";
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['first_name', 'last_name', 'is_verified', 'is_active'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
                $types .= is_int($value) ? "i" : "s";
            }
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $values[] = $id;
        $types .= "i";
        
        $sql = "UPDATE users SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        return $this->findById($id);
    }
    
    public function updatePassword($id, $password) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $passwordHash, $id);
        return $stmt->execute();
    }
    
    public function incrementLoginAttempts($email) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?");
        $stmt->bind_param("s", $email);
        return $stmt->execute();
    }
    
    public function lockAccount($email, $duration = 900) {
        $lockedUntil = date('Y-m-d H:i:s', time() + $duration);
        $stmt = $this->db->prepare("UPDATE users SET locked_until = ? WHERE email = ?");
        $stmt->bind_param("ss", $lockedUntil, $email);
        return $stmt->execute();
    }
    
    public function resetLoginAttempts($email) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
        $stmt->bind_param("s", $email);
        return $stmt->execute();
    }
    
    public function recordLogin($userId, $ipAddress, $userAgent, $sessionId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $stmt = $this->db->prepare("INSERT INTO login_logs (user_id, success, ip_address, user_agent, session_id) VALUES (?, 1, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $ipAddress, $userAgent, $sessionId);
        return $stmt->execute();
    }
    
    public function recordFailedLogin($email, $ipAddress, $userAgent, $reason) {
        $stmt = $this->db->prepare("INSERT INTO login_logs (user_id, success, ip_address, user_agent, failure_reason) VALUES (NULL, 0, ?, ?, ?)");
        $stmt->bind_param("sss", $ipAddress, $userAgent, $reason);
        return $stmt->execute();
    }
    
    public function softDelete($id) {
        $stmt = $this->db->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function getAll($limit = 100, $offset = 0) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function countAll() {
        $result = $this->db->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
        return $result->fetch_assoc()['count'];
    }
}