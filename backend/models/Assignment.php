<?php
// backend/models/Assignment.php

require_once __DIR__ . '/../config/database.php';

class Assignment {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO assignments 
             (subject_id, faculty_id, title, description, instructions, deadline, max_marks, attachment_path, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $status = $data['status'] ?? 'published';
        $stmt->bind_param(
            "iissssiss",
            $data['subject_id'],
            $data['faculty_id'],
            $data['title'],
            $data['description'],
            $data['instructions'],
            $data['deadline'],
            $data['max_marks'],
            $data['attachment_path'],
            $status
        );
        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }
        return false;
    }

    public function findById($id) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    u.first_name AS faculty_first, u.last_name AS faculty_last
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             JOIN users u ON a.faculty_id = u.id
             WHERE a.id = ? AND a.deleted_at IS NULL"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findBySubject($subjectId) {
        $stmt = $this->db->prepare("SELECT * FROM assignments WHERE subject_id = ? AND deleted_at IS NULL ORDER BY deadline ASC");
        $stmt->bind_param("i", $subjectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getPendingForStudent($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    u.first_name AS faculty_first, u.last_name AS faculty_last
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             JOIN student_subjects ss ON s.id = ss.subject_id
             JOIN users u ON a.faculty_id = u.id
             LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
             WHERE ss.student_id = ? AND a.deleted_at IS NULL AND asub.id IS NULL AND a.deadline >= NOW()
             ORDER BY a.deadline ASC"
        );
        $stmt->bind_param("ii", $studentUserId, $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllForStudent($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    u.first_name AS faculty_first, u.last_name AS faculty_last,
                    asub.id AS submission_id, asub.file_path AS submission_file,
                    asub.marks_obtained, asub.feedback, asub.submitted_at, asub.status AS submission_status
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             JOIN student_subjects ss ON s.id = ss.subject_id
             JOIN users u ON a.faculty_id = u.id
             LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
             WHERE ss.student_id = ? AND a.deleted_at IS NULL
             ORDER BY a.deadline DESC"
        );
        $stmt->bind_param("ii", $studentUserId, $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getByFaculty($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, s.name AS subject_name, s.code AS subject_code,
                    (SELECT COUNT(*) FROM student_subjects WHERE subject_id = a.subject_id) AS total_assigned,
                    (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count,
                    (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'graded') AS graded_count
             FROM assignments a
             JOIN subjects s ON a.subject_id = s.id
             WHERE a.faculty_id = ? AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function submit($assignmentId, $studentUserId, $text, $filePath = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, status, submitted_at)
             VALUES (?, ?, ?, ?, 'submitted', NOW())
             ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), file_path = VALUES(file_path), status = 'resubmitted', submitted_at = NOW()"
        );
        $stmt->bind_param("iiss", $assignmentId, $studentUserId, $text, $filePath);
        return $stmt->execute();
    }

    public function getSubmissions($assignmentId) {
        $stmt = $this->db->prepare(
            "SELECT asub.*, u.first_name, u.last_name, u.email, sp.student_id AS roll_code, sp.roll_number
             FROM assignment_submissions asub
             JOIN users u ON asub.student_id = u.id
             LEFT JOIN student_profiles sp ON u.id = sp.user_id
             WHERE asub.assignment_id = ?
             ORDER BY asub.submitted_at DESC"
        );
        $stmt->bind_param("i", $assignmentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function gradeSubmission($submissionId, $marks, $feedback, $graderId) {
        $stmt = $this->db->prepare(
            "UPDATE assignment_submissions 
             SET marks_obtained = ?, feedback = ?, graded_by = ?, status = 'graded', graded_at = NOW() 
             WHERE id = ?"
        );
        $stmt->bind_param("dsii", $marks, $feedback, $graderId, $submissionId);
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->db->prepare("UPDATE assignments SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}