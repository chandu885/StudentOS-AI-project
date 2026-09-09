<?php
// frontend/faculty/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();

$assignedSubjects = [];
$totalStudentsCount = 0;
$submissionsToGradeCount = 0;
$avgAttendancePct = 0;
$todayClasses = [];
$pendingGrading = [];
$facultyDept = 'Department of Computer Science & Engineering';

if ($db && $userId > 0) {
    // Faculty profile / department
    $stmt = $db->prepare("SELECT d.name as dept_name FROM faculty_profiles fp LEFT JOIN departments d ON fp.department_id = d.id WHERE fp.user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $prof = $stmt->get_result()->fetch_assoc();
        if (!empty($prof['dept_name'])) {
            $facultyDept = $prof['dept_name'];
        }
        $stmt->close();
    }

    // 1. Fetch assigned subjects
    $stmt = $db->prepare("SELECT s.id, s.name, s.code, s.semester, COUNT(DISTINCT ss.student_id) as students_count 
                          FROM subjects s 
                          LEFT JOIN student_subjects ss ON ss.subject_id = s.id 
                          WHERE s.faculty_id = ? 
                          GROUP BY s.id");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $assignedSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // If no subjects specifically assigned to this faculty, fallback to general subjects
    if (empty($assignedSubjects)) {
        $res = $db->query("SELECT s.id, s.name, s.code, s.semester, COUNT(DISTINCT ss.student_id) as students_count 
                           FROM subjects s 
                           LEFT JOIN student_subjects ss ON ss.subject_id = s.id 
                           GROUP BY s.id LIMIT 4");
        if ($res) {
            $assignedSubjects = $res->fetch_all(MYSQLI_ASSOC);
        }
    }

    $subjectIds = array_column($assignedSubjects, 'id');
    $subIdsList = !empty($subjectIds) ? implode(',', array_map('intval', $subjectIds)) : '0';

    // 2. Total Enrolled Students
    $res = $db->query("SELECT COUNT(DISTINCT student_id) as cnt FROM student_subjects WHERE subject_id IN ($subIdsList)");
    if ($res && $r = $res->fetch_assoc()) {
        $totalStudentsCount = (int)$r['cnt'];
    }

    // 3. Submissions to Grade
    $res = $db->query("SELECT COUNT(*) as cnt FROM assignment_submissions sub 
                       JOIN assignments a ON sub.assignment_id = a.id 
                       WHERE a.subject_id IN ($subIdsList) AND (sub.status = 'submitted' OR sub.status = 'pending' OR sub.marks_obtained IS NULL)");
    if ($res && $r = $res->fetch_assoc()) {
        $submissionsToGradeCount = (int)$r['cnt'];
    }

    // 4. Average Class Attendance
    $res = $db->query("SELECT ROUND(COUNT(CASE WHEN LOWER(status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as avg_pct 
                       FROM attendance WHERE subject_id IN ($subIdsList)");
    if ($res && $r = $res->fetch_assoc()) {
        $avgAttendancePct = $r['avg_pct'] !== null ? (float)$r['avg_pct'] : 85.0;
    }

    // 5. Today's Lectures
    $dayOfWeek = date('l');
    $stmt = $db->prepare("SELECT cs.*, s.name as subject_name, s.code as subject_code, cs.room_number as room 
                          FROM class_schedules cs 
                          JOIN subjects s ON cs.subject_id = s.id 
                          WHERE (cs.faculty_id = ? OR cs.subject_id IN ($subIdsList)) AND cs.day_of_week = ? 
                          ORDER BY cs.start_time ASC");
    if ($stmt) {
        $stmt->bind_param("is", $userId, $dayOfWeek);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $timeStr = date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time']));
            $todayClasses[] = [
                'time' => $timeStr,
                'subject' => $row['subject_name'] . ' (' . $row['subject_code'] . ')',
                'room' => !empty($row['room']) ? 'Hall ' . $row['room'] : 'Hall 201',
                'semester' => !empty($row['semester']) ? 'Semester ' . $row['semester'] : 'Sem 6'
            ];
        }
    }
    // Fallback to regular schedules if none today
    if (empty($todayClasses)) {
        $res = $db->query("SELECT cs.*, s.name as subject_name, s.code as subject_code 
                           FROM class_schedules cs 
                           JOIN subjects s ON cs.subject_id = s.id 
                           WHERE cs.subject_id IN ($subIdsList) 
                           ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), cs.start_time ASC 
                           LIMIT 4");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $timeStr = date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time']));
                $todayClasses[] = [
                    'time' => $row['day_of_week'] . ' ' . $timeStr,
                    'subject' => $row['subject_name'],
                    'room' => !empty($row['room_number']) ? 'Hall ' . $row['room_number'] : 'Hall 201',
                    'semester' => !empty($row['semester']) ? 'Semester ' . $row['semester'] : 'Sem 6'
                ];
            }
        }
    }

    // 6. Submissions Awaiting Grading
    $res = $db->query("SELECT a.id, a.title, s.code as subject_code, COUNT(sub.id) as pending_count, MAX(sub.submitted_at) as latest_sub 
                       FROM assignments a 
                       JOIN subjects s ON a.subject_id = s.id 
                       JOIN assignment_submissions sub ON sub.assignment_id = a.id 
                       WHERE a.subject_id IN ($subIdsList) AND (sub.status = 'submitted' OR sub.status = 'pending' OR sub.marks_obtained IS NULL) 
                       GROUP BY a.id 
                       LIMIT 5");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pendingGrading[] = [
                'assignment' => $row['title'],
                'subject' => $row['subject_code'],
                'pending_count' => (int)$row['pending_count'],
                'due' => !empty($row['latest_sub']) ? timeAgo($row['latest_sub']) : 'Recently'
            ];
        }
    }
}
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
                        <p class="welcome-subtitle">Academic Overview • <?php echo htmlspecialchars($facultyDept); ?></p>
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
                            <span class="stat-number"><?php echo number_format($totalStudentsCount); ?></span>
                            <span class="stat-label">Total Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-tasks"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($submissionsToGradeCount); ?></span>
                            <span class="stat-label">Submissions to Grade</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $avgAttendancePct; ?>%</span>
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
                            <?php if (!empty($todayClasses)): ?>
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
                            <?php else: ?>
                                <div class="empty-state" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-check" style="font-size: 28px; margin-bottom: 8px; display: block;"></i>
                                    <p>No classes scheduled for today.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pending Grading -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-pen-alt"></i> Submissions Awaiting Grading</h3>
                            <a href="submissions.php" class="link">Grade All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendingGrading)): ?>
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
                            <?php else: ?>
                                <div class="empty-state" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    <i class="fas fa-check-circle" style="font-size: 28px; color: var(--success); margin-bottom: 8px; display: block;"></i>
                                    <p>All student submissions graded!</p>
                                </div>
                            <?php endif; ?>
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
