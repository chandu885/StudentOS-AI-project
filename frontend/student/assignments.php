<?php
// frontend/student/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asgId = (int)($_POST['assignment_id'] ?? 0);
    $text = sanitize($_POST['submission_text'] ?? '');
    
    // File upload handling if present
    $filePath = null;
    if (!empty($_FILES['submission_file']['name'])) {
        $uploadDir = BASE_PATH . '/storage/assignments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['submission_file']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetFile)) {
            $filePath = 'assignments/' . $fileName;
        }
    }

    $res = apiCall('/assignments.php?action=submit', 'POST', [
        'assignment_id' => $asgId,
        'submission_text' => $text,
        'file_path' => $filePath
    ]);

    if (!empty($res['success'])) {
        $successMsg = 'Assignment submitted successfully!';
    } else {
        $errorMsg = $res['error'] ?? 'Submission failed. Please try again.';
    }
}

$asgRes = apiCall('/assignments.php', 'GET');
$assignments = $asgRes['assignments'] ?? [
    ['id' => 1, 'title' => 'ER Diagram & Relational Schema Design', 'subject_name' => 'Database Management Systems', 'deadline' => date('Y-m-d H:i:s', strtotime('+3 days')), 'status' => 'pending', 'max_marks' => 20, 'obtained_marks' => null],
    ['id' => 2, 'title' => 'Red-Black Tree Implementation in C++', 'subject_name' => 'Data Structures & Algorithms', 'deadline' => date('Y-m-d H:i:s', strtotime('+6 days')), 'status' => 'pending', 'max_marks' => 30, 'obtained_marks' => null],
    ['id' => 3, 'title' => 'CPU Scheduling Simulation', 'subject_name' => 'Operating Systems', 'deadline' => date('Y-m-d H:i:s', strtotime('-2 days')), 'status' => 'submitted', 'max_marks' => 25, 'obtained_marks' => 23],
    ['id' => 4, 'title' => 'Socket Programming Client/Server', 'subject_name' => 'Computer Networks', 'deadline' => date('Y-m-d H:i:s', strtotime('-10 days')), 'status' => 'graded', 'max_marks' => 20, 'obtained_marks' => 19]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - StudentOS AI</title>
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
                        <h1>Coursework & Assignments</h1>
                        <p class="page-subtitle">Track, submit, and review grades for your coursework</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> All Assignments</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title & Subject</th>
                                        <th>Due Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $asg): 
                                        $overdue = isOverdue($asg['deadline']) && ($asg['status'] ?? 'pending') === 'pending';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                            </td>
                                            <td>
                                                <span style="font-size: 13px; font-weight: 500; <?php echo $overdue ? 'color: var(--danger);' : ''; ?>">
                                                    <?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?>
                                                </span>
                                                <?php if ($overdue): ?>
                                                    <span class="badge badge-danger" style="margin-left: 6px;">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($asg['max_marks'] ?? 100); ?> pts</td>
                                            <td>
                                                <?php 
                                                $st = strtolower($asg['status'] ?? 'pending');
                                                if ($st === 'submitted') echo '<span class="badge badge-info">Submitted</span>';
                                                elseif ($st === 'graded') echo '<span class="badge badge-success">Graded</span>';
                                                else echo '<span class="badge badge-warning">Pending</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo isset($asg['obtained_marks']) ? "<strong>{$asg['obtained_marks']}</strong> / {$asg['max_marks']}" : '—'; ?>
                                            </td>
                                            <td>
                                                <?php if (($asg['status'] ?? 'pending') === 'pending'): ?>
                                                    <button class="btn btn-primary" style="padding: 6px 14px; font-size: 12px;" onclick="openSubmitModal(<?php echo $asg['id']; ?>, '<?php echo addslashes($asg['title']); ?>')">
                                                        <i class="fas fa-upload"></i> Submit
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary" style="padding: 6px 14px; font-size: 12px;" disabled>
                                                        <i class="fas fa-check"></i> Submitted
                                                    </button>
                                                <?php endif; ?>
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

    <!-- Submit Assignment Modal -->
    <div class="modal-backdrop" id="submitModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="submitModalTitle">Submit Assignment</h3>
                <button class="modal-close" onclick="closeModal('submitModal')">&times;</button>
            </div>
            <form method="POST" action="assignments.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="submitAsgId">
                    <div class="form-group">
                        <label for="submission_text">Submission Notes / Written Response</label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="4" placeholder="Enter links, GitHub URL, or answer text..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="submission_file">Attach File (PDF, ZIP, DOCX up to 10MB)</label>
                        <input type="file" name="submission_file" id="submission_file" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('submitModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Work</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openSubmitModal(id, title) {
        document.getElementById('submitAsgId').value = id;
        document.getElementById('submitModalTitle').textContent = 'Submit: ' + title;
        openModal('submitModal');
    }
    </script>
</body>
</html>
