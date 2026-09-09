<?php
// frontend/faculty/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Get faculty department
$deptId = 1;
if ($db) {
    $deptStmt = $db->prepare("SELECT department_id FROM faculty_profiles WHERE user_id = ?");
    if ($deptStmt) {
        $deptStmt->bind_param("i", $userId);
        $deptStmt->execute();
        $deptRes = $deptStmt->get_result()->fetch_assoc();
        if (!empty($deptRes['department_id'])) {
            $deptId = (int)$deptRes['department_id'];
        }
        $deptStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $targetRole = sanitize($_POST['target_role'] ?? 'STUDENT');
    $priority = sanitize($_POST['priority'] ?? 'medium');

    if (!empty($title) && !empty($content) && $db) {
        $ins = $db->prepare("INSERT INTO notices (title, content, target_role, department_id, priority, posted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($ins) {
            $ins->bind_param("sssisi", $title, $content, $targetRole, $deptId, $priority, $userId);
            if ($ins->execute()) {
                $successMsg = 'Notice published successfully to student and faculty portals!';
            } else {
                $errorMsg = 'Failed to publish notice: ' . $ins->error;
            }
            $ins->close();
        }
    } else {
        $errorMsg = 'Please enter both title and announcement content.';
    }
}

$notices = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT n.id, n.title, n.content, n.target_role, n.priority, n.created_at,
                d.name AS department_name,
                CONCAT(u.first_name, ' ', u.last_name) AS author_name
         FROM notices n
         LEFT JOIN departments d ON n.department_id = d.id
         LEFT JOIN users u ON n.posted_by = u.id
         WHERE n.posted_by = ? OR n.target_role IN ('all', 'faculty', 'FACULTY', 'STUDENT')
         ORDER BY n.created_at DESC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (empty($notices)) {
        $res = $db->query(
            "SELECT n.id, n.title, n.content, n.target_role, n.priority, n.created_at,
                    d.name AS department_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM notices n
             LEFT JOIN departments d ON n.department_id = d.id
             LEFT JOIN users u ON n.posted_by = u.id
             ORDER BY n.created_at DESC LIMIT 20"
        );
        if ($res) {
            $notices = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Notices - StudentOS AI</title>
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
                        <h1>Class Notices & Announcements</h1>
                        <p class="page-subtitle">Publish circulars and lecture announcements to your enrolled students</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('postNoticeModal')">
                            <i class="fas fa-bullhorn"></i> Post New Notice
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($notices)): ?>
                        <div class="card">
                            <div class="card-body" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                <i class="fas fa-bullhorn" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                No active announcements posted yet. Click "Post New Notice" to create one.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $not): 
                            $prio = strtolower($not['priority'] ?? 'medium');
                            $badgeClass = 'badge-primary';
                            if ($prio === 'high') $badgeClass = 'badge-danger';
                            elseif ($prio === 'medium') $badgeClass = 'badge-warning';
                            elseif ($prio === 'low') $badgeClass = 'badge-secondary';
                        ?>
                            <div class="card" style="margin-bottom: 0;">
                                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <span class="badge <?php echo $badgeClass; ?>"><i class="fas fa-flag"></i> <?php echo ucfirst($prio); ?> Priority</span>
                                        <span class="badge badge-secondary"><i class="fas fa-users"></i> <?php echo ucfirst(strtolower($not['target_role'])); ?></span>
                                    </div>
                                    <span style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($not['created_at'])); ?></span>
                                </div>
                                <div class="card-body">
                                    <h3 style="font-size: 16px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($not['title']); ?></h3>
                                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($not['content'])); ?></p>
                                    <?php if (!empty($not['author_name'])): ?>
                                        <div style="margin-top: 10px; font-size: 12px; color: var(--text-muted);">
                                            <i class="fas fa-user-circle"></i> Posted by <strong><?php echo htmlspecialchars($not['author_name']); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Post Notice Modal -->
    <div class="modal-backdrop" id="postNoticeModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Post Announcement</h3>
                <button class="modal-close" onclick="closeModal('postNoticeModal')">&times;</button>
            </div>
            <form method="POST" action="notices.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="noticeTitle">Announcement Title</label>
                        <input type="text" name="title" id="noticeTitle" class="form-control" placeholder="e.g. Extra Doubt Clearing Session on Saturday" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="noticeTarget">Target Audience</label>
                            <select name="target_role" id="noticeTarget" class="form-control">
                                <option value="STUDENT">Enrolled Students</option>
                                <option value="FACULTY">Faculty Colleagues</option>
                                <option value="all">All Campus</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="noticePrio">Priority</label>
                            <select name="priority" id="noticePrio" class="form-control">
                                <option value="medium">Medium Priority</option>
                                <option value="high">High Priority</option>
                                <option value="low">Low Priority</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="noticeMsg">Notice Content</label>
                        <textarea name="content" id="noticeMsg" class="form-control" rows="4" placeholder="Enter announcement text..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('postNoticeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
