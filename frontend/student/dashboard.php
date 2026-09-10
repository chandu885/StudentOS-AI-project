<?php
// frontend/student/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

$db = getDbConnection();

// Handle Password Change directly from Student Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errorMsg = 'Please fill in all password fields.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'New password must be at least 8 characters in length.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'New password and confirmation do not match.';
        } else {
            if ($db) {
                $pStmt = $db->prepare("SELECT password_hash FROM `users` WHERE `id` = ? AND `deleted_at` IS NULL");
                if ($pStmt) {
                    $pStmt->bind_param("i", $userId);
                    $pStmt->execute();
                    $curRow = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();

                    if ($curRow && password_verify($currentPass, $curRow['password_hash'])) {
                        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                        $upStmt = $db->prepare("UPDATE `users` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
                        if ($upStmt) {
                            $upStmt->bind_param("si", $newHash, $userId);
                            if ($upStmt->execute()) {
                                $successMsg = 'Your password has been successfully changed in the database! Please remember your new password.';
                            } else {
                                $errorMsg = 'Failed to update password in database: ' . $db->error;
                            }
                            $upStmt->close();
                        }
                    } else {
                        $errorMsg = 'Current password does not match our records. Verification failed.';
                    }
                }
            } else {
                $errorMsg = 'Database connection error.';
            }
        }
    }
}

$todayClasses = [];
$pendingAssignments = [];
$attendanceSummary = [];
$upcomingExams = [];
$studentProfile = null;

if ($db && $userId) {
    // Fetch student's academic profile details (Department, Course, Section, Phone)
    $spQuery = $db->prepare(
        "SELECT sp.*, u.phone AS user_phone, u.first_name, u.last_name, u.email,
                d.name AS department_name, d.code AS department_code,
                c.name AS course_name, c.code AS course_code
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN departments d ON sp.department_id = d.id
         LEFT JOIN courses c ON sp.course_id = c.id
         WHERE u.id = ?"
    );
    if ($spQuery) {
        $spQuery->bind_param("i", $userId);
        $spQuery->execute();
        $studentProfile = $spQuery->get_result()->fetch_assoc();
        $spQuery->close();
    }
    // 1. Classes from database
    $dayOfWeek = date('l');
    $stmt = $db->prepare(
        "SELECT cs.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as faculty_name 
         FROM class_schedules cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN users u ON cs.faculty_id = u.id
         JOIN student_subjects ss ON ss.subject_id = cs.subject_id
         WHERE ss.student_id = ? AND cs.day_of_week = ?
         ORDER BY cs.start_time"
    );
    if ($stmt) {
        $stmt->bind_param("is", $userId, $dayOfWeek);
        $stmt->execute();
        $todayClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // If no classes today, fetch upcoming schedules for student's subjects
    if (empty($todayClasses)) {
        $stmt = $db->prepare(
            "SELECT cs.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as faculty_name 
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.faculty_id = u.id
             JOIN student_subjects ss ON ss.subject_id = cs.subject_id
             WHERE ss.student_id = ?
             ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time
             LIMIT 4"
        );
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $todayClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    // 2. Pending Assignments from database
    $stmt = $db->prepare(
        "SELECT a.*, s.name as subject_name 
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         WHERE ss.student_id = ? 
         AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
         ORDER BY a.deadline ASC
         LIMIT 5"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $pendingAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 3. Attendance Summary from database
    $stmt = $db->prepare(
        "SELECT s.name as subject_name, 
                COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) as present,
                COUNT(*) as total,
                ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as percentage
         FROM attendance a
         JOIN subjects s ON a.subject_id = s.id
         WHERE a.student_id = ?
         GROUP BY a.subject_id"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $attendanceSummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 4. Upcoming Exams from database
    $stmt = $db->prepare(
        "SELECT e.*, s.name as subject_name 
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         WHERE ss.student_id = ?
         ORDER BY e.exam_date ASC
         LIMIT 4"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $upcomingExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Notifications and recommendations
$notifications = [];
if ($db && $userId) {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Dynamic AI Recommendations
$recommendations = [];
if ($db && $userId) {
    $stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = ? OR user_id = 0 ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Synthesize contextual recommendations if none stored
if (empty($recommendations)) {
    foreach ($attendanceSummary as $att) {
        if (($att['percentage'] ?? 100) < 75) {
            $recommendations[] = [
                'title' => 'Attendance Warning: ' . ($att['subject_name'] ?? 'Subject'),
                'description' => 'Current attendance is ' . $att['percentage'] . '%. Attend upcoming lectures to meet the mandatory 75% exam eligibility threshold.',
                'icon' => 'exclamation-triangle',
                'priority' => 'high'
            ];
        }
    }
    foreach ($pendingAssignments as $pa) {
        $daysUntil = round((strtotime($pa['deadline']) - time()) / 86400);
        if ($daysUntil <= 3) {
            $recommendations[] = [
                'title' => 'Deadline Alert: ' . $pa['title'],
                'description' => 'Coursework for ' . $pa['subject_name'] . ' is due in ' . max(0, (int)$daysUntil) . ' day(s). Submit before deadline.',
                'icon' => 'clock',
                'priority' => 'high'
            ];
            break;
        }
    }
    if (!empty($upcomingExams[0])) {
        $ex = $upcomingExams[0];
        $recommendations[] = [
            'title' => 'Exam Preparation: ' . $ex['subject_name'],
            'description' => 'Exam scheduled for ' . date('M d, Y', strtotime($ex['exam_date'])) . '. Generate an AI Study Plan to structure revision.',
            'icon' => 'book-open',
            'priority' => 'medium'
        ];
    }
    if (empty($recommendations)) {
        $recommendations[] = [
            'title' => 'Great Academic Momentum',
            'description' => 'You are on track with your coursework and attendance. Challenge yourself with an AI Practice Quiz to test retention.',
            'icon' => 'lightbulb',
            'priority' => 'low'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentOS AI</title>
    
    <!-- External Google Font Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CDN Resources (Font Awesome, Normalize) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    
    <!-- Application Stylesheets -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md);">
                        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md);">
                        <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($errorMsg); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>! 👋</h1>
                        <p class="welcome-subtitle">Here's your academic overview for today</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="window.location.href='ai-assistant.php'">
                            <i class="fas fa-robot"></i> Ask AI
                        </button>
                        <button class="btn btn-outline" onclick="window.location.href='tasks.php'">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                        <button class="btn btn-outline" onclick="openModal('studentPasswordModal')" title="Change Account Password">
                            <i class="fas fa-key" style="color: #F59E0B;"></i> Change Password
                        </button>
                    </div>
                </div>

                <!-- Student Academic & Profile Details Card (Department, Section, Phone) -->
                <div class="card" style="margin-bottom: 24px; padding: 18px 22px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                                    <?php echo htmlspecialchars(($studentProfile['first_name'] ?? $_SESSION['user']['first_name']) . ' ' . ($studentProfile['last_name'] ?? $_SESSION['user']['last_name'])); ?>
                                    <span class="badge badge-primary" style="font-size: 11.5px;"><?php echo htmlspecialchars(!empty($studentProfile['course_name']) ? $studentProfile['course_name'] : (!empty($studentProfile['course_code']) ? $studentProfile['course_code'] : 'BCA')); ?></span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                                    ID: <strong><?php echo htmlspecialchars($studentProfile['student_id'] ?? ('STU-' . str_pad($userId, 4, '0', STR_PAD_LEFT))); ?></strong>
                                    <?php if (!empty($studentProfile['roll_number'])): ?>
                                        &bull; Roll No: <strong><?php echo htmlspecialchars($studentProfile['roll_number']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                            <!-- Department -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-building" style="color: var(--primary); font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Department</div>
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars(!empty($studentProfile['department_name']) ? $studentProfile['department_name'] : 'Computer Science'); ?></div>
                                </div>
                            </div>

                            <!-- Section & Semester -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-layer-group" style="color: #8B5CF6; font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Section & Term</div>
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                        Section <span style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars(!empty($studentProfile['section']) ? $studentProfile['section'] : 'A'); ?></span> &bull; Sem <?php echo htmlspecialchars(!empty($studentProfile['semester']) ? $studentProfile['semester'] : '1'); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-phone" style="color: #10B981; font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Phone Number</div>
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                        <?php 
                                        $stuPhone = !empty($studentProfile['phone']) ? $studentProfile['phone'] : (!empty($studentProfile['user_phone']) ? $studentProfile['user_phone'] : 'Not provided');
                                        echo htmlspecialchars($stuPhone); 
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($todayClasses); ?></span>
                            <span class="stat-label">Today's Classes</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($pendingAssignments); ?></span>
                            <span class="stat-label">Pending Assignments</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($upcomingExams); ?></span>
                            <span class="stat-label">Upcoming Exams</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">
                                <?php 
                                $totalAttendance = 0;
                                if (!empty($attendanceSummary)) {
                                    foreach ($attendanceSummary as $att) {
                                        $totalAttendance += $att['percentage'];
                                    }
                                    $totalAttendance = round($totalAttendance / count($attendanceSummary));
                                }
                                echo $totalAttendance; ?>%
                            </span>
                            <span class="stat-label">Overall Attendance</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="dashboard-grid">
                    <!-- Today's Schedule -->
                    <div class="card schedule-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                            <a href="schedule.php" class="link">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($todayClasses)): ?>
                                <?php foreach ($todayClasses as $class): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time">
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?>
                                        </div>
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($class['faculty_name']); ?> • Room <?php echo htmlspecialchars($class['room']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-plus"></i>
                                    <p>No classes scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Assignments -->
                    <div class="card assignments-card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-alt"></i> Pending Assignments</h3>
                            <a href="assignments.php" class="link">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendingAssignments)): ?>
                                <?php foreach ($pendingAssignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-info">
                                            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                        </div>
                                        <div class="assignment-deadline">
                                            <span class="deadline-label">Due:</span>
                                            <span class="deadline-date <?php echo isOverdue($assignment['deadline']) ? 'overdue' : ''; ?>">
                                                <?php echo date('M d, Y', strtotime($assignment['deadline'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No pending assignments. Great job! 🎉</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Overview -->
                <div class="card attendance-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-check"></i> Attendance Overview</h3>
                        <a href="attendance.php" class="link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendanceSummary)): ?>
                            <div class="attendance-grid">
                                <?php foreach ($attendanceSummary as $att): ?>
                                    <div class="attendance-item">
                                        <span class="subject-name"><?php echo htmlspecialchars($att['subject_name'] ?? $att['name'] ?? 'Subject'); ?></span>
                                        <div class="attendance-bar">
                                            <div class="attendance-fill <?php echo $att['percentage'] < 75 ? 'danger' : ($att['percentage'] < 85 ? 'warning' : 'success'); ?>" 
                                                 style="width: <?php echo $att['percentage']; ?>%"></div>
                                        </div>
                                        <span class="attendance-percent"><?php echo $att['percentage']; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <p>No attendance records found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- AI Recommendations -->
                <div class="card recommendations-card">
                    <div class="card-header">
                        <h3><i class="fas fa-robot"></i> AI Recommendations</h3>
                        <a href="ai-recommendations.php" class="link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php 
                            $recList = $recommendations['recommendations'] ?? $recommendations['data'] ?? $recommendations;
                        ?>
                        <?php if (!empty($recList)): ?>
                            <?php foreach (array_slice($recList, 0, 3) as $rec): ?>
                                <div class="recommendation-item">
                                    <div class="rec-icon">
                                        <i class="fas fa-<?php echo htmlspecialchars($rec['icon'] ?? 'lightbulb'); ?>"></i>
                                    </div>
                                    <div class="rec-content">
                                        <h4><?php echo htmlspecialchars($rec['title'] ?? 'Recommendation'); ?></h4>
                                        <p><?php echo htmlspecialchars($rec['suggestion'] ?? $rec['description'] ?? ''); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-robot"></i>
                                <p>AI recommendations will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="studentPasswordModal">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fas fa-key" style="color: #F59E0B; margin-right: 8px;"></i> Change Account Password</h3>
                <button type="button" class="modal-close" onclick="closeModal('studentPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="dashboard.php" id="studentPasswordForm">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body" style="padding: var(--spacing-lg);">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="current_password">Current Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="current_password" id="current_password" class="form-control has-toggle" placeholder="Enter current password" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('current_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="new_password">New Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" name="new_password" id="new_password" class="form-control has-toggle" placeholder="Minimum 8 characters" minlength="8" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('new_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Must be at least 8 characters long</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 8px;">
                        <label for="confirm_password">Confirm New Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control has-toggle" placeholder="Re-enter new password" minlength="8" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('confirm_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: var(--spacing-lg); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('studentPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        function togglePassVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.getElementById('studentPasswordForm').addEventListener('submit', function(e) {
            const newP = document.getElementById('new_password').value;
            const confP = document.getElementById('confirm_password').value;
            if (newP.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                return false;
            }
            if (newP !== confP) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
        });

        // Auto-refresh notifications
        setInterval(function() {
            fetchNotifications();
        }, 30000);
    </script>
</body>
</html>