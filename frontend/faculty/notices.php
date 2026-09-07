<?php
// frontend/faculty/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Notice published successfully to enrolled student dashboards!';
}

$notices = [
    ['title' => 'Lab Session Rescheduled to Thursday', 'subject' => 'DBMS Practical Lab', 'date' => '2026-03-01', 'content' => 'Please note that this Monday lab session is rescheduled to Thursday 2:00 PM in Lab 3 due to university symposium.']
];
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

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($notices as $not): ?>
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <span class="badge badge-primary"><?php echo htmlspecialchars($not['subject']); ?></span>
                                <span style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($not['date'])); ?></span>
                            </div>
                            <div class="card-body">
                                <h3 style="font-size: 16px; margin-bottom: 8px; color: var(--text-primary);"><?php echo htmlspecialchars($not['title']); ?></h3>
                                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($not['content'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                    <div class="form-group">
                        <label for="noticeSub">Target Course</label>
                        <select name="subject" id="noticeSub" class="form-control">
                            <option value="Database Management Systems">Database Management Systems</option>
                            <option value="Advanced Database Systems">Advanced Database Systems</option>
                            <option value="All Assigned Classes">All My Assigned Classes</option>
                        </select>
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
