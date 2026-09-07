<?php
// frontend/admin/tasks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

if (!isset($_SESSION['admin_tasks'])) {
    $_SESSION['admin_tasks'] = [
        ['id' => 201, 'title' => 'Verify Spring 2026 Student Admissions & Enrollments', 'description' => 'Verify prerequisite credits for 45 incoming transfer students', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'pending'],
        ['id' => 202, 'title' => 'Publish Finalized Semester 6 Master Timetable', 'description' => 'Cross-verify lab allocation conflicts across Computer Science & IT', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+2 days')), 'status' => 'pending'],
        ['id' => 203, 'title' => 'Conduct Departmental Faculty Workload Audit', 'description' => 'Ensure maximum 16 teaching credit hours per faculty member', 'priority' => 'medium', 'due_date' => date('Y-m-d', strtotime('+7 days')), 'status' => 'completed'],
        ['id' => 204, 'title' => 'Generate Midterm Hall Tickets & Seating Arrangements', 'description' => 'Coordinate with examination controller for hall numbers', 'priority' => 'medium', 'due_date' => date('Y-m-d', strtotime('+5 days')), 'status' => 'pending']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $dueDate = sanitize($_POST['due_date'] ?? date('Y-m-d'));
        if (!empty($title)) {
            array_unshift($_SESSION['admin_tasks'], [
                'id' => time(),
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'due_date' => $dueDate,
                'status' => 'pending'
            ]);
            $successMsg = 'Administrative task scheduled successfully!';
        }
    } elseif ($action === 'toggle_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? 'completed');
        foreach ($_SESSION['admin_tasks'] as &$t) {
            if ($t['id'] == $taskId) {
                $t['status'] = $newStatus;
                break;
            }
        }
        $successMsg = 'Task status updated!';
    } elseif ($action === 'delete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $_SESSION['admin_tasks'] = array_values(array_filter($_SESSION['admin_tasks'], fn($t) => $t['id'] != $taskId));
        $successMsg = 'Task deleted.';
    }
}

$tasks = $_SESSION['admin_tasks'];
$totalCount = count($tasks);
$pendingCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'completed'));
$completedCount = $totalCount - $pendingCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tasks - StudentOS AI</title>
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
                        <h1>Administrative Action Tasks</h1>
                        <p class="page-subtitle">Track department deadlines, institutional accreditation milestones, and audits</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                            <i class="fas fa-plus"></i> New Admin Task
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="task-stats-bar">
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--primary);"><?php echo $totalCount; ?></div>
                        <div class="task-stat-label">Total Departmental</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--warning);"><?php echo $pendingCount; ?></div>
                        <div class="task-stat-label">Pending Action</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--success);"><?php echo $completedCount; ?></div>
                        <div class="task-stat-label">Resolved / Done</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-check"></i> Institutional Milestones</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($tasks as $task): 
                                $isDone = ($task['status'] ?? '') === 'completed';
                                $prio = strtolower($task['priority'] ?? 'medium');
                                $prioColor = $prio === 'high' ? 'danger' : ($prio === 'medium' ? 'warning' : 'info');
                            ?>
                                <div class="task-row-card">
                                    <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                                        <form method="POST" action="tasks.php" style="margin: 0;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $isDone ? 'pending' : 'completed'; ?>">
                                            <button type="submit" class="task-checkbox-btn <?php echo $isDone ? 'done' : ''; ?>">
                                                <i class="fas <?php echo $isDone ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                            </button>
                                        </form>
                                        <div>
                                            <div style="font-size: 14.5px; font-weight: 500; color: var(--text-primary); <?php echo $isDone ? 'text-decoration: line-through; opacity: 0.6;' : ''; ?>">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                <?php echo htmlspecialchars($task['description'] ?? ''); ?> • Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span class="badge badge-<?php echo $prioColor; ?>"><?php echo ucfirst($prio); ?></span>
                                        <form method="POST" action="tasks.php" style="margin: 0;" onsubmit="return confirm('Delete this task?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" class="task-action-btn" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal-backdrop" id="addTaskModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Schedule Administrative Task</h3>
                <button class="modal-close" onclick="closeModal('addTaskModal')">&times;</button>
            </div>
            <form method="POST" action="tasks.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Task Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Audit CS Lab 2 Licenses" required>
                    </div>
                    <div class="form-group">
                        <label>Operational Details</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Deadline</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+5 days')); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
