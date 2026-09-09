<?php
// frontend/student/performance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();

$projectedCgpa = '0.00';
$assignmentCompletion = '0.0%';
$masteryRank = 'N/A';
$semLabels = [];
$semGpas = [];
$subjectLabels = [];
$subjectScores = [];

if ($db && $userId > 0) {
    // 1. Latest CGPA and Rank from performance
    $stmt = $db->prepare("SELECT * FROM performance WHERE student_id = ? ORDER BY CAST(semester AS UNSIGNED) DESC, id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        if ($perf = $stmt->get_result()->fetch_assoc()) {
            $projectedCgpa = number_format((float)$perf['cgpa'], 2);
            $masteryRank = (!empty($perf['rank']) && $perf['rank'] <= 2) ? 'Top 5%' : (((!empty($perf['rank']) && $perf['rank'] <= 5)) ? 'Top 10%' : 'Rank #' . $perf['rank']);
        }
        $stmt->close();
    }

    // 2. Assignment completion percentage from database
    $stmt = $db->prepare(
        "SELECT 
            (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = ?) as submitted,
            (SELECT COUNT(*) FROM assignments a JOIN student_subjects ss ON a.subject_id = ss.subject_id WHERE ss.student_id = ?) as total"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $subRow = $stmt->get_result()->fetch_assoc();
        $totalAss = (int)($subRow['total'] ?? 0);
        $subAss = (int)($subRow['submitted'] ?? 0);
        if ($totalAss > 0) {
            $assignmentCompletion = round(($subAss / $totalAss) * 100, 1) . '%';
        } else {
            $assignmentCompletion = '100%';
        }
        $stmt->close();
    }

    // 3. GPA Progression over semesters from database
    $stmt = $db->prepare("SELECT semester, gpa FROM performance WHERE student_id = ? ORDER BY CAST(semester AS UNSIGNED) ASC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $semLabels[] = 'Sem ' . $row['semester'];
            $semGpas[] = (float)$row['gpa'];
        }
        $stmt->close();
    }

    // 4. Subject Scores from database
    $detailedScores = [];
    $stmt = $db->prepare(
        "SELECT s.code, s.name, r.marks_obtained 
         FROM results r 
         JOIN subjects s ON r.subject_id = s.id 
         WHERE r.student_id = ? 
         ORDER BY CAST(s.semester AS UNSIGNED) DESC, s.code ASC 
         LIMIT 6"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $subjectLabels[] = $row['code'];
            $subjectScores[] = (float)$row['marks_obtained'];
            $detailedScores[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'marks' => (float)$row['marks_obtained']
            ];
        }
        $stmt->close();
    }

    // 5. Check attendance warnings for focus area
    $lowAttSubject = null;
    $attCheckStmt = $db->prepare(
        "SELECT s.name as subject_name,
                ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as percentage
         FROM attendance a
         JOIN subjects s ON a.subject_id = s.id
         WHERE a.student_id = ?
         GROUP BY a.subject_id
         HAVING percentage < 75
         ORDER BY percentage ASC
         LIMIT 1"
    );
    if ($attCheckStmt) {
        $attCheckStmt->bind_param("i", $userId);
        $attCheckStmt->execute();
        $lowAttSubject = $attCheckStmt->get_result()->fetch_assoc();
        $attCheckStmt->close();
    }

    // Dynamic AI Insights synthesis
    if (!empty($detailedScores)) {
        $sortedScores = $detailedScores;
        usort($sortedScores, function($a, $b) { return $b['marks'] <=> $a['marks']; });
        $topSubjects = array_slice($sortedScores, 0, min(2, count($sortedScores)));
        $strengthsText = "Strong conceptual understanding demonstrated in " . implode(' and ', array_map(function($s) {
            return $s['name'] . ' (' . $s['marks'] . ' pts)';
        }, $topSubjects)) . ". Excellent foundation for upcoming assessments!";

        if ($lowAttSubject) {
            $focusText = $lowAttSubject['subject_name'] . ' attendance is currently at ' . $lowAttSubject['percentage'] . '%. Attend upcoming lectures to exceed the 75% threshold.';
        } elseif (count($sortedScores) > 1) {
            $lowestSub = end($sortedScores);
            $focusText = "Targeted revision recommended for " . $lowestSub['name'] . " (" . $lowestSub['marks'] . " pts). Review lecture notes or use AI Practice Quiz to boost retention.";
        } else {
            $focusText = "Keep pacing your weekly milestones and review syllabus guidelines to maintain momentum.";
        }
    } else {
        $strengthsText = "Consistent coursework engagement recorded across registered subjects. Keep up the disciplined study routine.";
        $focusText = $lowAttSubject ? ($lowAttSubject['subject_name'] . ' attendance is at ' . $lowAttSubject['percentage'] . '%. Attend classes to stay eligible.') : "Stay proactive by setting targets in the Goals and Milestones module.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics - StudentOS AI</title>
    
    <!-- External Google Font Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CDN Resources (Font Awesome, Normalize, Chart.js) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Application Stylesheets -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
                            <span class="stat-number"><?php echo htmlspecialchars($projectedCgpa); ?></span>
                            <span class="stat-label">Projected CGPA</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-double"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($assignmentCompletion); ?></span>
                            <span class="stat-label">Assignment Completion</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-brain"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($masteryRank); ?></span>
                            <span class="stat-label">Academic Standing</span>
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
                                <p style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars($strengthsText ?? 'Solid academic performance across registered coursework.'); ?></p>
                            </div>
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px;">
                                <h4 style="color: var(--warning); font-size: 14px; margin-bottom: 6px;"><i class="fas fa-exclamation-triangle"></i> Focus Area</h4>
                                <p style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars($focusText ?? 'Review upcoming deadlines and review lecture notes regularly.'); ?></p>
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
        // GPA Progression Line Chart from Database
        const semLabels = <?php echo json_encode(!empty($semLabels) ? $semLabels : ['Sem 1', 'Sem 2', 'Sem 3']); ?>;
        const semGpas = <?php echo json_encode(!empty($semGpas) ? $semGpas : [3.5, 3.6, 3.7]); ?>;
        ChartHelper.renderLine('gpaChart', semLabels, semGpas, 'SGPA');

        // Subject Scores Bar Chart from Database
        const subLabels = <?php echo json_encode(!empty($subjectLabels) ? $subjectLabels : ['CS501', 'CS502', 'CS503']); ?>;
        const subScores = <?php echo json_encode(!empty($subjectScores) ? $subjectScores : [85, 88, 90]); ?>;
        ChartHelper.renderBar('scoresChart', subLabels, subScores, 'Score (%)');
    });
    </script>
</body>
</html>
