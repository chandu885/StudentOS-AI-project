<?php
// frontend/faculty/exams.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 1);
    $examDate = sanitize($_POST['exam_date'] ?? date('Y-m-d H:i:s', strtotime('+10 days')));
    $duration = (int)($_POST['duration_minutes'] ?? 90);
    $totalMarks = (int)($_POST['total_marks'] ?? 50);

    $res = apiCall('/exams.php?action=create', 'POST', [
        'subject_id' => $subjectId,
        'title' => $title,
        'exam_date' => $examDate,
        'duration_minutes' => $duration,
        'total_marks' => $totalMarks
    ]);
    $successMsg = 'Examination scheduled and published!';
}

$exams = [
    ['id' => 1, 'title' => 'Midterm Examination 2026', 'subject_name' => 'Database Management Systems', 'exam_date' => date('Y-m-d 10:00:00', strtotime('+7 days')), 'duration_minutes' => 90, 'total_marks' => 50, 'status' => 'scheduled'],
    ['id' => 2, 'title' => 'Advanced Database Quiz 1', 'subject_name' => 'Advanced Database Systems', 'exam_date' => date('Y-m-d 14:00:00', strtotime('+12 days')), 'duration_minutes' => 45, 'total_marks' => 30, 'status' => 'scheduled']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - StudentOS AI</title>
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
                        <h1>Examination Management</h1>
                        <p class="page-subtitle">Schedule exams, define assessment criteria, and link question papers</p>
                    </div>
                    <div class="header-actions">
                        <a href="questions.php" class="btn btn-secondary"><i class="fas fa-database"></i> Question Bank</a>
                        <button class="btn btn-primary" onclick="openModal('createExamModal')">
                            <i class="fas fa-plus"></i> Schedule Exam
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-pencil-alt"></i> Scheduled Examinations</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Exam Title & Subject</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Total Marks</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $ex): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($ex['title']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($ex['subject_name']); ?></div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 500;"><?php echo date('M d, Y h:i A', strtotime($ex['exam_date'])); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($ex['duration_minutes']); ?> mins</td>
                                            <td><strong><?php echo htmlspecialchars($ex['total_marks']); ?></strong> pts</td>
                                            <td><span class="badge badge-primary"><?php echo ucfirst($ex['status']); ?></span></td>
                                            <td>
                                                <a href="marks.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                                                    <i class="fas fa-table"></i> Enter Marks
                                                </a>
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

    <!-- Create Exam Modal -->
    <div class="modal-backdrop" id="createExamModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Schedule Examination</h3>
                <button class="modal-close" onclick="closeModal('createExamModal')">&times;</button>
            </div>
            <form method="POST" action="exams.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="examTitle">Exam Title</label>
                        <input type="text" name="title" id="examTitle" class="form-control" placeholder="e.g. Midterm Examination 2026" required>
                    </div>
                    <div class="form-group">
                        <label for="examSub">Course</label>
                        <select name="subject_id" id="examSub" class="form-control">
                            <option value="1">Database Management Systems (CS301)</option>
                            <option value="2">Advanced Database Systems (CS502)</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="examDate">Exam Date & Time</label>
                            <input type="datetime-local" name="exam_date" id="examDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="examDur">Duration (mins)</label>
                            <input type="number" name="duration_minutes" id="examDur" class="form-control" value="90" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="examTotal">Total Marks</label>
                        <input type="number" name="total_marks" id="examTotal" class="form-control" value="50" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createExamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Schedule Exam</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
