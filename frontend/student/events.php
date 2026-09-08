<?php
// frontend/student/events.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$db = getDbConnection();
$events = [];
if ($db) {
    $res = $db->query("SELECT * FROM `events` ORDER BY `event_date` ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $timeStr = '';
            if (!empty($row['start_time'])) {
                $timeStr = date('h:i A', strtotime($row['start_time']));
                if (!empty($row['end_time'])) {
                    $timeStr .= ' - ' . date('h:i A', strtotime($row['end_time']));
                }
            } else {
                $timeStr = 'Full Day';
            }
            $events[] = [
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => $timeStr,
                'venue' => $row['venue'] ?? 'Campus Center',
                'category' => $row['organized_by'] ?? 'Workshop',
                'status' => 'open'
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
    <title>Campus Events - StudentOS AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
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
                        <h1>Campus Events & Workshops</h1>
                        <p class="page-subtitle">Tech conferences, hackathons, and extracurricular workshops</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                    <?php if (empty($events)): ?>
                        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 48px;">
                            <i class="fas fa-calendar-times" style="font-size: 40px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Campus Events Scheduled</h3>
                            <p style="color: var(--text-muted); font-size: 14px;">Check back soon for new campus workshops, competitions, and guest lectures.</p>
                        </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
