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

// Fetch active departments and courses directly from database
$departments = [];
$courses = [];
$regSummary = [];
$db = getDbConnection();
if ($db) {
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes && $dRes->num_rows > 0) {
        while ($d = $dRes->fetch_assoc()) {
            $departments[] = $d;
        }
    }
    
    $cRes = $db->query("SELECT id, name, code, degree_type, department_id FROM courses WHERE status = 'active' ORDER BY name ASC");
    if ($cRes && $cRes->num_rows > 0) {
        while ($c = $cRes->fetch_assoc()) {
            $courses[] = $c;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $deptId = (int)($_POST['department_id'] ?? 0);
    $section = strtoupper(trim(sanitize($_POST['section'] ?? 'A')));
    if (empty($section)) {
        $section = 'A';
    }
    $phone = sanitize($_POST['phone'] ?? '');

    // Auto-resolve department if not provided but course selected
    if (!$deptId && $courseId) {
        foreach ($courses as $c) {
            if ((int)($c['id'] ?? 0) === $courseId && !empty($c['department_id'])) {
                $deptId = (int)$c['department_id'];
                break;
            }
        }
    }
    // Auto-resolve course if not provided but department selected
    if (!$courseId && $deptId) {
        foreach ($courses as $c) {
            if ((int)($c['department_id'] ?? 0) === $deptId) {
                $courseId = (int)$c['id'];
                break;
            }
        }
    }
    if (!$deptId && !empty($departments)) {
        $deptId = (int)$departments[0]['id'];
    }

    $formData = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone' => $phone,
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'student_id' => strtoupper(sanitize($_POST['student_id'] ?? '')),
        'department_id' => $deptId,
        'course_id' => $courseId,
        'semester' => sanitize($_POST['semester'] ?? '1'),
        'section' => $section,
        'roll_number' => strtoupper(sanitize($_POST['roll_number'] ?? '')),
    ];
    
    // Validate required fields
    $errors = [];
    foreach (['first_name', 'last_name', 'email', 'phone', 'department_id', 'course_id', 'section', 'password', 'student_id', 'semester'] as $field) {
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
        // Build summary for popup modal
        $deptName = 'Department #' . $formData['department_id'];
        foreach ($departments as $d) {
            if ((int)$d['id'] === (int)$formData['department_id']) {
                $deptName = $d['name'] . ' (' . $d['code'] . ')';
                break;
            }
        }
        $courseName = 'Course #' . $formData['course_id'];
        foreach ($courses as $c) {
            if ((int)$c['id'] === (int)$formData['course_id']) {
                $courseName = $c['name'];
                break;
            }
        }
        $regSummary = [
            'name' => $formData['first_name'] . ' ' . $formData['last_name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'student_id' => $formData['student_id'],
            'roll_number' => !empty($formData['roll_number']) ? $formData['roll_number'] : $formData['student_id'],
            'department' => $deptName,
            'course' => $courseName,
            'semester' => $formData['semester'],
            'section' => $formData['section']
        ];

        // Attempt API call to register
        $response = apiCall('/auth.php?path=register', 'POST', $formData);
        
        if (isset($response['success']) && $response['success']) {
            $success = 'Registration successful! Your student account has been registered.';
            $formData = [];
        } else {
            // Direct database registration fallback
            $registeredDirectly = false;
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
                                // Create user with phone
                                $passHash = password_hash($formData['password'], PASSWORD_BCRYPT);
                                $uIns = $db->prepare("INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone, is_active, created_at, updated_at) VALUES (4, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                                if ($uIns) {
                                    $uIns->bind_param("sssss", $formData['email'], $passHash, $formData['first_name'], $formData['last_name'], $formData['phone']);
                                    if ($uIns->execute()) {
                                        $newUserId = $uIns->insert_id;
                                        $uIns->close();

                                        $spIns = $db->prepare("INSERT INTO student_profiles (user_id, student_id, department_id, course_id, semester, section, roll_number, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                                        if ($spIns) {
                                            $roll = !empty($formData['roll_number']) ? $formData['roll_number'] : $formData['student_id'];
                                            $spIns->bind_param("isiissss", $newUserId, $formData['student_id'], $formData['department_id'], $formData['course_id'], $formData['semester'], $formData['section'], $roll, $formData['phone']);
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
                            <div style="font-weight: 700; font-size: 15px; color: var(--success); margin-bottom: 6px;">Registration Successful!</div>
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

                <!-- Registration Success Pop-up Modal Dialog with 15-Second Countdown Timer -->
                <div id="regSuccessModal" class="modal-backdrop show" style="display: flex; opacity: 1; z-index: 99999; backdrop-filter: blur(8px); background: rgba(15, 23, 42, 0.82); position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; align-items: center; justify-content: center;">
                    <div class="modal-card" style="max-width: 540px; width: 92%; border-radius: var(--radius-xl); border: 1.5px solid rgba(34, 197, 94, 0.45); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 35px rgba(34, 197, 94, 0.2); text-align: center; padding: 0; overflow: hidden; transform: scale(1); background: var(--bg-card);">
                        <!-- Header Banner -->
                        <div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.18), rgba(16, 185, 129, 0.06)); padding: 26px 24px 18px; border-bottom: 1px solid var(--border-color);">
                            <div style="width: 66px; height: 66px; margin: 0 auto 12px; border-radius: 50%; background: linear-gradient(135deg, #22C55E, #16A34A); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 30px; box-shadow: 0 10px 25px rgba(34, 197, 94, 0.4);">
                                <i class="fas fa-check"></i>
                            </div>
                            <h2 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Registration Successful!</h2>
                            <p style="font-size: 13.5px; color: var(--text-secondary); margin: 0;">Your student credentials and profile have been registered in the database.</p>
                        </div>

                        <div class="modal-body" style="padding: 22px 24px;">
                            <?php if (!empty($regSummary)): ?>
                            <!-- Summary Details Grid -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px; margin-bottom: 20px; text-align: left; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12.5px;">
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Student Name</span>
                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($regSummary['name'] ?? ''); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Student ID / Roll</span>
                                    <strong style="color: var(--primary);"><?php echo htmlspecialchars($regSummary['student_id'] ?? ''); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Department</span>
                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($regSummary['department'] ?? ''); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Program & Section</span>
                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($regSummary['course'] ?? ''); ?> &bull; Sec <?php echo htmlspecialchars($regSummary['section'] ?? 'A'); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Phone Number</span>
                                    <strong style="color: #10B981;"><i class="fas fa-phone" style="font-size: 10px;"></i> <?php echo htmlspecialchars($regSummary['phone'] ?? ''); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block; font-size: 10.5px; text-transform: uppercase;">Email</span>
                                    <span style="color: var(--text-secondary); word-break: break-all;"><?php echo htmlspecialchars($regSummary['email'] ?? ''); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 15-Second Countdown Display -->
                            <div style="background: rgba(37, 99, 235, 0.08); border: 1.5px dashed var(--primary); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 14px;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
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
                
                <!-- Email & Phone Number -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Student Email <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="student@gmail.com" 
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   placeholder="e.g. +91 98765 43210" 
                                   value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Academic Department & Degree Course Program -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-building"></i></span>
                            <select id="department_id" name="department_id" class="form-control" style="padding-left: 40px;" required onchange="onDepartmentChange(this.value)">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['id']; ?>" <?php echo ((string)($formData['department_id'] ?? '') === (string)$dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="course_id">Degree Course Program <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-graduation-cap"></i></span>
                            <select id="course_id" name="course_id" class="form-control" style="padding-left: 40px;" required onchange="onCourseChange(this)">
                                <option value="">Select Degree Course</option>
                                <?php foreach ($courses as $course): 
                                    $cId = $course['id'] ?? '';
                                    $code = strtoupper(trim($course['code'] ?? ''));
                                    $name = trim($course['name'] ?? ($course['course_name'] ?? ('Course #' . $cId)));
                                    $deptRef = $course['department_id'] ?? '';
                                    if (in_array($code, ['BBA', 'BCA'])) {
                                        $displayLabel = $code;
                                    } elseif ($name === $code || empty($code)) {
                                        $displayLabel = $name;
                                    } else {
                                        $displayLabel = $name . ' (' . $code . ')';
                                    }
                                    $selected = ((string)($formData['course_id'] ?? '') === (string)$cId) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($cId); ?>" data-dept="<?php echo htmlspecialchars($deptRef); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($displayLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Semester & Section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="semester">Current Semester <span style="color: var(--danger);">*</span></label>
                        <select id="semester" name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                    <?php echo (($formData['semester'] ?? '1') == $i) ? 'selected' : ''; ?>>
                                    Semester <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section">Class Section <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-layer-group"></i></span>
                            <select id="section" name="section" class="form-control" style="padding-left: 40px;" required>
                                <option value="">Select Section</option>
                                <?php foreach (['A', 'B', 'C', 'D'] as $sec): ?>
                                    <option value="<?php echo $sec; ?>" <?php echo (($formData['section'] ?? 'A') === $sec) ? 'selected' : ''; ?>>
                                        Section <?php echo $sec; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

                <!-- Password and Confirm Password -->
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

        // Department & Degree Course Synchronization
        function onDepartmentChange(deptId) {
            if (!deptId) return;
            const courseSelect = document.getElementById('course_id');
            if (!courseSelect) return;
            let firstMatch = '';
            for (let i = 0; i < courseSelect.options.length; i++) {
                const opt = courseSelect.options[i];
                if (!opt.value) continue;
                const optDept = opt.getAttribute('data-dept');
                if (optDept && optDept == deptId) {
                    opt.style.display = '';
                    if (!firstMatch) firstMatch = opt.value;
                } else if (optDept) {
                    opt.style.display = 'none';
                }
            }
            if (firstMatch) {
                courseSelect.value = firstMatch;
            }
        }

        function onCourseChange(sel) {
            const opt = sel.options[sel.selectedIndex];
            if (opt) {
                const deptId = opt.getAttribute('data-dept');
                if (deptId) {
                    const deptSelect = document.getElementById('department_id');
                    if (deptSelect && deptSelect.value != deptId) {
                        deptSelect.value = deptId;
                    }
                }
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
