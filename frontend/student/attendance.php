<?php
// frontend/student/attendance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$db = getDbConnection();
$attendanceRecords = [];
$totalHeld = 0;
$totalAttended = 0;

if ($db && $userId > 0) {
    $stmt = $db->prepare(
        "SELECT s.name AS subject_name, s.code AS subject_code,
                COUNT(*) AS total_classes,
                COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) AS present_count,
                COUNT(CASE WHEN LOWER(a.status) = 'absent' THEN 1 END) AS absent_count,
                COUNT(CASE WHEN LOWER(a.status) = 'late' THEN 1 END) AS late_count,
                ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) AS percentage
         FROM attendance a
         JOIN subjects s ON a.subject_id = s.id
         WHERE a.student_id = ?
         GROUP BY a.subject_id
         ORDER BY s.code ASC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $attendanceRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

foreach ($attendanceRecords as $rec) {
    $totalHeld += (int)($rec['total_classes'] ?? 0);
    $totalAttended += (int)($rec['present_count'] ?? 0);
}
$overallPct = $totalHeld > 0 ? round(($totalAttended / $totalHeld) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview - StudentOS AI</title>
    
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
                <div class="page-header">
                    <div>
                        <h1>Attendance Tracking</h1>
                        <p class="page-subtitle">Monitor your subject attendance and eligibility criteria (Min. 75%)</p>
                    </div>
                </div>

                <?php if ($overallPct < 75): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                        <div>
                            <strong>Attendance Warning:</strong> Your overall attendance is <?php echo $overallPct; ?>%, which is below the mandatory 75% examination threshold.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                        <div class="stat-content">
                            <span class="stat-number" style="color: <?php echo $overallPct >= 75 ? 'var(--success)' : 'var(--danger)'; ?>;"><?php echo $overallPct; ?>%</span>
                            <span class="stat-label">Overall Percentage</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalAttended; ?></span>
                            <span class="stat-label">Classes Attended</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo ($totalHeld - $totalAttended); ?></span>
                            <span class="stat-label">Classes Missed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalHeld; ?></span>
                            <span class="stat-label">Total Conducted</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-check"></i> Subject-Wise Breakdown</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Subject Name</th>
                                        <th>Conducted</th>
                                        <th>Attended</th>
                                        <th>Absent</th>
                                        <th>Progress Bar</th>
                                        <th>Percentage</th>
                                        <th>Eligibility Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendanceRecords)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-calendar-check" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                                <strong style="color: var(--text-primary);">No Attendance Data Recorded</strong>
                                                <p style="font-size: 13px; margin-top: 4px;">Attendance records marked by your faculty will be displayed here.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendanceRecords as $rec): 
                                            $pct = $rec['percentage'] ?? 0;
                                            $isEligible = $pct >= 75;
                                            $fillColor = $pct >= 85 ? 'success' : ($pct >= 75 ? 'warning' : 'danger');
                                            $attCount = $rec['present_count'] ?? $rec['attended'] ?? 0;
                                            $totClasses = $rec['total_classes'] ?? 0;
                                            $missedCount = max(0, $totClasses - $attCount);
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($rec['subject_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($totClasses); ?></td>
                                                <td><span style="color: var(--success); font-weight: 600;"><?php echo htmlspecialchars($attCount); ?></span></td>
                                                <td><span style="color: var(--danger); font-weight: 600;"><?php echo htmlspecialchars($missedCount); ?></span></td>
                                                <td style="width: 200px;">
                                                    <div class="attendance-bar" style="height: 10px;">
                                                        <div class="attendance-fill <?php echo $fillColor; ?>" style="width: <?php echo $pct; ?>%;"></div>
                                                    </div>
                                                </td>
                                                <td><strong><?php echo $pct; ?>%</strong></td>
                                                <td>
                                                    <?php if ($isEligible): ?>
                                                        <span class="badge badge-success"><i class="fas fa-check"></i> Eligible</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><i class="fas fa-times"></i> At Risk</span>
                                                    <?php endif; ?>
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
