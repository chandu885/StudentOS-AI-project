<?php
// frontend/student/subjects.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$subjects = [];

if ($db && $userId > 0) {
    $stmt = $db->prepare(
        "SELECT s.*, ss.semester AS enrolled_sem, ss.status AS enroll_status,
                CONCAT(u.first_name, ' ', u.last_name) AS faculty_name, u.email AS faculty_email,
                c.name AS course_name,
                COALESCE((
                    SELECT ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1)
                    FROM attendance a 
                    WHERE a.subject_id = s.id AND a.student_id = ?
                ), 92.0) AS attendance_pct
         FROM student_subjects ss
         JOIN subjects s ON ss.subject_id = s.id
         JOIN courses c ON s.course_id = c.id
         LEFT JOIN users u ON s.faculty_id = u.id
         WHERE ss.student_id = ?
         ORDER BY CAST(s.semester AS UNSIGNED) DESC, s.code ASC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects - StudentOS AI</title>
    
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
                <div class="page-header">
                    <div>
                        <h1>Enrolled Subjects</h1>
                        <p class="page-subtitle">Your active academic courses for Current Semester</p>
                    </div>
                    <div class="header-actions">
                        <a href="schedule.php" class="btn btn-outline"><i class="fas fa-calendar"></i> View Timetable</a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($subjects); ?></span>
                            <span class="stat-label">Total Subjects</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-award"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">
                                <?php 
                                $credits = 0;
                                foreach($subjects as $s) $credits += ($s['credits'] ?? 3);
                                echo $credits;
                                ?>
                            </span>
                            <span class="stat-label">Total Credits</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">86%</span>
                            <span class="stat-label">Avg Attendance</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                    <?php foreach ($subjects as $subj): 
                        $pct = $subj['attendance_pct'] ?? 85;
                        $pctClass = $pct >= 85 ? 'success' : ($pct >= 75 ? 'warning' : 'danger');
                    ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <span class="badge badge-primary"><?php echo htmlspecialchars($subj['code'] ?? 'CS'); ?></span>
                                <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($subj['credits'] ?? 3); ?> Credits</span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 16px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($subj['name']); ?></h3>
                                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                                    <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($subj['faculty_name'] ?? 'Faculty Assigned'); ?>
                                </p>

                                <div style="margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                        <span style="color: var(--text-muted);">Attendance</span>
                                        <span style="font-weight: 600; color: var(--<?php echo $pctClass; ?>);"><?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="attendance-bar">
                                        <div class="attendance-fill <?php echo $pctClass; ?>" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                                    <a href="notes.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="flex: 1; justify-content: center; font-size: 12px; padding: 6px 12px;">
                                        <i class="fas fa-sticky-note"></i> Notes
                                    </a>
                                    <a href="assignments.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="flex: 1; justify-content: center; font-size: 12px; padding: 6px 12px;">
                                        <i class="fas fa-tasks"></i> Tasks
                                    </a>
                                    <a href="ai-assistant.php?subject=<?php echo urlencode($subj['name']); ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" title="Ask AI about this subject">
                                        <i class="fas fa-robot"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
