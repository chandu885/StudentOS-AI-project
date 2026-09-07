<?php
// frontend/student/events.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$events = [
    ['title' => 'Generative AI & LLM Systems Workshop', 'date' => '2026-03-18', 'time' => '10:00 AM - 02:00 PM', 'venue' => 'Auditorium Hall 1', 'category' => 'Workshop', 'status' => 'registered'],
    ['title' => 'Annual 24-Hour Hackathon 2026', 'date' => '2026-03-25', 'time' => 'Starts 09:00 AM', 'venue' => 'Innovation Hub & Labs', 'category' => 'Competition', 'status' => 'open'],
    ['title' => 'Guest Lecture: Cloud Infrastructure at Scale', 'date' => '2026-04-02', 'time' => '03:00 PM - 05:00 PM', 'venue' => 'Seminar Hall C', 'category' => 'Seminar', 'status' => 'open']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Events - StudentOS AI</title>
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
                        <h1>Campus Events & Workshops</h1>
                        <p class="page-subtitle">Tech conferences, hackathons, and extracurricular workshops</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                    <?php foreach ($events as $ev): 
                        $isReg = ($ev['status'] ?? '') === 'registered';
                    ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <span class="badge badge-purple"><?php echo htmlspecialchars($ev['category']); ?></span>
                                <span style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($ev['date'])); ?></span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 16px; margin-bottom: 12px; color: var(--text-primary);"><?php echo htmlspecialchars($ev['title']); ?></h3>
                                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
                                    <div><i class="fas fa-clock" style="color: var(--primary); width: 18px;"></i> <?php echo htmlspecialchars($ev['time']); ?></div>
                                    <div><i class="fas fa-map-marker-alt" style="color: var(--danger); width: 18px;"></i> <?php echo htmlspecialchars($ev['venue']); ?></div>
                                </div>
                                <?php if ($isReg): ?>
                                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;" disabled>
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Registered
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="showToast('Registered for event successfully!', 'success'); this.disabled=true; this.textContent='Registered';">
                                        <i class="fas fa-ticket-alt"></i> Register Now
                                    </button>
                                <?php endif; ?>
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
