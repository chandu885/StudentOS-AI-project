<?php
// frontend/faculty/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();

// Load exams list
$examsList = [];
if ($db) {
    $res = $db->query(
        "SELECT e.id, e.title, e.total_marks, e.passing_marks, s.name AS subject_name, s.code AS subject_code,
                (SELECT COUNT(*) FROM results WHERE exam_id = e.id) AS results_count
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         ORDER BY results_count DESC, e.exam_date DESC LIMIT 30"
    );
    if ($res) {
        $examsList = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$selectedExamId = (int)($_GET['exam_id'] ?? ($examsList[0]['id'] ?? 0));
$currentExam = null;
foreach ($examsList as $ex) {
    if ($ex['id'] == $selectedExamId) {
        $currentExam = $ex;
        break;
    }
}
if (!$currentExam && !empty($examsList)) {
    $currentExam = $examsList[0];
    $selectedExamId = (int)$currentExam['id'];
}

$results = [];
$stats = [
    'pass_rate' => '0%',
    'avg_score' => '0 / ' . ($currentExam['total_marks'] ?? 100),
    'highest_score' => '0 / ' . ($currentExam['total_marks'] ?? 100),
    'total_students' => 0
];

if ($db && $selectedExamId > 0) {
    $stmt = $db->prepare(
        "SELECT r.id, r.marks_obtained, r.total_marks, r.grade, r.remarks,
                u.id AS student_id, CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                e.title AS exam_title, e.total_marks AS exam_total, e.passing_marks,
                s.name AS subject_name, s.code AS subject_code
         FROM results r
         JOIN users u ON r.student_id = u.id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         JOIN exams e ON r.exam_id = e.id
         JOIN subjects s ON r.subject_id = s.id
         WHERE r.exam_id = ?
         ORDER BY r.marks_obtained DESC, u.first_name ASC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $selectedExamId);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if (!empty($results)) {
        $totalStudents = count($results);
        $examTotal = (float)($currentExam['total_marks'] ?? 100);
        $passingMarks = (float)($currentExam['passing_marks'] ?? ($examTotal * 0.4));
        
        $passedCount = 0;
        $totalMarksObtained = 0;
        $highest = 0;

        foreach ($results as $r) {
            $obtained = (float)$r['marks_obtained'];
            $totalMarksObtained += $obtained;
            if ($obtained > $highest) $highest = $obtained;
            if ($obtained >= $passingMarks) $passedCount++;
        }

        $stats['total_students'] = $totalStudents;
        $stats['pass_rate'] = round(($passedCount / $totalStudents) * 100, 1) . '%';
        $stats['avg_score'] = round($totalMarksObtained / $totalStudents, 1) . ' / ' . $examTotal;
        $stats['highest_score'] = $highest . ' / ' . $examTotal;
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Exam_Results_' . $selectedExamId . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Roll Number', 'Student Name', 'Marks Obtained', 'Total Marks', 'Percentage', 'Grade', 'Remarks']);
    foreach ($results as $row) {
        $pct = $row['total_marks'] > 0 ? round(($row['marks_obtained'] / $row['total_marks']) * 100, 1) . '%' : '0%';
        fputcsv($out, [
            $row['roll'],
            $row['student_name'],
            $row['marks_obtained'],
            $row['total_marks'],
            $pct,
            $row['grade'],
            $row['remarks']
        ]);
    }
    fclose($out);
    exit;
}
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
                        <a href="results.php?exam_id=<?php echo (int)$selectedExamId; ?>&export=csv" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <div style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                            <div class="form-group" style="margin-bottom: 0; min-width: 300px; flex: 1;">
                                <label for="filterExam">Select Assessment / Exam</label>
                                <select id="filterExam" class="form-control" onchange="window.location.href='results.php?exam_id='+this.value;">
                                    <?php foreach ($examsList as $ex): ?>
                                        <option value="<?php echo (int)$ex['id']; ?>" <?php echo $ex['id'] == $selectedExamId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ex['title'] . ' (' . ($ex['subject_name'] ?? '') . ' - ' . ($ex['results_count'] ?? 0) . ' evaluated)'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <a href="marks.php?exam_id=<?php echo (int)$selectedExamId; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i> Edit / Enter Marks
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-percent"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($stats['pass_rate']); ?></span>
                            <span class="stat-label">Overall Pass Rate</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($stats['avg_score']); ?></span>
                            <span class="stat-label">Class Average</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-star"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($stats['highest_score']); ?></span>
                            <span class="stat-label">Highest Score</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-poll"></i> Results: <?php echo htmlspecialchars(($currentExam['title'] ?? 'Examination') . ' (' . ($currentExam['subject_name'] ?? '') . ')'); ?> (<?php echo count($results); ?> students)</h3>
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
                                    <?php if (empty($results)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                                <i class="fas fa-info-circle"></i> No results published yet for this examination. <a href="marks.php?exam_id=<?php echo (int)$selectedExamId; ?>" style="color: var(--primary); font-weight: 500;">Enter Marks here</a>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($results as $res): 
                                            $pct = $res['total_marks'] > 0 ? round(($res['marks_obtained'] / $res['total_marks']) * 100, 1) : 0;
                                            $isPassed = $res['marks_obtained'] >= ($currentExam['passing_marks'] ?? ($res['total_marks'] * 0.4));
                                            $g = $res['grade'] ?? '';
                                            $gClass = 'badge-secondary';
                                            if (in_array($g, ['A+', 'A'])) $gClass = 'badge-success';
                                            elseif (in_array($g, ['B+', 'B'])) $gClass = 'badge-primary';
                                            elseif (in_array($g, ['C', 'D'])) $gClass = 'badge-warning';
                                            elseif ($g === 'F') $gClass = 'badge-danger';
                                        ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($res['roll']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($res['student_name']); ?></strong></td>
                                                <td><?php echo (float)$res['marks_obtained']; ?> / <?php echo (float)$res['total_marks']; ?></td>
                                                <td><?php echo $pct; ?>%</td>
                                                <td><span class="badge <?php echo $gClass; ?>"><?php echo htmlspecialchars($g ?: 'N/A'); ?></span></td>
                                                <td>
                                                    <span class="badge <?php echo $isPassed ? 'badge-success' : 'badge-danger'; ?>">
                                                        <?php echo $isPassed ? 'Pass' : 'Fail'; ?>
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
