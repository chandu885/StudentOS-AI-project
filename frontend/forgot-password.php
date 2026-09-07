<?php
// frontend/forgot-password.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $res = apiCall('/auth.php?path=forgot-password', 'POST', ['email' => $email]);
        if (!empty($res['success'])) {
            $message = $res['message'] ?? 'Password reset instructions have been sent to your email.';
        } else {
            $error = $res['error'] ?? 'Unable to process your request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - StudentOS AI</title>
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
                <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 6px;">Reset Password</h1>
                <p style="font-size: 13px; color: var(--text-secondary);">Enter your account email to receive recovery instructions</p>
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

            <form method="POST" action="forgot-password.php">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="email" style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" placeholder="name@college.edu" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-secondary);">
                Remembered your password? <a href="login.php" style="color: var(--primary); font-weight: 600;">Sign in here</a>
            </div>
        </div>
    </div>
</body>
</html>
