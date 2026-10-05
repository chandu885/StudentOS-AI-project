<?php
// frontend/student/login.php - Dedicated Student Portal Login
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    redirect('/index.php');
}

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    $roleId = (int)($user['role_id'] ?? 4);
    if ($roleId === 4) {
        redirect('/student/dashboard.php');
    } else {
        redirect(getDashboardUrl($roleId));
    }
}

$error = '';
$email = sanitize($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter your student email and password.';
    } else {
        $apiUrl = API_URL . '/auth.php?path=login';
        $data = ['email' => $email, 'password' => $password];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $loginSuccess = false;
        $userRow = null;
        $authToken = null;
        $sessionToken = null;

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (!empty($result['success']) && !empty($result['user'])) {
                $userRow = $result['user'];
                $authToken = $result['token'] ?? bin2hex(random_bytes(32));
                $sessionToken = $result['session_token'] ?? bin2hex(random_bytes(32));
                $loginSuccess = true;
            }
        }

        // Direct database authentication fallback
        if (!$loginSuccess) {
            $db = getDbConnection();
            if ($db) {
                $stmt = $db->prepare("SELECT u.*, r.display_name as role_name, LOWER(r.name) as role_slug FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? AND u.is_active = 1 AND u.deleted_at IS NULL LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $dbUser = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($dbUser && password_verify($password, $dbUser['password_hash'])) {
                        $userRow = $dbUser;
                        unset($userRow['password_hash']);
                        $authToken = bin2hex(random_bytes(32));
                        $sessionToken = bin2hex(random_bytes(32));
                        $loginSuccess = true;

                        // Record user session
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';
                        $sessStmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, last_activity, expires_at) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))");
                        if ($sessStmt) {
                            $sessStmt->bind_param("isss", $dbUser['id'], $sessionToken, $ip, $ua);
                            $sessStmt->execute();
                            $sessStmt->close();
                        }
                    }
                }
            }
        }

        if ($loginSuccess && $userRow) {
            $roleId = (int)($userRow['role_id'] ?? 0);
            if ($roleId === 4 || $roleId === 1) {
                $_SESSION['auth_token'] = $authToken;
                $_SESSION['session_token'] = $sessionToken;
                $_SESSION['user'] = $userRow;
                
                if ($remember) {
                    setcookie('remember_token', $authToken, time() + 86400 * 30, '/');
                }
                
                $redirect = ($roleId === 1) ? '/super-admin/dashboard.php' : '/student/dashboard.php';
                redirect($redirect);
            } else {
                $error = 'Access Restricted: This login portal is reserved for Students. Faculty and Administrators should log in through their respective portals.';
            }
        } else {
            $error = 'Invalid student email address or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Student Portal Login - StudentOS AI</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/images/favicon.svg')); ?>">
    <link rel="alternate icon" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/images/favicon.ico')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/icons/all.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/normalize.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/variables.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/reset.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/global.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/components.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/student/login.css')); ?>">

    <!-- Immediate Theme Initialization to Prevent FOUC -->
    <script>
        (function() {
            var saved = localStorage.getItem('studentos_theme') || 'day';
            var theme = (saved === 'auto' || saved === 'middle') ? 'deep' : saved;
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="<?php echo htmlspecialchars(resolveAssetUrl('/assets/js/theme.js')); ?>"></script>
</head>
<body class="auth-page page-student-login">
    <div class="auth-container">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; max-width: 460px; margin-bottom: 16px;">
            <a href="../index.php" class="auth-back-link" style="margin-bottom: 0;">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
            <div class="theme-switcher-pill" role="radiogroup" aria-label="Select Color Theme">
                <button type="button" class="theme-btn" data-theme-val="day" title="☀ Day Mode" aria-label="Day Mode">
                    <span class="theme-icon">☀</span>
                </button>
                <button type="button" class="theme-btn" data-theme-val="deep" title="◐ Deep Mode" aria-label="Deep Mode">
                    <span class="theme-icon">◐</span>
                </button>
                <button type="button" class="theme-btn" data-theme-val="night" title="☾ Night Mode" aria-label="Night Mode">
                    <span class="theme-icon">☾</span>
                </button>
            </div>
        </div>

        <div class="auth-card" style="border-top: 3px solid var(--primary);">
            <div class="auth-header">
                <a href="../index.php" class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>StudentOS AI</span>
                </a>
                <div class="auth-role-badge auth-badge-student">
                    <i class="fas fa-user-graduate"></i> Student Academic Portal
                </div>
                <h1>Student Sign In</h1>
                <p>Access your classes, study roadmap, grades, and AI study companion</p>
            </div>


            

            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="auth-form" id="studentLoginForm">
                <div class="form-group">
                    <label for="email">Student Institutional Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="student@gmail.com" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                               placeholder="Enter your student password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="Show/Hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span>Remember credentials</span>
                    </label>
                    <a href="../forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Launch Student Dashboard
                </button>
            </form>

            <div style="text-align: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13px;">
                <span style="color: var(--text-secondary);">New student?</span>
                <a href="../register.php" style="color: var(--primary); font-weight: 700; margin-left: 4px;">
                    <i class="fas fa-user-plus"></i> Register an Account
                </a>
            </div>
            
            <div class="auth-footer">
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                    <i class="fas fa-shield-alt"></i> Student accounts are provisioned by Institutional Administration.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function fillLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
