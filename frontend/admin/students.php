<?php
// frontend/admin/students.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Student account created and login credentials generated!';
}

$students = [
    ['id' => 1, 'roll' => 'CS-2023-01', 'name' => 'Alex Morgan', 'email' => 'alex.m@college.edu', 'dept' => 'Computer Science', 'sem' => 'Sem 6', 'cgpa' => '3.85', 'status' => 'Active'],
    ['id' => 2, 'roll' => 'CS-2023-02', 'name' => 'Brian Clark', 'email' => 'brian.c@college.edu', 'dept' => 'Computer Science', 'sem' => 'Sem 6', 'cgpa' => '3.42', 'status' => 'Active'],
    ['id' => 3, 'roll' => 'EC-2023-14', 'name' => 'Diana Prince', 'email' => 'diana.p@college.edu', 'dept' => 'Electronics', 'sem' => 'Sem 4', 'cgpa' => '3.91', 'status' => 'Active'],
    ['id' => 4, 'roll' => 'ME-2023-09', 'name' => 'Ethan Hunt', 'email' => 'ethan.h@college.edu', 'dept' => 'Mechanical', 'sem' => 'Sem 6', 'cgpa' => '3.60', 'status' => 'Active']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - StudentOS AI</title>
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
                        <h1>Student Records Management</h1>
                        <p class="page-subtitle">Enrolled students master database across all departments and semesters</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addStudentModal')">
                            <i class="fas fa-user-plus"></i> Add New Student
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
                        <h3><i class="fas fa-users"></i> All Students</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>CGPA</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $stu): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($stu['email']); ?></td>
                                            <td><?php echo htmlspecialchars($stu['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($stu['sem']); ?></td>
                                            <td><strong style="color: var(--success);"><?php echo htmlspecialchars($stu['cgpa']); ?></strong></td>
                                            <td><span class="badge badge-success"><?php echo htmlspecialchars($stu['status']); ?></span></td>
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

    <!-- Add Student Modal -->
    <div class="modal-backdrop" id="addStudentModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="fn">First Name</label>
                            <input type="text" name="first_name" id="fn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ln">Last Name</label>
                            <input type="text" name="last_name" id="ln" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="em">Institutional Email</label>
                        <input type="email" name="email" id="em" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="rn">Roll Number</label>
                            <input type="text" name="roll_number" id="rn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="deptSelect">Department</label>
                            <select name="department_id" id="deptSelect" class="form-control">
                                <option value="1">Computer Science & Engineering</option>
                                <option value="2">Electronics & Comm.</option>
                                <option value="3">Mechanical Engineering</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Student</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
