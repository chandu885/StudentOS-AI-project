<?php
// frontend/student/schedule.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$schedules = [];

if ($db && $userId > 0) {
    $stmt = $db->prepare(
        "SELECT cs.day_of_week AS day, cs.start_time, cs.end_time, cs.room_number AS room,
                s.name AS subject_name, s.code AS subject_code,
                CONCAT(u.first_name, ' ', u.last_name) AS faculty_name
         FROM class_schedules cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         LEFT JOIN users u ON cs.faculty_id = u.id
         WHERE ss.student_id = ?
         ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<?php
$pageTitle = 'Class Schedule - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header">
                    <div>
                        <h1>Weekly Class Timetable</h1>
                        <p class="page-subtitle">Your weekly lecture schedule and classroom locations</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($days as $day): 
                        $dayClasses = array_filter($schedules, function($c) use ($day) {
                            $classDay = $c['day_of_week'] ?? $c['day'] ?? '';
                            return strcasecmp($classDay, $day) === 0;
                        });
                    ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header" style="background: var(--bg-input);">
                                <h3><i class="fas fa-calendar-day" style="color: var(--primary);"></i> <?php echo htmlspecialchars($day); ?></h3>
                                <span class="badge badge-secondary"><?php echo count($dayClasses); ?> Classes</span>
                            </div>
                            <div class="card-body" style="padding: 12px 20px;">
                                <?php if (!empty($dayClasses)): ?>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;">
                                        <?php foreach ($dayClasses as $cls): 
                                            $faculty = !empty($cls['faculty_name']) ? $cls['faculty_name'] : trim(($cls['faculty_first'] ?? '') . ' ' . ($cls['faculty_last'] ?? ''));
                                            if (empty($faculty)) $faculty = 'Instructor';
                                            $room = $cls['room_number'] ?? $cls['room'] ?? 'TBD';
                                        ?>
                                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px; border-left: 4px solid var(--primary);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                    <span style="font-size: 11px; font-weight: 700; color: var(--primary);">
                                                        <?php echo date('h:i A', strtotime($cls['start_time'])); ?> - <?php echo date('h:i A', strtotime($cls['end_time'])); ?>
                                                    </span>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($room); ?></span>
                                                </div>
                                                <h4 style="font-size: 14px; color: var(--text-primary); margin-bottom: 4px;"><?php echo htmlspecialchars($cls['subject_name']); ?></h4>
                                                <p style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-user"></i> <?php echo htmlspecialchars($faculty); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size: 13px; color: var(--text-muted); padding: 8px 0;">No lectures scheduled for this day.</p>
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
