<?php
// frontend/faculty/tasks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

$db = getDbConnection();

// Auto-seed initial academic tasks for faculty if none exist
if ($db) {
    $chkStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM tasks WHERE user_id = ?");
    if ($chkStmt) {
        $chkStmt->bind_param("i", $userId);
        $chkStmt->execute();
        $hasTasks = (int)$chkStmt->get_result()->fetch_assoc()['cnt'];
        $chkStmt->close();

        if ($hasTasks === 0) {
            $seedStmt = $db->prepare("INSERT INTO tasks (user_id, title, description, priority, status, category, deadline, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'academic', ?, NOW(), NOW())");
            if ($seedStmt) {
                $seeds = [
                    ['Grade CS301 Operating Systems Midterm Exams', 'Grade Section A & B submissions (62 students)', 'high', 'todo', date('Y-m-d 23:59:59', strtotime('+2 days'))],
                    ['Submit Monthly Attendance Audit for February', 'Flag students below 75% threshold to Dean of Academics', 'medium', 'todo', date('Y-m-d 23:59:59', strtotime('+4 days'))],
                    ['Draft Question Bank for Algorithms Quiz 2', 'Prepare 20 MCQs with difficulty tagging in question portal', 'medium', 'completed', date('Y-m-d 23:59:59', strtotime('+6 days'))],
                    ['Publish Assignment 3 Solution Key & Code Samples', 'Upload C++ test harness to files repository', 'low', 'todo', date('Y-m-d 23:59:59', strtotime('+5 days'))]
                ];
                foreach ($seeds as $s) {
                    $seedStmt->bind_param("isssss", $userId, $s[0], $s[1], $s[2], $s[3], $s[4]);
                    $seedStmt->execute();
                }
                $seedStmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $priorityRaw = strtolower(sanitize($_POST['priority'] ?? 'medium'));
        $priority = in_array($priorityRaw, ['low', 'medium', 'high', 'urgent']) ? $priorityRaw : 'medium';
        $dueDateRaw = sanitize($_POST['due_date'] ?? date('Y-m-d'));
        $deadline = date('Y-m-d 23:59:59', strtotime($dueDateRaw));

        if (!empty($title) && $db) {
            $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, priority, status, category, deadline, created_at, updated_at) VALUES (?, ?, ?, ?, 'todo', 'academic', ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param("issss", $userId, $title, $description, $priority, $deadline);
                if ($stmt->execute()) {
                    $successMsg = 'Academic task added successfully!';
                } else {
                    $errorMsg = 'Failed to create task: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'toggle_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $reqStatus = sanitize($_POST['status'] ?? 'completed');
        $newStatus = ($reqStatus === 'completed') ? 'completed' : 'todo';
        if ($db && $taskId > 0) {
            $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("sii", $newStatus, $taskId, $userId);
                $stmt->execute();
                $stmt->close();
                $successMsg = 'Task status updated!';
            }
        }
    } elseif ($action === 'delete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($db && $taskId > 0) {
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $taskId, $userId);
                $stmt->execute();
                $stmt->close();
                $successMsg = 'Task removed.';
            }
        }
    }
}

$tasks = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, title, description, priority, status, deadline FROM tasks WHERE user_id = ? ORDER BY (status = 'completed') ASC, deadline ASC, id DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$totalCount = count($tasks);
$completedCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'completed'));
$pendingCount = $totalCount - $completedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Task Manager - StudentOS AI</title>
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
                        <h1>Faculty Academic Tasks</h1>
                        <p class="page-subtitle">Track grading milestones, syllabus delivery, and exam preparations</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                            <i class="fas fa-plus"></i> Add New Task
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
                        <div class="task-stat-label">Total Assigned</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--warning);"><?php echo $pendingCount; ?></div>
                        <div class="task-stat-label">Pending Action</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--success);"><?php echo $completedCount; ?></div>
                        <div class="task-stat-label">Completed</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-tasks"></i> Course Deliverables</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php if (empty($tasks)): ?>
                                <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                                    <i class="fas fa-clipboard-check" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                    No academic tasks currently scheduled. Click "Add New Task" to create one.
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): 
                                    $isDone = ($task['status'] ?? '') === 'completed';
                                    $prio = strtolower($task['priority'] ?? 'medium');
                                    $prioColor = ($prio === 'urgent' || $prio === 'high') ? 'danger' : ($prio === 'medium' ? 'warning' : 'info');
                                    $dueStr = !empty($task['deadline']) ? date('M d, Y', strtotime($task['deadline'])) : 'No due date';
                                ?>
                                    <div class="task-row-card">
                                        <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                                            <form method="POST" action="tasks.php" style="margin: 0;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $isDone ? 'todo' : 'completed'; ?>">
                                                <button type="submit" class="task-checkbox-btn <?php echo $isDone ? 'done' : ''; ?>">
                                                    <i class="fas <?php echo $isDone ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                                </button>
                                            </form>
                                            <div>
                                                <div style="font-size: 14.5px; font-weight: 500; color: var(--text-primary); <?php echo $isDone ? 'text-decoration: line-through; opacity: 0.6;' : ''; ?>">
                                                    <?php echo htmlspecialchars($task['title']); ?>
                                                </div>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                    <?php echo htmlspecialchars($task['description'] ?? ''); ?> &bull; Due: <?php echo htmlspecialchars($dueStr); ?>
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
                            <?php endif; ?>
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
                <h3>Add Academic Task</h3>
                <button class="modal-close" onclick="closeModal('addTaskModal')">&times;</button>
            </div>
            <form method="POST" action="tasks.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Task Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Finalize Marks for Quiz 1" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
