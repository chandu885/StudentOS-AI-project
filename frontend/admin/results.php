<?php
// frontend/admin/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Semester results published to student portals!';
}

$publishedResults = [
    ['sem' => 'Semester 5 (Fall 2025)', 'dept' => 'Computer Science & Engineering', 'students' => 128, 'pass_pct' => 97.6, 'avg_sgpa' => 3.74, 'status' => 'Published'],
    ['sem' => 'Semester 4 (Spring 2025)', 'dept' => 'Computer Science & Engineering', 'students' => 134, 'pass_pct' => 95.8, 'avg_sgpa' => 3.68, 'status' => 'Published'],
    ['sem' => 'Semester 6 (Spring 2026)', 'dept' => 'Computer Science & Engineering', 'students' => 128, 'pass_pct' => '—', 'avg_sgpa' => '—', 'status' => 'Draft / In Evaluation']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Results Moderation - StudentOS AI</title>
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
                        <h1>Academic Results Moderation</h1>
                        <p class="page-subtitle">Publish official semester grade reports, calculate SGPA/CGPA, and manage mark re-evaluations</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-award"></i> Semester Result Cohorts</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Cohort & Semester</th>
                                        <th>Department</th>
                                        <th>Total Students</th>
                                        <th>Pass Rate</th>
                                        <th>Average SGPA</th>
                                        <th>Publish Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($publishedResults as $res): 
                                        $isPublished = $res['status'] === 'Published';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($res['sem']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($res['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($res['students']); ?></td>
                                            <td><strong style="color: var(--success);"><?php echo $res['pass_pct']; ?><?php echo $isPublished ? '%' : ''; ?></strong></td>
                                            <td><strong><?php echo $res['avg_sgpa']; ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $isPublished ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars($res['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!$isPublished): ?>
                                                    <form method="POST" action="results.php" style="margin:0;">
                                                        <button type="submit" class="btn btn-primary" style="font-size: 11px; padding: 4px 10px;">
                                                            <i class="fas fa-check"></i> Publish
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="showToast('Result ledger downloaded', 'info')">
                                                        <i class="fas fa-download"></i> Ledger
                                                    </button>
                                                <?php endif; ?>
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
