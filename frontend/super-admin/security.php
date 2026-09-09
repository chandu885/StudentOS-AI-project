<?php
// frontend/super-admin/security.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mfaRequired = isset($_POST['mfa_required']) ? '1' : '0';
    $lockoutThreshold = sanitize($_POST['lockout_threshold'] ?? '5');
    $sessionTimeout = sanitize($_POST['session_timeout'] ?? '60');

    if ($conn) {
        $settingsToSave = [
            'mfa_required' => [$mfaRequired, 'Require 2FA for administrators and faculty'],
            'brute_force_lockout_threshold' => [$lockoutThreshold, 'Lockout threshold for failed login attempts'],
            'session_idle_timeout_min' => [$sessionTimeout, 'Session inactivity timeout in minutes']
        ];
        foreach ($settingsToSave as $k => $v) {
            $stmt = $conn->prepare("INSERT INTO system_settings (`key`, `value`, `description`, `updated_at`) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
            if ($stmt) {
                $stmt->bind_param("sss", $k, $v[0], $v[1]);
                $stmt->execute();
                $stmt->close();
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $details = "Updated security policy: MFA={$mfaRequired}, Lockout={$lockoutThreshold}, Timeout={$sessionTimeout}m";
        $aud = $conn->prepare("INSERT INTO audit_logs (user_id, action, resource, details, ip_address) VALUES (?, 'SECURITY_SETTINGS_UPDATE', 'system_settings', ?, ?)");
        if ($aud) { $aud->bind_param("iss", $userId, $details, $ip); $aud->execute(); $aud->close(); }
    }
    $successMsg = 'Security configurations updated successfully and recorded in audit log!';
}

// Load current security settings
$secSettings = [];
if ($conn) {
    $res = $conn->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('mfa_required', 'brute_force_lockout_threshold', 'session_idle_timeout_min')");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $secSettings[$r['key']] = $r['value'];
        }
    }
}
$isMfa = ($secSettings['mfa_required'] ?? '1') === '1';
$lockoutVal = $secSettings['brute_force_lockout_threshold'] ?? '5';
$timeoutVal = $secSettings['session_idle_timeout_min'] ?? '60';
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
                                    <input type="checkbox" name="mfa_required" value="1" <?php echo $isMfa ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Brute Force Lockout Threshold</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Temporarily lock IP address after consecutive failed login attempts.</p>
                                    </div>
                                    <select name="lockout_threshold" class="form-control" style="width: 160px;">
                                        <option value="3" <?php echo $lockoutVal === '3' ? 'selected' : ''; ?>>3 Failed Attempts</option>
                                        <option value="5" <?php echo $lockoutVal === '5' ? 'selected' : ''; ?>>5 Failed Attempts</option>
                                        <option value="10" <?php echo $lockoutVal === '10' ? 'selected' : ''; ?>>10 Failed Attempts</option>
                                    </select>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                                    <div>
                                        <strong style="color: var(--text-primary); font-size: 14px;">Session Idle Inactivity Timeout</strong>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Automatically terminate browser session after idle period.</p>
                                    </div>
                                    <select name="session_timeout" class="form-control" style="width: 160px;">
                                        <option value="30" <?php echo $timeoutVal === '30' ? 'selected' : ''; ?>>30 Minutes</option>
                                        <option value="60" <?php echo $timeoutVal === '60' ? 'selected' : ''; ?>>1 Hour</option>
                                        <option value="120" <?php echo $timeoutVal === '120' ? 'selected' : ''; ?>>2 Hours</option>
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
