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
            return $this->findById($id);
        }
        
        $values[] = $id;
        $types .= "i";
        
        $sql = "UPDATE student_profiles SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        return $this->findById($id);
    }
    
    public function getDashboard($userId) {
        $student = $this->findByUserId($userId);
        if (!$student) {
            return null;
        }
        
        // Get today's classes
        $classes = $this->getTodayClasses($student['id']);
        
        // Get pending assignments
        $assignments = $this->getPendingAssignments($student['id']);
        
        // Get attendance summary
        $attendance = $this->getAttendanceSummary($student['id']);
        
        // Get upcoming exams
        $exams = $this->getUpcomingExams($student['id']);
        
        return [
            'student' => $student,
            'today_classes' => $classes,
            'pending_assignments' => $assignments,
            'attendance' => $attendance,
            'upcoming_exams' => $exams
        ];
    }
    
    private function getTodayClasses($studentId) {
        $dayOfWeek = date('l');
        $stmt = $this->db->prepare(
            "SELECT cs.*, s.name as subject_name, u.first_name as faculty_name 
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN faculty_profiles fp ON cs.faculty_id = fp.id
             JOIN users u ON fp.user_id = u.id
             JOIN student_subjects ss ON ss.subject_id = cs.subject_id
             WHERE ss.student_id = ? AND cs.day_of_week = ? AND cs.academic_year = ?
             ORDER BY cs.start_time"
        );
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $stmt->bind_param("iss", $studentId, $dayOfWeek, $academicYear);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getPendingAssignments($studentId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name as subject_name 
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             JOIN student_subjects ss ON ss.subject_id = s.id
             WHERE ss.student_id = ? 
             AND a.deadline > NOW() 
             AND a.status = 'Published'
             AND a.id NOT IN (
                 SELECT assignment_id FROM assignment_submissions 
                 WHERE student_id = ?
             )
             ORDER BY a.deadline ASC"
        );
        $stmt->bind_param("ii", $studentId, $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getAttendanceSummary($studentId) {
        $stmt = $this->db->prepare(
            "SELECT s.name, 
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                    COUNT(*) as total,
                    ROUND(COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100 / COUNT(*), 2) as percentage
             FROM attendance a
             JOIN subjects s ON a.subject_id = s.id
             WHERE a.student_id = ?
             GROUP BY a.subject_id"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getUpcomingExams($studentId) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name as subject_name 
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             JOIN student_subjects ss ON ss.subject_id = s.id
             WHERE ss.student_id = ? 
             AND e.exam_date >= CURDATE()
             AND e.status = 'Published'
             ORDER BY e.exam_date ASC
             LIMIT 5"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}