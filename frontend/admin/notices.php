<?php
// frontend/admin/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['notice_id'] ?? 0);
        if ($delId > 0 && $db) {
            $del = $db->prepare("DELETE FROM notices WHERE id = ?");
            $del->bind_param("i", $delId);
            if ($del->execute()) {
                $successMsg = 'Circular deleted successfully.';
            } else {
                $errorMsg = 'Failed to delete circular: ' . $db->error;
            }
            $del->close();
        }
    } elseif (isset($_POST['title'], $_POST['content'])) {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $target = sanitize($_POST['target_role'] ?? 'all');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if (empty($title) || empty($content)) {
            $errorMsg = 'Notice Title and Content are required.';
        } elseif ($db) {
            if ($deptId) {
                $stmt = $db->prepare("INSERT INTO notices (title, content, target_role, department_id, priority, posted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssisi", $title, $content, $target, $deptId, $priority, $userId);
            } else {
                $stmt = $db->prepare("INSERT INTO notices (title, content, target_role, priority, posted_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssi", $title, $content, $target, $priority, $userId);
            }
            if ($stmt && $stmt->execute()) {
                $successMsg = 'Campus-wide official circular published successfully!';
                $stmt->close();
            } else {
                $errorMsg = 'Failed to publish circular: ' . ($db->error ?? 'Database error');
            }
        }
    }
}

// Fetch departments
$departments = [];
if ($db) {
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes) $departments = $dRes->fetch_all(MYSQLI_ASSOC);
}

// Fetch notices
$notices = [];
if ($db) {
    $q = "SELECT n.*, 
                 CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                 COALESCE(d.name, 'Campus Wide') AS dept_name
          FROM notices n
          LEFT JOIN users u ON n.posted_by = u.id
          LEFT JOIN departments d ON n.department_id = d.id
          ORDER BY n.created_at DESC";
    $nRes = $db->query($q);
    if ($nRes) {
        $notices = $nRes->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Notices - StudentOS AI</title>
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
                        <h1>Institutional Circulars & Notices</h1>
                        <p class="page-subtitle">Publish and broadcast campus advisories, holidays, and official administrative updates</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addNoticeModal')">
                            <i class="fas fa-bullhorn"></i> Issue Circular
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-newspaper"></i> Published Circulars (<?php echo count($notices); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Circular Title & Summary</th>
                                        <th>Audience</th>
                                        <th>Scope / Dept</th>
                                        <th>Priority</th>
                                        <th>Author</th>
                                        <th>Date Issued</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($notices)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No circulars published. Click "Issue Circular" to publish one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($notices as $n): 
                                            $prio = strtolower($n['priority'] ?? 'medium');
                                            $prioClass = 'badge-primary';
                                            if ($prio === 'high' || $prio === 'urgent') $prioClass = 'badge-danger';
                                            elseif ($prio === 'low') $prioClass = 'badge-secondary';
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted); max-width: 360px; line-height: 1.4; margin-top: 3px;">
                                                        <?php echo htmlspecialchars(mb_substr($n['content'], 0, 100)) . (mb_strlen($n['content']) > 100 ? '...' : ''); ?>
                                                    </div>
                                                </td>
                                                <td><span class="badge badge-secondary"><?php echo ucfirst(htmlspecialchars($n['target_role'] ?? 'all')); ?></span></td>
                                                <td><?php echo htmlspecialchars($n['dept_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $prioClass; ?>">
                                                        <?php echo ucfirst($prio); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($n['author_name'] ?? 'Admin Office'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($n['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this circular?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="notice_id" value="<?php echo (int)$n['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete circular">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
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

    <!-- Add Notice Modal -->
    <div class="modal-backdrop" id="addNoticeModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Issue Official Circular</h3>
                <button class="modal-close" onclick="closeModal('addNoticeModal')">&times;</button>
            </div>
            <form method="POST" action="notices.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nTitle">Circular Title *</label>
                        <input type="text" name="title" id="nTitle" class="form-control" placeholder="e.g. End-Semester Examination Registration Schedule" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="nTarget">Audience *</label>
                            <select name="target_role" id="nTarget" class="form-control">
                                <option value="all">Campus Wide (All)</option>
                                <option value="student">Students Only</option>
                                <option value="faculty">Faculty Only</option>
                                <option value="admin">Administrators Only</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nPrio">Priority</label>
                            <select name="priority" id="nPrio" class="form-control">
                                <option value="medium">Medium</option>
                                <option value="high">High / Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nDept">Department Filter (Optional)</label>
                        <select name="department_id" id="nDept" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d['id']; ?>">
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nText">Circular Content *</label>
                        <textarea name="content" id="nText" class="form-control" rows="4" placeholder="Detail the official instructions, timelines, and contact persons..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addNoticeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Circular</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
