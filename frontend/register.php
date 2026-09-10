<?php
// frontend/register.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

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
$success = '';
$formData = [];

$regSummary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'department' => strtoupper(sanitize($_POST['department'] ?? 'BCA')),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'student_id' => strtoupper(sanitize($_POST['student_id'] ?? '')),
        'semester' => sanitize($_POST['semester'] ?? '1'),
        'roll_number' => strtoupper(sanitize($_POST['roll_number'] ?? '')),
    ];
    
    // Validate required fields
    $errors = [];
    foreach (['first_name', 'last_name', 'email', 'department', 'password', 'student_id', 'semester'] as $field) {
        if (empty($formData[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    if (!in_array($formData['department'], ['BBA', 'BCA'])) {
        $errors[] = 'Department must be either BBA or BCA';
    }
    
    if ($formData['password'] !== $formData['password_confirm']) {
        $errors[] = 'Passwords do not match';
    }
    
    if (strlen($formData['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $formData['password']) || !preg_match('/[a-z]/', $formData['password']) || !preg_match('/[0-9]/', $formData['password']) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $formData['password'])) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (e.g. Student@123)';
    }
    
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format';
    }
    
    if (empty($errors)) {
        // Build summary for popup modal
        $regSummary = [
            'name' => $formData['first_name'] . ' ' . $formData['last_name'],
            'email' => $formData['email'],
            'department' => $formData['department'],
            'student_id' => $formData['student_id'],
            'roll_number' => !empty($formData['roll_number']) ? $formData['roll_number'] : $formData['student_id'],
            'semester' => $formData['semester']
        ];

        // Attempt API call to register
        $response = apiCall('/auth.php?path=register', 'POST', $formData);
        
        if (isset($response['success']) && $response['success']) {
            $success = 'Registration successful! Your student account has been registered.';
            $formData = [];
        } else {
            // Direct database registration fallback
            $registeredDirectly = false;
            $db = getDbConnection();
            if ($db) {
                // Check duplicate email
                $chkEmail = $db->prepare("SELECT id FROM users WHERE email = ?");
                if ($chkEmail) {
                    $chkEmail->bind_param("s", $formData['email']);
                    $chkEmail->execute();
                    if ($chkEmail->get_result()->num_rows > 0) {
                        $error = 'Email is already registered.';
                        $chkEmail->close();
                    } else {
                        $chkEmail->close();
                        // Check duplicate student_id
                        $chkStu = $db->prepare("SELECT id FROM student_profiles WHERE student_id = ?");
                        if ($chkStu) {
                            $chkStu->bind_param("s", $formData['student_id']);
                            $chkStu->execute();
                            if ($chkStu->get_result()->num_rows > 0) {
                                $error = 'Student ID is already registered.';
                                $chkStu->close();
                            } else {
                                $chkStu->close();
                                // Create user without phone
                                $passHash = password_hash($formData['password'], PASSWORD_BCRYPT);
                                $uIns = $db->prepare("INSERT INTO users (role_id, email, password_hash, first_name, last_name, is_active, created_at, updated_at) VALUES (4, ?, ?, ?, ?, 1, NOW(), NOW())");
                                if ($uIns) {
                                    $uIns->bind_param("ssss", $formData['email'], $passHash, $formData['first_name'], $formData['last_name']);
                                    if ($uIns->execute()) {
                                        $newUserId = $uIns->insert_id;
                                        $uIns->close();

                                        // Lookup department_id
                                        $deptId = null;
                                        $deptStmt = $db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
                                        if ($deptStmt) {
                                            $deptStmt->bind_param("s", $formData['department']);
                                            $deptStmt->execute();
                                            $dRow = $deptStmt->get_result()->fetch_assoc();
                                            if ($dRow) {
                                                $deptId = (int)$dRow['id'];
                                            }
                                            $deptStmt->close();
                                        }

                                        $spIns = $db->prepare("INSERT INTO student_profiles (user_id, student_id, department, department_id, semester, roll_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                                        if ($spIns) {
                                            $roll = !empty($formData['roll_number']) ? $formData['roll_number'] : $formData['student_id'];
                                            $semVal = (string)$formData['semester'];
                                            $spIns->bind_param("ississ", $newUserId, $formData['student_id'], $formData['department'], $deptId, $semVal, $roll);
                                            $spIns->execute();
                                            $spIns->close();
                                        }
                                        $registeredDirectly = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($registeredDirectly) {
                $success = 'Registration successful! Your student account has been registered.';
                $formData = [];
            } elseif (empty($error)) {
                $error = $response['error'] ?? 'Registration failed. Please verify your details.';
            }
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Student Registration - StudentOS AI</title>
    <?php if (!empty($success)): ?>
    <meta http-equiv="refresh" content="15;url=student/login.php">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="index.php" class="auth-back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-card auth-card-large">
            <div class="auth-header">
                <a href="index.php" class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>StudentOS AI</span>
                </a>
                <div class="auth-role-badge auth-badge-student">
                    <i class="fas fa-user-graduate"></i> Student Registration
                </div>
                <h1>Student Registration</h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1.5px solid var(--success); color: var(--text-primary); padding: 18px 20px; border-radius: var(--radius-lg); margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start; gap: 14px;">
                        <i class="fas fa-check-circle" style="font-size: 24px; color: var(--success); margin-top: 2px;"></i>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 15px; color: var(--success); margin-bottom: 6px;">User Registration Successful!</div>
                            <div style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5;">
                                <?php echo htmlspecialchars($success); ?> You will be automatically redirected to the student login page in <strong id="redirectCountdown" style="color: var(--primary); font-size: 16px;">15</strong> seconds.
                            </div>
                            <div style="margin-top: 12px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <a href="student/login.php" class="btn btn-primary" style="font-size: 13px; padding: 7px 16px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-sign-in-alt"></i> Sign In to Student Portal Now
                                </a>
                                <span style="font-size: 12px; color: var(--text-muted);">(Auto redirecting in <span id="redirectCountdownText">15s</span>)</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="auth-form" id="registerForm">
                <!-- Section 1: Personal & Contact Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="first_name" name="first_name" class="form-control"
                                   placeholder="First name" 
                                   value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="last_name" name="last_name" class="form-control"
                                   placeholder="Last name" 
                                   value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <!-- Email Address -->
                <div class="form-group">
                    <label for="email">Student Email Address <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="student@example.com" 
                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                               required>
                    </div>
                </div>

                <!-- Student ID & Roll Number -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id">Student ID / Roll Code <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                            <input type="text" id="student_id" name="student_id" class="form-control"
                                   placeholder="e.g. STU-2026-001" 
                                   value="<?php echo htmlspecialchars($formData['student_id'] ?? ''); ?>" 
                                   style="text-transform: uppercase;"
                                   oninput="this.value = this.value.toUpperCase()"
                                   autocomplete="off"
                                   required>
                        </div>
                        <small style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">Must be entered in CAPITAL letters (auto-uppercased)</small>
                    </div>
                    <div class="form-group">
                        <label for="roll_number">Class Roll Number</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-hashtag"></i></span>
                            <input type="text" id="roll_number" name="roll_number" class="form-control"
                                   placeholder="e.g. 24" 
                                   value="<?php echo htmlspecialchars($formData['roll_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Department & Semester -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-building"></i></span>
                            <select id="department" name="department" class="form-control" style="padding-left: 40px;" required>
                                <option value="">Select Department</option>
                                <option value="BBA" <?php echo (($formData['department'] ?? '') === 'BBA') ? 'selected' : ''; ?>>BBA (Bachelor of Business Administration)</option>
                                <option value="BCA" <?php echo (($formData['department'] ?? 'BCA') === 'BCA') ? 'selected' : ''; ?>>BCA (Bachelor of Computer Applications)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="semester">Current Semester <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-graduation-cap"></i></span>
                            <select id="semester" name="semester" class="form-control" style="padding-left: 40px;" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                        <?php echo (($formData['semester'] ?? '1') == $i) ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password and Confirm Password -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control has-toggle"
                                   placeholder="Enter password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="Show/Hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control has-toggle"
                                   placeholder="Re-enter password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password_confirm', this)" title="Show/Hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div> 
                
                <div class="form-group" style="margin-top: 8px;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" id="terms" required checked>
                        <span>I agree to the StudentOS AI Academic Honor Code and Terms of Service</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
                    <i class="fas fa-user-plus"></i> Complete Student Registration
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="student/login.php" style="font-weight: 600;">Student Sign In</a> | <a href="login.php">Staff Login</a></p>
            </div>
        </div>
    </div>
    
    <?php if (!empty($success)): ?>
    <!-- "User Registration Successful" Pop-up Modal Dialog centered globally across all screens and devices -->
    <div id="regSuccessModal" class="modal-backdrop show" style="position: fixed; inset: 0; top: 0; left: 0; right: 0; bottom: 0; width: 100%; width: 100vw; height: 100%; height: 100vh; height: 100dvh; min-height: 100vh; min-height: 100dvh; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 999999; background: rgba(10, 15, 30, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 16px; margin: 0; box-sizing: border-box;">
        <div class="modal-card" style="margin: auto !important; max-width: 520px; width: 100%; max-height: calc(100dvh - 32px); max-height: calc(100vh - 32px); overflow-y: auto; -webkit-overflow-scrolling: touch; border-radius: var(--radius-xl); border: 1.5px solid rgba(34, 197, 94, 0.45); box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.75), 0 0 35px rgba(34, 197, 94, 0.25); text-align: center; padding: 0; background: var(--bg-card); position: relative;">
            <!-- Header Banner -->
            <div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.18), rgba(16, 185, 129, 0.06)); padding: 26px 24px 18px; border-bottom: 1px solid var(--border-color);">
                <div style="width: 66px; height: 66px; margin: 0 auto 12px; border-radius: 50%; background: linear-gradient(135deg, #22C55E, #16A34A); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 30px; box-shadow: 0 10px 25px rgba(34, 197, 94, 0.4);">
                    <i class="fas fa-check"></i>
                </div>
                <h2 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">User Registration Successful!</h2>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin: 0;">Your student credentials and profile have been registered in the database.</p>
            </div>

            <div class="modal-body" style="padding: 22px 24px;">
                <?php if (!empty($regSummary)): ?>
                <!-- Summary Details Grid -->
                <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px; margin-bottom: 20px; text-align: left; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; font-size: 12.5px;">
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Student Name</span>
                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($regSummary['name'] ?? ''); ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Student ID</span>
                        <strong style="color: var(--primary);"><?php echo htmlspecialchars($regSummary['student_id'] ?? ''); ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Department</span>
                        <span class="badge badge-primary" style="font-weight: 700; background: rgba(37, 99, 235, 0.15); color: var(--primary); padding: 3px 8px; border-radius: 4px;"><?php echo htmlspecialchars($regSummary['department'] ?? 'BCA'); ?></span>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Roll Number</span>
                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($regSummary['roll_number'] ?? ''); ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Current Semester</span>
                        <span class="badge badge-info">Semester <?php echo htmlspecialchars($regSummary['semester'] ?? '1'); ?></span>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Email</span>
                        <span style="color: var(--text-secondary); word-break: break-all;"><?php echo htmlspecialchars($regSummary['email'] ?? ''); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 15-Second Countdown Display -->
                <div style="background: rgba(37, 99, 235, 0.08); border: 1.5px dashed var(--primary); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 14px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); flex-shrink: 0;">
                            <span id="popupCountdownNumber" style="font-size: 21px; line-height: 1;">15</span>
                            <span style="font-size: 8.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">SEC</span>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 14px; font-weight: 700; color: var(--text-primary);">Automatic Portal Redirect</div>
                            <div style="font-size: 12.5px; color: var(--text-secondary);">Redirecting to student login in <strong id="popupCountdownSec" style="color: var(--primary); font-size: 14px;">15</strong> seconds...</div>
                        </div>
                    </div>
                    <!-- Animated Progress Bar -->
                    <div style="width: 100%; height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; margin-top: 12px; overflow: hidden;">
                        <div id="popupProgressBar" style="width: 100%; height: 100%; background: linear-gradient(90deg, var(--primary), #10B981); transition: width 1s linear;"></div>
                    </div>
                </div>

                <!-- Modal Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="student/login.php" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center; padding: 11px; font-weight: 700; font-size: 14.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-sign-in-alt"></i> Sign In to Student Portal Now
                    </a>
                    <button type="button" class="btn btn-outline" onclick="closeSuccessModal()" style="font-size: 12px; padding: 7px;">
                        <i class="fas fa-pause"></i> Stay on this page (Pause redirect)
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
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
        
        // Auto-uppercase Student ID / Roll Code
        const studentIdInput = document.getElementById('student_id');
        if (studentIdInput) {
            studentIdInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
            studentIdInput.addEventListener('blur', function() {
                this.value = this.value.toUpperCase().trim();
            });
        }

        // 15-second automatic countdown redirect to student/login.php with Pop-up Modal
        let redirectTimer = null;
        let secondsRemaining = 15;
        const totalDuration = 15;

        function startRedirectTimer() {
            const countdownEl = document.getElementById('redirectCountdown');
            const countdownTextEl = document.getElementById('redirectCountdownText');
            const popupNumEl = document.getElementById('popupCountdownNumber');
            const popupSecEl = document.getElementById('popupCountdownSec');
            const popupBarEl = document.getElementById('popupProgressBar');

            redirectTimer = setInterval(() => {
                secondsRemaining--;
                if (countdownEl) countdownEl.textContent = secondsRemaining;
                if (countdownTextEl) countdownTextEl.textContent = secondsRemaining + 's';
                if (popupNumEl) popupNumEl.textContent = secondsRemaining;
                if (popupSecEl) popupSecEl.textContent = secondsRemaining;
                if (popupBarEl) {
                    const pct = Math.max(0, (secondsRemaining / totalDuration) * 100);
                    popupBarEl.style.width = pct + '%';
                }
                if (secondsRemaining <= 0) {
                    clearInterval(redirectTimer);
                    window.location.href = 'student/login.php';
                }
            }, 1000);
        }

        function closeSuccessModal() {
            if (redirectTimer) {
                clearInterval(redirectTimer);
            }
            const modal = document.getElementById('regSuccessModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
        }

        // Initialize countdown timer if success message / pop-up is active
        if (document.getElementById('regSuccessModal') || document.getElementById('redirectCountdown')) {
            startRedirectTimer();
        }

        // Client-side quick validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (studentIdInput) {
                studentIdInput.value = studentIdInput.value.toUpperCase().trim();
            }
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('password_confirm').value;
            if (p1 !== p2) {
                e.preventDefault();
                alert('Passwords do not match! Please check and confirm your password.');
                return false;
            }
            if (p1.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            if (!/[A-Z]/.test(p1) || !/[a-z]/.test(p1) || !/[0-9]/.test(p1) || !/[!@#$%^&*(),.?":{}|<>]/.test(p1)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (e.g. Student@123).');
                return false;
            }
        });
    </script>
</body>
</html>
