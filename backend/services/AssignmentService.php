<?php
// backend/services/AssignmentService.php

require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Notification.php';

class AssignmentService {
    private $assignmentModel;
    private $notificationModel;

    public function __construct() {
        $this->assignmentModel = new Assignment();
        $this->notificationModel = new Notification();
    }

    public function getAssignment($id) {
        $asg = $this->assignmentModel->findById($id);
        if (!$asg) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }
        return ['success' => true, 'assignment' => $asg];
    }

    public function createAssignment($data, $facultyUserId) {
        $data['faculty_id'] = $facultyUserId;
        $created = $this->assignmentModel->create($data);
        if ($created) {
            return ['success' => true, 'assignment' => $created];
        }
        return ['success' => false, 'error' => 'Failed to create assignment'];
    }

    public function submitAssignment($assignmentId, $studentUserId, $text, $filePath = null) {
        $ok = $this->assignmentModel->submit($assignmentId, $studentUserId, $text, $filePath);
        if ($ok) {
            return ['success' => true, 'message' => 'Assignment submitted successfully'];
        }
        return ['success' => false, 'error' => 'Failed to submit assignment'];
    }

    public function gradeSubmission($submissionId, $marks, $feedback, $graderId) {
        $ok = $this->assignmentModel->gradeSubmission($submissionId, $marks, $feedback, $graderId);
        if ($ok) {
            return ['success' => true, 'message' => 'Submission graded successfully'];
        }
        return ['success' => false, 'error' => 'Failed to grade submission'];
    }
}
