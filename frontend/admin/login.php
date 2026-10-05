<?php
// frontend/admin/login.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    redirect('/index.php');
}

// Redirect if already logged in as admin
if (isLoggedIn()) {
    $user = getCurrentUser();
    $roleId = (int)($user['role_id'] ?? 0);
    if ($roleId === 2) {
        redirect('/admin/dashboard.php');
    } elseif ($roleId === 1) {
        redirect('/super-admin/dashboard.php');
    }
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter your administrator credentials.';
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
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $roleId = (int)($result['user']['role_id'] ?? 0);
            
            // Allow Admin (2) and Super Admin (1)
            if ($roleId === 2 || $roleId === 1) {
                $_SESSION['auth_token'] = $result['token'];
                $_SESSION['session_token'] = $result['session_token'];
                $_SESSION['user'] = $result['user'];
                
                if ($remember) {
                    setcookie('remember_token', $result['token'], time() + 86400 * 30, '/');
                }
                
                $redirect = ($roleId === 1) ? '/super-admin/dashboard.php' : '/admin/dashboard.php';
                redirect($redirect);
            } else {
                $error = 'Access Restricted: This portal is reserved for Institutional Administrators. Student & Faculty accounts cannot access this console.';
            }
        } else {
            $result = json_decode($response, true);
            $error = $result['error'] ?? 'Invalid administrator credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Administrator Control Portal - StudentOS AI</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/images/favicon.svg')); ?>">
    <link rel="alternate icon" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/images/favicon.ico')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/icons/all.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/normalize.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/variables.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/reset.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/global.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/components.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/admin/login.css')); ?>">

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
<body class="auth-page page-admin-login">
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

        <div class="auth-card" style="border-top: 3px solid #F59E0B;">
            <div class="auth-header">
                <a href="../index.php" class="auth-logo">
                    <i class="fas fa-graduation-cap" style="color: #F59E0B;"></i>
                    <span>StudentOS AI</span>
                </a>
                <div class="auth-role-badge auth-badge-admin">
                    <i class="fas fa-shield-alt"></i> Institutional Admin Portal
                </div>
                <h1>Administrator Sign In</h1>
                <p>Academic operations, course scheduling, admissions, and institutional reports</p>
            </div>

            <!-- Role Selector Tabs -->
            <div class="auth-role-tabs">
                <a href="../faculty/login.php" class="auth-role-tab">
                    <i class="fas fa-chalkboard-teacher"></i> Faculty
                </a>
                <a href="login.php" class="auth-role-tab active admin">
                    <i class="fas fa-shield-alt"></i> Admin
                </a>
                <a href="../super-admin/login.php" class="auth-role-tab">
                    <i class="fas fa-crown"></i> Super Admin
                </a>
            </div>
            
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="auth-form" id="adminLoginForm">
                <div class="form-group">
                    <label for="email">Administrator Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="admin@gmail.com" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                               placeholder="Enter admin password" required>
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
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="background: linear-gradient(135deg, #F59E0B, #D97706); border: none;">
                    <i class="fas fa-shield-alt"></i> Access Admin Console
                </button>
            </form>

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
