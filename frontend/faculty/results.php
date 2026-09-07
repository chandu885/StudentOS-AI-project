<?php
// frontend/faculty/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Results - StudentOS AI</title>
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
                        <h1>Exam Results & Performance Overview</h1>
                        <p class="page-subtitle">Evaluation summaries, pass percentages, and grade distributions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-file-export"></i> Export Report</button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-percent"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">96.8%</span>
                            <span class="stat-label">Overall Pass Rate</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">41.8 / 50</span>
                            <span class="stat-label">Class Average</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-star"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">49 / 50</span>
                            <span class="stat-label">Highest Score</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-poll"></i> Midterm Exam Results: Database Management Systems</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-secondary">CS-2023-01</span></td>
                                        <td><strong>Alex Morgan</strong></td>
                                        <td>46 / 50</td>
                                        <td>92%</td>
                                        <td><span class="badge badge-success">A+</span></td>
                                        <td><span class="badge badge-success">Pass</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-secondary">CS-2023-02</span></td>
                                        <td><strong>Brian Clark</strong></td>
                                        <td>38 / 50</td>
                                        <td>76%</td>
                                        <td><span class="badge badge-primary">B+</span></td>
                                        <td><span class="badge badge-success">Pass</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-secondary">CS-2023-03</span></td>
                                        <td><strong>Catherine Davis</strong></td>
                                        <td>44 / 50</td>
                                        <td>88%</td>
                                        <td><span class="badge badge-success">A</span></td>
                                        <td><span class="badge badge-success">Pass</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-secondary">CS-2023-04</span></td>
                                        <td><strong>Daniel Evans</strong></td>
                                        <td>49 / 50</td>
                                        <td>98%</td>
                                        <td><span class="badge badge-success">A+</span></td>
                                        <td><span class="badge badge-success">Pass</span></td>
                                    </tr>
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
