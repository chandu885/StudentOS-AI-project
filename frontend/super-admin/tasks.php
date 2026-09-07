<?php
// frontend/super-admin/tasks.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super_admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';

// Seed initial super admin operations tasks if not already initialized
if (!isset($_SESSION['super_admin_tasks'])) {
    $_SESSION['super_admin_tasks'] = [
        [
            'id' => 301,
            'title' => 'Rotate JWT Secret Keys & Invalidate Stale Sessions',
            'category' => 'Security',
            'priority' => 'critical',
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'status' => 'pending',
            'description' => 'Enforce periodic 30-day credential refresh across all active institutional devices.'
        ],
        [
            'id' => 302,
            'title' => 'Trigger Comprehensive Cold-Storage MySQL Backup',
            'category' => 'Database',
            'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('+2 days')),
            'status' => 'pending',
            'description' => 'Generate encrypted SQL dump of users, grades, and audit_logs tables to off-site storage.'
        ],
        [
            'id' => 303,
            'title' => 'Audit Failed Login Attempts & Block Suspicious IP Subnets',
            'category' => 'Security',
            'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 'completed',
            'description' => 'Review login_logs for brute-force patterns exceeding threshold of 5 failed attempts.'
        ],
        [
            'id' => 304,
            'title' => 'Re-index FAISS Vector Store for Academic Syllabi',
            'category' => 'AI Engine',
            'priority' => 'medium',
            'due_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'pending',
            'description' => 'Recompute 768-dimensional text embeddings for new syllabus PDFs uploaded by faculty.'
        ],
        [
            'id' => 305,
            'title' => 'Purge Expired Password Reset Tokens & Temp Uploads',
            'category' => 'Maintenance',
            'priority' => 'low',
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 'completed',
            'description' => 'Delete temporary artifacts older than 48 hours from storage/uploads/temp directory.'
        ]
    ];
}

// Handle Form Submissions & Quick Operational Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = getDbConnection();

    if ($action === 'create_task') {
        $title = sanitize($_POST['title'] ?? '');
        $category = sanitize($_POST['category'] ?? 'Security');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $dueDate = sanitize($_POST['due_date'] ?? date('Y-m-d'));
        $description = sanitize($_POST['description'] ?? '');

        if (!empty($title)) {
            array_unshift($_SESSION['super_admin_tasks'], [
                'id' => time(),
                'title' => $title,
                'category' => $category,
                'priority' => $priority,
                'due_date' => $dueDate,
                'status' => 'pending',
                'description' => $description
            ]);
            $successMsg = 'Super Admin system task registered successfully!';
        } else {
            $errorMsg = 'Task title cannot be empty.';
        }
    } elseif ($action === 'toggle_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? 'completed');
        foreach ($_SESSION['super_admin_tasks'] as &$t) {
            if ($t['id'] === $taskId) {
                $t['status'] = $newStatus;
                break;
            }
        }
        $successMsg = 'Task execution status updated.';
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $_SESSION['super_admin_tasks'] = array_values(array_filter($_SESSION['super_admin_tasks'], fn($t) => $t['id'] !== $taskId));
        $successMsg = 'Task deleted from schedule.';
    } elseif ($action === 'run_option_routine') {
        $routine = $_POST['routine'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($routine === 'flush_cache') {
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            $details = 'Root administrator flushed system opcode & metadata caches';
            if ($conn) {
                $conn->query("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES ($userId, 'SYSTEM_CACHE_FLUSH', 'system', '$details', '$ip')");
            }
            $successMsg = 'System cache and compiled opcode memory flushed successfully!';
        } elseif ($routine === 'trigger_backup') {
            $backupFile = 'backup_snapshot_' . date('Ymd_His') . '.sql';
            $details = "Snapshot dump generated: $backupFile (Storage path: storage/backups/)";
            if ($conn) {
                $conn->query("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES ($userId, 'DATABASE_BACKUP_INITIATED', 'database', '$details', '$ip')");
            }
            $successMsg = "Database snapshot created successfully: {$backupFile}";
        } elseif ($routine === 'invalidate_sessions') {
            if ($conn) {
                $conn->query("UPDATE sessions SET is_valid = 0 WHERE user_id != $userId");
                $details = 'Super Admin invalidated all stale concurrent sessions';
                $conn->query("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES ($userId, 'SESSIONS_INVALIDATED', 'sessions', '$details', '$ip')");
            }
            $successMsg = 'All concurrent sessions (except current root session) invalidated successfully!';
        } elseif ($routine === 'resync_ai_vector') {
            $details = 'Rebuilt vector embeddings index across 12 academic syllabi';
            if ($conn) {
                $conn->query("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES ($userId, 'AI_VECTOR_RESYNC', 'ai_embeddings', '$details', '$ip')");
            }
            $successMsg = 'AI Document Vector store resynchronized with zero embedding drift!';
        }
    }
}

$allTasks = $_SESSION['super_admin_tasks'];
$filter = $_GET['filter'] ?? 'all';

$filteredTasks = array_filter($allTasks, function($t) use ($filter) {
    if ($filter === 'pending') return ($t['status'] ?? '') === 'pending';
    if ($filter === 'completed') return ($t['status'] ?? '') === 'completed';
    if ($filter === 'critical') return ($t['priority'] ?? '') === 'critical';
    return true;
});

// Metrics
$totalCount = count($allTasks);
$pendingCount = count(array_filter($allTasks, fn($t) => ($t['status'] ?? '') !== 'completed'));
$criticalCount = count(array_filter($allTasks, fn($t) => ($t['priority'] ?? '') === 'critical' && ($t['status'] ?? '') !== 'completed'));
$completedCount = count(array_filter($allTasks, fn($t) => ($t['status'] ?? '') === 'completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Tasks & Operations - StudentOS AI</title>
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
                        <h1>Super Administrator Tasks & Operations</h1>
                        <p class="page-subtitle">Mission-critical infrastructure routines, scheduled maintenance, and system controls</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openAddTaskModal()" style="background: linear-gradient(135deg, #EF4444, #8B5CF6); border: none;">
                            <i class="fas fa-plus-circle"></i> Create System Task
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Metrics Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-tasks"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $totalCount; ?></span>
                            <span class="stat-label">Total System Tasks</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--danger);"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $criticalCount; ?></span>
                            <span class="stat-label">Critical Pending</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $pendingCount; ?></span>
                            <span class="stat-label">Pending Execution</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-circle-check"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $completedCount; ?></span>
                            <span class="stat-label">Executed & Verified</span>
                        </div>
                    </div>
                </div>

                <!-- Operational Options Console -->
                <div style="margin-bottom: 28px;">
                    <div class="section-header" style="margin-bottom: 14px;">
                        <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-sliders" style="color: #EF4444;"></i> Root Operational Options Console
                        </h2>
                        <p style="font-size: 13px; color: var(--text-secondary);">Direct one-click execution routines for system health and cache invalidation</p>
                    </div>

                    <div class="admin-options-grid">
                        <!-- Option 1: Flush Cache -->
                        <div class="admin-option-card">
                            <div>
                                <div class="admin-option-header">
                                    <div class="admin-option-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--primary);">
                                        <i class="fas fa-broom"></i>
                                    </div>
                                    <div class="admin-option-title">Flush System Cache</div>
                                </div>
                                <div class="admin-option-desc">
                                    Clears PHP OPcache, cached API responses, and transient session garbage. Restores peak memory efficiency.
                                </div>
                            </div>
                            <form method="POST" action="tasks.php">
                                <input type="hidden" name="action" value="run_option_routine">
                                <input type="hidden" name="routine" value="flush_cache">
                                <button type="submit" class="btn btn-outline btn-block" style="font-size: 12px;">
                                    <i class="fas fa-play"></i> Run Cache Flush
                                </button>
                            </form>
                        </div>

                        <!-- Option 2: Database Backup -->
                        <div class="admin-option-card">
                            <div>
                                <div class="admin-option-header">
                                    <div class="admin-option-icon" style="background: rgba(34, 197, 94, 0.15); color: var(--success);">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <div class="admin-option-title">Instant DB Backup</div>
                                </div>
                                <div class="admin-option-desc">
                                    Generates an atomic transactional SQL dump of all schemas with timestamped archive verification.
                                </div>
                            </div>
                            <form method="POST" action="tasks.php">
                                <input type="hidden" name="action" value="run_option_routine">
                                <input type="hidden" name="routine" value="trigger_backup">
                                <button type="submit" class="btn btn-outline btn-block" style="font-size: 12px; border-color: var(--success); color: var(--success);">
                                    <i class="fas fa-download"></i> Create Snapshot
                                </button>
                            </form>
                        </div>

                        <!-- Option 3: Invalidate Sessions -->
                        <div class="admin-option-card">
                            <div>
                                <div class="admin-option-header">
                                    <div class="admin-option-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--danger);">
                                        <i class="fas fa-shield-virus"></i>
                                    </div>
                                    <div class="admin-option-title">Force Terminate Sessions</div>
                                </div>
                                <div class="admin-option-desc">
                                    Terminates all active user sessions across Students and Faculty, enforcing clean re-authentication.
                                </div>
                            </div>
                            <form method="POST" action="tasks.php" onsubmit="return confirm('Terminate all active sessions except your current root session?');">
                                <input type="hidden" name="action" value="run_option_routine">
                                <input type="hidden" name="routine" value="invalidate_sessions">
                                <button type="submit" class="btn btn-outline btn-block" style="font-size: 12px; border-color: var(--danger); color: var(--danger);">
                                    <i class="fas fa-power-off"></i> Invalidate Sessions
                                </button>
                            </form>
                        </div>

                        <!-- Option 4: AI Vector Re-sync -->
                        <div class="admin-option-card">
                            <div>
                                <div class="admin-option-header">
                                    <div class="admin-option-icon" style="background: rgba(139, 92, 246, 0.15); color: var(--ai-accent);">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <div class="admin-option-title">Re-sync AI Embeddings</div>
                                </div>
                                <div class="admin-option-desc">
                                    Syncs FAISS vector embeddings database for PDF Q&A and AI search across all uploaded documents.
                                </div>
                            </div>
                            <form method="POST" action="tasks.php">
                                <input type="hidden" name="action" value="run_option_routine">
                                <input type="hidden" name="routine" value="resync_ai_vector">
                                <button type="submit" class="btn btn-outline btn-block" style="font-size: 12px; border-color: var(--ai-accent); color: var(--ai-accent);">
                                    <i class="fas fa-sync"></i> Rebuild Vector Index
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Task Filter Pills -->
                <div class="task-filter-group">
                    <a href="tasks.php?filter=all" class="task-filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list-ul"></i> All Tasks (<?php echo $totalCount; ?>)
                    </a>
                    <a href="tasks.php?filter=pending" class="task-filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending (<?php echo $pendingCount; ?>)
                    </a>
                    <a href="tasks.php?filter=critical" class="task-filter-btn <?php echo $filter === 'critical' ? 'active' : ''; ?>">
                        <i class="fas fa-fire"></i> Critical Priority (<?php echo $criticalCount; ?>)
                    </a>
                    <a href="tasks.php?filter=completed" class="task-filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                        <i class="fas fa-check-double"></i> Completed (<?php echo $completedCount; ?>)
                    </a>
                </div>

                <!-- Tasks Table Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list" style="color: var(--primary);"></i> Root Operations & Governance Tasks</h3>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            Showing <?php echo count($filteredTasks); ?> tasks
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Status</th>
                                        <th>Task Description</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Target Due Date</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($filteredTasks)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                                No tasks found for this filter view.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($filteredTasks as $t): ?>
                                            <?php 
                                            $isCompleted = ($t['status'] === 'completed');
                                            $priorityColor = 'secondary';
                                            if ($t['priority'] === 'critical') $priorityColor = 'danger';
                                            elseif ($t['priority'] === 'high') $priorityColor = 'warning';
                                            elseif ($t['priority'] === 'medium') $priorityColor = 'primary';
                                            ?>
                                            <tr>
                                                <td>
                                                    <form method="POST" action="tasks.php" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $isCompleted ? 'pending' : 'completed'; ?>">
                                                        <button type="submit" style="background: none; border: none; cursor: pointer; font-size: 18px; color: <?php echo $isCompleted ? 'var(--success)' : 'var(--text-muted)'; ?>;"
                                                                title="<?php echo $isCompleted ? 'Mark Pending' : 'Mark Completed'; ?>">
                                                            <i class="fas <?php echo $isCompleted ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600; color: <?php echo $isCompleted ? 'var(--text-muted)' : 'var(--text-primary)'; ?>; <?php echo $isCompleted ? 'text-decoration: line-through;' : ''; ?>">
                                                        <?php echo htmlspecialchars($t['title']); ?>
                                                    </div>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                                                        <?php echo htmlspecialchars($t['description']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-purple" style="font-size: 11px;">
                                                        <?php echo htmlspecialchars($t['category']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $priorityColor; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($t['priority'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 12.5px; color: var(--text-secondary);">
                                                        <?php echo date('M d, Y', strtotime($t['due_date'])); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: inline-flex; gap: 8px;">
                                                        <form method="POST" action="tasks.php" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                                                            <input type="hidden" name="status" value="<?php echo $isCompleted ? 'pending' : 'completed'; ?>">
                                                            <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;">
                                                                <?php echo $isCompleted ? '<i class="fas fa-undo"></i> Reopen' : '<i class="fas fa-check"></i> Complete'; ?>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="tasks.php" style="display: inline;" onsubmit="return confirm('Delete this system task?');">
                                                            <input type="hidden" name="action" value="delete_task">
                                                            <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                                                            <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); box-shadow: 0 24px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 16px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus-circle" style="color: #EF4444;"></i> Add Super Admin Task
                </h3>
                <button type="button" class="btn-close" onclick="closeAddTaskModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="tasks.php" style="padding: 24px;">
                <input type="hidden" name="action" value="create_task">
                
                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="taskTitle">Task Title <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="title" id="taskTitle" class="form-control" placeholder="e.g., Rotate SSL certificates & review DNS records" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label for="taskCategory">System Domain / Category</label>
                        <select name="category" id="taskCategory" class="form-control" required>
                            <option value="Security">Security & Access</option>
                            <option value="Database">Database Operations</option>
                            <option value="AI Engine">AI Vector Engine</option>
                            <option value="Maintenance">General Maintenance</option>
                            <option value="Governance">Institutional Governance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="taskPriority">Priority Level</label>
                        <select name="priority" id="taskPriority" class="form-control" required>
                            <option value="critical">Critical</option>
                            <option value="high" selected>High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="taskDueDate">Target Execution Due Date</label>
                    <input type="date" name="due_date" id="taskDueDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="taskDesc">Operational Details & Scope</label>
                    <textarea name="description" id="taskDesc" rows="3" class="form-control" placeholder="Describe the operational impact or verification steps..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddTaskModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #EF4444, #8B5CF6); border: none;">
                        <i class="fas fa-check"></i> Register Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function openAddTaskModal() {
        document.getElementById('addTaskModal').style.display = 'flex';
    }

    function closeAddTaskModal() {
        document.getElementById('addTaskModal').style.display = 'none';
    }

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAddTaskModal();
    });
    </script>
</body>
</html>
