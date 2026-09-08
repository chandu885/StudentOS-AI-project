<?php
// frontend/faculty/login.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    redirect('/faculty/login.php');
}

// Redirect if already logged in as faculty
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ((int)($user['role_id'] ?? 0) === 3) {
        redirect('/faculty/dashboard.php');
    } elseif ((int)($user['role_id'] ?? 0) === 1) {
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
        $error = 'Please enter your faculty email and password.';
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
            
            // Allow Faculty (3) and Super Admin (1)
            if ($roleId === 3 || $roleId === 1) {
                $_SESSION['auth_token'] = $result['token'];
                $_SESSION['session_token'] = $result['session_token'];
                $_SESSION['user'] = $result['user'];
                
                if ($remember) {
                    setcookie('remember_token', $result['token'], time() + 86400 * 30, '/');
                }
                
                redirect('/faculty/dashboard.php');
            } else {
                $error = 'Access Restricted: This portal is reserved exclusively for Faculty members. Please use the Student login portal.';
            }
        } else {
            $result = json_decode($response, true);
            $error = $result['error'] ?? 'Invalid faculty credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal Login - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="../index.php" class="auth-back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-card" style="border-top: 3px solid #22C55E;">
            <div class="auth-header">
                <a href="../index.php" class="auth-logo">
                    <i class="fas fa-graduation-cap" style="color: #22C55E;"></i>
                    <span>StudentOS AI</span>
                </a>
                <div class="auth-role-badge auth-badge-faculty">
                    <i class="fas fa-chalkboard-teacher"></i> Faculty & Staff Portal
                </div>
                <h1>Faculty Sign In</h1>
                <p>Access course grading, attendance registers, and academic syllabus</p>
            </div>

            <!-- Role Selector Tabs -->
            <div class="auth-role-tabs">
                <a href="../student/login.php" class="auth-role-tab">
                    <i class="fas fa-user-graduate"></i> Student
                </a>
                <a href="login.php" class="auth-role-tab active faculty">
                    <i class="fas fa-chalkboard-teacher"></i> Faculty
                </a>
                <a href="../admin/login.php" class="auth-role-tab">
                    <i class="fas fa-shield-alt"></i> Admin
                </a>
                <a href="../super-admin/login.php" class="auth-role-tab">
                    <i class="fas fa-crown"></i> Super Admin
                </a>
            </div>
            
            <!-- Demo Credentials Quick Selector -->
            <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: var(--radius-md); padding: 12px; margin-bottom: 18px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--success); letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fas fa-bolt"></i> Example Faculty Credentials</span>
                    <button type="button" class="btn btn-success" style="font-size: 11px; padding: 3px 8px; height: auto; background: var(--success); border: none;" onclick="fillLogin('faculty@gmail.com', 'Faculty@12345')">
                        Auto Fill
                    </button>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                    <div><strong>Email:</strong> <code style="color: var(--success);">faculty@gmail.com</code> (or <code>faculty@studentos.ai</code>)</div>
                    <div><strong>Password:</strong> <code style="color: var(--success);">Faculty@12345</code></div>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="auth-form" id="facultyLoginForm">
                <div class="form-group">
                    <label for="email">Institutional Faculty Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="faculty@gmail.com" 
                               value="<?php echo htmlspecialchars($email ?: 'faculty@gmail.com'); ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                               placeholder="Enter faculty password" required>
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
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="background: linear-gradient(135deg, #22C55E, #16A34A); border: none;">
                    <i class="fas fa-sign-in-alt"></i> Access Faculty Dashboard
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Not a faculty member? <a href="../student/login.php">Go to Student Login</a> • <a href="../register.php">Register Student</a></p>
                <div style="margin-top: 14px; font-size: 11.5px; color: var(--text-secondary);">
                    <i class="fas fa-shield-alt"></i> Administrative staff? Switch to <a href="../admin/login.php" style="color: #F59E0B; font-weight: 600;">Admin Portal</a>.
                </div>
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
