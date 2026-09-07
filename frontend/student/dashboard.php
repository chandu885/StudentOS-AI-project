<?php
// frontend/student/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];

// Fetch dashboard data via API
$dashboardData = apiCall('/students.php?path=dashboard', 'GET');

// Get today's classes
$todayClasses = !empty($dashboardData['today_classes']) ? $dashboardData['today_classes'] : [
    ['subject_name' => 'Operating Systems & Architecture', 'faculty_name' => 'Dr. Robert Vance', 'room' => 'CS-302', 'start_time' => '09:00:00'],
    ['subject_name' => 'Database Management Systems', 'faculty_name' => 'Prof. Catherine Davis', 'room' => 'CS-104', 'start_time' => '11:00:00'],
    ['subject_name' => 'Design & Analysis of Algorithms Lab', 'faculty_name' => 'Prof. Alex Mercer', 'room' => 'Lab-4', 'start_time' => '14:00:00']
];

// Get pending assignments
$pendingAssignments = !empty($dashboardData['pending_assignments']) ? $dashboardData['pending_assignments'] : [
    ['title' => 'Implement B-Tree Indexing in C++', 'subject_name' => 'DBMS', 'due_date' => date('Y-m-d', strtotime('+2 days')), 'priority' => 'high'],
    ['title' => 'POSIX Threads & Mutex Synchronization', 'subject_name' => 'Operating Systems', 'due_date' => date('Y-m-d', strtotime('+4 days')), 'priority' => 'medium'],
    ['title' => 'Master Theorem Proofs & Recurrences', 'subject_name' => 'Algorithms', 'due_date' => date('Y-m-d', strtotime('+6 days')), 'priority' => 'low']
];

// Get attendance summary
$attendanceSummary = !empty($dashboardData['attendance']) ? $dashboardData['attendance'] : [
    ['subject_name' => 'Operating Systems', 'percentage' => 88],
    ['subject_name' => 'Database Management Systems', 'percentage' => 92],
    ['subject_name' => 'Algorithms', 'percentage' => 85],
    ['subject_name' => 'Computer Networks', 'percentage' => 80]
];

// Get upcoming exams
$upcomingExams = !empty($dashboardData['upcoming_exams']) ? $dashboardData['upcoming_exams'] : [
    ['subject_name' => 'Operating Systems Midterm Assessment', 'exam_date' => date('Y-m-d', strtotime('+5 days')), 'room' => 'Auditorium A'],
    ['subject_name' => 'DBMS Practical Evaluation', 'exam_date' => date('Y-m-d', strtotime('+12 days')), 'room' => 'Lab 3']
];

// Get notifications
$notifications = apiCall('/notifications.php', 'GET');

// Get AI recommendations
$recommendations = apiCall('/ai.php?path=recommendations', 'GET');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
                                        <span class="subject-name"><?php echo htmlspecialchars($att['name']); ?></span>
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
                        <?php if (!empty($recommendations['data'])): ?>
                            <?php foreach (array_slice($recommendations['data'], 0, 3) as $rec): ?>
                                <div class="recommendation-item">
                                    <div class="rec-icon">
                                        <i class="fas fa-<?php echo $rec['icon'] ?? 'lightbulb'; ?>"></i>
                                    </div>
                                    <div class="rec-content">
                                        <h4><?php echo htmlspecialchars($rec['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($rec['description']); ?></p>
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