<?php
// frontend/student/tasks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

// Initialize default tasks if not in session
if (!isset($_SESSION['student_tasks'])) {
    $_SESSION['student_tasks'] = [
        ['id' => 1, 'title' => 'Complete Chapter 4 Notes on BCNF Normalization', 'description' => 'Review 3NF vs BCNF dependency preservation rules', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+1 day')), 'status' => 'pending'],
        ['id' => 2, 'title' => 'Submit OS Lab Exercise 3: Semaphores & Mutex', 'description' => 'Implement producer-consumer problem in C with POSIX threads', 'priority' => 'medium', 'due_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'pending'],
        ['id' => 3, 'title' => 'Review DSA QuickSort & MergeSort Recurrence Analysis', 'description' => 'Use Master Theorem to calculate worst and average cases', 'priority' => 'low', 'due_date' => date('Y-m-d', strtotime('+4 days')), 'status' => 'completed'],
        ['id' => 4, 'title' => 'Prepare 20 Flashcards for Computer Networks Midterm', 'description' => 'TCP 3-way handshake and OSI vs TCP/IP layer comparison', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+2 days')), 'status' => 'pending']
    ];
}

// Handle Task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $dueDate = sanitize($_POST['due_date'] ?? date('Y-m-d'));
        
        if (!empty($title)) {
            $newId = time();
            $newTask = [
                'id' => $newId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'due_date' => $dueDate,
                'status' => 'pending'
            ];
            
            // Try API call
            apiCall('/tasks.php', 'POST', $newTask);
            
            // Persist in session
            array_unshift($_SESSION['student_tasks'], $newTask);
            $successMsg = 'Task added successfully!';
        } else {
            $errorMsg = 'Please enter a task title.';
        }
    } elseif ($action === 'toggle_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? 'completed');
        
        apiCall('/tasks.php?action=status', 'POST', [
            'task_id' => $taskId,
            'status' => $newStatus
        ]);
        
        foreach ($_SESSION['student_tasks'] as &$t) {
            if ($t['id'] == $taskId) {
                $t['status'] = $newStatus;
                break;
            }
        }
        $successMsg = 'Task status updated!';
    } elseif ($action === 'delete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        apiCall("/tasks.php?id={$taskId}", 'DELETE');
        
        $_SESSION['student_tasks'] = array_values(array_filter($_SESSION['student_tasks'], function($t) use ($taskId) {
            return $t['id'] != $taskId;
        }));
        $successMsg = 'Task deleted successfully!';
    }
}

$tasks = $_SESSION['student_tasks'];

// Compute metrics
$totalCount = count($tasks);
$pendingCount = 0;
$completedCount = 0;
$highPrioCount = 0;

foreach ($tasks as $t) {
    if (($t['status'] ?? '') === 'completed') {
        $completedCount++;
    } else {
        $pendingCount++;
        if (strtolower($t['priority'] ?? '') === 'high') {
            $highPrioCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - StudentOS AI</title>
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
                        <h1>Task & Study Manager</h1>
                        <p class="page-subtitle">Track course assignments, exam preparation, and daily study milestones</p>
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

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Counters -->
                <div class="task-stats-bar">
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--primary-light);"><?php echo $totalCount; ?></div>
                        <div class="task-stat-label">Total Tasks</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--warning);"><?php echo $pendingCount; ?></div>
                        <div class="task-stat-label">Pending Action</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--success);"><?php echo $completedCount; ?></div>
                        <div class="task-stat-label">Completed</div>
                    </div>
                    <div class="task-stat-box">
                        <div class="task-stat-number" style="color: var(--danger);"><?php echo $highPrioCount; ?></div>
                        <div class="task-stat-label">High Priority</div>
                    </div>
                </div>

                <!-- Controls Bar -->
                <div class="task-controls-bar">
                    <div class="task-filter-group">
                        <button class="task-filter-btn active" onclick="filterTasks('all', this)">All (<?php echo $totalCount; ?>)</button>
                        <button class="task-filter-btn" onclick="filterTasks('pending', this)">Pending (<?php echo $pendingCount; ?>)</button>
                        <button class="task-filter-btn" onclick="filterTasks('completed', this)">Completed (<?php echo $completedCount; ?>)</button>
                        <button class="task-filter-btn" onclick="filterTasks('high', this)">Urgent (<?php echo $highPrioCount; ?>)</button>
                    </div>

                    <div style="position: relative; width: 260px;">
                        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                        <input type="text" id="taskSearchInput" class="form-control" placeholder="Search tasks..." style="padding-left: 36px; height: 38px; font-size: 13px;" onkeyup="searchTasks()">
                    </div>
                </div>

                <!-- Task List Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-check"></i> Active Study Tasks</h3>
                        <span style="font-size: 12px; color: var(--text-muted);">Showing all active items</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <h3>No Tasks Created Yet</h3>
                                <p>Stay organized by adding your first study task or assignment reminder.</p>
                                <button class="btn btn-primary mt-2" onclick="openModal('addTaskModal')">
                                    <i class="fas fa-plus"></i> Create Task
                                </button>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;" id="tasksContainer">
                                <?php foreach ($tasks as $task): 
                                    $isDone = ($task['status'] ?? '') === 'completed';
                                    $prio = strtolower($task['priority'] ?? 'medium');
                                    $prioColor = $prio === 'high' ? 'danger' : ($prio === 'medium' ? 'warning' : 'info');
                                    $dueTime = strtotime($task['due_date'] ?? date('Y-m-d'));
                                    $isOverdue = !$isDone && ($dueTime < strtotime('today'));
                                ?>
                                    <div class="task-row-card task-card-item" 
                                         data-status="<?php echo $task['status'] ?? 'pending'; ?>" 
                                         data-priority="<?php echo $prio; ?>"
                                         data-title="<?php echo htmlspecialchars(strtolower($task['title'])); ?>">
                                        
                                        <div style="display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0;">
                                            <!-- Checkbox toggle button -->
                                            <form method="POST" action="tasks.php" style="margin: 0;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $isDone ? 'pending' : 'completed'; ?>">
                                                <button type="submit" class="task-checkbox-btn <?php echo $isDone ? 'done' : ''; ?>" title="<?php echo $isDone ? 'Mark Incomplete' : 'Mark Complete'; ?>">
                                                    <i class="fas <?php echo $isDone ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                                </button>
                                            </form>

                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-size: 14.5px; font-weight: 500; color: var(--text-primary); <?php echo $isDone ? 'text-decoration: line-through; opacity: 0.55;' : ''; ?>; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                                    <?php echo htmlspecialchars($task['title']); ?>
                                                </div>
                                                <?php if (!empty($task['description'])): ?>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px; <?php echo $isDone ? 'text-decoration: line-through; opacity: 0.5;' : ''; ?>">
                                                        <?php echo htmlspecialchars($task['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="font-size: 11.5px; color: <?php echo $isOverdue ? 'var(--danger)' : 'var(--text-muted)'; ?>; margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                                                    <i class="fas fa-calendar-alt"></i> Due: <?php echo date('M d, Y', $dueTime); ?>
                                                    <?php if ($isOverdue): ?>
                                                        <span class="badge badge-danger" style="font-size: 10px; padding: 2px 6px;">Overdue</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 14px; flex-shrink: 0;">
                                            <span class="badge badge-<?php echo $prioColor; ?>">
                                                <?php echo ucfirst($prio); ?>
                                            </span>

                                            <!-- Delete Button -->
                                            <form method="POST" action="tasks.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" class="task-action-btn" title="Delete Task">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Add Task Modal -->
    <div class="modal-backdrop" id="addTaskModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Create New Study Task</h3>
                <button class="modal-close" onclick="closeModal('addTaskModal')">&times;</button>
            </div>
            <form method="POST" action="tasks.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="taskTitle">Task Title *</label>
                        <input type="text" name="title" id="taskTitle" class="form-control" placeholder="e.g. Solve 5 problems on Binary Trees" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="taskDesc">Description / Objectives (Optional)</label>
                        <textarea name="description" id="taskDesc" class="form-control" rows="3" placeholder="Key topics to focus on, textbook page numbers, references..."></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="taskPrio">Priority Level</label>
                            <select name="priority" id="taskPrio" class="form-control">
                                <option value="low">Low Priority</option>
                                <option value="medium" selected>Medium Priority</option>
                                <option value="high">High / Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="taskDue">Due Date</label>
                            <input type="date" name="due_date" id="taskDue" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function filterTasks(filterType, btn) {
        document.querySelectorAll('.task-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const items = document.querySelectorAll('.task-card-item');
        items.forEach(item => {
            const status = item.getAttribute('data-status');
            const priority = item.getAttribute('data-priority');
            
            if (filterType === 'all') {
                item.style.display = 'flex';
            } else if (filterType === 'pending') {
                item.style.display = (status === 'pending') ? 'flex' : 'none';
            } else if (filterType === 'completed') {
                item.style.display = (status === 'completed') ? 'flex' : 'none';
            } else if (filterType === 'high') {
                item.style.display = (priority === 'high') ? 'flex' : 'none';
            }
        });
    }

    function searchTasks() {
        const query = (document.getElementById('taskSearchInput').value || '').toLowerCase().trim();
        const items = document.querySelectorAll('.task-card-item');
        
        items.forEach(item => {
            const title = item.getAttribute('data-title') || '';
            if (title.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>
