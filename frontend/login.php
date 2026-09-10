<?php
// frontend/login.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    redirect('/login.php');
}

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    $roleId = (int)($user['role_id'] ?? 4);
    $redirectMap = [
        1 => '/super-admin/dashboard.php',
        2 => '/admin/dashboard.php',
        3 => '/faculty/dashboard.php',
        4 => '/student/dashboard.php'
    ];
    redirect($redirectMap[$roleId] ?? '/dashboard.php');
}

$error = '';
$email = sanitize($_GET['email'] ?? '');
if (empty($email) && isset($_GET['role'])) {
    $roleName = strtolower(trim($_GET['role']));
    $db = getDbConnection();
    if ($db) {
        $stmt = $db->prepare("SELECT u.email FROM users u JOIN roles r ON u.role_id = r.id WHERE LOWER(r.name) = ? OR LOWER(r.display_name) = ? ORDER BY u.id ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $roleName, $roleName);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $email = $row['email'];
            }
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
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
            $_SESSION['auth_token'] = $authToken;
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['user'] = $userRow;
            
            if ($remember) {
                setcookie('remember_token', $authToken, time() + 86400 * 30, '/');
            }
            
            $roleId = (int)$userRow['role_id'];
            $redirectMap = [
                1 => '/super-admin/dashboard.php',
                2 => '/admin/dashboard.php',
                3 => '/faculty/dashboard.php',
                4 => '/student/dashboard.php'
            ];
            $redirect = $redirectMap[$roleId] ?? '/student/dashboard.php';
            redirect($redirect);
        } else {
            $error = 'Invalid email address or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student & Universal Login - StudentOS AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="index.php" class="auth-back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>StudentOS AI</span>
                </a>
                <div class="auth-role-badge auth-badge-student">
                    <i class="fas fa-user-graduate"></i> Student & Universal Portal
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to access your courses, study planner, and AI assistant</p>
            </div>

            <!-- Role Selector Tabs -->
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="auth-form" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
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
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="Show/Hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In to Account
                </button>
            </form>

            <div style="text-align: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13px;">
                <span style="color: var(--text-secondary);">New to StudentOS AI?</span>
                <a href="register.php" style="color: var(--primary); font-weight: 700; margin-left: 4px;">
                    <i class="fas fa-user-plus"></i> Register as Student
                </a>
            </div>
            
            <div class="auth-footer">
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                    <i class="fas fa-shield-alt"></i> Institutional accounts are provisioned by University Administration.
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
    </script>
</body>
</html>
