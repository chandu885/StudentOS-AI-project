<?php
// frontend/reset-password.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$token = sanitize($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = '';
$error = '';
$isResetSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $error = 'Invalid or missing reset token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $res = apiCall('/auth.php?path=reset-password', 'POST', [
            'token' => $token,
            'password' => $password
        ]);

        if (!empty($res['success'])) {
            $isResetSuccess = true;
            $message = 'Password has been reset successfully! You can now log in.';
        } else {
            $error = $res['error'] ?? 'Failed to reset password. The link may have expired.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - StudentOS AI</title>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;">
        <div class="auth-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 40px; width: 100%; max-width: 440px; box-shadow: var(--shadow-xl);">
            <div class="auth-header" style="text-align: center; margin-bottom: 28px;">
                <a href="index.php" class="auth-logo" style="display: inline-flex; align-items: center; gap: 8px; font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                    <i class="fas fa-graduation-cap" style="color: var(--primary);"></i>
                    <span>StudentOS AI</span>
                </a>
                <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 6px;">New Password</h1>
                <p style="font-size: 13px; color: var(--text-secondary);">Enter and confirm your new secure password</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; color: var(--danger); font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); border-radius: var(--radius-md); padding: 12px 16px; color: var(--success); font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isResetSuccess): ?>
                <a href="login.php" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                    <i class="fas fa-sign-in-alt"></i> Proceed to Login
                </a>
            <?php else: ?>
                <form method="POST" action="reset-password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="password" style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">New Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="confirm_password" style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required minlength="8">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                        <i class="fas fa-check"></i> Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-secondary);">
                Back to <a href="login.php" style="color: var(--primary); font-weight: 600;">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
