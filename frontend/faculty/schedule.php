<?php
// frontend/faculty/schedule.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$facultySchedule = [
    ['day' => 'Monday', 'time' => '09:00 AM - 10:30 AM', 'subject' => 'Database Management Systems', 'room' => 'Hall 201', 'type' => 'Lecture'],
    ['day' => 'Monday', 'time' => '02:00 PM - 04:00 PM', 'subject' => 'DBMS Practical Lab', 'room' => 'Lab 3', 'type' => 'Lab Session'],
    ['day' => 'Wednesday', 'time' => '11:00 AM - 12:30 PM', 'subject' => 'Advanced Database Systems', 'room' => 'Room 405', 'type' => 'Lecture'],
    ['day' => 'Thursday', 'time' => '09:00 AM - 10:30 AM', 'subject' => 'Database Management Systems', 'room' => 'Hall 201', 'type' => 'Lecture'],
    ['day' => 'Friday', 'time' => '10:00 AM - 11:30 AM', 'subject' => 'Advanced Database Systems', 'room' => 'Room 405', 'type' => 'Lecture']
];
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
                        <h3><i class="fas fa-calendar-alt"></i> Weekly Class Slots</h3>
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
                                    <?php foreach ($facultySchedule as $slot): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($slot['day']); ?></strong></td>
                                            <td><span style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($slot['time']); ?></span></td>
                                            <td><?php echo htmlspecialchars($slot['subject']); ?></td>
                                            <td><span class="badge badge-purple"><?php echo htmlspecialchars($slot['type']); ?></span></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($slot['room']); ?></span></td>
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
