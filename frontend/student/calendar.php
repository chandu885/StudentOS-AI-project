<?php
// frontend/student/calendar.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$events = [
    ['title' => 'DBMS Midterm Exam', 'date' => date('Y-m-d', strtotime('+7 days')), 'type' => 'exam', 'color' => 'danger'],
    ['title' => 'DSA Red-Black Tree Due', 'date' => date('Y-m-d', strtotime('+6 days')), 'type' => 'assignment', 'color' => 'warning'],
    ['title' => 'Hackathon Workshop', 'date' => date('Y-m-d', strtotime('+10 days')), 'type' => 'event', 'color' => 'info'],
    ['title' => 'Operating Systems Midterm', 'date' => date('Y-m-d', strtotime('+12 days')), 'type' => 'exam', 'color' => 'danger']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar - StudentOS AI</title>
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
                        <h1>Academic Calendar</h1>
                        <p class="page-subtitle">Synchronized deadlines, scheduled exams, and campus events</p>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-alt"></i> Upcoming Deadlines & Events</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php foreach ($events as $ev): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-primary); border-left: 4px solid var(--<?php echo $ev['color']; ?>); border-radius: var(--radius-md); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                                        <div>
                                            <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars($ev['title']); ?></strong>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                <i class="fas fa-tag"></i> <?php echo ucfirst($ev['type']); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">
                                                <?php echo date('l, M d, Y', strtotime($ev['date'])); ?>
                                            </span>
                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                In <?php echo round((strtotime($ev['date']) - time()) / 86400); ?> days
                                            </div>
                                        </div>
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
