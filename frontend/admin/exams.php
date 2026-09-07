<?php
// frontend/admin/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$exams = [
    ['title' => 'Semester 6 Midterm Examination', 'dept' => 'Computer Science', 'date' => '2026-03-15', 'sessions' => 4, 'status' => 'Approved & Published'],
    ['title' => 'Semester 4 Midterm Examination', 'dept' => 'Computer Science', 'date' => '2026-03-18', 'sessions' => 4, 'status' => 'Approved & Published'],
    ['title' => 'Semester 6 Practical Lab Assessments', 'dept' => 'Computer Science', 'date' => '2026-03-24', 'sessions' => 2, 'status' => 'Draft / Scheduling']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Schedules - StudentOS AI</title>
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
                        <h1>Institutional Examination Cell</h1>
                        <p class="page-subtitle">Examination timetables, exam centers, and invigilation coordination</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-signature"></i> Examination Date Sheets</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Examination Scheme</th>
                                        <th>Department</th>
                                        <th>Commencing Date</th>
                                        <th>Subject Sessions</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $ex): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ex['title']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($ex['dept']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($ex['date'])); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo $ex['sessions']; ?> Papers</span></td>
                                            <td><span class="badge badge-success"><?php echo htmlspecialchars($ex['status']); ?></span></td>
                                            <td>
                                                <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="showToast('Date sheet exported', 'info')">
                                                    <i class="fas fa-file-pdf"></i> Date Sheet
                                                </button>
                                            </td>
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
