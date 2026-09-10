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
        $bio = sanitize($_POST['bio'] ?? '');
        
        if (!empty($firstName) && $db) {
            $uStmt = $db->prepare("UPDATE `users` SET `first_name` = ?, `last_name` = ?, `updated_at` = NOW() WHERE `id` = ?");
            if ($uStmt) {
                $uStmt->bind_param("ssi", $firstName, $lastName, $userId);
                $uStmt->execute();
                $uStmt->close();
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
    } elseif ($action === 'opt_in_promotion') {
        require_once __DIR__ . '/../../backend/models/Student.php';
        $stuObj = new Student();
        $notes = sanitize($_POST['notes'] ?? '');
        $res = $stuObj->optInPromotion($userId, null, $notes);
        if ($res['success']) {
            $successMsg = $res['message'];
        } else {
            $errorMsg = $res['error'];
        }
    } elseif ($action === 'opt_out_promotion') {
        require_once __DIR__ . '/../../backend/models/Student.php';
        $stuObj = new Student();
        $notes = sanitize($_POST['notes'] ?? '');
        $res = $stuObj->optOutPromotion($userId, $notes);
        if ($res['success']) {
            $successMsg = $res['message'];
        } else {
            $errorMsg = $res['error'];
        }
    }
}

$profile = [];
if ($db) {
    // 1. Fetch user's student profile
    $stmt = $db->prepare(
        "SELECT sp.*, u.first_name, u.last_name, u.email 
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id 
         WHERE u.id = ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $dbProf = $stmt->get_result()->fetch_assoc();
        if ($dbProf) {
            $profile = $dbProf;
        }
        $stmt->close();
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
        $perfStmt->close();
    }
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
                            <i class="fas fa-user-graduate"></i> Student Profile
                        </span>

                        <div class="profile-meta-list">
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Student ID:</span>
                                <strong style="color: var(--primary);"><?php echo htmlspecialchars($profile['student_id'] ?? ('STU' . str_pad($userId, 4, '0', STR_PAD_LEFT))); ?></strong>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Department:</span>
                                <span class="badge <?php echo (($profile['department'] ?? '') === 'BBA') ? 'badge-purple' : 'badge-primary'; ?>" style="font-weight: 700; font-size: 11px;">
                                    <i class="fas fa-building-columns"></i> <?php echo htmlspecialchars($profile['department'] ?? 'BCA'); ?>
                                </span>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Roll Number:</span>
                                <strong><?php echo htmlspecialchars($profile['roll_number'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Current Term:</span>
                                <span class="badge badge-success"><?php echo htmlspecialchars(!empty($profile['semester']) ? (is_numeric($profile['semester']) ? 'Semester ' . $profile['semester'] : $profile['semester']) : 'Semester 1'); ?></span>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Promotion Status:</span>
                                <?php if (($profile['promotion_status'] ?? '') === 'promoted'): ?>
                                    <span class="badge badge-purple" style="font-weight: 700; font-size: 11px;"><i class="fas fa-check-double"></i> Promoted</span>
                                <?php elseif (($profile['promotion_status'] ?? '') === 'opted_in'): ?>
                                    <span class="badge badge-success" style="font-weight: 700; font-size: 11px;"><i class="fas fa-check-circle"></i> Opted-In</span>
                                <?php elseif (($profile['promotion_status'] ?? '') === 'rejected'): ?>
                                    <span class="badge badge-danger" style="font-weight: 700; font-size: 11px;"><i class="fas fa-times-circle"></i> Deferred</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="font-weight: 600; font-size: 11px;">Not Opted</span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-meta-row">
                                <span style="color: var(--text-muted);">Email:</span>
                                <strong style="color: var(--text-primary); font-size: 12px;"><?php echo htmlspecialchars($user['email'] ?? 'student@studentos.ai'); ?></strong>
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
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px;">
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Full Legal Name</div>
                                        <div style="font-size: 14px; font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars(($user['first_name'] ?? 'Alex') . ' ' . ($user['last_name'] ?? 'Johnson')); ?></div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Institutional Email</div>
                                        <div style="font-size: 14px; font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars($user['email'] ?? 'student@studentos.ai'); ?></div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Student ID</div>
                                        <div style="font-size: 14px; font-weight: 700; color: var(--primary); margin-top: 4px;">
                                            <?php echo htmlspecialchars($profile['student_id'] ?? ('STU' . str_pad($userId, 4, '0', STR_PAD_LEFT))); ?>
                                        </div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Class Roll Number</div>
                                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                                            <?php echo htmlspecialchars($profile['roll_number'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Academic Department</div>
                                        <div style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-building-columns" style="color: var(--primary);"></i>
                                            <span class="badge <?php echo (($profile['department'] ?? '') === 'BBA') ? 'badge-purple' : 'badge-primary'; ?>" style="font-size: 12px; font-weight: 700;">
                                                <?php echo htmlspecialchars($profile['department'] ?? 'BCA'); ?>
                                            </span>
                                            <span style="font-size: 12px; color: var(--text-muted); font-weight: 400;">
                                                (<?php echo (($profile['department'] ?? '') === 'BBA') ? 'Bachelor of Business Administration' : 'Bachelor of Computer Applications'; ?>)
                                            </span>
                                        </div>
                                    </div>
                                    <div style="background: var(--bg-primary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Current Academic Term</div>
                                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                                            <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 13px;"></i> <?php echo htmlspecialchars(!empty($profile['semester']) ? (is_numeric($profile['semester']) ? 'Semester ' . $profile['semester'] : $profile['semester']) : 'Semester 1'); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                $profPromStatus = $profile['promotion_status'] ?? 'not_opted';
                                $profCurrentSem = !empty($profile['semester']) ? $profile['semester'] : '1';
                                $profTargetSem = !empty($profile['promotion_target_sem']) ? $profile['promotion_target_sem'] : (is_numeric($profCurrentSem) ? (string)((int)$profCurrentSem + 1) : '2');
                                
                                $promWindowOpen = true;
                                if ($db) {
                                    $wRes = $db->query("SELECT value FROM system_settings WHERE `key` = 'semester_promotion_open' LIMIT 1");
                                    if ($wRes && $wRow = $wRes->fetch_assoc()) {
                                        if ($wRow['value'] === '0') $promWindowOpen = false;
                                    }
                                }
                                ?>
                                <div style="background: var(--bg-primary); padding: 18px 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); border-left: 4px solid <?php echo $profPromStatus === 'promoted' ? 'var(--purple, #8B5CF6)' : ($profPromStatus === 'opted_in' ? 'var(--success)' : ($promWindowOpen ? 'var(--primary)' : 'var(--border-color)')); ?>; margin-bottom: 24px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                                        <div>
                                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Semester Promotion Status</div>
                                            <div style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                                                <i class="fas fa-level-up-alt" style="color: var(--primary);"></i>
                                                <?php if ($profPromStatus === 'promoted'): ?>
                                                    <span class="badge badge-purple" style="font-weight: 700;">Promoted to Semester <?php echo htmlspecialchars($profCurrentSem); ?></span>
                                                <?php elseif ($profPromStatus === 'opted_in'): ?>
                                                    <span class="badge badge-success" style="font-weight: 700;">Opted-In (Semester <?php echo htmlspecialchars($profCurrentSem); ?> &rarr; <?php echo htmlspecialchars($profTargetSem); ?>)</span>
                                                <?php elseif ($profPromStatus === 'rejected'): ?>
                                                    <span class="badge badge-danger" style="font-weight: 700;">Promotion Deferred</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary" style="font-weight: 600;">Not Opted In</span>
                                                <?php endif; ?>

                                                <span style="font-size: 12px; font-weight: normal; color: var(--text-muted);">
                                                    (Window: <?php echo $promWindowOpen ? '<strong style="color:var(--success);">Open</strong>' : '<strong style="color:var(--danger);">Closed</strong>'; ?>)
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; color: var(--text-secondary); margin: 6px 0 0 0;">
                                                <?php if ($profPromStatus === 'promoted'): ?>
                                                    Your enrollment advancement has been approved and finalized by Academic Administration.
                                                <?php elseif ($profPromStatus === 'opted_in'): ?>
                                                    Opt-in submitted. Waiting for administrative approval.
                                                <?php elseif ($profPromStatus === 'rejected'): ?>
                                                    Notes: <?php echo htmlspecialchars($profile['promotion_notes'] ?? 'Pending review'); ?>.
                                                <?php else: ?>
                                                    Eligible for next semester progression upon opting in.
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <div>
                                            <?php if ($profPromStatus === 'opted_in'): ?>
                                                <form method="POST" action="profile.php" onsubmit="return confirm('Withdraw your promotion opt-in request?');">
                                                    <input type="hidden" name="action" value="opt_out_promotion">
                                                    <button type="submit" class="btn btn-outline" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.4); font-size: 12px; padding: 6px 12px;">
                                                        <i class="fas fa-undo"></i> Withdraw Opt-In
                                                    </button>
                                                </form>
                                            <?php elseif ($profPromStatus === 'not_opted' || $profPromStatus === 'rejected'): ?>
                                                <?php if ($promWindowOpen): ?>
                                                    <form method="POST" action="profile.php">
                                                        <input type="hidden" name="action" value="opt_in_promotion">
                                                        <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 6px 14px; font-weight: 600;">
                                                            <i class="fas fa-arrow-circle-up"></i> Opt-In for Promotion
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
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

        const activeBtn = document.querySelector(`.profile-tab-btn[onclick*="'${tabKey}'"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        } else if (window.event && window.event.target) {
            const btn = window.event.target.closest('.profile-tab-btn');
            if (btn) btn.classList.add('active');
        }
    }

    // Check if tab is requested in URL query string (e.g. ?tab=security)
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const requestedTab = urlParams.get('tab');
        if (requestedTab) {
            switchProfileTab(requestedTab);
        }
    });
    </script>
</body>
</html>
