<?php
// frontend/student/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$notices = [
    ['title' => 'Final Exam Fee Submission Deadline Extended', 'category' => 'Examination Cell', 'date' => '2026-03-05', 'priority' => 'urgent', 'content' => 'The deadline for regular and backlog exam registration for Semester 6 has been extended until March 20, 2026 without late fee.'],
    ['title' => 'Annual Tech Fest "InnoVision 2026" Registrations Open', 'category' => 'Campus Activities', 'date' => '2026-03-02', 'priority' => 'general', 'content' => 'Registrations for competitive coding, hackathon, and robotics tracks are now open. Visit the student council desk.'],
    ['title' => 'Library Extended Hours during Midterms', 'category' => 'Library Advisory', 'date' => '2026-02-28', 'priority' => 'general', 'content' => 'The central university library will remain open 24/7 starting from March 10 through the end of the midterm examinations.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices & Circulars - StudentOS AI</title>
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
                        <h1>Official Notices & Circulars</h1>
                        <p class="page-subtitle">College notifications, administrative updates, and department circulars</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($notices as $not): 
                        $isUrgent = ($not['priority'] ?? '') === 'urgent';
                    ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="badge <?php echo $isUrgent ? 'badge-danger' : 'badge-primary'; ?>">
                                        <?php echo $isUrgent ? 'Urgent Notice' : 'Circular'; ?>
                                    </span>
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($not['category']); ?></span>
                                </div>
                                <span style="font-size: 12px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($not['date'])); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 16px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($not['title']); ?></h3>
                                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($not['content'])); ?>
                                </p>
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
