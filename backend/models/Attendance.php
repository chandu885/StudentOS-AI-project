<?php
// backend/models/Attendance.php

require_once __DIR__ . '/../config/database.php';

class Attendance {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function mark($subjectId, $studentId, $facultyId, $date, $status, $remarks = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO attendance (subject_id, student_id, faculty_id, date, status, remarks)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), faculty_id = VALUES(faculty_id)"
        );
        $stmt->bind_param("iiisss", $subjectId, $studentId, $facultyId, $date, $status, $remarks);
        return $stmt->execute();
    }

    public function getStudentAttendanceSummary($studentUserId) {
        $sql = "SELECT s.id AS subject_id, s.code AS subject_code, s.name AS subject_name,
                       COUNT(a.id) AS total_classes,
                       SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                       SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
                       SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) AS excused_count,
                       ROUND(
                           (SUM(CASE WHEN a.status = 'present' THEN 1 WHEN a.status = 'late' THEN 0.5 ELSE 0 END) / 
                           NULLIF(COUNT(a.id), 0)) * 100, 1
                       ) AS percentage
                FROM student_subjects ss
                JOIN subjects s ON ss.subject_id = s.id
                LEFT JOIN attendance a ON s.id = a.subject_id AND a.student_id = ?
                WHERE ss.student_id = ?
                GROUP BY s.id, s.code, s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $studentUserId, $studentUserId);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalClasses = 0;
        $totalPresent = 0;
        foreach ($summary as &$row) {
            $row['percentage'] = $row['percentage'] !== null ? (float)$row['percentage'] : 100.0;
            $totalClasses += $row['total_classes'];
            $totalPresent += $row['present_count'] + ($row['late_count'] * 0.5);
        }

        $overallPct = $totalClasses > 0 ? round(($totalPresent / $totalClasses) * 100, 1) : 100.0;

        return [
            'overall_percentage' => $overallPct,
            'total_classes' => $totalClasses,
            'subjects' => $summary
        ];
    }

    public function getStudentRecords($studentUserId, $subjectId = null) {
        if ($subjectId) {
            $stmt = $this->db->prepare(
                "SELECT a.*, s.name AS subject_name, s.code AS subject_code 
                 FROM attendance a 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.student_id = ? AND a.subject_id = ? 
                 ORDER BY a.date DESC"
            );
            $stmt->bind_param("ii", $studentUserId, $subjectId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT a.*, s.name AS subject_name, s.code AS subject_code 
                 FROM attendance a 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.student_id = ? 
                 ORDER BY a.date DESC"
            );
            $stmt->bind_param("i", $studentUserId);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getSubjectRosterForDate($subjectId, $date) {
        $stmt = $this->db->prepare(
            "SELECT u.id AS student_id, u.first_name, u.last_name, sp.student_id AS roll, sp.roll_number,
                    COALESCE(a.status, 'present') AS status, a.remarks
             FROM student_subjects ss
             JOIN users u ON ss.student_id = u.id
             JOIN student_profiles sp ON u.id = sp.user_id
             LEFT JOIN attendance a ON ss.subject_id = a.subject_id AND u.id = a.student_id AND a.date = ?
             WHERE ss.subject_id = ?
             ORDER BY sp.roll_number ASC, u.first_name ASC"
        );
        $stmt->bind_param("si", $date, $subjectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
