<?php
// frontend/faculty/marks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Exam marks and grading records successfully saved!';
}

$students = [
    ['id' => 101, 'roll' => 'CS-2023-01', 'name' => 'Alex Morgan', 'marks' => 46],
    ['id' => 102, 'roll' => 'CS-2023-02', 'name' => 'Brian Clark', 'marks' => 38],
    ['id' => 103, 'roll' => 'CS-2023-03', 'name' => 'Catherine Davis', 'marks' => 44],
    ['id' => 104, 'roll' => 'CS-2023-04', 'name' => 'Daniel Evans', 'marks' => 49]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook & Marks Entry - StudentOS AI</title>
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
                        <h1>Marks Entry & Gradebook</h1>
                        <p class="page-subtitle">Record and publish marks for continuous internal assessments and examinations</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="marks.php">
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="examSelect">Assessment / Exam</label>
                                    <select name="exam_id" id="examSelect" class="form-control">
                                        <option value="1">Midterm Examination 2026 - DBMS (Max: 50)</option>
                                        <option value="2">Class Test 1 - DBMS (Max: 20)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Weightage</label>
                                    <input type="text" class="form-control" value="25% of Total Grade" disabled style="opacity: 0.7;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calculator"></i> Student Scores Sheet</h3>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Marks</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>Student Name</th>
                                            <th>Marks Obtained (out of 50)</th>
                                            <th>Grade Equivalent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $stu): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                                <td style="width: 220px;">
                                                    <input type="number" step="0.5" name="marks[<?php echo $stu['id']; ?>]" class="form-control" value="<?php echo $stu['marks']; ?>" min="0" max="50" style="width: 120px; height: 36px;">
                                                </td>
                                                <td>
                                                    <span class="badge badge-success">
                                                        <?php echo $stu['marks'] >= 45 ? 'A+' : ($stu['marks'] >= 40 ? 'A' : 'B+'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
