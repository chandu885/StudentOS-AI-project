<?php
// frontend/admin/departments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Department added successfully!';
}

$departments = [
    ['id' => 1, 'code' => 'CSE', 'name' => 'Computer Science & Engineering', 'hod' => 'Dr. Robert Smith', 'faculty_count' => 24, 'students_count' => 450],
    ['id' => 2, 'code' => 'ECE', 'name' => 'Electronics & Communication Engineering', 'hod' => 'Dr. Marcus Vance', 'faculty_count' => 18, 'students_count' => 320],
    ['id' => 3, 'code' => 'ME', 'name' => 'Mechanical Engineering', 'hod' => 'Dr. Henry Ford', 'faculty_count' => 16, 'students_count' => 280],
    ['id' => 4, 'code' => 'CE', 'name' => 'Civil Engineering', 'hod' => 'Dr. Walter White', 'faculty_count' => 14, 'students_count' => 198]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - StudentOS AI</title>
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
                        <h1>Academic Departments</h1>
                        <p class="page-subtitle">Configure faculties, departmental heads, and academic divisions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addDeptModal')">
                            <i class="fas fa-plus"></i> Add Department
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
                        <h3><i class="fas fa-building"></i> Active Departments</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Department Name</th>
                                        <th>Head of Department (HOD)</th>
                                        <th>Faculty Staff</th>
                                        <th>Enrolled Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dept['hod']); ?></td>
                                            <td><?php echo $dept['faculty_count']; ?> Professors</td>
                                            <td><span class="badge badge-info"><?php echo $dept['students_count']; ?> Students</span></td>
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

    <!-- Add Department Modal -->
    <div class="modal-backdrop" id="addDeptModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Create New Department</h3>
                <button class="modal-close" onclick="closeModal('addDeptModal')">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="deptName">Department Name</label>
                        <input type="text" name="name" id="deptName" class="form-control" placeholder="e.g. Electrical Engineering" required>
                    </div>
                    <div class="form-group">
                        <label for="deptCode">Department Code</label>
                        <input type="text" name="code" id="deptCode" class="form-control" placeholder="e.g. EE" required>
                    </div>
                    <div class="form-group">
                        <label for="deptHod">Head of Department (HOD)</label>
                        <input type="text" name="hod" id="deptHod" class="form-control" placeholder="e.g. Dr. Jane Doe">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
