<?php
// frontend/student/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];

$semesters = [
    'Semester 5 (Fall 2025)' => [
        'sgpa' => 3.82,
        'credits' => 20,
        'courses' => [
            ['code' => 'CS501', 'name' => 'Database Management Systems', 'credits' => 4, 'marks' => 88, 'grade' => 'A', 'points' => 4.0],
            ['code' => 'CS502', 'name' => 'Operating Systems', 'credits' => 4, 'marks' => 82, 'grade' => 'A-', 'points' => 3.7],
            ['code' => 'CS503', 'name' => 'Theory of Computation', 'credits' => 3, 'marks' => 79, 'grade' => 'B+', 'points' => 3.3],
            ['code' => 'CS504', 'name' => 'Computer Networks', 'credits' => 4, 'marks' => 91, 'grade' => 'A+', 'points' => 4.0],
            ['code' => 'CS505', 'name' => 'Microprocessors Lab', 'credits' => 2, 'marks' => 94, 'grade' => 'A+', 'points' => 4.0]
        ]
    ],
    'Semester 4 (Spring 2025)' => [
        'sgpa' => 3.75,
        'credits' => 21,
        'courses' => [
            ['code' => 'CS401', 'name' => 'Design & Analysis of Algorithms', 'credits' => 4, 'marks' => 85, 'grade' => 'A', 'points' => 4.0],
            ['code' => 'CS402', 'name' => 'Discrete Mathematics', 'credits' => 4, 'marks' => 80, 'grade' => 'A-', 'points' => 3.7],
            ['code' => 'CS403', 'name' => 'Object Oriented Programming (Java)', 'credits' => 4, 'marks' => 89, 'grade' => 'A', 'points' => 4.0],
            ['code' => 'CS404', 'name' => 'Computer Architecture', 'credits' => 3, 'marks' => 76, 'grade' => 'B', 'points' => 3.0]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Results - StudentOS AI</title>
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
                        <h1>Academic Results & Transcripts</h1>
                        <p class="page-subtitle">Cumulative Grade Point Average (CGPA) and official semester scorecards</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print Transcript</button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1);"><i class="fas fa-medal"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">3.79</span>
                            <span class="stat-label">Cumulative GPA (CGPA)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">98</span>
                            <span class="stat-label">Credits Completed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1);"><i class="fas fa-trophy"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">Top 5%</span>
                            <span class="stat-label">Class Standing</span>
                        </div>
                    </div>
                </div>

                <?php foreach ($semesters as $semName => $semData): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($semName); ?></h3>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <span class="badge badge-primary">SGPA: <?php echo $semData['sgpa']; ?></span>
                                <span class="badge badge-secondary"><?php echo $semData['credits']; ?> Credits</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Credits</th>
                                            <th>Marks</th>
                                            <th>Letter Grade</th>
                                            <th>Grade Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($semData['courses'] as $crs): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($crs['code']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($crs['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($crs['credits']); ?></td>
                                                <td><?php echo htmlspecialchars($crs['marks']); ?>%</td>
                                                <td><span class="badge badge-success"><?php echo htmlspecialchars($crs['grade']); ?></span></td>
                                                <td><strong><?php echo number_format($crs['points'], 1); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
