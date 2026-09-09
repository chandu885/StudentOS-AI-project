<?php
// frontend/student/calendar.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$events = [];

if ($db && $userId > 0) {
    // 1. calendar_events for user or college-wide
    $stmt = $db->prepare("SELECT title, start_time, event_type, location FROM `calendar_events` WHERE user_id = ? OR user_id = 0 ORDER BY `start_time` ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $type = $row['event_type'] ?? 'event';
            $color = 'primary';
            if ($type === 'exam') $color = 'danger';
            elseif ($type === 'deadline' || $type === 'assignment') $color = 'warning';
            elseif ($type === 'event') $color = 'purple';
            elseif ($type === 'class') $color = 'info';

            $events[] = [
                'title' => $row['title'],
                'date' => date('Y-m-d', strtotime($row['start_time'])),
                'type' => $type,
                'color' => $color,
                'location' => $row['location'] ?? ''
            ];
        }
        $stmt->close();
    }

    // 2. Upcoming exams for enrolled subjects
    $exStmt = $db->prepare(
        "SELECT e.title, e.exam_date, e.room_number, s.name as subject_name 
         FROM `exams` e 
         JOIN `subjects` s ON e.subject_id = s.id 
         JOIN `student_subjects` ss ON ss.subject_id = s.id 
         WHERE ss.student_id = ? AND e.exam_date >= CURDATE() 
         ORDER BY e.exam_date ASC LIMIT 6"
    );
    if ($exStmt) {
        $exStmt->bind_param("i", $userId);
        $exStmt->execute();
        $exRes = $exStmt->get_result();
        while ($er = $exRes->fetch_assoc()) {
            $events[] = [
                'title' => $er['subject_name'] . ' Exam: ' . $er['title'],
                'date' => $er['exam_date'],
                'type' => 'exam',
                'color' => 'danger',
                'location' => !empty($er['room_number']) ? 'Room ' . $er['room_number'] : 'Examination Hall'
            ];
        }
        $exStmt->close();
    }

    // 3. Assignment deadlines for enrolled subjects
    $asgStmt = $db->prepare(
        "SELECT a.title, a.deadline, s.name as subject_name 
         FROM assignments a 
         JOIN subjects s ON a.subject_id = s.id 
         JOIN student_subjects ss ON ss.subject_id = s.id 
         WHERE ss.student_id = ? AND a.deadline >= NOW() 
         ORDER BY a.deadline ASC LIMIT 6"
    );
    if ($asgStmt) {
        $asgStmt->bind_param("i", $userId);
        $asgStmt->execute();
        $asgRes = $asgStmt->get_result();
        while ($ar = $asgRes->fetch_assoc()) {
            $events[] = [
                'title' => $ar['subject_name'] . ' Due: ' . $ar['title'],
                'date' => date('Y-m-d', strtotime($ar['deadline'])),
                'type' => 'deadline',
                'color' => 'warning',
                'location' => 'Online Portal Submission'
            ];
        }
        $asgStmt->close();
    }

    // 4. Campus events & workshops
    $evStmt = $db->prepare(
        "SELECT e.title, e.event_date, e.venue, e.organized_by 
         FROM events e 
         WHERE e.event_date >= CURDATE() 
         ORDER BY e.event_date ASC LIMIT 5"
    );
    if ($evStmt) {
        $evStmt->execute();
        $evRes = $evStmt->get_result();
        while ($evr = $evRes->fetch_assoc()) {
            $events[] = [
                'title' => $evr['title'] . (!empty($evr['organized_by']) ? ' (' . $evr['organized_by'] . ')' : ''),
                'date' => $evr['event_date'],
                'type' => 'event',
                'color' => 'purple',
                'location' => $evr['venue'] ?? 'Campus Grounds'
            ];
        }
        $evStmt->close();
    }

    // Sort all events by date ASC
    usort($events, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar - StudentOS AI</title>
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
                            <?php if (empty($events)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-check" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                                    <strong style="color: var(--text-primary);">No scheduled events or deadlines</strong>
                                    <p style="font-size: 13px; margin-top: 4px;">Your schedule is clear! New exams and assignment due dates will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 14px;">
                                    <?php foreach ($events as $ev): 
                                        $diffDays = round((strtotime($ev['date']) - time()) / 86400);
                                        $dayText = $diffDays > 0 ? "In {$diffDays} days" : ($diffDays == 0 ? "Today" : abs($diffDays) . " days ago");
                                    ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-primary); border-left: 4px solid var(--<?php echo $ev['color']; ?>); border-radius: var(--radius-md); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                                            <div>
                                                <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars($ev['title']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                    <i class="fas fa-tag"></i> <?php echo ucfirst($ev['type']); ?>
                                                    <?php if (!empty($ev['location'])): ?>
                                                        <span style="margin-left: 10px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">
                                                    <?php echo date('l, M d, Y', strtotime($ev['date'])); ?>
                                                </span>
                                                <div style="font-size: 11px; color: var(--text-muted);">
                                                    <?php echo $dayText; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
