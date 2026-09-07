<?php
// backend/models/Notification.php

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByUser($userId, $limit = 30) {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    }

    public function markAsRead($id, $userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        return $stmt->execute();
    }

    public function markAllAsRead($userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }

    public function create($userId, $title, $message, $type = 'info', $actionUrl = null) {
        $stmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, action_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $title, $message, $type, $actionUrl);
        return $stmt->execute();
    }

    // College Notices
    public function getNotices($roleName = null, $departmentId = null) {
        $sql = "SELECT n.*, u.first_name, u.last_name, d.name AS department_name 
                FROM notices n
                JOIN users u ON n.posted_by = u.id
                LEFT JOIN departments d ON n.department_id = d.id
                WHERE (n.expires_at IS NULL OR n.expires_at >= CURDATE())";
        if ($roleName) {
            $sql .= " AND (n.target_role = 'all' OR n.target_role = '" . $this->db->escape($roleName) . "')";
        }
        $sql .= " ORDER BY FIELD(n.priority, 'high', 'medium', 'low'), n.created_at DESC";
        $res = $this->db->query($sql);
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function postNotice($postedBy, $title, $content, $targetRole, $departmentId, $priority, $expiresAt = null) {
        $stmt = $this->db->prepare("INSERT INTO notices (posted_by, title, content, target_role, department_id, priority, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiss", $postedBy, $title, $content, $targetRole, $departmentId, $priority, $expiresAt);
        return $stmt->execute();
    }
}
