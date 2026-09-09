<?php
// frontend/student/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];

// Fetch live data directly from Database
$db = getDbConnection();
$todayClasses = [];
$pendingAssignments = [];
$attendanceSummary = [];
$upcomingExams = [];

if ($db && $userId) {
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
    
    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        // Auto-refresh notifications
        setInterval(function() {
            fetchNotifications();
        }, 30000);
    </script>
</body>
</html>