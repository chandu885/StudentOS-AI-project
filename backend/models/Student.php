<?php
// backend/models/Student.php

require_once __DIR__ . '/../config/database.php';

class Student {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data) {
        $userId = (int)$data['user_id'];
        $studentId = strtoupper(trim($data['student_id']));
        $department = !empty($data['department']) ? strtoupper(trim($data['department'])) : 'BCA';
        if (!in_array($department, ['BBA', 'BCA'])) {
            $department = 'BCA';
        }
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        if (!$departmentId) {
            $deptStmt = $this->db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
            if ($deptStmt) {
                $deptStmt->bind_param("s", $department);
                $deptStmt->execute();
                $dRow = $deptStmt->get_result()->fetch_assoc();
                if ($dRow) {
                    $departmentId = (int)$dRow['id'];
                }
                $deptStmt->close();
            }
        }
        $semester = (string)($data['semester'] ?? '1');
        $rollNumber = !empty($data['roll_number']) ? (string)$data['roll_number'] : null;
        $dateOfBirth = !empty($data['date_of_birth']) ? (string)$data['date_of_birth'] : null;
        $address = !empty($data['address']) ? (string)$data['address'] : null;

        $stmt = $this->db->prepare(
            "INSERT INTO student_profiles 
            (user_id, student_id, department, department_id, semester, roll_number, date_of_birth, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ississss",
            $userId,
            $studentId,
            $department,
            $departmentId,
            $semester,
            $rollNumber,
            $dateOfBirth,
            $address
        );
        
        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }
        return false;
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare(
            "SELECT sp.*, d.name AS department_name, d.code AS department_code, u.first_name, u.last_name, u.email 
             FROM student_profiles sp 
             LEFT JOIN departments d ON sp.department_id = d.id
             LEFT JOIN users u ON sp.user_id = u.id 
             WHERE sp.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function findByUserId($userId) {
        $stmt = $this->db->prepare(
            "SELECT sp.*, d.name AS department_name, d.code AS department_code, u.first_name, u.last_name, u.email 
             FROM student_profiles sp 
             LEFT JOIN departments d ON sp.department_id = d.id
             LEFT JOIN users u ON sp.user_id = u.id 
             WHERE sp.user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function findByStudentId($studentId) {
        $stmt = $this->db->prepare(
            "SELECT sp.*, d.name AS department_name, d.code AS department_code, u.first_name, u.last_name, u.email 
             FROM student_profiles sp 
             LEFT JOIN departments d ON sp.department_id = d.id
             LEFT JOIN users u ON sp.user_id = u.id 
             WHERE sp.student_id = ?"
        );
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function update($id, $data) {
        $fields = [];
        $types = "";
        $values = [];

        if (isset($data['department'])) {
            $dept = strtoupper(trim($data['department']));
            if (in_array($dept, ['BBA', 'BCA'])) {
                $data['department'] = $dept;
                // auto set department_id if not explicitly provided
                if (!isset($data['department_id'])) {
                    $deptStmt = $this->db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
                    if ($deptStmt) {
                        $deptStmt->bind_param("s", $dept);
                        $deptStmt->execute();
                        $dRow = $deptStmt->get_result()->fetch_assoc();
                        if ($dRow) {
                            $data['department_id'] = (int)$dRow['id'];
                        }
                        $deptStmt->close();
                    }
                }
            }
        }
        
        $allowedFields = [
            'department', 'department_id', 'semester', 'roll_number', 'date_of_birth', 'address',
            'promotion_opt_in', 'promotion_status', 'promotion_target_sem', 'promotion_requested_at',
            'promoted_at', 'promoted_by', 'promotion_notes', 'prev_semester'
        ];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $values[] = $value;
                $types .= ($key === 'department_id' || $key === 'promotion_opt_in' || $key === 'promoted_by') ? "i" : "s";
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

    public function optInPromotion($userId, $targetSem = null, $notes = '') {
        $profile = $this->findByUserId($userId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Student profile not found.'];
        }

        // Check if promotion window is open
        $res = $this->db->query("SELECT `value` FROM `system_settings` WHERE `key` = 'semester_promotion_open' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['value'] === '0') {
                return ['success' => false, 'error' => 'Semester promotion opt-in window is currently closed by the administration.'];
            }
        }

        $currentSem = $profile['semester'] ?? '1';
        if (!$targetSem) {
            $targetSem = is_numeric($currentSem) ? (string)((int)$currentSem + 1) : '2';
        }

        $stmt = $this->db->prepare(
            "UPDATE student_profiles 
             SET promotion_opt_in = 1, promotion_status = 'opted_in', promotion_target_sem = ?, promotion_requested_at = NOW(), promotion_notes = ?, updated_at = NOW() 
             WHERE user_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("ssi", $targetSem, $notes, $userId);
            $stmt->execute();
            $stmt->close();

            $ayRes = $this->db->query("SELECT `value` FROM `system_settings` WHERE `key` = 'semester_promotion_academic_year' LIMIT 1");
            $ayRow = $ayRes ? $ayRes->fetch_assoc() : null;
            $academicYear = $ayRow['value'] ?? '2026-2027';
            $dept = $profile['department'] ?? 'BCA';

            $logStmt = $this->db->prepare(
                "INSERT INTO semester_promotions (student_id, from_semester, to_semester, department, academic_year, action, performed_by, notes) 
                 VALUES (?, ?, ?, ?, ?, 'opt_in', ?, ?)"
            );
            if ($logStmt) {
                $logStmt->bind_param("issssis", $userId, $currentSem, $targetSem, $dept, $academicYear, $userId, $notes);
                $logStmt->execute();
                $logStmt->close();
            }

            return ['success' => true, 'message' => "Successfully opted in for promotion to Semester $targetSem! Your application is pending review."];
        }

        return ['success' => false, 'error' => 'Database error during opt-in.'];
    }

    public function optOutPromotion($userId, $notes = '') {
        $profile = $this->findByUserId($userId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Student profile not found.'];
        }

        $currentSem = $profile['semester'] ?? '1';
        $targetSem = $profile['promotion_target_sem'] ?? (string)((int)$currentSem + 1);

        $stmt = $this->db->prepare(
            "UPDATE student_profiles 
             SET promotion_opt_in = 0, promotion_status = 'not_opted', promotion_target_sem = NULL, promotion_notes = ?, updated_at = NOW() 
             WHERE user_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("si", $notes, $userId);
            $stmt->execute();
            $stmt->close();

            $dept = $profile['department'] ?? 'BCA';
            $logStmt = $this->db->prepare(
                "INSERT INTO semester_promotions (student_id, from_semester, to_semester, department, action, performed_by, notes) 
                 VALUES (?, ?, ?, ?, 'opt_out', ?, ?)"
            );
            if ($logStmt) {
                $logStmt->bind_param("isssis", $userId, $currentSem, $targetSem, $dept, $userId, $notes);
                $logStmt->execute();
                $logStmt->close();
            }

            return ['success' => true, 'message' => 'Successfully opted out of semester promotion.'];
        }

        return ['success' => false, 'error' => 'Database error during opt-out.'];
    }

    public function promoteStudent($userId, $targetSem = null, $promotedBy = 1, $notes = '') {
        $profile = $this->findByUserId($userId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Student profile not found.'];
        }

        $currentSem = $profile['semester'] ?? '1';
        if (!$targetSem) {
            $targetSem = is_numeric($currentSem) ? (string)((int)$currentSem + 1) : '2';
        }

        $stmt = $this->db->prepare(
            "UPDATE student_profiles 
             SET prev_semester = semester, semester = ?, promotion_opt_in = 0, promotion_status = 'promoted', promotion_target_sem = NULL, promoted_at = NOW(), promoted_by = ?, promotion_notes = ?, updated_at = NOW() 
             WHERE user_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("sisi", $targetSem, $promotedBy, $notes, $userId);
            $stmt->execute();
            $stmt->close();

            $dept = $profile['department'] ?? 'BCA';
            $logStmt = $this->db->prepare(
                "INSERT INTO semester_promotions (student_id, from_semester, to_semester, department, action, performed_by, notes) 
                 VALUES (?, ?, ?, ?, 'promoted', ?, ?)"
            );
            if ($logStmt) {
                $logStmt->bind_param("isssis", $userId, $currentSem, $targetSem, $dept, $promotedBy, $notes);
                $logStmt->execute();
                $logStmt->close();
            }

            // Notification for student
            $notifTitle = "Semester Promotion Confirmed!";
            $notifMsg = "Congratulations! You have been officially promoted from Semester $currentSem to Semester $targetSem.";
            $notifStmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'success', 0, NOW())");
            if ($notifStmt) {
                $notifStmt->bind_param("iss", $userId, $notifTitle, $notifMsg);
                $notifStmt->execute();
                $notifStmt->close();
            }

            return ['success' => true, 'message' => "Student promoted from Semester $currentSem to Semester $targetSem successfully!"];
        }

        return ['success' => false, 'error' => 'Database error during promotion.'];
    }

    public function rollbackPromotion($userId, $performedBy = 1, $reason = '') {
        $profile = $this->findByUserId($userId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Student profile not found.'];
        }

        $currentSem = $profile['semester'] ?? '2';
        $prevSem = $profile['prev_semester'];
        if (empty($prevSem)) {
            $prevSem = is_numeric($currentSem) && (int)$currentSem > 1 ? (string)((int)$currentSem - 1) : '1';
        }

        $notes = !empty($reason) ? "Rollback: $reason" : "Rollback to previous semester";
        $stmt = $this->db->prepare(
            "UPDATE student_profiles 
             SET semester = ?, prev_semester = NULL, promotion_opt_in = 1, promotion_status = 'opted_in', promotion_target_sem = ?, promoted_at = NULL, promoted_by = NULL, promotion_notes = ?, updated_at = NOW() 
             WHERE user_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("sssi", $prevSem, $currentSem, $notes, $userId);
            $stmt->execute();
            $stmt->close();

            $dept = $profile['department'] ?? 'BCA';
            $logStmt = $this->db->prepare(
                "INSERT INTO semester_promotions (student_id, from_semester, to_semester, department, action, performed_by, notes) 
                 VALUES (?, ?, ?, ?, 'rollback', ?, ?)"
            );
            if ($logStmt) {
                $logStmt->bind_param("isssis", $userId, $currentSem, $prevSem, $dept, $performedBy, $notes);
                $logStmt->execute();
                $logStmt->close();
            }

            // Notification for student
            $notifTitle = "Academic Semester Update";
            $notifMsg = "Your semester status has been reverted to Semester $prevSem by Academic Administration.";
            $notifStmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'warning', 0, NOW())");
            if ($notifStmt) {
                $notifStmt->bind_param("iss", $userId, $notifTitle, $notifMsg);
                $notifStmt->execute();
                $notifStmt->close();
            }

            return ['success' => true, 'message' => "Student rolled back from Semester $currentSem to Semester $prevSem successfully."];
        }

        return ['success' => false, 'error' => 'Database error during rollback.'];
    }

    /**
     * Promote students filtered by degree and semester
     *
     * @param string|int $degree Department name, code, or ID ('all' for any)
     * @param string $fromSemester Current semester to promote from ('all' for any)
     * @param string|null $targetSemester Specific semester or null/'next' for current + 1
     * @param int $promotedBy Admin user ID executing the promotion
     * @param string $notes Optional audit notes
     * @param bool $onlyOptedIn Only promote students who opted in
     * @return array
     */
    public function promoteByDegreeAndSemester($degree = 'all', $fromSemester = 'all', $targetSemester = null, $promotedBy = 1, $notes = '', $onlyOptedIn = false) {
        $where = ["u.is_active = 1", "u.deleted_at IS NULL"];
        $params = [];
        $types = "";

        if (!empty($fromSemester) && $fromSemester !== 'all') {
            $where[] = "sp.semester = ?";
            $params[] = (string)$fromSemester;
            $types .= "s";
        }

        if (!empty($degree) && $degree !== 'all') {
            if (is_numeric($degree)) {
                $where[] = "sp.department_id = ?";
                $params[] = (int)$degree;
                $types .= "i";
            } else {
                $where[] = "(sp.department = ? OR d.code = ? OR d.name = ?)";
                $params[] = (string)$degree;
                $params[] = (string)$degree;
                $params[] = (string)$degree;
                $types .= "sss";
            }
        }

        if ($onlyOptedIn) {
            $where[] = "sp.promotion_opt_in = 1";
        }

        $sql = "SELECT sp.user_id, sp.semester, sp.department, sp.promotion_target_sem, u.first_name, u.last_name 
                FROM student_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                LEFT JOIN departments d ON sp.department_id = d.id 
                WHERE " . implode(" AND ", $where);

        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            return ['success' => false, 'error' => 'Database query preparation failed.'];
        }

        if (empty($students)) {
            return ['success' => false, 'error' => 'No matching active students found for the selected degree and semester.'];
        }

        $promotedCount = 0;
        $studentNames = [];

        foreach ($students as $stu) {
            $uId = (int)$stu['user_id'];
            $curSem = $stu['semester'] ?? '1';

            if (!empty($targetSemester) && $targetSemester !== 'next') {
                $tSem = (string)$targetSemester;
            } elseif (!empty($stu['promotion_target_sem'])) {
                $tSem = (string)$stu['promotion_target_sem'];
            } else {
                $tSem = is_numeric($curSem) ? (string)((int)$curSem + 1) : '2';
            }

            $res = $this->promoteStudent($uId, $tSem, $promotedBy, $notes);
            if ($res['success']) {
                $promotedCount++;
                $studentNames[] = $stu['first_name'] . ' ' . $stu['last_name'];
            }
        }

        $targetLabel = (!empty($targetSemester) && $targetSemester !== 'next') ? "Semester $targetSemester" : "their next semester";
        $degLabel = ($degree !== 'all') ? "in $degree" : "across all departments";
        $fromLabel = ($fromSemester !== 'all') ? "from Semester $fromSemester" : "";

        return [
            'success' => true,
            'count' => $promotedCount,
            'students' => $studentNames,
            'message' => "Promotion completed! Successfully promoted $promotedCount student(s) $degLabel $fromLabel to $targetLabel."
        ];
    }

    /**
     * Get candidate count for degree/semester promotion preview
     */
    public function getPromotionCandidates($degree = 'all', $fromSemester = 'all', $onlyOptedIn = false) {
        $where = ["u.is_active = 1", "u.deleted_at IS NULL"];
        $params = [];
        $types = "";

        if (!empty($fromSemester) && $fromSemester !== 'all') {
            $where[] = "sp.semester = ?";
            $params[] = (string)$fromSemester;
            $types .= "s";
        }

        if (!empty($degree) && $degree !== 'all') {
            if (is_numeric($degree)) {
                $where[] = "sp.department_id = ?";
                $params[] = (int)$degree;
                $types .= "i";
            } else {
                $where[] = "(sp.department = ? OR d.code = ? OR d.name = ?)";
                $params[] = (string)$degree;
                $params[] = (string)$degree;
                $params[] = (string)$degree;
                $types .= "sss";
            }
        }

        if ($onlyOptedIn) {
            $where[] = "sp.promotion_opt_in = 1";
        }

        $sql = "SELECT sp.user_id, sp.student_id, sp.semester, sp.department, sp.promotion_status, u.first_name, u.last_name, u.email 
                FROM student_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                LEFT JOIN departments d ON sp.department_id = d.id 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY sp.semester ASC, u.first_name ASC";

        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $results;
        }
        return [];
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