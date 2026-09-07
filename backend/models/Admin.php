<?php
// backend/models/Admin.php

require_once __DIR__ . '/../config/database.php';

class Admin {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO admin_profiles (user_id, employee_id, department_id, designation, phone, office_location)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isisss",
            $data['user_id'],
            $data['employee_id'],
            $data['department_id'],
            $data['designation'],
            $data['phone'],
            $data['office_location']
        );
        if ($stmt->execute()) {
            return $this->findByUserId($data['user_id']);
        }
        return false;
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare(
            "SELECT ap.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
             FROM admin_profiles ap
             JOIN users u ON ap.user_id = u.id
             LEFT JOIN departments d ON ap.department_id = d.id
             WHERE ap.user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getAll() {
        $stmt = $this->db->prepare(
            "SELECT ap.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
             FROM admin_profiles ap
             JOIN users u ON ap.user_id = u.id
             LEFT JOIN departments d ON ap.department_id = d.id
             WHERE u.deleted_at IS NULL
             ORDER BY u.first_name ASC"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
