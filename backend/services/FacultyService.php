<?php
// backend/services/FacultyService.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Faculty.php';
require_once __DIR__ . '/../models/Academic.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Exam.php';

class FacultyService {
    private $facultyModel;
    private $academicModel;
    private $assignmentModel;
    private $attendanceModel;
    private $examModel;

    public function __construct() {
        $this->facultyModel = new Faculty();
        $this->academicModel = new Academic();
        $this->assignmentModel = new Assignment();
        $this->attendanceModel = new Attendance();
        $this->examModel = new Exam();
    }

    public function getDashboard($facultyUserId) {
        $profile = $this->facultyModel->findByUserId($facultyUserId);
        $dayOfWeek = date('l');

        // Today's classes
        $allSchedule = $this->academicModel->getFacultySchedule($facultyUserId);
        $todayClasses = array_values(array_filter($allSchedule, function($s) use ($dayOfWeek) {
            return strcasecmp($s['day_of_week'], $dayOfWeek) === 0;
        }));

        // Assigned subjects
        $subjects = $this->academicModel->getFacultySubjects($facultyUserId);

        // Assignments
        $assignments = $this->assignmentModel->getByFaculty($facultyUserId);
        $pendingGrading = 0;
        foreach ($assignments as $a) {
            $pendingGrading += ($a['submission_count'] - $a['graded_count']);
        }

        // Upcoming exams
        $exams = $this->examModel->getExamsByFaculty($facultyUserId);

        return [
            'success' => true,
            'profile' => $profile,
            'today_classes' => $todayClasses,
            'assigned_subjects' => $subjects,
            'assignments_count' => count($assignments),
            'pending_grading' => max(0, $pendingGrading),
            'upcoming_exams' => $exams
        ];
    }
}
