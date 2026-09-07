<?php
// frontend/student/performance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Academic Performance Analytics</h1>
                        <p class="page-subtitle">AI-assisted insights, grade projections, and mastery metrics</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">3.79</span>
                            <span class="stat-label">Projected CGPA</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-double"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">94.2%</span>
                            <span class="stat-label">Assignment Completion</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-brain"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">Top 10%</span>
                            <span class="stat-label">Algorithmic Mastery</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-area"></i> Semester-wise SGPA Progression</h3>
                        </div>
                        <div class="card-body" style="height: 320px;">
                            <canvas id="gpaChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Subject Scores vs Class Average</h3>
                        </div>
                        <div class="card-body" style="height: 320px;">
                            <canvas id="scoresChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-lightbulb" style="color: var(--warning);"></i> AI Performance Insights</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px;">
                                <h4 style="color: var(--success); font-size: 14px; margin-bottom: 6px;"><i class="fas fa-arrow-trend-up"></i> Academic Strengths</h4>
                                <p style="font-size: 13px; color: var(--text-secondary);">Strong conceptual understanding demonstrated in Software Engineering (95%) and Database Management Systems (88%).</p>
                            </div>
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px;">
                                <h4 style="color: var(--warning); font-size: 14px; margin-bottom: 6px;"><i class="fas fa-exclamation-triangle"></i> Focus Area</h4>
                                <p style="font-size: 13px; color: var(--text-secondary);">Operating Systems attendance is at 73.1%. Spend 30 minutes extra on Process Synchronization this week.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/charts.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // GPA Progression Line Chart
        ChartHelper.renderLine('gpaChart', ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'], [3.65, 3.70, 3.74, 3.75, 3.82], 'SGPA');

        // Subject Scores Bar Chart
        ChartHelper.renderBar('scoresChart', ['DBMS', 'DSA', 'OS', 'Networks', 'SE'], [88, 92, 79, 85, 95], 'Score (%)');
    });
    </script>
</body>
</html>
