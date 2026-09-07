<?php
// frontend/admin/schedules.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Timetable slot created successfully!';
}

$slots = [
    ['day' => 'Monday', 'time' => '09:00 AM - 10:30 AM', 'subject' => 'DBMS', 'faculty' => 'Dr. Robert Smith', 'room' => 'Hall 201', 'sem' => 'Sem 6'],
    ['day' => 'Monday', 'time' => '11:00 AM - 12:30 PM', 'subject' => 'Operating Systems', 'faculty' => 'Dr. Alan Walker', 'room' => 'Room 301', 'sem' => 'Sem 6'],
    ['day' => 'Tuesday', 'time' => '09:00 AM - 10:30 AM', 'subject' => 'DSA', 'faculty' => 'Prof. Sarah Jenkins', 'room' => 'Room 204', 'sem' => 'Sem 4'],
    ['day' => 'Wednesday', 'time' => '10:00 AM - 11:30 AM', 'subject' => 'Software Engineering', 'faculty' => 'Dr. Michael Brown', 'room' => 'Room 402', 'sem' => 'Sem 6']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Schedules - StudentOS AI</title>
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
                        <h1>Master Timetable Schedules</h1>
                        <p class="page-subtitle">Coordinate institutional lecture periods, laboratories, and classroom allocations</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Allocated Time Slots</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time Slot</th>
                                        <th>Subject</th>
                                        <th>Semester</th>
                                        <th>Assigned Instructor</th>
                                        <th>Room / Hall</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slots as $s): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($s['day']); ?></strong></td>
                                            <td><span style="color: var(--primary); font-weight: 500;"><?php echo htmlspecialchars($s['time']); ?></span></td>
                                            <td><?php echo htmlspecialchars($s['subject']); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($s['sem']); ?></span></td>
                                            <td><?php echo htmlspecialchars($s['faculty']); ?></td>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($s['room']); ?></span></td>
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
