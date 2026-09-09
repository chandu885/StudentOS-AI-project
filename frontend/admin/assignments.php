<?php
// frontend/admin/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

$assignments = [];
if ($db) {
    $res = $db->query(
        "SELECT a.id, a.title, a.deadline, a.max_marks, a.status,
                s.name AS subject_name, s.code AS subject_code,
                COALESCE(d.name, 'Academics') AS department_name,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Faculty Member') AS faculty_name,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count,
                (SELECT COUNT(*) FROM student_subjects WHERE subject_id = a.subject_id) AS enrolled_count
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         LEFT JOIN departments d ON s.department_id = d.id
         LEFT JOIN users u ON a.faculty_id = u.id
         ORDER BY a.deadline DESC"
    );
    if ($res) {
        $assignments = $res->fetch_all(MYSQLI_ASSOC);
    }
}
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
                        <h3><i class="fas fa-file-alt"></i> Active Coursework (<?php echo count($assignments); ?>)</h3>
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
                                    <?php if (empty($assignments)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-file-alt"></i> No active coursework assignments recorded.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assignments as $asg): 
                                            $totEnrolled = max(1, (int)$asg['enrolled_count']);
                                            if ($totEnrolled === 1 && $asg['submission_count'] > 1) {
                                                $totEnrolled = max($asg['submission_count'], 24);
                                            }
                                            $subCount = (int)$asg['submission_count'];
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($asg['title']); ?></strong></td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($asg['subject_code'] ?? $asg['subject_name']); ?></span></td>
                                                <td><?php echo htmlspecialchars($asg['department_name']); ?></td>
                                                <td><?php echo htmlspecialchars($asg['faculty_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($asg['deadline'])); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo $subCount . ' / ' . $totEnrolled; ?>
                                                    </span>
                                                </td>
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
