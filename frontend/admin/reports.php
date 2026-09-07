<?php
// frontend/admin/reports.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Reports - StudentOS AI</title>
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
                        <h1>Institutional Reports & Export</h1>
                        <p class="page-subtitle">Generate official transcripts, attendance records, and enrollment analytics</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="margin-bottom: 16px;"><i class="fas fa-clipboard-user"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Department Attendance Report</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Comprehensive lecture-by-lecture attendance registers with eligibility audit.</p>
                            <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="showToast('Exporting Attendance CSV...', 'info')">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </button>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1); margin-bottom: 16px;"><i class="fas fa-chart-bar"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Semester Grade Distribution</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Semester results ledger with SGPA breakdowns and pass/fail percentiles.</p>
                            <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="showToast('Exporting Grade Sheet PDF...', 'info')">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1); margin-bottom: 16px;"><i class="fas fa-id-card"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Student Master Enrollment</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Complete directory of all registered students, contact info, and department tags.</p>
                            <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="showToast('Exporting Enrollment Excel...', 'info')">
                                <i class="fas fa-file-excel"></i> Download Excel
                            </button>
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
