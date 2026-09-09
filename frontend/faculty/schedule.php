<?php
// frontend/faculty/schedule.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

$facultySchedule = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT cs.id, cs.day_of_week, cs.start_time, cs.end_time, cs.room_number, cs.section, cs.semester,
                s.name AS subject_name, s.code AS subject_code, s.type AS subject_type
         FROM class_schedules cs
         JOIN subjects s ON cs.subject_id = s.id
         WHERE cs.faculty_id = ? OR cs.subject_id IN (SELECT id FROM subjects WHERE faculty_id = ?)
         ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $facultySchedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($facultySchedule)) {
        $res = $db->query(
            "SELECT cs.id, cs.day_of_week, cs.start_time, cs.end_time, cs.room_number, cs.section, cs.semester,
                    s.name AS subject_name, s.code AS subject_code, s.type AS subject_type
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC
             LIMIT 15"
        );
        if ($res) {
            $facultySchedule = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Teaching Timetable - StudentOS AI</title>
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
                        <h1>Teaching Timetable</h1>
                        <p class="page-subtitle">Your weekly schedule of lectures, lab sessions, and tutorials</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Weekly Class Slots (<?php echo count($facultySchedule); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Class Time</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Classroom / Hall</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($facultySchedule)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-calendar-times"></i> No scheduled class slots found in the timetable.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($facultySchedule as $slot): 
                                            $timeStr = date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time']));
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($slot['day_of_week']); ?></strong></td>
                                                <td><span style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($timeStr); ?></span></td>
                                                <td>
                                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($slot['subject_name']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);">
                                                        <?php echo htmlspecialchars($slot['subject_code'] ?? ''); ?>
                                                        <?php if (!empty($slot['semester'])): ?>
                                                            &bull; Semester <?php echo htmlspecialchars($slot['semester']); ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($slot['section'])): ?>
                                                            &bull; Section <?php echo htmlspecialchars($slot['section']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><span class="badge badge-purple"><?php echo htmlspecialchars(ucfirst($slot['subject_type'] ?? 'Lecture')); ?></span></td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($slot['room_number'] ?: 'LH-101'); ?></span></td>
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
