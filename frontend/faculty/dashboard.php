<?php
// frontend/faculty/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$facRes = apiCall('/faculty.php?path=dashboard', 'GET');
$facData = $facRes['dashboard'] ?? $facRes ?? [];

$assignedSubjects = $facData['subjects'] ?? [
    ['id' => 1, 'name' => 'Database Management Systems', 'code' => 'CS301', 'students_count' => 64],
    ['id' => 2, 'name' => 'Advanced Database Systems', 'code' => 'CS502', 'students_count' => 42]
];

$todayClasses = [
    ['time' => '09:00 AM - 10:30 AM', 'subject' => 'Database Management Systems', 'room' => 'Hall 201', 'semester' => 'Semester 6'],
    ['time' => '02:00 PM - 04:00 PM', 'subject' => 'DBMS Practical Lab', 'room' => 'Lab 3', 'semester' => 'Semester 6']
];

$pendingGrading = [
    ['assignment' => 'ER Diagram & Relational Schema', 'subject' => 'CS301', 'pending_count' => 14, 'due' => 'Yesterday'],
    ['assignment' => 'SQL Triggers & Stored Procedures', 'subject' => 'CS502', 'pending_count' => 22, 'due' => '2 days ago']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - StudentOS AI</title>
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
                <div class="welcome-section">
                    <div>
                        <h1>Welcome, Professor <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>! 👨‍🏫</h1>
                        <p class="welcome-subtitle">Academic Overview • Department of Computer Science & Engineering</p>
                    </div>
                    <div class="quick-actions">
                        <a href="attendance.php" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Mark Attendance</a>
                        <a href="assignments.php" class="btn btn-outline"><i class="fas fa-plus"></i> New Assignment</a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($assignedSubjects); ?></span>
                            <span class="stat-label">Assigned Courses</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">106</span>
                            <span class="stat-label">Total Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-tasks"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">36</span>
                            <span class="stat-label">Submissions to Grade</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">88.4%</span>
                            <span class="stat-label">Average Class Attendance</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Today's Teaching Schedule -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-day"></i> Today's Lectures</h3>
                            <a href="schedule.php" class="link">Full Timetable</a>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($todayClasses as $cls): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-primary); border-left: 4px solid var(--primary); border-radius: var(--radius-md); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                                        <div>
                                            <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars($cls['subject']); ?></strong>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                <?php echo htmlspecialchars($cls['semester']); ?> • <?php echo htmlspecialchars($cls['room']); ?>
                                            </div>
                                        </div>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($cls['time']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Grading -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-pen-alt"></i> Submissions Awaiting Grading</h3>
                            <a href="submissions.php" class="link">Grade All</a>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($pendingGrading as $pg): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div>
                                            <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars($pg['assignment']); ?></strong>
                                            <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($pg['subject']); ?> • Submitted <?php echo htmlspecialchars($pg['due']); ?></div>
                                        </div>
                                        <a href="submissions.php" class="btn btn-primary" style="font-size: 11px; padding: 4px 10px;">
                                            Grade (<?php echo $pg['pending_count']; ?>)
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
