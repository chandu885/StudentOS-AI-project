<?php
// frontend/faculty/subjects.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$facRes = apiCall('/faculty.php?path=dashboard', 'GET');
$subjects = [
    ['id' => 1, 'name' => 'Database Management Systems', 'code' => 'CS301', 'credits' => 4, 'semester' => 'Semester 6', 'students' => 64, 'syllabus_pct' => 70],
    ['id' => 2, 'name' => 'Advanced Database Systems', 'code' => 'CS502', 'credits' => 3, 'semester' => 'Semester 8', 'students' => 42, 'syllabus_pct' => 55]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Subjects - StudentOS AI</title>
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
                        <h1>Assigned Teaching Subjects</h1>
                        <p class="page-subtitle">Manage curriculum, student rosters, and evaluations for your active courses</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px;">
                    <?php foreach ($subjects as $subj): ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <span class="badge badge-primary"><?php echo htmlspecialchars($subj['code']); ?></span>
                                <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($subj['semester']); ?></span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 17px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($subj['name']); ?></h3>
                                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                                    <i class="fas fa-users"></i> <?php echo $subj['students']; ?> Enrolled Students • <?php echo $subj['credits']; ?> Credits
                                </p>

                                <div style="margin-bottom: 18px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                        <span style="color: var(--text-muted);">Syllabus Covered</span>
                                        <strong><?php echo $subj['syllabus_pct']; ?>%</strong>
                                    </div>
                                    <div class="attendance-bar">
                                        <div class="attendance-fill success" style="width: <?php echo $subj['syllabus_pct']; ?>%;"></div>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                                    <a href="attendance.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="font-size: 12px; justify-content: center;">
                                        <i class="fas fa-check-square"></i> Attendance
                                    </a>
                                    <a href="assignments.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="font-size: 12px; justify-content: center;">
                                        <i class="fas fa-file-alt"></i> Assignments
                                    </a>
                                    <a href="students.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="font-size: 12px; justify-content: center;">
                                        <i class="fas fa-user-graduate"></i> Students
                                    </a>
                                    <a href="marks.php?subject_id=<?php echo $subj['id']; ?>" class="btn btn-secondary" style="font-size: 12px; justify-content: center;">
                                        <i class="fas fa-calculator"></i> Gradebook
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
