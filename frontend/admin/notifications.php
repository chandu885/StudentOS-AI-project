<?php
// frontend/admin/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Broadcast notification dispatched successfully to all target users!';
}

$history = [
    ['title' => 'Semester 6 Timetable Finalized', 'target' => 'All Students', 'time' => '2 days ago', 'status' => 'Delivered'],
    ['title' => 'Midterm Question Submission Deadline', 'target' => 'Faculty Only', 'time' => '4 days ago', 'status' => 'Delivered']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Notifications - StudentOS AI</title>
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
                        <h1>Institutional Notifications & Broadcasts</h1>
                        <p class="page-subtitle">Send high-priority alerts to student and faculty dashboards</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bullhorn"></i> Dispatch New Notification</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="notifications.php">
                                <div class="form-group">
                                    <label for="bTitle">Notification Title</label>
                                    <input type="text" name="title" id="bTitle" class="form-control" placeholder="e.g. Campus Wi-Fi Maintenance Window" required>
                                </div>
                                <div class="form-group">
                                    <label for="bTarget">Recipient Audience</label>
                                    <select name="target" id="bTarget" class="form-control">
                                        <option value="all">Everyone (Students & Faculty)</option>
                                        <option value="students">Students Only</option>
                                        <option value="faculty">Faculty Only</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bMessage">Message Body</label>
                                    <textarea name="message" id="bMessage" class="form-control" rows="4" placeholder="Brief announcement text..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-paper-plane"></i> Broadcast Message
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Broadcast History</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php foreach ($history as $h): ?>
                                    <div style="padding: 14px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($h['title']); ?></strong>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($h['status']); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted);">
                                            <span>To: <?php echo htmlspecialchars($h['target']); ?></span>
                                            <span><?php echo htmlspecialchars($h['time']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
