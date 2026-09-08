<?php
// frontend/admin/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$adminRes = apiCall('/admin.php?path=dashboard', 'GET');
$adminData = $adminRes['dashboard'] ?? $adminRes ?? [];

$recentStudents = [
    ['roll' => 'CS-2023-01', 'name' => 'Alex Morgan', 'dept' => 'Computer Science', 'sem' => 'Semester 6', 'status' => 'Active'],
    ['roll' => 'CS-2023-02', 'name' => 'Brian Clark', 'dept' => 'Computer Science', 'sem' => 'Semester 6', 'status' => 'Active'],
    ['roll' => 'EC-2023-14', 'name' => 'Diana Prince', 'dept' => 'Electronics', 'sem' => 'Semester 4', 'status' => 'Active'],
    ['roll' => 'ME-2023-09', 'name' => 'Ethan Hunt', 'dept' => 'Mechanical', 'sem' => 'Semester 6', 'status' => 'Active']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Administration - StudentOS AI</title>
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
                <div class="welcome-section">
                    <div>
                        <h1>Academic Administration</h1>
                        <p class="welcome-subtitle">Institution Overview • Campus Operations & Department Management</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">1,248</span>
                            <span class="stat-label">Total Enrolled Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent);"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">84</span>
                            <span class="stat-label">Faculty Members</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">6</span>
                            <span class="stat-label">Departments</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">14</span>
                            <span class="stat-label">Degree Programs</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-check"></i> Recently Registered Students</h3>
                        <a href="students.php" class="link">View All Students</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Full Name</th>
                                        <th>Department</th>
                                        <th>Current Semester</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentStudents as $stu): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($stu['dept']); ?></td>
                                            <td><?php echo htmlspecialchars($stu['sem']); ?></td>
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

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
