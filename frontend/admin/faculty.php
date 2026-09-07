<?php
// frontend/admin/faculty.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Faculty member account created successfully!';
}

$facultyList = [
    ['name' => 'Dr. Robert Smith', 'email' => 'robert.smith@college.edu', 'dept' => 'Computer Science', 'designation' => 'Professor', 'cabin' => 'Block B 308', 'courses' => 2],
    ['name' => 'Prof. Sarah Jenkins', 'email' => 'sarah.j@college.edu', 'dept' => 'Computer Science', 'designation' => 'Associate Professor', 'cabin' => 'Block B 214', 'courses' => 3],
    ['name' => 'Dr. Alan Walker', 'email' => 'alan.w@college.edu', 'dept' => 'Computer Science', 'designation' => 'Assistant Professor', 'cabin' => 'Block B 102', 'courses' => 2],
    ['name' => 'Prof. Emily Chen', 'email' => 'emily.c@college.edu', 'dept' => 'Computer Science', 'designation' => 'Associate Professor', 'cabin' => 'Block B 205', 'courses' => 2]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management - StudentOS AI</title>
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
                        <h1>Faculty Staff Management</h1>
                        <p class="page-subtitle">Professors, teaching faculty, and departmental lecturers</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addFacultyModal')">
                            <i class="fas fa-plus"></i> Add Faculty
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
                        <h3><i class="fas fa-chalkboard-teacher"></i> Faculty Directory</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Office Room</th>
                                        <th>Active Courses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($facultyList as $fac): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($fac['email']); ?></td>
                                            <td><?php echo htmlspecialchars($fac['dept']); ?></td>
                                            <td><span class="badge badge-purple"><?php echo htmlspecialchars($fac['designation']); ?></span></td>
                                            <td><?php echo htmlspecialchars($fac['cabin']); ?></td>
                                            <td><span class="badge badge-info"><?php echo $fac['courses']; ?> Courses</span></td>
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

    <!-- Add Faculty Modal -->
    <div class="modal-backdrop" id="addFacultyModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add Faculty Member</h3>
                <button class="modal-close" onclick="closeModal('addFacultyModal')">&times;</button>
            </div>
            <form method="POST" action="faculty.php">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="ffn">First Name</label>
                            <input type="text" name="first_name" id="ffn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="fln">Last Name</label>
                            <input type="text" name="last_name" id="fln" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fem">Official Email</label>
                        <input type="email" name="email" id="fem" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="fdept">Department</label>
                            <select name="department_id" id="fdept" class="form-control">
                                <option value="1">Computer Science & Engineering</option>
                                <option value="2">Electronics Engineering</option>
                                <option value="3">Mechanical Engineering</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fdesig">Designation</label>
                            <select name="designation" id="fdesig" class="form-control">
                                <option value="Assistant Professor">Assistant Professor</option>
                                <option value="Associate Professor">Associate Professor</option>
                                <option value="Professor">Professor</option>
                                <option value="HOD">Head of Department</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addFacultyModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Register Faculty</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
