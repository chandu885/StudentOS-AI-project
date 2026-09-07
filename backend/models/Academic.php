<?php
// backend/models/Academic.php

require_once __DIR__ . '/../config/database.php';

class Academic {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // --- DEPARTMENTS ---
    public function getDepartments() {
        $res = $this->db->query("SELECT d.*, u.first_name AS head_first, u.last_name AS head_last FROM departments d LEFT JOIN users u ON d.head_id = u.id ORDER BY d.name ASC");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getDepartmentById($id) {
        $stmt = $this->db->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createDepartment($code, $name, $desc, $headId = null) {
        $stmt = $this->db->prepare("INSERT INTO departments (code, name, description, head_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $code, $name, $desc, $headId);
        return $stmt->execute();
    }

    // --- COURSES ---
    public function getCourses($departmentId = null) {
        if ($departmentId) {
            $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.department_id = ? ORDER BY c.name ASC");
            $stmt->bind_param("i", $departmentId);
        } else {
            $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY c.name ASC");
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCourseById($id) {
        $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createCourse($deptId, $code, $name, $desc, $years, $semesters, $degreeType) {
        $stmt = $this->db->prepare("INSERT INTO courses (department_id, code, name, description, duration_years, total_semesters, degree_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiis", $deptId, $code, $name, $desc, $years, $semesters, $degreeType);
        return $stmt->execute();
    }

    // --- SUBJECTS ---
    public function getSubjects($courseId = null, $semester = null) {
        $sql = "SELECT s.*, c.name AS course_name, d.name AS department_name, u.first_name AS faculty_first, u.last_name AS faculty_last 
                FROM subjects s
                JOIN courses c ON s.course_id = c.id
                JOIN departments d ON s.department_id = d.id
                LEFT JOIN users u ON s.faculty_id = u.id
                WHERE 1=1";
        $params = [];
        $types = "";

        if ($courseId) {
            $sql .= " AND s.course_id = ?";
            $params[] = $courseId;
            $types .= "i";
        }
        if ($semester) {
            $sql .= " AND s.semester = ?";
            $params[] = $semester;
            $types .= "s";
        }
        $sql .= " ORDER BY s.code ASC";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getSubjectById($id) {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name AS course_name, d.name AS department_name, u.first_name AS faculty_first, u.last_name AS faculty_last 
             FROM subjects s
             JOIN courses c ON s.course_id = c.id
             JOIN departments d ON s.department_id = d.id
             LEFT JOIN users u ON s.faculty_id = u.id
             WHERE s.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getStudentSubjects($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT s.*, ss.semester AS enrolled_sem, ss.status AS enroll_status,
                    u.first_name AS faculty_first, u.last_name AS faculty_last, u.email AS faculty_email,
                    c.name AS course_name
             FROM student_subjects ss
             JOIN subjects s ON ss.subject_id = s.id
             JOIN courses c ON s.course_id = c.id
             LEFT JOIN users u ON s.faculty_id = u.id
             WHERE ss.student_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getFacultySubjects($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name AS course_name, d.name AS department_name,
                    (SELECT COUNT(*) FROM student_subjects WHERE subject_id = s.id) AS enrolled_students_count
             FROM subjects s
             JOIN courses c ON s.course_id = c.id
             JOIN departments d ON s.department_id = d.id
             WHERE s.faculty_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // --- SCHEDULES ---
    public function getSchedules($courseId = null, $semester = null, $section = null) {
        $sql = "SELECT cs.*, s.name AS subject_name, s.code AS subject_code,
                       u.first_name AS faculty_first, u.last_name AS faculty_last
                FROM class_schedules cs
                JOIN subjects s ON cs.subject_id = s.id
                JOIN users u ON cs.faculty_id = u.id
                WHERE 1=1";
        $params = [];
        $types = "";

        if ($courseId) {
            $sql .= " AND cs.course_id = ?";
            $params[] = $courseId;
            $types .= "i";
        }
        if ($semester) {
            $sql .= " AND cs.semester = ?";
            $params[] = $semester;
            $types .= "s";
        }
        if ($section) {
            $sql .= " AND cs.section = ?";
            $params[] = $section;
            $types .= "s";
        }

        $sql .= " ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC";
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getFacultySchedule($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT cs.*, s.name AS subject_name, s.code AS subject_code, c.name AS course_name
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN courses c ON cs.course_id = c.id
             WHERE cs.faculty_id = ?
             ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
