<?php
// backend/services/StudentService.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Academic.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Notification.php';

class StudentService {
    private $userModel;
    private $studentModel;
    private $academicModel;
    private $attendanceModel;
    private $assignmentModel;
    private $examModel;
    private $taskModel;
    private $notificationModel;

    public function __construct() {
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->academicModel = new Academic();
        $this->attendanceModel = new Attendance();
        $this->assignmentModel = new Assignment();
        $this->examModel = new Exam();
        $this->taskModel = new Task();
        $this->notificationModel = new Notification();
    }

    public function getDashboard($userId) {
        $profile = $this->studentModel->findByUserId($userId);
        $dayOfWeek = date('l');

        // Today's classes
        $allSchedule = [];
        if ($profile) {
            $allSchedule = $this->academicModel->getSchedules($profile['course_id'], $profile['semester'], $profile['section']);
        }
        $todayClasses = array_values(array_filter($allSchedule, function($s) use ($dayOfWeek) {
            return strcasecmp($s['day_of_week'], $dayOfWeek) === 0;
        }));

        // Pending assignments
        $pendingAssignments = $this->assignmentModel->getPendingForStudent($userId);

        // Attendance summary
        $attendance = $this->attendanceModel->getStudentAttendanceSummary($userId);

        // Upcoming exams
        $upcomingExams = $this->examModel->getUpcomingExamsForStudent($userId);

        // Tasks
        $tasks = $this->taskModel->getByUser($userId);

        // Performance
        $perf = $this->examModel->getPerformance($userId);

        return [
            'success' => true,
            'profile' => $profile,
            'today_classes' => $todayClasses,
            'pending_assignments' => $pendingAssignments,
            'attendance' => $attendance,
            'upcoming_exams' => $upcomingExams,
            'tasks' => $tasks,
            'performance' => $perf[0] ?? null
        ];
    }

    public function getProfile($userId) {
        $profile = $this->studentModel->findByUserId($userId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Student profile not found'];
        }
        return ['success' => true, 'profile' => $profile];
    }

    public function updateProfile($userId, $data) {
        $updated = $this->studentModel->update($userId, $data);
        return ['success' => true, 'profile' => $updated];
    }
}
