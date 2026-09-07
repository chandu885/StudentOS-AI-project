<?php
// backend/services/AdminService.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Faculty.php';
require_once __DIR__ . '/../models/Academic.php';
require_once __DIR__ . '/../models/SystemModel.php';

class AdminService {
    private $db;
    private $userModel;
    private $studentModel;
    private $facultyModel;
    private $academicModel;
    private $systemModel;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->facultyModel = new Faculty();
        $this->academicModel = new Academic();
        $this->systemModel = new SystemModel();
    }

    public function getDashboard() {
        $stats = [];
        $res = $this->db->query("SELECT COUNT(*) AS c FROM users WHERE role_id = 4 AND deleted_at IS NULL");
        $stats['total_students'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $res = $this->db->query("SELECT COUNT(*) AS c FROM users WHERE role_id = 3 AND deleted_at IS NULL");
        $stats['total_faculty'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $res = $this->db->query("SELECT COUNT(*) AS c FROM departments WHERE status = 'active'");
        $stats['total_departments'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $res = $this->db->query("SELECT COUNT(*) AS c FROM courses WHERE status = 'active'");
        $stats['total_courses'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $res = $this->db->query("SELECT COUNT(*) AS c FROM subjects WHERE status = 'active'");
        $stats['total_subjects'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $res = $this->db->query("SELECT COUNT(*) AS c FROM user_sessions WHERE expires_at > NOW()");
        $stats['active_sessions'] = (int)($res->fetch_assoc()['c'] ?? 0);

        $recentLogins = $this->systemModel->getLoginLogs(8);

        return [
            'success' => true,
            'stats' => $stats,
            'recent_logins' => $recentLogins
        ];
    }
}
