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

// Fetch active courses for degree selection directly from database
$coursesList = [];
$db = getDbConnection();
if ($db) {
    $res = $db->query("SELECT id, name, code, degree_type, department_id FROM courses WHERE status = 'active' ORDER BY name ASC");
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $coursesList[] = $r;
        }
    }
}
$courses = $coursesList;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    if (!$deptId && $courseId) {
        foreach ($courses as $c) {
            if ((int)($c['id'] ?? 0) === $courseId && !empty($c['department_id'])) {
                $deptId = (int)$c['department_id'];
                break;
            }
        }
    }
    if (!$deptId) {
        $deptId = ($courseId === 1) ? 4 : 1;
    }

    $formData = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'student_id' => strtoupper(sanitize($_POST['student_id'] ?? '')),
        'department_id' => $deptId,
        'course_id' => $courseId,
        'semester' => sanitize($_POST['semester'] ?? ''),
        'section' => 'A',
        'roll_number' => strtoupper(sanitize($_POST['roll_number'] ?? '')),
    ];
    
    // Validate required fields (department and section removed)
    $errors = [];
    foreach (['first_name', 'last_name', 'email', 'password', 'student_id', 'course_id', 'semester'] as $field) {
        if (empty($formData[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
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
        // API call to register
        $response = apiCall('/auth.php?path=register', 'POST', $formData);
        
        if (isset($response['success']) && $response['success']) {
            $success = 'Registration successful! Your account has been registered. You may now sign in.';
            $formData = [];
        } else {
            $error = $response['error'] ?? 'Registration failed. Please verify your details.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Fetch courses based on selected department (AJAX)
if (isset($_GET['department_id']) && is_numeric($_GET['department_id'])) {
    $deptId = (int)$_GET['department_id'];
    $filteredCourses = [];
    $resp = apiCall('/academic.php?path=courses&department_id=' . $deptId, 'GET');
    if (is_array($resp) && !empty($resp['courses'])) {
        $filteredCourses = $resp['courses'];
    } else {
        $db = getDbConnection();
        if ($db) {
            $stmt = $db->prepare("SELECT id, name, code, degree_type FROM courses WHERE department_id = ? AND status = 'active' ORDER BY name ASC");
            if ($stmt) {
                $stmt->bind_param("i", $deptId);
                $stmt->execute();
                $filteredCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(!empty($filteredCourses) ? $filteredCourses : $coursesList);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - StudentOS AI</title>
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
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?> <a href="student/login.php" style="font-weight: 700; text-decoration: underline; margin-left: 6px;">Sign In to Student Portal</a></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="auth-form" id="registerForm">
                <!-- Section 1: Account Credentials -->
        

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
                
                <div class="form-group">
                    <label for="email">Student Email <span style="color: var(--danger);">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="chandu@gmail.com" 
                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                               required>
                    </div>
                </div>
                

                

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

            
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Degree Course Program <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-graduation-cap"></i></span>
                            <select id="course_id" name="course_id" class="form-control" style="padding-left: 40px;" required>
                                <option value="">Select Degree Course</option>
                                <?php if (!empty($courses)): ?>
                                    <?php foreach ($courses as $course): 
                                        $cId = $course['id'] ?? '';
                                        $code = strtoupper(trim($course['code'] ?? ''));
                                        $name = trim($course['name'] ?? ($course['course_name'] ?? ('Course #' . $cId)));
                                        // Display 'BBA' and 'BCA' as requested
                                        if (in_array($code, ['BBA', 'BCA'])) {
                                            $displayLabel = $code;
                                        } elseif ($name === $code || empty($code)) {
                                            $displayLabel = $name;
                                        } else {
                                            $displayLabel = $name . ' (' . $code . ')';
                                        }
                                        $selected = ((string)($formData['course_id'] ?? '') === (string)$cId) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($cId); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($displayLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1" <?php echo ($formData['course_id'] ?? '') == 1 ? 'selected' : ''; ?>>BBA</option>
                                    <option value="2" <?php echo ($formData['course_id'] ?? '') == 2 ? 'selected' : ''; ?>>BCA</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="semester">Current Semester <span style="color: var(--danger);">*</span></label>
                        <select id="semester" name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                    <?php echo ($formData['semester'] ?? '') == $i ? 'selected' : ''; ?>>
                                    Semester <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                            <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control has-toggle"
                                   placeholder="Min 8 chars with uppercase, digit & symbol" required>
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
