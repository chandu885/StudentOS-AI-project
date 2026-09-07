<?php
// backend/services/ExamService.php

require_once __DIR__ . '/../models/Exam.php';

class ExamService {
    private $examModel;

    public function __construct() {
        $this->examModel = new Exam();
    }

    public function getStudentExams($studentUserId) {
        $upcoming = $this->examModel->getUpcomingExamsForStudent($studentUserId);
        $all = $this->examModel->getAllExamsForStudent($studentUserId);
        $results = $this->examModel->getResultsForStudent($studentUserId);
        return [
            'success' => true,
            'upcoming' => $upcoming,
            'all' => $all,
            'results' => $results
        ];
    }

    public function createExam($data, $facultyUserId) {
        $data['faculty_id'] = $facultyUserId;
        $created = $this->examModel->createExam($data);
        if ($created) {
            return ['success' => true, 'exam' => $created];
        }
        return ['success' => false, 'error' => 'Failed to create exam'];
    }

    public function submitResult($examId, $studentId, $subjectId, $marks, $totalMarks, $grade, $remarks = null) {
        $ok = $this->examModel->recordResult($examId, $studentId, $subjectId, $marks, $totalMarks, $grade, $remarks);
        return ['success' => (bool)$ok];
    }
}
