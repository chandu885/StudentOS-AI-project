<?php
// frontend/student/schedule.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$schedulesRes = apiCall('/academic.php?path=schedules', 'GET');
$schedules = $schedulesRes['schedules'] ?? [
    ['day' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'subject_name' => 'Database Management Systems', 'room' => 'Lab 2', 'faculty_name' => 'Dr. Robert Smith'],
    ['day' => 'Monday', 'start_time' => '11:00:00', 'end_time' => '12:30:00', 'subject_name' => 'Operating Systems', 'room' => 'Room 301', 'faculty_name' => 'Dr. Alan Walker'],
    ['day' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'subject_name' => 'Data Structures & Algorithms', 'room' => 'Room 204', 'faculty_name' => 'Prof. Sarah Jenkins'],
    ['day' => 'Tuesday', 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'subject_name' => 'Computer Networks Lab', 'room' => 'Network Lab', 'faculty_name' => 'Prof. Emily Chen'],
    ['day' => 'Wednesday', 'start_time' => '10:00:00', 'end_time' => '11:30:00', 'subject_name' => 'Software Engineering', 'room' => 'Room 402', 'faculty_name' => 'Dr. Michael Brown'],
    ['day' => 'Thursday', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'subject_name' => 'Database Management Systems', 'room' => 'Room 301', 'faculty_name' => 'Dr. Robert Smith'],
    ['day' => 'Friday', 'start_time' => '11:00:00', 'end_time' => '12:30:00', 'subject_name' => 'Data Structures & Algorithms', 'room' => 'Room 204', 'faculty_name' => 'Prof. Sarah Jenkins']
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule - StudentOS AI</title>
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
