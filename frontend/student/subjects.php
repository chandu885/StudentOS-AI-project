<?php
// frontend/student/subjects.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$subjectsRes = apiCall('/students.php', 'GET');
$subjects = $subjectsRes['subjects'] ?? [
    ['id' => 1, 'name' => 'Database Management Systems', 'code' => 'CS301', 'credits' => 4, 'faculty_name' => 'Dr. Robert Smith', 'attendance_pct' => 88],
    ['id' => 2, 'name' => 'Data Structures & Algorithms', 'code' => 'CS302', 'credits' => 4, 'faculty_name' => 'Prof. Sarah Jenkins', 'attendance_pct' => 92],
    ['id' => 3, 'name' => 'Operating Systems', 'code' => 'CS303', 'credits' => 3, 'faculty_name' => 'Dr. Alan Walker', 'attendance_pct' => 74],
    ['id' => 4, 'name' => 'Computer Networks', 'code' => 'CS304', 'credits' => 3, 'faculty_name' => 'Prof. Emily Chen', 'attendance_pct' => 81],
    ['id' => 5, 'name' => 'Software Engineering', 'code' => 'CS305', 'credits' => 3, 'faculty_name' => 'Dr. Michael Brown', 'attendance_pct' => 95]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects - StudentOS AI</title>
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
