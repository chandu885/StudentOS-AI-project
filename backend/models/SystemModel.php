<?php
// backend/models/SystemModel.php

require_once __DIR__ . '/../config/database.php';

class SystemModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // System Settings
    public function getSystemSettings() {
        $res = $this->db->query("SELECT * FROM system_settings");
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }

    public function updateSystemSetting($key, $value) {
        $stmt = $this->db->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    }

    // AI Settings
    public function getAISettings() {
        $res = $this->db->query("SELECT * FROM ai_settings");
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function updateAISetting($key, $value) {
        $stmt = $this->db->prepare("UPDATE ai_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    }

    // Logs
    public function getLoginLogs($limit = 100) {
        $stmt = $this->db->prepare("SELECT ll.*, u.email, u.first_name, u.last_name FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.id ORDER BY ll.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAuditLogs($limit = 100) {
        $stmt = $this->db->prepare("SELECT al.*, u.email, u.first_name, u.last_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function logAudit($userId, $action, $resource, $resourceId = null, $details = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $this->db->prepare("INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $userId, $action, $resource, $resourceId, $details, $ip);
        return $stmt->execute();
    }

    // Backups
    public function getBackups() {
        $res = $this->db->query("SELECT b.*, u.first_name, u.last_name FROM backup_logs b LEFT JOIN users u ON b.created_by = u.id ORDER BY b.created_at DESC");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function createBackupLog($filename, $filesize, $type, $status, $userId) {
        $stmt = $this->db->prepare("INSERT INTO backup_logs (filename, file_size, backup_type, status, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sissi", $filename, $filesize, $type, $status, $userId);
        return $stmt->execute();
    }

    // Support Tickets
    public function getTickets($userId = null, $roleId = null) {
        if ($roleId == 4) { // Student
            $stmt = $this->db->prepare("SELECT t.*, u.first_name, u.last_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.user_id = ? ORDER BY t.created_at DESC");
            $stmt->bind_param("i", $userId);
        } else {
            $stmt = $this->db->prepare("SELECT t.*, u.first_name, u.last_name, u.email, r.name AS role_name FROM support_tickets t JOIN users u ON t.user_id = u.id JOIN roles r ON u.role_id = r.id ORDER BY FIELD(t.status, 'open', 'in_progress', 'resolved', 'closed'), t.created_at DESC");
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createTicket($userId, $subject, $description, $priority = 'medium') {
        $stmt = $this->db->prepare("INSERT INTO support_tickets (user_id, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
        $stmt->bind_param("isss", $userId, $subject, $description, $priority);
        return $stmt->execute();
    }

    public function updateTicket($ticketId, $status, $response, $assignedTo = null) {
        $stmt = $this->db->prepare("UPDATE support_tickets SET status = ?, response = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssii", $status, $response, $assignedTo, $ticketId);
        return $stmt->execute();
    }
}
