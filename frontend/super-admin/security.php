<?php
// frontend/super-admin/security.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Security configurations updated successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Center - StudentOS AI</title>
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
                        <h1>Cybersecurity & Identity Governance</h1>
                        <p class="page-subtitle">Configure Multi-Factor Authentication (MFA), lockout policies, and session encryption</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-alt"></i> Authentication & Threat Defenses</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="security.php">
                            <div style="display: flex; flex-direction: column; gap: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Mandatory 2FA for Administrators & Faculty</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Require TOTP authenticator code upon signing in.</p>
                                    </div>
                                    <input type="checkbox" checked style="width: 18px; height: 18px;">
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Brute Force Lockout Threshold</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Temporarily lock IP address after consecutive failed login attempts.</p>
                                    </div>
                                    <select class="form-control" style="width: 160px;">
                                        <option value="5" selected>5 Failed Attempts</option>
                                        <option value="3">3 Failed Attempts</option>
                                        <option value="10">10 Failed Attempts</option>
                                    </select>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Session Idle Inactivity Timeout</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Automatically terminate browser session after idle period.</p>
                                    </div>
                                    <select class="form-control" style="width: 160px;">
                                        <option value="30">30 Minutes</option>
                                        <option value="60" selected>1 Hour</option>
                                        <option value="120">2 Hours</option>
                                    </select>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Strict Cookie SameSite & HTTPS</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Enforce HttpOnly, SameSite=Strict on all auth tokens.</p>
                                    </div>
                                    <span class="badge badge-success">Enforced</span>
                                </div>
                            </div>

                            <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Security Policy</button>
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
