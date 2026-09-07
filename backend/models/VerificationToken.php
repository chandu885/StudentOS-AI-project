<?php
// backend/models/VerificationToken.php

require_once __DIR__ . '/../config/database.php';

class VerificationToken {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $token, $expiresAt = null) {
        if (!$expiresAt) {
            $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        }
        $stmt = $this->db->prepare(
            "INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iss", $userId, $token, $expiresAt);
        return $stmt->execute();
    }

    public function findByToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM email_verification_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) {
            $res['used_at'] = null; // compatibility
        }
        return $res;
    }

    public function markUsed($id) {
        // Can delete or mark
        $stmt = $this->db->prepare("DELETE FROM email_verification_tokens WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function delete($token) {
        $stmt = $this->db->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        return $stmt->execute();
    }

    public function deleteForUser($userId) {
        $stmt = $this->db->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }

    // Password reset methods
    public function createPasswordReset($userId, $tokenHash, $expiresAt) {
        $stmt = $this->db->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iss", $userId, $tokenHash, $expiresAt);
        return $stmt->execute();
    }

    public function findPasswordReset($tokenHash) {
        $stmt = $this->db->prepare("SELECT * FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) {
            $res['used_at'] = null;
        }
        return $res;
    }
}
