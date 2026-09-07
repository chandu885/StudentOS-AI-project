<?php
// frontend/admin/subjects.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Subject added to curriculum catalog!';
}

$subjects = [
    ['code' => 'CS301', 'name' => 'Database Management Systems', 'dept' => 'Computer Science', 'sem' => 'Sem 6', 'credits' => 4, 'faculty' => 'Dr. Robert Smith'],
    ['code' => 'CS302', 'name' => 'Data Structures & Algorithms', 'dept' => 'Computer Science', 'sem' => 'Sem 4', 'credits' => 4, 'faculty' => 'Prof. Sarah Jenkins'],
    ['code' => 'CS303', 'name' => 'Operating Systems', 'dept' => 'Computer Science', 'sem' => 'Sem 6', 'credits' => 3, 'faculty' => 'Dr. Alan Walker'],
    ['code' => 'CS304', 'name' => 'Computer Networks', 'dept' => 'Computer Science', 'sem' => 'Sem 6', 'credits' => 3, 'faculty' => 'Prof. Emily Chen']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Catalog - StudentOS AI</title>
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
                        <h1>Subject & Course Catalog</h1>
                        <p class="page-subtitle">Configure courses, assign subject instructors, and specify credit structures</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addSubjectModal')">
                            <i class="fas fa-plus"></i> Add Subject
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
                        <h3><i class="fas fa-book"></i> Active Course Catalog</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Subject Name</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>Credits</th>
                                        <th>Assigned Instructor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $s): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($s['code']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($s['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($s['sem']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($s['credits']); ?></strong> pts</td>
                                            <td><i class="fas fa-user-tie" style="color: var(--text-muted); margin-right: 4px;"></i> <?php echo htmlspecialchars($s['faculty']); ?></td>
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

    <!-- Add Subject Modal -->
    <div class="modal-backdrop" id="addSubjectModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add New Subject</h3>
                <button class="modal-close" onclick="closeModal('addSubjectModal')">&times;</button>
            </div>
            <form method="POST" action="subjects.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="subName">Subject Title</label>
                        <input type="text" name="name" id="subName" class="form-control" placeholder="e.g. Distributed Operating Systems" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="subCode">Code</label>
                            <input type="text" name="code" id="subCode" class="form-control" placeholder="e.g. CS401" required>
                        </div>
                        <div class="form-group">
                            <label for="subCredits">Credits</label>
                            <input type="number" name="credits" id="subCredits" class="form-control" value="4" min="1" max="8">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="subDept">Department</label>
                            <select name="department_id" id="subDept" class="form-control">
                                <option value="1">Computer Science</option>
                                <option value="2">Electronics</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subSem">Semester</label>
                            <input type="number" name="semester" id="subSem" class="form-control" value="6" min="1" max="8">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
