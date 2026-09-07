<?php
// backend/models/Exam.php

require_once __DIR__ . '/../config/database.php';

class Exam {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getUpcomingExamsForStudent($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                    u.first_name AS faculty_first, u.last_name AS faculty_last
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             JOIN student_subjects ss ON s.id = ss.subject_id
             JOIN users u ON e.faculty_id = u.id
             WHERE ss.student_id = ? AND e.exam_date >= CURDATE()
             ORDER BY e.exam_date ASC, e.start_time ASC"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllExamsForStudent($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                    r.marks_obtained, r.grade, r.published_at
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             JOIN student_subjects ss ON s.id = ss.subject_id
             LEFT JOIN results r ON e.id = r.exam_id AND r.student_id = ?
             WHERE ss.student_id = ?
             ORDER BY e.exam_date DESC"
        );
        $stmt->bind_param("ii", $studentUserId, $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getExamsByFaculty($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                    (SELECT COUNT(*) FROM results WHERE exam_id = e.id) AS graded_count
             FROM exams e
             JOIN subjects s ON e.subject_id = s.id
             WHERE e.faculty_id = ?
             ORDER BY e.exam_date DESC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getExamById($id) {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS subject_name, s.code AS subject_code 
             FROM exams e 
             JOIN subjects s ON e.subject_id = s.id 
             WHERE e.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createExam($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO exams (subject_id, faculty_id, title, exam_type, exam_date, start_time, end_time, total_marks, passing_marks, instructions, room_number, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $status = $data['status'] ?? 'scheduled';
        $stmt->bind_param(
            "iisssssiisss",
            $data['subject_id'],
            $data['faculty_id'],
            $data['title'],
            $data['exam_type'],
            $data['exam_date'],
            $data['start_time'],
            $data['end_time'],
            $data['total_marks'],
            $data['passing_marks'],
            $data['instructions'],
            $data['room_number'],
            $status
        );
        if ($stmt->execute()) {
            return $this->getExamById($this->db->lastInsertId());
        }
        return false;
    }

    public function getResultsForStudent($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT r.*, e.title AS exam_title, e.exam_type, e.exam_date,
                    s.name AS subject_name, s.code AS subject_code, s.credits
             FROM results r
             JOIN exams e ON r.exam_id = e.id
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.student_id = ?
             ORDER BY e.exam_date DESC"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function recordResult($examId, $studentId, $subjectId, $marksObtained, $totalMarks, $grade, $remarks = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO results (exam_id, student_id, subject_id, marks_obtained, total_marks, grade, remarks, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), total_marks = VALUES(total_marks), grade = VALUES(grade), remarks = VALUES(remarks), published_at = NOW()"
        );
        $stmt->bind_param("iiiddss", $examId, $studentId, $subjectId, $marksObtained, $totalMarks, $grade, $remarks);
        return $stmt->execute();
    }

    public function getPerformance($studentUserId) {
        $stmt = $this->db->prepare("SELECT * FROM performance WHERE student_id = ? ORDER BY semester DESC");
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
