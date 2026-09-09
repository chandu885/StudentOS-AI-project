<?php
// frontend/student/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$exams = [];

if ($db && $userId > 0) {
    $stmt = $db->prepare(
        "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                TIMESTAMPDIFF(MINUTE, e.start_time, e.end_time) AS duration_minutes,
                e.room_number AS room
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         WHERE ss.student_id = ?
         ORDER BY e.exam_date ASC, e.start_time ASC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examinations - StudentOS AI</title>
    
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
                        <h1>Examination Schedule</h1>
                        <p class="page-subtitle">Upcoming midterms, finals, and practical assessments</p>
                    </div>
                    <div class="header-actions">
                        <a href="results.php" class="btn btn-outline"><i class="fas fa-chart-line"></i> View Past Results</a>
                        <a href="ai-planner.php" class="btn btn-primary"><i class="fas fa-robot"></i> AI Study Plan</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-pencil-alt"></i> Upcoming Exams</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Subject & Exam Title</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Hall / Room</th>
                                        <th>Max Marks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exams)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-calendar-check" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                                <strong style="color: var(--text-primary);">No Upcoming Examinations</strong>
                                                <p style="font-size: 13px; margin-top: 4px;">Midterms and final assessment schedules will be published here.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($exams as $ex): ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($ex['subject_name']); ?></strong>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($ex['title']); ?></div>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 500; color: var(--primary);">
                                                        <?php echo date('M d, Y', strtotime($ex['exam_date'])); ?>
                                                    </span>
                                                    <div style="font-size: 12px; color: var(--text-muted);">
                                                        <?php echo !empty($ex['start_time']) ? date('h:i A', strtotime($ex['start_time'])) : '10:00 AM'; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($ex['duration_minutes'] ?? 90); ?> mins</td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($ex['room'] ?? 'Hall A'); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($ex['total_marks'] ?? 100); ?></strong> pts</td>
                                                <td>
                                                    <a href="ai-quiz.php?subject=<?php echo urlencode($ex['subject_name']); ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
                                                        <i class="fas fa-magic"></i> Practice Quiz
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
