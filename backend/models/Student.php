<?php
// backend/models/Student.php

require_once __DIR__ . '/../config/database.php';

class Student {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO student_profiles 
            (user_id, student_id, department_id, course_id, semester, section, roll_number, phone, date_of_birth, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isiiisssss",
            $data['user_id'],
            $data['student_id'],
            $data['department_id'],
            $data['course_id'],
            $data['semester'],
            $data['section'],
            $data['roll_number'],
            $data['phone'],
            $data['date_of_birth'],
            $data['address']
        );
        
        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }
        return false;
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function findByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function findByStudentId($studentId) {
        $stmt = $this->db->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function update($id, $data) {
        $fields = [];
        $types = "";
        $values = [];
        
        $allowedFields = ['semester', 'section', 'roll_number', 'phone', 'date_of_birth', 'address'];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $values[] = $value;
                $types .= "s";
            }
        }
        
        if (empty($fields)) {
            return $this->findById($id) ?: $this->findByUserId($id);
        }
        
        $values[] = $id;
        $values[] = $id;
        $types .= "ii";
        
        $sql = "UPDATE student_profiles SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ? OR user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        return $this->findById($id) ?: $this->findByUserId($id);
    }
    
    public function getDashboard($userId) {
        $student = $this->findByUserId($userId);
        if (!$student) {
            return null;
        }
        
        $studentUserId = (int)$student['user_id'];

        // Get today's classes
        $classes = $this->getTodayClasses($studentUserId);
        
        // Get pending assignments
        $assignments = $this->getPendingAssignments($studentUserId);
        
        // Get attendance summary
        $attendance = $this->getAttendanceSummary($studentUserId);
        
        // Get upcoming exams
        $exams = $this->getUpcomingExams($studentUserId);
        
        return [
            'student' => $student,
            'today_classes' => $classes,
            'pending_assignments' => $assignments,
            'attendance' => $attendance,
            'upcoming_exams' => $exams
        ];
    }
    
    private function getTodayClasses($studentUserId) {
        $dayOfWeek = date('l');
        $stmt = $this->db->prepare(
            "SELECT cs.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as faculty_name 
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.faculty_id = u.id
             JOIN student_subjects ss ON ss.subject_id = cs.subject_id
             WHERE ss.student_id = ? AND cs.day_of_week = ?
             ORDER BY cs.start_time"
        );
        $stmt->bind_param("is", $studentUserId, $dayOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getPendingAssignments($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name as subject_name 
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             JOIN student_subjects ss ON ss.subject_id = s.id
             WHERE ss.student_id = ? 
             AND a.deadline > NOW() 
             AND (LOWER(a.status) = 'published' OR a.status IS NULL)
             AND a.id NOT IN (
                 SELECT assignment_id FROM assignment_submissions 
                 WHERE student_id = ?
             )
             ORDER BY a.deadline ASC"
        );
        $stmt->bind_param("ii", $studentUserId, $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getAttendanceSummary($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT s.name, 
                    COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) as present,
                    COUNT(*) as total,
                    ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 2) as percentage
             FROM attendance a
             JOIN subjects s ON a.subject_id = s.id
             WHERE a.student_id = ?
             GROUP BY a.subject_id"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getUpcomingExams($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name as subject_name 
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             JOIN student_subjects ss ON ss.subject_id = s.id
             WHERE ss.student_id = ? 
             AND e.exam_date >= CURDATE()
             AND (LOWER(e.status) IN ('scheduled', 'in_progress', 'published') OR e.status IS NULL)
             ORDER BY e.exam_date ASC
             LIMIT 5"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}