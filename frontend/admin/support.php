<?php
// frontend/admin/support.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if (!isset($_SESSION['admin_tickets'])) {
    $_SESSION['admin_tickets'] = [
        ['id' => 'TKT-1042', 'student' => 'Alex Morgan (CS-2023-01)', 'subject' => 'Discrepancy in Attendance for OS Lab on Feb 24', 'category' => 'Attendance', 'priority' => 'medium', 'status' => 'in_progress'],
        ['id' => 'TKT-1043', 'student' => 'Brian Clark (CS-2023-02)', 'subject' => 'Course Registration error for Elective CS502', 'category' => 'Registration', 'priority' => 'high', 'status' => 'pending'],
        ['id' => 'TKT-0988', 'student' => 'Catherine Davis (CS-2023-03)', 'subject' => 'Access error downloading syllabus PDF for CS304', 'category' => 'Technical', 'priority' => 'low', 'status' => 'resolved']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tId = sanitize($_POST['ticket_id'] ?? '');
    foreach ($_SESSION['admin_tickets'] as &$t) {
        if ($t['id'] === $tId) {
            $t['status'] = ($t['status'] === 'resolved') ? 'in_progress' : 'resolved';
            break;
        }
    }
    $successMsg = 'Support ticket #' . htmlspecialchars($tId) . ' status updated and requester notified!';
}

$tickets = $_SESSION['admin_tickets'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Helpdesk - StudentOS AI</title>
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
                        <h1>Helpdesk Ticket Resolution</h1>
                        <p class="page-subtitle">Manage, assign, and resolve student and faculty support requests</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-life-ring"></i> Active Support Requests</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $t): 
                                        $isResolved = $t['status'] === 'resolved';
                                        $prioClass = $t['priority'] === 'high' ? 'danger' : ($t['priority'] === 'medium' ? 'warning' : 'info');
                                    ?>
                                        <tr>
                                            <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($t['id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($t['student']); ?></td>
                                            <td><?php echo htmlspecialchars($t['subject']); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                            <td><span class="badge badge-<?php echo $prioClass; ?>"><?php echo ucfirst($t['priority']); ?></span></td>
                                            <td>
                                                <span class="badge badge-<?php echo $isResolved ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="support.php" style="margin: 0;">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;">
                                                        <i class="fas fa-check"></i> <?php echo $isResolved ? 'Re-open' : 'Resolve'; ?>
                                                    </button>
                                                </form>
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

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
