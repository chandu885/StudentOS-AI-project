<?php
// backend/models/Faculty.php

require_once __DIR__ . '/../config/database.php';

class Faculty {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO faculty_profiles 
             (user_id, employee_id, department_id, designation, qualification, specialization, office_location, phone, joining_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isissssss",
            $data['user_id'],
            $data['employee_id'],
            $data['department_id'],
            $data['designation'],
            $data['qualification'],
            $data['specialization'],
            $data['office_location'],
            $data['phone'],
            $data['joining_date']
        );
        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }
        return false;
    }

    public function findById($id) {
        $stmt = $this->db->prepare(
            "SELECT fp.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
             FROM faculty_profiles fp
             JOIN users u ON fp.user_id = u.id
             LEFT JOIN departments d ON fp.department_id = d.id
             WHERE fp.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare(
            "SELECT fp.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
             FROM faculty_profiles fp
             JOIN users u ON fp.user_id = u.id
             LEFT JOIN departments d ON fp.department_id = d.id
             WHERE fp.user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getAll($departmentId = null) {
        if ($departmentId) {
            $stmt = $this->db->prepare(
                "SELECT fp.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
                 FROM faculty_profiles fp
                 JOIN users u ON fp.user_id = u.id
                 LEFT JOIN departments d ON fp.department_id = d.id
                 WHERE fp.department_id = ? AND u.deleted_at IS NULL
                 ORDER BY u.first_name ASC"
            );
            $stmt->bind_param("i", $departmentId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT fp.*, u.first_name, u.last_name, u.email, u.avatar, u.is_active, d.name AS department_name
                 FROM faculty_profiles fp
                 JOIN users u ON fp.user_id = u.id
                 LEFT JOIN departments d ON fp.department_id = d.id
                 WHERE u.deleted_at IS NULL
                 ORDER BY u.first_name ASC"
            );
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function update($userId, $data) {
        $allowed = ['designation', 'qualification', 'specialization', 'office_location', 'phone'];
        $fields = [];
        $values = [];
        $types = "";

        foreach ($data as $k => $v) {
            if (in_array($k, $allowed)) {
                $fields[] = "$k = ?";
                $values[] = $v;
                $types .= "s";
            }
        }

        if (empty($fields)) {
            return $this->findByUserId($userId);
        }

        $values[] = $userId;
        $types .= "i";
        $sql = "UPDATE faculty_profiles SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $this->findByUserId($userId);
    }
}
