<?php
// backend/api/index.php - Unified Central REST API Router

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Faculty.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/Academic.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AIModel.php';
require_once __DIR__ . '/../models/SystemModel.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/StudentService.php';
require_once __DIR__ . '/../services/FacultyService.php';
require_once __DIR__ . '/../services/AdminService.php';
require_once __DIR__ . '/../services/AssignmentService.php';
require_once __DIR__ . '/../services/AttendanceService.php';
require_once __DIR__ . '/../services/ExamService.php';
require_once __DIR__ . '/../services/AIService.php';

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

// Determine resource and action
$scriptName = basename($_SERVER['SCRIPT_NAME'], '.php');
$rawPath = $_GET['path'] ?? '';

// If called via a specific file (e.g. auth.php?path=login)
if ($scriptName !== 'index' && !empty($scriptName)) {
    $resource = $scriptName;
    $action = $rawPath;
    $id = $_GET['id'] ?? null;
} else {
    $parts = explode('/', trim($rawPath, '/'));
    $resource = $parts[0] ?? '';
    $id = $parts[1] ?? ($_GET['id'] ?? null);
    $action = $parts[2] ?? ($_GET['action'] ?? null);
}

// Helper send response
function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    switch ($resource) {
        // ---------------- AUTH ----------------
        case 'auth':
            $authService = new AuthService();
            if ($action === 'login' && $method === 'POST') {
                $email = $input['email'] ?? '';
                $password = $input['password'] ?? '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $res = $authService->login($email, $password, $ip, $ua);
                jsonOut($res, $res['success'] ? 200 : 401);
            } elseif ($action === 'register' && $method === 'POST') {
                $res = $authService->registerStudent($input);
                jsonOut($res, $res['success'] ? 201 : 400);
            } elseif ($action === 'logout') {
                $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? ($input['session_token'] ?? null);
                if ($token) {
                    $authService->logout($token);
                }
                jsonOut(['success' => true]);
            } elseif ($action === 'verify-email') {
                $token = $_GET['token'] ?? ($input['token'] ?? '');
                $res = $authService->verifyEmail($token);
                jsonOut($res, $res['success'] ? 200 : 400);
            } elseif ($action === 'forgot-password' && $method === 'POST') {
                $res = $authService->forgotPassword($input['email'] ?? '');
                jsonOut($res, $res['success'] ? 200 : 400);
            } elseif ($action === 'reset-password' && $method === 'POST') {
                $res = $authService->resetPassword($input['token'] ?? '', $input['password'] ?? '');
                jsonOut($res, $res['success'] ? 200 : 400);
            } else {
                jsonOut(['error' => 'Invalid auth endpoint'], 400);
            }
            break;

        // ---------------- STUDENTS ----------------
        case 'students':
            $user = requireAuth();
            $studentService = new StudentService();
            if ($action === 'dashboard' || $id === 'dashboard') {
                jsonOut($studentService->getDashboard($user['id']));
            } elseif ($action === 'profile' || $id === 'profile') {
                if ($method === 'POST' || $method === 'PUT') {
                    jsonOut($studentService->updateProfile($user['id'], $input));
                } else {
                    jsonOut($studentService->getProfile($user['id']));
                }
            } else {
                $academic = new Academic();
                jsonOut([
                    'success' => true,
                    'subjects' => $academic->getStudentSubjects($user['id'])
                ]);
            }
            break;

        // ---------------- FACULTY ----------------
        case 'faculty':
            $user = requireAuth();
            $facultyService = new FacultyService();
            if ($action === 'dashboard' || $id === 'dashboard') {
                jsonOut($facultyService->getDashboard($user['id']));
            } else {
                $academic = new Academic();
                jsonOut(['success' => true, 'subjects' => $academic->getFacultySubjects($user['id'])]);
            }
            break;

        // ---------------- ADMIN ----------------
        case 'admin':
            $user = requireAuth();
            $adminService = new AdminService();
            if ($action === 'dashboard' || $id === 'dashboard') {
                jsonOut($adminService->getDashboard());
            } else {
                jsonOut($adminService->getDashboard());
            }
            break;

        // ---------------- ACADEMIC ----------------
        case 'academic':
            $academic = new Academic();
            if ($action === 'departments' || $id === 'departments') {
                jsonOut(['success' => true, 'departments' => $academic->getDepartments()]);
            } elseif ($action === 'courses' || $id === 'courses') {
                $deptId = $_GET['department_id'] ?? null;
                jsonOut(['success' => true, 'courses' => $academic->getCourses($deptId)]);
            } elseif ($action === 'subjects' || $id === 'subjects') {
                $courseId = $_GET['course_id'] ?? null;
                $semester = $_GET['semester'] ?? null;
                jsonOut(['success' => true, 'subjects' => $academic->getSubjects($courseId, $semester)]);
            } elseif ($action === 'schedules' || $id === 'schedules') {
                $courseId = $_GET['course_id'] ?? null;
                $semester = $_GET['semester'] ?? null;
                $section = $_GET['section'] ?? null;
                jsonOut(['success' => true, 'schedules' => $academic->getSchedules($courseId, $semester, $section)]);
            } else {
                jsonOut(['departments' => $academic->getDepartments()]);
            }
            break;

        // ---------------- ASSIGNMENTS ----------------
        case 'assignments':
            $user = requireAuth();
            $asgService = new AssignmentService();
            $asgModel = new Assignment();

            if ($method === 'POST' && $action === 'create') {
                jsonOut($asgService->createAssignment($input, $user['id']));
            } elseif ($method === 'POST' && $action === 'submit') {
                $asgId = (int)($input['assignment_id'] ?? $id);
                $text = $input['submission_text'] ?? '';
                $filePath = $input['file_path'] ?? null;
                jsonOut($asgService->submitAssignment($asgId, $user['id'], $text, $filePath));
            } elseif ($method === 'POST' && $action === 'grade') {
                $subId = (int)$input['submission_id'];
                $marks = (float)$input['marks'];
                $feedback = $input['feedback'] ?? '';
                jsonOut($asgService->gradeSubmission($subId, $marks, $feedback, $user['id']));
            } else {
                if ($user['role_id'] == 4) { // Student
                    jsonOut(['success' => true, 'assignments' => $asgModel->getAllForStudent($user['id'])]);
                } elseif ($user['role_id'] == 3) { // Faculty
                    jsonOut(['success' => true, 'assignments' => $asgModel->getByFaculty($user['id'])]);
                } else {
                    jsonOut(['success' => true, 'assignments' => $asgModel->findBySubject($_GET['subject_id'] ?? 1)]);
                }
            }
            break;

        // ---------------- ATTENDANCE ----------------
        case 'attendance':
            $user = requireAuth();
            $attService = new AttendanceService();
            if ($method === 'POST') {
                $subjectId = (int)($input['subject_id'] ?? 0);
                $date = $input['date'] ?? date('Y-m-d');
                $records = $input['records'] ?? [];
                jsonOut($attService->markAttendance($subjectId, $records, $user['id'], $date));
            } else {
                if ($action === 'roster') {
                    $subId = (int)($_GET['subject_id'] ?? 1);
                    $date = $_GET['date'] ?? date('Y-m-d');
                    jsonOut(['success' => true, 'roster' => $attService->getSubjectRoster($subId, $date)]);
                } else {
                    jsonOut($attService->getStudentSummary($user['id']));
                }
            }
            break;

        // ---------------- EXAMS ----------------
        case 'exams':
            $user = requireAuth();
            $examService = new ExamService();
            if ($method === 'POST' && $action === 'create') {
                jsonOut($examService->createExam($input, $user['id']));
            } else {
                jsonOut($examService->getStudentExams($user['id']));
            }
            break;

        // ---------------- TASKS & GOALS ----------------
        case 'tasks':
            $user = requireAuth();
            $taskModel = new Task();
            if ($method === 'POST') {
                if ($action === 'status') {
                    jsonOut(['success' => $taskModel->updateStatus((int)$input['task_id'], $user['id'], $input['status'])]);
                } elseif ($action === 'goal') {
                    jsonOut(['success' => $taskModel->createGoal($user['id'], $input)]);
                } elseif ($action === 'session') {
                    jsonOut(['success' => $taskModel->logStudySession($user['id'], $input['subject_id'] ?? null, $input['topic'], (int)$input['duration'], $input['notes'] ?? '')]);
                } else {
                    jsonOut(['success' => $taskModel->create($user['id'], $input)]);
                }
            } elseif ($method === 'DELETE') {
                jsonOut(['success' => $taskModel->delete((int)$id, $user['id'])]);
            } else {
                jsonOut([
                    'success' => true,
                    'tasks' => $taskModel->getByUser($user['id']),
                    'goals' => $taskModel->getGoals($user['id']),
                    'sessions' => $taskModel->getStudySessions($user['id'])
                ]);
            }
            break;

        // ---------------- NOTES & DOCUMENTS ----------------
        case 'notes':
            $user = requireAuth();
            $noteModel = new Note();
            if ($method === 'POST') {
                $subId = !empty($input['subject_id']) ? (int)$input['subject_id'] : null;
                jsonOut(['success' => $noteModel->createNote($user['id'], $subId, $input['title'], $input['content'], $input['tags'] ?? null)]);
            } elseif ($method === 'DELETE') {
                jsonOut(['success' => $noteModel->deleteNote((int)$id, $user['id'])]);
            } else {
                jsonOut(['success' => true, 'notes' => $noteModel->getNotes($user['id'])]);
            }
            break;

        // ---------------- NOTIFICATIONS ----------------
        case 'notifications':
            $user = requireAuth();
            $notifModel = new Notification();
            if ($action === 'read' && $method === 'POST') {
                jsonOut(['success' => $notifModel->markAsRead((int)($input['id'] ?? $id), $user['id'])]);
            } elseif ($action === 'read-all' && $method === 'POST') {
                jsonOut(['success' => $notifModel->markAllAsRead($user['id'])]);
            } else {
                jsonOut([
                    'success' => true,
                    'notifications' => $notifModel->getByUser($user['id']),
                    'unread_count' => $notifModel->getUnreadCount($user['id'])
                ]);
            }
            break;

        // ---------------- AI SYSTEM ----------------
        case 'ai':
            $user = requireAuth();
            $aiService = new AIService();
            $aiModel = new AIModel();

            if ($action === 'assistant' && $method === 'POST') {
                $q = $input['question'] ?? '';
                $convId = !empty($input['conversation_id']) ? (int)$input['conversation_id'] : null;
                jsonOut($aiService->askAssistant($user['id'], $q, $convId));
            } elseif ($action === 'pdf-qa' && $method === 'POST') {
                $docId = (int)($input['document_id'] ?? 1);
                $q = $input['question'] ?? '';
                jsonOut($aiService->askDocument($user['id'], $docId, $q));
            } elseif ($action === 'planner' && $method === 'POST') {
                $subId = (int)($input['subject_id'] ?? 1);
                $examDate = $input['exam_date'] ?? date('Y-m-d', strtotime('+14 days'));
                $days = (int)($input['days'] ?? 14);
                jsonOut($aiService->generateStudyPlan($user['id'], $subId, $examDate, $days));
            } elseif ($action === 'quiz' && $method === 'POST') {
                $subId = (int)($input['subject_id'] ?? 1);
                $topic = $input['topic'] ?? 'General Engineering';
                $count = (int)($input['count'] ?? 5);
                $difficulty = $input['difficulty'] ?? 'medium';
                jsonOut($aiService->generateQuiz($user['id'], $subId, $topic, $count, $difficulty));
            } elseif ($action === 'summarize' && $method === 'POST') {
                $text = $input['text'] ?? '';
                jsonOut($aiService->summarizeText($user['id'], $text));
            } elseif ($action === 'search') {
                $query = $_GET['q'] ?? ($input['q'] ?? '');
                jsonOut($aiService->search($user['id'], $query));
            } elseif ($action === 'recommendations') {
                jsonOut(['success' => true, 'recommendations' => $aiModel->getRecommendations($user['id'])]);
            } else {
                jsonOut(['success' => true, 'conversations' => $aiModel->getConversations($user['id'])]);
            }
            break;

        default:
            jsonOut(['error' => "Endpoint '$resource' not found"], 404);
    }
} catch (Exception $e) {
    Logger::error('API Unhandled Exception: ' . $e->getMessage(), ['resource' => $resource, 'action' => $action]);
    jsonOut(['error' => 'Server error: ' . $e->getMessage()], 500);
}