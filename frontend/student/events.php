<?php
// frontend/student/events.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event_id'])) {
    $evId = (int)$_POST['register_event_id'];
    if ($evId > 0 && $db) {
        $regStmt = $db->prepare("INSERT IGNORE INTO event_registrations (event_id, user_id) VALUES (?, ?)");
        if ($regStmt) {
            $regStmt->bind_param("ii", $evId, $userId);
            if ($regStmt->execute()) {
                $successMsg = 'You have successfully registered for this event!';
            }
            $regStmt->close();
        }
    }
}

$events = [];
if ($db) {
    $stmt = $db->prepare("SELECT e.*, 
                   (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.user_id = ?) AS is_registered
            FROM `events` e 
            ORDER BY e.`event_date` ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
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
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => $timeStr,
                'venue' => $row['venue'] ?? 'Campus Center',
                'category' => $row['organized_by'] ?? 'Workshop',
                'is_registered' => (int)$row['is_registered'] > 0
            ];
        }
        $stmt->close();
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

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                    <?php if (empty($events)): ?>
                        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 48px;">
                            <i class="fas fa-calendar-times" style="font-size: 40px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Campus Events Scheduled</h3>
                            <p style="color: var(--text-muted); font-size: 14px;">Check back soon for new campus workshops, competitions, and guest lectures.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
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
                                    <?php if ($ev['is_registered']): ?>
                                        <button class="btn btn-secondary" style="width: 100%; justify-content: center;" disabled>
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i> Registered
                                        </button>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="register_event_id" value="<?php echo (int)$ev['id']; ?>">
                                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                                <i class="fas fa-ticket-alt"></i> Register Now
                                            </button>
                                        </form>
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
