<?php
// frontend/faculty/assignments.php
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
    $description = sanitize($_POST['description'] ?? '');
    $deadline = sanitize($_POST['deadline'] ?? date('Y-m-d H:i:s', strtotime('+7 days')));
    $maxMarks = (int)($_POST['max_marks'] ?? 20);

    $res = apiCall('/assignments.php?action=create', 'POST', [
        'subject_id' => $subjectId,
        'title' => $title,
        'description' => $description,
        'deadline' => $deadline,
        'max_marks' => $maxMarks
    ]);
    $successMsg = 'Assignment created and published to students!';
}

$assignments = [
    ['id' => 1, 'title' => 'ER Diagram & Relational Schema Design', 'subject_name' => 'Database Management Systems', 'deadline' => date('Y-m-d H:i:s', strtotime('+3 days')), 'submissions_count' => 48, 'total_students' => 64, 'max_marks' => 20],
    ['id' => 2, 'title' => 'SQL Queries, Joins, and Aggregations', 'subject_name' => 'Database Management Systems', 'deadline' => date('Y-m-d H:i:s', strtotime('-5 days')), 'submissions_count' => 62, 'total_students' => 64, 'max_marks' => 25],
    ['id' => 3, 'title' => 'B-Tree & Indexing Research Summary', 'subject_name' => 'Advanced Database Systems', 'deadline' => date('Y-m-d H:i:s', strtotime('+10 days')), 'submissions_count' => 12, 'total_students' => 42, 'max_marks' => 30]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - StudentOS AI</title>
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
                        <h1>Assignment Management</h1>
                        <p class="page-subtitle">Publish coursework, specify grading rubrics, and monitor student submissions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('createAsgModal')">
                            <i class="fas fa-plus"></i> Create Assignment
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
                        <h3><i class="fas fa-file-alt"></i> Published Coursework</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title & Course</th>
                                        <th>Deadline</th>
                                        <th>Submissions</th>
                                        <th>Max Marks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $asg): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 500;"><?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $asg['submissions_count']; ?> / <?php echo $asg['total_students']; ?> Submitted</span>
                                            </td>
                                            <td><strong><?php echo $asg['max_marks']; ?></strong> pts</td>
                                            <td>
                                                <a href="submissions.php?assignment_id=<?php echo $asg['id']; ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                                                    <i class="fas fa-eye"></i> View Submissions
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

    <!-- Create Assignment Modal -->
    <div class="modal-backdrop" id="createAsgModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Create New Assignment</h3>
                <button class="modal-close" onclick="closeModal('createAsgModal')">&times;</button>
            </div>
            <form method="POST" action="assignments.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="asgTitle">Assignment Title</label>
                        <input type="text" name="title" id="asgTitle" class="form-control" placeholder="e.g. Normalization and Lossless Join Synthesis" required>
                    </div>
                    <div class="form-group">
                        <label for="asgSubject">Course</label>
                        <select name="subject_id" id="asgSubject" class="form-control">
                            <option value="1">Database Management Systems (CS301)</option>
                            <option value="2">Advanced Database Systems (CS502)</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="asgDeadline">Submission Deadline</label>
                            <input type="datetime-local" name="deadline" id="asgDeadline" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="asgMarks">Maximum Points</label>
                            <input type="number" name="max_marks" id="asgMarks" class="form-control" value="20" min="5" max="100" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="asgDesc">Instructions & Problem Statement</label>
                        <textarea name="description" id="asgDesc" class="form-control" rows="4" placeholder="Detailed assignment instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createAsgModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
