<?php
// frontend/student/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$examsRes = apiCall('/exams.php', 'GET');
$exams = $examsRes['exams'] ?? [
    ['id' => 1, 'title' => 'Midterm Examination 2026', 'subject_name' => 'Database Management Systems', 'exam_date' => date('Y-m-d 10:00:00', strtotime('+7 days')), 'duration_minutes' => 90, 'total_marks' => 50, 'room' => 'Hall A', 'status' => 'scheduled'],
    ['id' => 2, 'title' => 'Midterm Examination 2026', 'subject_name' => 'Data Structures & Algorithms', 'exam_date' => date('Y-m-d 10:00:00', strtotime('+9 days')), 'duration_minutes' => 120, 'total_marks' => 60, 'room' => 'Hall B', 'status' => 'scheduled'],
    ['id' => 3, 'title' => 'Midterm Examination 2026', 'subject_name' => 'Operating Systems', 'exam_date' => date('Y-m-d 14:00:00', strtotime('+12 days')), 'duration_minutes' => 90, 'total_marks' => 50, 'room' => 'Hall A', 'status' => 'scheduled'],
    ['id' => 4, 'title' => 'Practical Exam', 'subject_name' => 'Computer Networks Lab', 'exam_date' => date('Y-m-d 11:00:00', strtotime('+15 days')), 'duration_minutes' => 180, 'total_marks' => 50, 'room' => 'Lab 3', 'status' => 'scheduled']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examinations - StudentOS AI</title>
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
                                                    <?php echo date('h:i A', strtotime($ex['exam_date'])); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($ex['duration_minutes']); ?> mins</td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($ex['room'] ?? 'Hall A'); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($ex['total_marks']); ?></strong> pts</td>
                                            <td>
                                                <a href="ai-quiz.php?subject=<?php echo urlencode($ex['subject_name']); ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
                                                    <i class="fas fa-magic"></i> Practice Quiz
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
