<?php
// frontend/admin/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$assignments = [
    ['title' => 'ER Diagram & Relational Schema', 'subject' => 'DBMS', 'dept' => 'Computer Science', 'faculty' => 'Dr. Robert Smith', 'deadline' => date('Y-m-d', strtotime('+3 days')), 'submissions' => '48/64'],
    ['title' => 'Red-Black Tree Implementation', 'subject' => 'DSA', 'dept' => 'Computer Science', 'faculty' => 'Prof. Sarah Jenkins', 'deadline' => date('Y-m-d', strtotime('+6 days')), 'submissions' => '52/64'],
    ['title' => 'Digital Signal Processing Filter Design', 'subject' => 'DSP', 'dept' => 'Electronics', 'faculty' => 'Dr. Marcus Vance', 'deadline' => date('Y-m-d', strtotime('+5 days')), 'submissions' => '30/42']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Coursework Overview - StudentOS AI</title>
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
                        <h1>Institution-Wide Coursework & Assignments</h1>
                        <p class="page-subtitle">Monitor assignment issuance, due dates, and grading turnarounds across all departments</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> Active Coursework</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Assignment Title</th>
                                        <th>Subject</th>
                                        <th>Department</th>
                                        <th>Faculty Instructor</th>
                                        <th>Submission Due</th>
                                        <th>Completion Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $asg): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($asg['title']); ?></strong></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($asg['subject']); ?></span></td>
                                            <td><?php echo htmlspecialchars($asg['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($asg['faculty']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($asg['deadline'])); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($asg['submissions']); ?></span></td>
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
