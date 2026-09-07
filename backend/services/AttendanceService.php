<?php
// backend/services/AttendanceService.php

require_once __DIR__ . '/../models/Attendance.php';

class AttendanceService {
    private $attendanceModel;

    public function __construct() {
        $this->attendanceModel = new Attendance();
    }

    public function markAttendance($subjectId, $records, $facultyUserId, $date) {
        $successCount = 0;
        foreach ($records as $r) {
            $studentId = (int)$r['student_id'];
            $status = $r['status'] ?? 'present';
            $remarks = $r['remarks'] ?? null;
            if ($this->attendanceModel->mark($subjectId, $studentId, $facultyUserId, $date, $status, $remarks)) {
                $successCount++;
            }
        }
        return ['success' => true, 'marked_count' => $successCount];
    }

    public function getStudentSummary($studentUserId) {
        return $this->attendanceModel->getStudentAttendanceSummary($studentUserId);
    }

    public function getSubjectRoster($subjectId, $date) {
        return $this->attendanceModel->getSubjectRosterForDate($subjectId, $date);
    }
}
