<?php
// frontend/admin/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Ledger CSV Download
if (isset($_GET['export']) && $_GET['export'] === 'ledger' && !empty($_GET['exam_id']) && $db) {
    $examId = (int)$_GET['exam_id'];
    $stmt = $db->prepare(
        "SELECT r.id, COALESCE(sp.student_id, sp.roll_number, CONCAT('STU-', u.id)) AS roll,
                CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                COALESCE(e.title, 'Exam') AS exam_title,
                s.code AS subject_code, s.name AS subject_name,
                r.marks_obtained, r.total_marks, r.grade, r.remarks, r.published_at
         FROM results r
         JOIN users u ON r.student_id = u.id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN exams e ON r.exam_id = e.id
         JOIN subjects s ON r.subject_id = s.id
         WHERE r.exam_id = ?
         ORDER BY roll ASC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="result_ledger_exam_' . $examId . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Result ID', 'Roll Number', 'Student Name', 'Examination', 'Subject Code', 'Subject Name', 'Marks Obtained', 'Total Marks', 'Grade', 'Remarks', 'Published Date']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['roll'],
                $row['student_name'],
                $row['exam_title'],
                $row['subject_code'],
                $row['subject_name'],
                $row['marks_obtained'],
                $row['total_marks'],
                $row['grade'],
                $row['remarks'] ?? '',
                $row['published_at'] ?? 'Unpublished'
            ]);
        }
        fclose($out);
        $stmt->close();
        exit;
    }
}

// Publish / Unpublish Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $examId = (int)($_POST['exam_id'] ?? 0);

    if ($action === 'publish' && $examId > 0 && $db) {
        $stmt = $db->prepare("UPDATE results SET published_at = NOW() WHERE exam_id = ?");
        $stmt->bind_param("i", $examId);
        if ($stmt->execute()) {
            $successMsg = 'Examination results published successfully to student portals!';
        } else {
            $errorMsg = 'Failed to publish results: ' . $db->error;
        }
        $stmt->close();
    } elseif ($action === 'unpublish' && $examId > 0 && $db) {
        $stmt = $db->prepare("UPDATE results SET published_at = NULL WHERE exam_id = ?");
        $stmt->bind_param("i", $examId);
        if ($stmt->execute()) {
            $successMsg = 'Results reverted to draft moderation status.';
        } else {
            $errorMsg = 'Failed to unpublish: ' . $db->error;
        }
        $stmt->close();
    }
}

// Fetch live result cohorts
$cohorts = [];
if ($db) {
    $q = "SELECT 
            e.id AS exam_id,
            COALESCE(e.title, s.name, 'General Examination') AS exam_title,
            COALESCE(d.name, 'Computer Science & Engineering') AS dept_name,
            COUNT(DISTINCT r.student_id) AS student_count,
            ROUND(AVG(r.marks_obtained), 1) AS avg_marks,
            ROUND((COUNT(CASE WHEN r.marks_obtained >= 40 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 1) AS pass_rate,
            MAX(r.published_at) AS published_at
          FROM results r
          LEFT JOIN exams e ON r.exam_id = e.id
          LEFT JOIN subjects s ON r.subject_id = s.id
          LEFT JOIN courses c ON s.course_id = c.id
          LEFT JOIN departments d ON c.department_id = d.id
          GROUP BY e.id, e.title, s.name, d.name
          ORDER BY e.id DESC";
    $res = $db->query($q);
    if ($res) {
        $cohorts = $res->fetch_all(MYSQLI_ASSOC);
    }
}
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
                        <p class="page-subtitle">Publish official semester grade reports, calculate pass rates, and download mark ledgers</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-award"></i> Semester Result Cohorts (<?php echo count($cohorts); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Examination Cohort</th>
                                        <th>Department</th>
                                        <th>Students Evaluated</th>
                                        <th>Pass Rate</th>
                                        <th>Average Score</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cohorts)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No examination results evaluated yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cohorts as $c): 
                                            $isPublished = !empty($c['published_at']);
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($c['exam_title']); ?></strong>
                                                    <?php if ($isPublished): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-check-circle" style="color: var(--success);"></i> Published: <?php echo date('M d, Y', strtotime($c['published_at'])); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($c['dept_name']); ?></td>
                                                <td><span class="badge badge-secondary"><?php echo (int)$c['student_count']; ?> Students</span></td>
                                                <td><strong style="color: var(--success);"><?php echo $c['pass_rate']; ?>%</strong></td>
                                                <td><strong><?php echo $c['avg_marks']; ?> / 100</strong></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isPublished ? 'success' : 'warning'; ?>">
                                                        <?php echo $isPublished ? 'Published' : 'Draft / Moderation'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 6px; align-items: center;">
                                                        <?php if (!$isPublished): ?>
                                                            <form method="POST" action="results.php" style="margin: 0; display: inline;">
                                                                <input type="hidden" name="action" value="publish">
                                                                <input type="hidden" name="exam_id" value="<?php echo (int)$c['exam_id']; ?>">
                                                                <button type="submit" class="btn btn-primary" style="font-size: 11px; padding: 4px 10px;">
                                                                    <i class="fas fa-check"></i> Publish
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <a href="results.php?export=ledger&exam_id=<?php echo (int)$c['exam_id']; ?>" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" title="Download Cohort CSV Ledger">
                                                                <i class="fas fa-download"></i> Ledger
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
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
