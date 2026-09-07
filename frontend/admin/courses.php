<?php
// frontend/admin/courses.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Degree program added successfully!';
}

$courses = [
    ['id' => 1, 'code' => 'BTECH-CS', 'name' => 'B.Tech in Computer Science & Engineering', 'department' => 'Computer Science', 'duration' => '4 Years', 'semesters' => 8, 'total_credits' => 160],
    ['id' => 2, 'code' => 'MTECH-CS', 'name' => 'M.Tech in Artificial Intelligence', 'department' => 'Computer Science', 'duration' => '2 Years', 'semesters' => 4, 'total_credits' => 72],
    ['id' => 3, 'code' => 'BTECH-EC', 'name' => 'B.Tech in Electronics & Communication', 'department' => 'Electronics', 'duration' => '4 Years', 'semesters' => 8, 'total_credits' => 160]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Courses & Degrees - StudentOS AI</title>
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
                        <h1>Degree Programs & Courses</h1>
                        <p class="page-subtitle">Configure undergraduate and graduate curricula and degree requirements</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                            <i class="fas fa-plus"></i> Add Degree Program
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
                        <h3><i class="fas fa-layer-group"></i> Active Programs</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Program Code</th>
                                        <th>Degree Name</th>
                                        <th>Department</th>
                                        <th>Duration</th>
                                        <th>Semesters</th>
                                        <th>Required Credits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($c['code']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['department']); ?></td>
                                            <td><?php echo htmlspecialchars($c['duration']); ?></td>
                                            <td><?php echo htmlspecialchars($c['semesters']); ?> Sems</td>
                                            <td><strong><?php echo htmlspecialchars($c['total_credits']); ?></strong> credits</td>
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

    <!-- Add Course Modal -->
    <div class="modal-backdrop" id="addCourseModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add Degree Program</h3>
                <button class="modal-close" onclick="closeModal('addCourseModal')">&times;</button>
            </div>
            <form method="POST" action="courses.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="cName">Program Name</label>
                        <input type="text" name="name" id="cName" class="form-control" placeholder="e.g. B.Tech in Data Science" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="cCode">Program Code</label>
                            <input type="text" name="code" id="cCode" class="form-control" placeholder="e.g. BTECH-DS" required>
                        </div>
                        <div class="form-group">
                            <label for="cDept">Department</label>
                            <select name="department_id" id="cDept" class="form-control">
                                <option value="1">Computer Science</option>
                                <option value="2">Electronics</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="cSem">Semesters</label>
                            <input type="number" name="semesters" id="cSem" class="form-control" value="8" min="1" max="12">
                        </div>
                        <div class="form-group">
                            <label for="cCred">Total Credits</label>
                            <input type="number" name="credits" id="cCred" class="form-control" value="160">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Program</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
