<?php
// backend/models/Academic.php

require_once __DIR__ . '/../config/database.php';

class Academic {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ==========================================
    // DEPARTMENTS
    // ==========================================
    public function getDepartments() {
        $res = $this->db->query("SELECT d.*, u.first_name AS head_first, u.last_name AS head_last FROM departments d LEFT JOIN users u ON d.head_id = u.id ORDER BY d.name ASC");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getDepartmentById($id) {
        $stmt = $this->db->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createDepartment($code, $name, $desc, $headId = null) {
        $stmt = $this->db->prepare("INSERT INTO departments (code, name, description, head_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $code, $name, $desc, $headId);
        return $stmt->execute();
    }

    // ==========================================
    // COURSES
    // ==========================================
    public function getCourses($departmentId = null) {
        if ($departmentId) {
            $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.department_id = ? ORDER BY c.name ASC");
            $stmt->bind_param("i", $departmentId);
        } else {
            $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY c.name ASC");
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCourseById($id) {
        $stmt = $this->db->prepare("SELECT c.*, d.name AS department_name FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createCourse($deptId, $code, $name, $desc, $years, $semesters, $degreeType) {
        $stmt = $this->db->prepare("INSERT INTO courses (department_id, code, name, description, duration_years, total_semesters, degree_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiis", $deptId, $code, $name, $desc, $years, $semesters, $degreeType);
        return $stmt->execute();
    }

    // ==========================================
    // SEMESTERS
    // ==========================================
    public function getSemesters($courseId = null, $status = null, $academicYear = null, $search = null) {
        $sql = "SELECT sem.*, c.name AS course_name, c.code AS course_code, d.name AS department_name, d.id AS department_id,
                       (SELECT COUNT(*) FROM subjects sub WHERE sub.course_id = sem.course_id AND (sub.semester = sem.semester_number OR sub.semester = CONCAT('Sem ', sem.semester_number) OR sub.semester = CONCAT('Semester ', sem.semester_number))) AS subject_count,
                       (SELECT COUNT(*) FROM student_profiles sp WHERE (sp.semester = sem.semester_number OR sp.semester = CONCAT('Sem ', sem.semester_number) OR sp.semester = CONCAT('Semester ', sem.semester_number))) AS student_count
                FROM semesters sem
                JOIN courses c ON sem.course_id = c.id
                JOIN departments d ON c.department_id = d.id
                WHERE 1=1";
        $params = [];
        $types = "";

        if ($courseId) {
            $sql .= " AND sem.course_id = ?";
            $params[] = (int)$courseId;
            $types .= "i";
        }
        if ($status) {
            $sql .= " AND sem.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        if ($academicYear) {
            $sql .= " AND sem.academic_year = ?";
            $params[] = $academicYear;
            $types .= "s";
        }
        if ($search) {
            $sql .= " AND (sem.name LIKE ? OR c.name LIKE ? OR c.code LIKE ?)";
            $term = "%" . $search . "%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= "sss";
        }

        $sql .= " ORDER BY sem.academic_year DESC, c.name ASC, sem.semester_number ASC";
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSemesterById($id) {
        $stmt = $this->db->prepare(
            "SELECT sem.*, c.name AS course_name, c.code AS course_code, d.name AS department_name, d.id AS department_id
             FROM semesters sem
             JOIN courses c ON sem.course_id = c.id
             JOIN departments d ON c.department_id = d.id
             WHERE sem.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createSemester($data, $createdBy = null) {
        $courseId = (int)($data['course_id'] ?? 0);
        $semNum = (int)($data['semester_number'] ?? 1);
        $name = trim($data['name'] ?? '');
        $academicYear = trim($data['academic_year'] ?? '2026-2027');
        $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $status = in_array($data['status'] ?? '', ['active', 'upcoming', 'completed']) ? $data['status'] : 'active';
        $desc = !empty($data['description']) ? trim($data['description']) : null;

        if ($courseId <= 0) {
            return ['success' => false, 'error' => 'Please select a valid academic degree program / course.'];
        }
        if ($semNum < 1 || $semNum > 12) {
            return ['success' => false, 'error' => 'Semester number must be between 1 and 12.'];
        }
        if (empty($name)) {
            $name = "Semester " . $semNum;
        }

        // Check if unique key exists (course_id, semester_number, academic_year)
        $chk = $this->db->prepare("SELECT id FROM semesters WHERE course_id = ? AND semester_number = ? AND academic_year = ?");
        $chk->bind_param("iis", $courseId, $semNum, $academicYear);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        if ($existing) {
            $upRes = $this->updateSemester($existing['id'], $data);
            if ($upRes['success']) {
                $upRes['message'] = "Semester '{$name}' was already configured and has been successfully updated with your latest settings!";
                $upRes['semester_id'] = $existing['id'];
            }
            return $upRes;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO semesters (course_id, semester_number, name, academic_year, start_date, end_date, status, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iissssssi", $courseId, $semNum, $name, $academicYear, $startDate, $endDate, $status, $desc, $createdBy);

        if ($stmt->execute()) {
            $newId = $this->db->lastInsertId();
            return [
                'success' => true,
                'semester_id' => $newId,
                'semester' => $this->getSemesterById($newId),
                'message' => "Semester '{$name}' created successfully!"
            ];
        }

        return ['success' => false, 'error' => 'Database insert error: ' . $this->db->getConnection()->error];
    }

    public function updateSemester($id, $data) {
        $id = (int)$id;
        $existing = $this->getSemesterById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Semester not found.'];
        }

        $courseId = (int)($data['course_id'] ?? $existing['course_id']);
        $semNum = (int)($data['semester_number'] ?? $existing['semester_number']);
        $name = trim($data['name'] ?? $existing['name']);
        $academicYear = trim($data['academic_year'] ?? $existing['academic_year']);
        $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $status = in_array($data['status'] ?? '', ['active', 'upcoming', 'completed']) ? $data['status'] : $existing['status'];
        $desc = isset($data['description']) ? trim($data['description']) : $existing['description'];

        // Duplicate check
        $chk = $this->db->prepare("SELECT id FROM semesters WHERE course_id = ? AND semester_number = ? AND academic_year = ? AND id != ?");
        $chk->bind_param("iisi", $courseId, $semNum, $academicYear, $id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            return ['success' => false, 'error' => "Another semester {$semNum} already exists for this course in academic year {$academicYear}."];
        }

        $stmt = $this->db->prepare(
            "UPDATE semesters 
             SET course_id = ?, semester_number = ?, name = ?, academic_year = ?, start_date = ?, end_date = ?, status = ?, description = ?, updated_at = NOW() 
             WHERE id = ?"
        );
        $stmt->bind_param("iissssssi", $courseId, $semNum, $name, $academicYear, $startDate, $endDate, $status, $desc, $id);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'semester' => $this->getSemesterById($id),
                'message' => "Semester '{$name}' updated successfully!"
            ];
        }

        return ['success' => false, 'error' => 'Database update error: ' . $this->db->getConnection()->error];
    }

    public function deleteSemester($id) {
        $id = (int)$id;
        $existing = $this->getSemesterById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Semester not found.'];
        }

        $stmt = $this->db->prepare("DELETE FROM semesters WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => "Semester '{$existing['name']}' deleted successfully."];
        }

        return ['success' => false, 'error' => 'Failed to delete semester: ' . $this->db->getConnection()->error];
    }

    // ==========================================
    // SUBJECTS
    // ==========================================
    public function getSubjects($courseId = null, $semester = null, $departmentId = null, $search = null, $status = null) {
        $sql = "SELECT s.*, c.name AS course_name, c.code AS course_code, d.name AS department_name, 
                       u.first_name AS faculty_first, u.last_name AS faculty_last, u.email AS faculty_email,
                       (SELECT COUNT(*) FROM student_subjects WHERE subject_id = s.id) AS enrolled_count
                FROM subjects s
                JOIN courses c ON s.course_id = c.id
                JOIN departments d ON s.department_id = d.id
                LEFT JOIN users u ON s.faculty_id = u.id
                WHERE 1=1";
        $params = [];
        $types = "";

        if ($courseId) {
            $sql .= " AND s.course_id = ?";
            $params[] = (int)$courseId;
            $types .= "i";
        }
        if ($semester) {
            $sql .= " AND (s.semester = ? OR s.semester = CONCAT('Sem ', ?) OR s.semester = CONCAT('Semester ', ?))";
            $cleanSem = preg_replace('/[^0-9]/', '', (string)$semester);
            $params[] = $semester;
            $params[] = $cleanSem;
            $params[] = $cleanSem;
            $types .= "sss";
        }
        if ($departmentId) {
            $sql .= " AND s.department_id = ?";
            $params[] = (int)$departmentId;
            $types .= "i";
        }
        if ($status) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        if ($search) {
            $sql .= " AND (s.name LIKE ? OR s.code LIKE ? OR c.name LIKE ?)";
            $term = "%" . $search . "%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= "sss";
        }

        $sql .= " ORDER BY s.code ASC";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSubjectById($id) {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name AS course_name, c.code AS course_code, d.name AS department_name, 
                    u.first_name AS faculty_first, u.last_name AS faculty_last, u.email AS faculty_email,
                    (SELECT COUNT(*) FROM student_subjects WHERE subject_id = s.id) AS enrolled_count
             FROM subjects s
             JOIN courses c ON s.course_id = c.id
             JOIN departments d ON s.department_id = d.id
             LEFT JOIN users u ON s.faculty_id = u.id
             WHERE s.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getSubjectByCode($code) {
        $stmt = $this->db->prepare("SELECT * FROM subjects WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createSubject($data, $createdBy = null) {
        $courseId = (int)($data['course_id'] ?? 0);
        $deptId = (int)($data['department_id'] ?? 0);
        $facultyId = !empty($data['faculty_id']) ? (int)$data['faculty_id'] : null;
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $semester = trim((string)($data['semester'] ?? '1'));
        $credits = (int)($data['credits'] ?? 3);
        $type = in_array($data['type'] ?? '', ['core', 'elective', 'lab']) ? $data['type'] : 'core';
        $syllabus = !empty($data['syllabus']) ? trim($data['syllabus']) : null;
        $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';

        if (empty($code)) {
            return ['success' => false, 'error' => 'Subject course code is required (e.g. CS501).'];
        }
        if (empty($name)) {
            return ['success' => false, 'error' => 'Subject title is required.'];
        }
        if ($courseId <= 0) {
            return ['success' => false, 'error' => 'Please select a valid degree course program.'];
        }

        // Auto-fetch department if not provided
        if ($deptId <= 0) {
            $course = $this->getCourseById($courseId);
            if ($course) {
                $deptId = (int)$course['department_id'];
            } else {
                return ['success' => false, 'error' => 'Selected course does not exist.'];
            }
        }

        // Check unique code
        $existing = $this->getSubjectByCode($code);
        if ($existing) {
            $upRes = $this->updateSubject($existing['id'], $data);
            if ($upRes['success']) {
                $upRes['message'] = "Subject '{$name}' ({$code}) was already registered and has been successfully updated with your changes!";
                $upRes['subject_id'] = $existing['id'];
            }
            return $upRes;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO subjects (course_id, department_id, faculty_id, code, name, semester, credits, type, syllabus, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiisssisss", $courseId, $deptId, $facultyId, $code, $name, $semester, $credits, $type, $syllabus, $status);

        if ($stmt->execute()) {
            $newId = $this->db->lastInsertId();
            return [
                'success' => true,
                'subject_id' => $newId,
                'subject' => $this->getSubjectById($newId),
                'message' => "Subject '{$name}' ({$code}) created successfully!"
            ];
        }

        return ['success' => false, 'error' => 'Failed to create subject: ' . $this->db->getConnection()->error];
    }

    public function updateSubject($id, $data) {
        $id = (int)$id;
        $existing = $this->getSubjectById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Subject not found.'];
        }

        $courseId = (int)($data['course_id'] ?? $existing['course_id']);
        $deptId = (int)($data['department_id'] ?? $existing['department_id']);
        $facultyId = array_key_exists('faculty_id', $data) ? (!empty($data['faculty_id']) ? (int)$data['faculty_id'] : null) : $existing['faculty_id'];
        $code = strtoupper(trim($data['code'] ?? $existing['code']));
        $name = trim($data['name'] ?? $existing['name']);
        $semester = trim((string)($data['semester'] ?? $existing['semester']));
        $credits = (int)($data['credits'] ?? $existing['credits']);
        $type = in_array($data['type'] ?? '', ['core', 'elective', 'lab']) ? $data['type'] : $existing['type'];
        $syllabus = array_key_exists('syllabus', $data) ? (!empty($data['syllabus']) ? trim($data['syllabus']) : null) : $existing['syllabus'];
        $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : $existing['status'];

        if (empty($code) || empty($name)) {
            return ['success' => false, 'error' => 'Subject code and title cannot be empty.'];
        }

        $chk = $this->getSubjectByCode($code);
        if ($chk && (int)$chk['id'] !== $id) {
            return ['success' => false, 'error' => "Subject code '{$code}' is already assigned to another subject."];
        }

        $stmt = $this->db->prepare(
            "UPDATE subjects 
             SET course_id = ?, department_id = ?, faculty_id = ?, code = ?, name = ?, semester = ?, credits = ?, type = ?, syllabus = ?, status = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param("iiisssisssi", $courseId, $deptId, $facultyId, $code, $name, $semester, $credits, $type, $syllabus, $status, $id);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'subject' => $this->getSubjectById($id),
                'message' => "Subject '{$name}' ({$code}) updated successfully!"
            ];
        }

        return ['success' => false, 'error' => 'Database update error: ' . $this->db->getConnection()->error];
    }

    public function deleteSubject($id) {
        $id = (int)$id;
        $existing = $this->getSubjectById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Subject not found.'];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM student_subjects WHERE subject_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $enrolledCount = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

        if ($enrolledCount > 0) {
            $stmt = $this->db->prepare("UPDATE subjects SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            return [
                'success' => true,
                'message' => "Subject '{$existing['name']}' has {$enrolledCount} enrolled student(s). Marked inactive to preserve academic grades."
            ];
        }

        $stmt = $this->db->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => "Subject '{$existing['name']}' deleted successfully."];
        }

        return ['success' => false, 'error' => 'Failed to delete subject: ' . $this->db->getConnection()->error];
    }

    public function getStudentSubjects($studentUserId) {
        $stmt = $this->db->prepare(
            "SELECT s.*, ss.semester AS enrolled_sem, ss.status AS enroll_status,
                    u.first_name AS faculty_first, u.last_name AS faculty_last, u.email AS faculty_email,
                    c.name AS course_name
             FROM student_subjects ss
             JOIN subjects s ON ss.subject_id = s.id
             JOIN courses c ON s.course_id = c.id
             LEFT JOIN users u ON s.faculty_id = u.id
             WHERE ss.student_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getFacultySubjects($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.name AS course_name, d.name AS department_name,
                    (SELECT COUNT(*) FROM student_subjects WHERE subject_id = s.id) AS enrolled_students_count
             FROM subjects s
             JOIN courses c ON s.course_id = c.id
             JOIN departments d ON s.department_id = d.id
             WHERE s.faculty_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ==========================================
    // SCHEDULES
    // ==========================================
    public function getSchedules($courseId = null, $semester = null, $section = null) {
        $sql = "SELECT cs.*, s.name AS subject_name, s.code AS subject_code,
                       u.first_name AS faculty_first, u.last_name AS faculty_last
                FROM class_schedules cs
                JOIN subjects s ON cs.subject_id = s.id
                JOIN users u ON cs.faculty_id = u.id
                WHERE 1=1";
        $params = [];
        $types = "";

        if ($courseId) {
            $sql .= " AND cs.course_id = ?";
            $params[] = $courseId;
            $types .= "i";
        }
        if ($semester) {
            $sql .= " AND cs.semester = ?";
            $params[] = $semester;
            $types .= "s";
        }
        if ($section) {
            $sql .= " AND cs.section = ?";
            $params[] = $section;
            $types .= "s";
        }

        $sql .= " ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC";
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getFacultySchedule($facultyUserId) {
        $stmt = $this->db->prepare(
            "SELECT cs.*, s.name AS subject_name, s.code AS subject_code, c.name AS course_name
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN courses c ON cs.course_id = c.id
             WHERE cs.faculty_id = ?
             ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC"
        );
        $stmt->bind_param("i", $facultyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
