<?php
// frontend/student/profile.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

$db = getDbConnection();

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'update_profile') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        
        if (!empty($firstName) && $db) {
            $uStmt = $db->prepare("UPDATE `users` SET `first_name` = ?, `last_name` = ?, `updated_at` = NOW() WHERE `id` = ?");
            if ($uStmt) {
                $uStmt->bind_param("ssi", $firstName, $lastName, $userId);
                $uStmt->execute();
            }
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $successMsg = 'Profile updated successfully!';
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8) {
            $errorMsg = 'New password must be at least 8 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'New password and confirmation do not match.';
        } else {
            if ($db) {
                $pStmt = $db->prepare("SELECT password_hash FROM `users` WHERE `id` = ?");
                if ($pStmt) {
                    $pStmt->bind_param("i", $userId);
                    $pStmt->execute();
                    $curRow = $pStmt->get_result()->fetch_assoc();
                    if ($curRow && password_verify($currentPass, $curRow['password_hash'])) {
                        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                        $upStmt = $db->prepare("UPDATE `users` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
                        $upStmt->bind_param("si", $newHash, $userId);
                        $upStmt->execute();
                        $successMsg = 'Security credentials updated successfully!';
                    } else {
                        $errorMsg = 'Current password does not match our records.';
                    }
                }
            }
        }
    }
}

$profile = [];
if ($db) {
    // 1. Fetch user's student profile & department & course
    $stmt = $db->prepare(
        "SELECT sp.*, d.name AS department_name, d.code AS department_code, c.name AS course_name, c.code AS course_code 
         FROM student_profiles sp 
         LEFT JOIN departments d ON sp.department_id = d.id 
         LEFT JOIN courses c ON sp.course_id = c.id 
         WHERE sp.user_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $dbProf = $stmt->get_result()->fetch_assoc();
        if ($dbProf) {
            $profile = $dbProf;
        }
    }

    // 2. Fetch latest performance metrics (CGPA, credits)
    $perfStmt = $db->prepare("SELECT cgpa, gpa, credits_completed, semester FROM `performance` WHERE `student_id` = ? ORDER BY `id` DESC LIMIT 1");
    if ($perfStmt) {
        $perfStmt->bind_param("i", $userId);
        $perfStmt->execute();
        $perfRow = $perfStmt->get_result()->fetch_assoc();
        if ($perfRow) {
            $profile['cgpa'] = $perfRow['cgpa'];
            $profile['credits_earned'] = $perfRow['credits_completed'];
            if (empty($profile['semester'])) {
                $profile['semester'] = $perfRow['semester'];
            }
        }
    }
}

// Determine 'BCA' or 'BBA' based on the department
$deptId = (int)($profile['department_id'] ?? 1);
$deptName = $profile['department_name'] ?? '';
$deptCode = strtoupper($profile['department_code'] ?? '');
$courseCode = strtoupper($profile['course_code'] ?? '');

if ($deptId === 4 || stripos($deptName, 'Management') !== false || stripos($deptName, 'Business') !== false || $deptCode === 'MGMT' || $courseCode === 'BBA') {
    $degreeProgram = 'BBA';
    $displayDepartment = !empty($deptName) ? $deptName : 'School of Business & Management';
} else {
    $degreeProgram = 'BCA';
    $displayDepartment = !empty($deptName) ? $deptName : 'Department of Computer Applications';
}

$user = $_SESSION['user'];
$initials = getInitials(($user['first_name'] ?? 'Alex') . ' ' . ($user['last_name'] ?? 'Johnson'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - StudentOS AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Student Profile</h1>
                        <p class="page-subtitle">View your academic identity, enrolled degree credentials, and account settings</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="profile-grid">
                    <!-- Left Identity Card -->
                    <div class="card" style="text-align: center; padding: 32px 24px;">
                        <div class="profile-avatar-large">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <h3 style="font-size: 19px; font-weight: 700; margin-bottom: 4px; color: var(--text-primary);">
                            <?php echo htmlspecialchars(($user['first_name'] ?? 'Alex') . ' ' . ($user['last_name'] ?? 'Johnson')); ?>
                        </h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;"><?php echo htmlspecialchars($user['email'] ?? 'student@studentos.ai'); ?></p>
                        <span class="badge badge-primary" style="font-weight: 700; font-size: 13px; padding: 4px 12px; margin-bottom: 12px;">
                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($degreeProgram); ?> Student
                        </span>

                        <div class="profile-meta-list">
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Roll Number:</span>
                                <strong><?php echo htmlspecialchars($profile['roll_number'] ?? $profile['student_id'] ?? ('STU' . str_pad($userId, 4, '0', STR_PAD_LEFT))); ?></strong>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Degree:</span>
                                <span class="badge badge-primary" style="font-weight: 700; font-size: 13px;"><?php echo htmlspecialchars($degreeProgram); ?></span>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Current Term:</span>
                                <span class="badge badge-success"><?php echo htmlspecialchars(!empty($profile['semester']) ? (is_numeric($profile['semester']) ? 'Semester ' . $profile['semester'] : $profile['semester']) : 'Semester 1'); ?></span>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Cumulative CGPA:</span>
                                <span class="badge badge-purple" style="font-weight: 700;"><?php echo htmlspecialchars(!empty($profile['cgpa']) ? number_format((float)$profile['cgpa'], 2) . ' / 4.0' : 'N/A'); ?></span>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Completed Credits:</span>
                                <strong><?php echo htmlspecialchars(!empty($profile['credits_earned']) ? $profile['credits_earned'] . ' Credits' : '0 Credits'); ?></strong>
                            </div>
                        </div>

                        <div style="margin-top: 24px;">
                            <a href="tasks.php" class="btn btn-outline btn-block" style="font-size: 13px;">
                                <i class="fas fa-tasks"></i> View My Tasks
                            </a>
                        </div>
                    </div>

                    <!-- Right Tabbed Details & Form Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-id-card"></i> Student Information & Settings</h3>
                        </div>
                        <div class="card-body">
                            <!-- Tab navigation -->
                            <div class="profile-tabs">
                                <button class="profile-tab-btn active" onclick="switchProfileTab('overview')">
                                    <i class="fas fa-info-circle"></i> Overview & Academic
                                </button>
                                <button class="profile-tab-btn" onclick="switchProfileTab('edit')">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </button>
                                <button class="profile-tab-btn" onclick="switchProfileTab('security')">
                                    <i class="fas fa-lock"></i> Security & Password
                                </button>
                            </div>

                            <!-- Tab 1: Overview -->
                            <div id="tab-overview" class="profile-tab-pane">
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px;">
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Full Legal Name</div>
                                        <div style="font-size: 14px; font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars(($user['first_name'] ?? 'Alex') . ' ' . ($user['last_name'] ?? 'Johnson')); ?></div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Institutional Email</div>
                                        <div style="font-size: 14px; font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars($user['email'] ?? 'student@studentos.ai'); ?></div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Degree</div>
                                        <div style="font-size: 14px; font-weight: 700; color: var(--primary); margin-top: 4px;">
                                            <span class="badge badge-primary" style="font-size: 13px; font-weight: 700; padding: 4px 10px;"><?php echo htmlspecialchars($degreeProgram); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 24px;">
                                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">Academic Bio / Objectives</div>
                                    <div style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($profile['bio'] ?? 'Honors student focusing on Applied Computing, Systems Architecture, and Software Engineering.')); ?>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 12px;">
                                    <button class="btn btn-primary" onclick="switchProfileTab('edit')">
                                        <i class="fas fa-edit"></i> Edit Details
                                    </button>
                                    <a href="results.php" class="btn btn-secondary">
                                        <i class="fas fa-file-invoice"></i> View Grade Transcripts
                                    </a>
                                </div>
                            </div>

                            <!-- Tab 2: Edit Form -->
                            <div id="tab-edit" class="profile-tab-pane" style="display: none;">
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                        <div class="form-group">
                                            <label for="first_name">First Name</label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="last_name">Last Name</label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="bio">Bio / Academic Interests</label>
                                        <textarea name="bio" id="bio" class="form-control" rows="4" placeholder="Share your academic interests, focus areas, and learning goals..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Profile Changes
                                    </button>
                                </form>
                            </div>

                            <!-- Tab 3: Security -->
                            <div id="tab-security" class="profile-tab-pane" style="display: none;">
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" name="current_password" id="current_password" class="form-control" required>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                        <div class="form-group">
                                            <label for="new_password">New Password</label>
                                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimum 8 characters" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm New Password</label>
                                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-shield-alt"></i> Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    function switchProfileTab(tabKey) {
        document.querySelectorAll('.profile-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.profile-tab-pane').forEach(pane => pane.style.display = 'none');
        
        const targetPane = document.getElementById('tab-' + tabKey);
        if (targetPane) targetPane.style.display = 'block';

        if (event && event.target) {
            const btn = event.target.closest('.profile-tab-btn');
            if (btn) btn.classList.add('active');
        }
    }
    </script>
</body>
</html>
