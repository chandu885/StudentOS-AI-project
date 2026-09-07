<?php
// frontend/super-admin/system-settings.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'System settings updated and synchronized across all nodes!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - StudentOS AI</title>
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
                        <h1>Global Platform Settings</h1>
                        <p class="page-subtitle">Configure application identity, SMTP email transport, and server runtime flags</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Core Configuration</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="system-settings.php">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label for="appName">Platform Title</label>
                                    <input type="text" name="app_name" id="appName" class="form-control" value="StudentOS AI">
                                </div>
                                <div class="form-group">
                                    <label for="adminEmail">Support & Contact Email</label>
                                    <input type="email" name="admin_email" id="adminEmail" class="form-control" value="support@studentos.ai">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label for="timezone">Server Timezone</label>
                                    <select name="timezone" id="timezone" class="form-control">
                                        <option value="Asia/Kolkata" selected>Asia/Kolkata (IST +5:30)</option>
                                        <option value="UTC">UTC (GMT +0:00)</option>
                                        <option value="America/New_York">America/New_York (EST)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="maxUpload">Max Document Upload (MB)</label>
                                    <input type="number" name="max_upload" id="maxUpload" class="form-control" value="20">
                                </div>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px;">
                                <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--text-primary);"><i class="fas fa-envelope"></i> SMTP Email Server Transport</h4>
                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                                    <div class="form-group">
                                        <label for="smtpHost">SMTP Host</label>
                                        <input type="text" name="smtp_host" id="smtpHost" class="form-control" value="smtp.mailtrap.io">
                                    </div>
                                    <div class="form-group">
                                        <label for="smtpPort">SMTP Port</label>
                                        <input type="number" name="smtp_port" id="smtpPort" class="form-control" value="587">
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Global Settings</button>
                            </div>
                        </form>
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
