<?php
// frontend/student/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$user = $_SESSION['user'] ?? [];
$studentName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($studentName)) {
    $studentName = $user['username'] ?? 'Student';
}

$db = getDbConnection();

$rollNumber = 'N/A';
$degreeProgram = 'BCA';
$departmentName = 'Department of Computer Applications';
$currentSemester = '1';

// 1. Fetch Student Profile from Database
if ($db && $userId > 0) {
    $stmtProf = $db->prepare(
        "SELECT sp.* 
         FROM student_profiles sp 
         WHERE sp.user_id = ?"
    );
    if ($stmtProf) {
        $stmtProf->bind_param("i", $userId);
        $stmtProf->execute();
        $profRes = $stmtProf->get_result();
        if ($dbProf = $profRes->fetch_assoc()) {
            $rollNumber = !empty($dbProf['student_id']) ? $dbProf['student_id'] : (!empty($dbProf['roll_number']) ? $dbProf['roll_number'] : 'STU-' . $userId);
            if (!empty($dbProf['semester'])) {
                $currentSemester = $dbProf['semester'];
            }
        }
        $stmtProf->close();
    }
}

// Dynamic Grade Point calculation
function getGradePointsFromMarks($marks) {
    $marks = (float)$marks;
    if ($marks >= 90) return ['grade' => 'A+', 'points' => 4.0];
    if ($marks >= 85) return ['grade' => 'A', 'points' => 4.0];
    if ($marks >= 80) return ['grade' => 'A-', 'points' => 3.7];
    if ($marks >= 75) return ['grade' => 'B+', 'points' => 3.3];
    if ($marks >= 70) return ['grade' => 'B', 'points' => 3.0];
    if ($marks >= 65) return ['grade' => 'B-', 'points' => 2.7];
    if ($marks >= 60) return ['grade' => 'C+', 'points' => 2.3];
    if ($marks >= 50) return ['grade' => 'C', 'points' => 2.0];
    return ['grade' => 'F', 'points' => 0.0];
}

// 2. Fetch Performance Metrics (CGPA, Credits Completed, Class Standing) from Database
$cgpa = '0.00';
$creditsCompleted = 0;
$classStanding = 'N/A';
$totalEarnedPoints = 0;
$totalAttemptedCredits = 0;

if ($db && $userId > 0) {
    $stmtPerf = $db->prepare(
        "SELECT * FROM performance WHERE student_id = ? ORDER BY CAST(semester AS UNSIGNED) DESC, id DESC LIMIT 1"
    );
    if ($stmtPerf) {
        $stmtPerf->bind_param("i", $userId);
        $stmtPerf->execute();
        $perfRes = $stmtPerf->get_result();
        if ($perfRow = $perfRes->fetch_assoc()) {
            $cgpa = number_format((float)$perfRow['cgpa'], 2);
            $creditsCompleted = (int)$perfRow['credits_completed'];
            if (!empty($perfRow['rank'])) {
                $classStanding = ($perfRow['rank'] <= 2) ? 'Top 5%' : (($perfRow['rank'] <= 5) ? 'Top 10%' : 'Rank #' . $perfRow['rank']);
            }
        }
        $stmtPerf->close();
    }
}

// 3. Fetch Semester Results completely from Database (results JOIN subjects JOIN exams)
$semesters = [];

if ($db && $userId > 0) {
    $stmtRes = $db->prepare(
        "SELECT r.id AS result_id, r.marks_obtained, r.total_marks, r.grade, r.remarks,
                s.id AS subject_id, s.code AS course_code, s.name AS course_name, s.credits, s.semester, s.type,
                e.title AS exam_title, e.exam_type
         FROM results r
         JOIN subjects s ON r.subject_id = s.id
         LEFT JOIN exams e ON r.exam_id = e.id
         WHERE r.student_id = ?
         ORDER BY CAST(s.semester AS UNSIGNED) DESC, s.code ASC"
    );
    if ($stmtRes) {
        $stmtRes->bind_param("i", $userId);
        $stmtRes->execute();
        $resSet = $stmtRes->get_result();
        
        while ($row = $resSet->fetch_assoc()) {
            $semKey = 'Semester ' . $row['semester'];
            if (!isset($semesters[$semKey])) {
                $semesters[$semKey] = [
                    'semester_number' => $row['semester'],
                    'sgpa' => '0.00',
                    'credits' => 0,
                    'weighted_points' => 0.0,
                    'courses' => []
                ];
            }
            
            $marks = (float)$row['marks_obtained'];
            $gradeCalc = getGradePointsFromMarks($marks);
            $letterGrade = !empty($row['grade']) ? $row['grade'] : $gradeCalc['grade'];
            $points = $gradeCalc['points'];
            $courseCredits = (int)$row['credits'];
            
            $semesters[$semKey]['courses'][] = [
                'code' => $row['course_code'],
                'name' => $row['course_name'],
                'credits' => $courseCredits,
                'marks' => round($marks),
                'grade' => $letterGrade,
                'points' => $points,
                'remarks' => $row['remarks'] ?? ''
            ];
            
            $semesters[$semKey]['credits'] += $courseCredits;
            $semesters[$semKey]['weighted_points'] += ($points * $courseCredits);
            
            $totalAttemptedCredits += $courseCredits;
            $totalEarnedPoints += ($points * $courseCredits);
        }
        $stmtRes->close();
        
        // Calculate SGPA for each semester dynamically
        foreach ($semesters as $key => &$sem) {
            if ($sem['credits'] > 0) {
                $sem['sgpa'] = number_format($sem['weighted_points'] / $sem['credits'], 2);
            } else {
                $sem['sgpa'] = '0.00';
            }
        }
        unset($sem);
        
        // Fallback calculations if performance record was not set
        if ((float)$cgpa == 0.0 && $totalAttemptedCredits > 0) {
            $cgpa = number_format($totalEarnedPoints / $totalAttemptedCredits, 2);
        }
        if ($creditsCompleted == 0 && $totalAttemptedCredits > 0) {
            $creditsCompleted = $totalAttemptedCredits;
        }
        if ($classStanding === 'N/A' && (float)$cgpa > 0.0) {
            $classStanding = ((float)$cgpa >= 3.8) ? 'Top 5%' : (((float)$cgpa >= 3.5) ? 'Top 10%' : 'Good Standing');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result - BSTUDENTOS</title>
    
    <!-- External Google Font Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CDN Resources (Font Awesome, Normalize) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    
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
                <!-- Screen-Visible Header with Print Option -->
                <div class="results-action-bar no-print">
                    <div>
                        <h1><i class="fas fa-file-invoice" style="color: var(--primary);"></i> Student Result</h1>
                        <p class="page-subtitle">Cumulative Grade Point Average (CGPA) and official semester scorecards</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn-print-result" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Result
                        </button>
                    </div>
                </div>

                <!-- Print-Only Official Document Header -->
                <div class="print-only print-header">
                    <div class="print-header-top">
                        <div class="print-brand">
                            <div class="print-brand-badge">B</div>
                            <div>
                                <div class="print-inst-name">BSTUDENTOS ACADEMY</div>
                                <div class="print-inst-sub">Official Academic Transcript & Semester Grade Report</div>
                            </div>
                        </div>
                        <div class="print-header-title-box">
                            <h1>Student Result</h1>
                            <div class="print-header-date">Date Issued: <?php echo date('F d, Y'); ?></div>
                        </div>
                    </div>

                    <!-- Student Details Box in Printout -->
                    <div class="print-student-info-card">
                        <div class="print-info-item">
                            <span class="print-info-label">Student Name</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($studentName); ?></span>
                        </div>
                        <div class="print-info-item">
                            <span class="print-info-label">Roll / Student ID</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($rollNumber); ?></span>
                        </div>
                        <div class="print-info-item">
                            <span class="print-info-label">Degree Program</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($degreeProgram); ?></span>
                        </div>
                        <div class="print-info-item">
                            <span class="print-info-label">Department</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($departmentName); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics Grid (Fetched from Database) -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1);"><i class="fas fa-medal"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($cgpa); ?></span>
                            <span class="stat-label">Cumulative GPA (CGPA)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo (int)$creditsCompleted; ?></span>
                            <span class="stat-label">Credits Completed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1);"><i class="fas fa-trophy"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo htmlspecialchars($classStanding); ?></span>
                            <span class="stat-label">Class Standing</span>
                        </div>
                    </div>
                </div>

                <!-- Semester Results Section (Rendered from Database) -->
                <?php if (empty($semesters)): ?>
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 48px 24px;">
                            <i class="fas fa-clipboard-list fa-3x" style="color: var(--text-muted); margin-bottom: 16px; display: inline-block;"></i>
                            <h3 style="font-size: 1.1rem; color: var(--text-primary); margin-bottom: 8px;">No Published Results Found</h3>
                            <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto;">No published semester examination results were found in the database for your student profile.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($semesters as $semName => $semData): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($semName); ?></h3>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <span class="badge badge-primary">SGPA: <?php echo htmlspecialchars($semData['sgpa']); ?></span>
                                    <span class="badge badge-secondary"><?php echo (int)$semData['credits']; ?> Credits</span>
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
                                                    <td><?php echo (int)$crs['credits']; ?></td>
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
                <?php endif; ?>

                <!-- Print-Only Official Certification & Signatures -->
                <div class="print-only print-footer">
                    <div class="print-signature-row">
                        <div class="print-sig-box">
                            <div class="print-sig-line"></div>
                            <div class="print-sig-title">Prepared By (Academic Office)</div>
                        </div>
                        <div class="print-sig-box">
                            <div class="print-sig-line"></div>
                            <div class="print-sig-title">Controller of Examinations</div>
                        </div>
                    </div>
                    <div class="print-disclaimer">
                        This is an official computer-generated Student Result issued by BSTUDENTOS Academic Management System. Valid without manual signature or seal alteration.
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
