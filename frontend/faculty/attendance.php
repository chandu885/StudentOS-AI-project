<?php
// frontend/faculty/attendance.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = (int)($_POST['subject_id'] ?? 1);
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $records = $_POST['status'] ?? [];

    $apiRecords = [];
    foreach ($records as $studentId => $status) {
        $apiRecords[] = [
            'student_id' => (int)$studentId,
            'status' => $status
        ];
    }

    $res = apiCall('/attendance.php', 'POST', [
        'subject_id' => $subjectId,
        'date' => $date,
        'records' => $apiRecords
    ]);
    $successMsg = 'Attendance recorded successfully!';
}

$students = [
    ['id' => 101, 'roll' => 'CS-2023-01', 'name' => 'Alex Morgan'],
    ['id' => 102, 'roll' => 'CS-2023-02', 'name' => 'Brian Clark'],
    ['id' => 103, 'roll' => 'CS-2023-03', 'name' => 'Catherine Davis'],
    ['id' => 104, 'roll' => 'CS-2023-04', 'name' => 'Daniel Evans']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - StudentOS AI</title>
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
                        <h1>Class Attendance Register</h1>
                        <p class="page-subtitle">Mark daily attendance for enrolled lecture and laboratory sessions</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="attendance.php">
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="subject_id">Select Course</label>
                                    <select name="subject_id" id="subject_id" class="form-control">
                                        <option value="1">Database Management Systems (CS301)</option>
                                        <option value="2">Advanced Database Systems (CS502)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="date">Class Date</label>
                                    <input type="date" name="date" id="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" style="height: 42px;" onclick="markAllPresent()">
                                        <i class="fas fa-check-double"></i> Mark All Present
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-clipboard-check"></i> Student Roster</h3>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>Student Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $stu): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($stu['roll']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($stu['name']); ?></strong></td>
                                                <td>
                                                    <div style="display: flex; gap: 16px;">
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="present" checked class="att-radio-present">
                                                            <span style="color: var(--success); font-weight: 500;">Present</span>
                                                        </label>
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="absent" class="att-radio-absent">
                                                            <span style="color: var(--danger); font-weight: 500;">Absent</span>
                                                        </label>
                                                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="status[<?php echo $stu['id']; ?>]" value="late">
                                                            <span style="color: var(--warning); font-weight: 500;">Late</span>
                                                        </label>
                                                    </div>
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
    <script>
    function markAllPresent() {
        document.querySelectorAll('.att-radio-present').forEach(r => r.checked = true);
        showToast('All students marked present', 'info');
    }
    </script>
</body>
</html>
