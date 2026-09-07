<?php
// frontend/admin/notices.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Campus-wide official circular published!';
}

$notices = [
    ['id' => 1, 'title' => 'Final Exam Fee Submission Deadline Extended', 'target' => 'All Students', 'priority' => 'urgent', 'date' => '2026-03-05'],
    ['id' => 2, 'title' => 'Annual Tech Fest "InnoVision 2026" Registrations Open', 'target' => 'Campus Wide', 'priority' => 'general', 'date' => '2026-03-02'],
    ['id' => 3, 'title' => 'Library Extended Hours during Midterms', 'target' => 'All Students & Faculty', 'priority' => 'general', 'date' => '2026-02-28']
];
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

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-newspaper"></i> Published Circulars</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Circular Title</th>
                                        <th>Audience</th>
                                        <th>Priority</th>
                                        <th>Date Issued</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notices as $n): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($n['title']); ?></strong></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($n['target']); ?></span></td>
                                            <td>
                                                <span class="badge badge-<?php echo $n['priority'] === 'urgent' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($n['priority']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($n['date'])); ?></td>
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
                        <label for="nTitle">Title</label>
                        <input type="text" name="title" id="nTitle" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="nTarget">Audience</label>
                            <select name="target" id="nTarget" class="form-control">
                                <option value="All Students">All Students</option>
                                <option value="Faculty Only">Faculty Only</option>
                                <option value="Campus Wide">Campus Wide</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nPrio">Priority</label>
                            <select name="priority" id="nPrio" class="form-control">
                                <option value="general">General</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nText">Circular Content</label>
                        <textarea name="content" id="nText" class="form-control" rows="4" required></textarea>
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
