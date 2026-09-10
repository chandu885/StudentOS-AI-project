<?php
// frontend/student/dashboard.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

$db = getDbConnection();

// Handle Password Change directly from Student Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errorMsg = 'Please fill in all password fields.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'New password must be at least 8 characters in length.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'New password and confirmation do not match.';
        } else {
            if ($db) {
                $pStmt = $db->prepare("SELECT password_hash FROM `users` WHERE `id` = ? AND `deleted_at` IS NULL");
                if ($pStmt) {
                    $pStmt->bind_param("i", $userId);
                    $pStmt->execute();
                    $curRow = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();

                    if ($curRow && password_verify($currentPass, $curRow['password_hash'])) {
                        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                        $upStmt = $db->prepare("UPDATE `users` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
                        if ($upStmt) {
                            $upStmt->bind_param("si", $newHash, $userId);
                            if ($upStmt->execute()) {
                                $successMsg = 'Your password has been successfully changed in the database! Please remember your new password.';
                            } else {
                                $errorMsg = 'Failed to update password in database: ' . $db->error;
                            }
                            $upStmt->close();
                        }
                    } else {
                        $errorMsg = 'Current password does not match our records. Verification failed.';
                    }
                }
            } else {
                $errorMsg = 'Database connection error.';
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

$todayClasses = [];
$pendingAssignments = [];
$attendanceSummary = [];
$upcomingExams = [];
$studentProfile = null;

if ($db && $userId) {
    // Fetch student's academic profile details
    $spQuery = $db->prepare(
        "SELECT sp.*, u.first_name, u.last_name, u.email
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.id = ?"
    );
    if ($spQuery) {
        $spQuery->bind_param("i", $userId);
        $spQuery->execute();
        $studentProfile = $spQuery->get_result()->fetch_assoc();
        $spQuery->close();
    }
    // 1. Classes from database
    $dayOfWeek = date('l');
    $stmt = $db->prepare(
        "SELECT cs.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as faculty_name 
         FROM class_schedules cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN users u ON cs.faculty_id = u.id
         JOIN student_subjects ss ON ss.subject_id = cs.subject_id
         WHERE ss.student_id = ? AND cs.day_of_week = ?
         ORDER BY cs.start_time"
    );
    if ($stmt) {
        $stmt->bind_param("is", $userId, $dayOfWeek);
        $stmt->execute();
        $todayClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // If no classes today, fetch upcoming schedules for student's subjects
    if (empty($todayClasses)) {
        $stmt = $db->prepare(
            "SELECT cs.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as faculty_name 
             FROM class_schedules cs
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.faculty_id = u.id
             JOIN student_subjects ss ON ss.subject_id = cs.subject_id
             WHERE ss.student_id = ?
             ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time
             LIMIT 4"
        );
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $todayClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    // 2. Pending Assignments from database
    $stmt = $db->prepare(
        "SELECT a.*, s.name as subject_name 
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         WHERE ss.student_id = ? 
         AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
         ORDER BY a.deadline ASC
         LIMIT 5"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $pendingAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 3. Attendance Summary from database
    $stmt = $db->prepare(
        "SELECT s.name as subject_name, 
                COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) as present,
                COUNT(*) as total,
                ROUND(COUNT(CASE WHEN LOWER(a.status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as percentage
         FROM attendance a
         JOIN subjects s ON a.subject_id = s.id
         WHERE a.student_id = ?
         GROUP BY a.subject_id"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $attendanceSummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 4. Upcoming Exams from database
    $stmt = $db->prepare(
        "SELECT e.*, s.name as subject_name 
         FROM exams e
         JOIN subjects s ON e.subject_id = s.id
         JOIN student_subjects ss ON ss.subject_id = s.id
         WHERE ss.student_id = ?
         ORDER BY e.exam_date ASC
         LIMIT 4"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $upcomingExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Notifications and recommendations
$notifications = [];
if ($db && $userId) {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Dynamic AI Recommendations
$recommendations = [];
if ($db && $userId) {
    $stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = ? OR user_id = 0 ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Synthesize contextual recommendations if none stored
if (empty($recommendations)) {
    foreach ($attendanceSummary as $att) {
        if (($att['percentage'] ?? 100) < 75) {
            $recommendations[] = [
                'title' => 'Attendance Warning: ' . ($att['subject_name'] ?? 'Subject'),
                'description' => 'Current attendance is ' . $att['percentage'] . '%. Attend upcoming lectures to meet the mandatory 75% exam eligibility threshold.',
                'icon' => 'exclamation-triangle',
                'priority' => 'high'
            ];
        }
    }
    foreach ($pendingAssignments as $pa) {
        $daysUntil = round((strtotime($pa['deadline']) - time()) / 86400);
        if ($daysUntil <= 3) {
            $recommendations[] = [
                'title' => 'Deadline Alert: ' . $pa['title'],
                'description' => 'Coursework for ' . $pa['subject_name'] . ' is due in ' . max(0, (int)$daysUntil) . ' day(s). Submit before deadline.',
                'icon' => 'clock',
                'priority' => 'high'
            ];
            break;
        }
    }
    if (!empty($upcomingExams[0])) {
        $ex = $upcomingExams[0];
        $recommendations[] = [
            'title' => 'Exam Preparation: ' . $ex['subject_name'],
            'description' => 'Exam scheduled for ' . date('M d, Y', strtotime($ex['exam_date'])) . '. Generate an AI Study Plan to structure revision.',
            'icon' => 'book-open',
            'priority' => 'medium'
        ];
    }
    if (empty($recommendations)) {
        $recommendations[] = [
            'title' => 'Great Academic Momentum',
            'description' => 'You are on track with your coursework and attendance. Challenge yourself with an AI Practice Quiz to test retention.',
            'icon' => 'lightbulb',
            'priority' => 'low'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentOS AI</title>
    
    <!-- External Google Font Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CDN Resources (Font Awesome, Normalize) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    
    <!-- Application Stylesheets -->
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
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md);">
                        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md);">
                        <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                        <div><?php echo htmlspecialchars($errorMsg); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>! 👋</h1>
                        <p class="welcome-subtitle">Here's your academic overview for today</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="window.location.href='ai-assistant.php'">
                            <i class="fas fa-robot"></i> Ask AI
                        </button>
                        <button class="btn btn-outline" onclick="window.location.href='tasks.php'">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                        <button class="btn btn-outline" onclick="openModal('studentPasswordModal')" title="Change Account Password">
                            <i class="fas fa-key" style="color: #F59E0B;"></i> Change Password
                        </button>
                    </div>
                </div>

                <!-- Student Academic & Profile Details Card (Department, Section, Phone) -->
                <div class="card" style="margin-bottom: 24px; padding: 18px 22px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <?php 
                                $stuDept = !empty($studentProfile['department']) ? $studentProfile['department'] : 'BCA';
                                $isBBA = ($stuDept === 'BBA');
                                ?>
                                <div style="font-weight: 700; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                                    <?php echo htmlspecialchars(($studentProfile['first_name'] ?? $_SESSION['user']['first_name']) . ' ' . ($studentProfile['last_name'] ?? $_SESSION['user']['last_name'])); ?>
                                    <span class="badge" style="font-size: 11.5px; font-weight: 700; <?php echo $isBBA ? 'background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);' : 'background: rgba(37, 99, 235, 0.15); color: var(--primary); border: 1px solid rgba(37, 99, 235, 0.3);'; ?>">
                                        <?php echo htmlspecialchars($stuDept); ?>
                                    </span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                                    ID: <strong><?php echo htmlspecialchars($studentProfile['student_id'] ?? ('STU-' . str_pad($userId, 4, '0', STR_PAD_LEFT))); ?></strong>
                                    <?php if (!empty($studentProfile['roll_number'])): ?>
                                        &bull; Roll No: <strong><?php echo htmlspecialchars($studentProfile['roll_number']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                            <!-- Department Widget -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-building" style="color: var(--primary); font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Department</div>
                                    <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($stuDept); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Semester -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Current Term</div>
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                        Semester <span style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars(!empty($studentProfile['semester']) ? $studentProfile['semester'] : '1'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Email -->
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 14px; display: flex; align-items: center; gap: 9px;">
                                <i class="fas fa-envelope" style="color: #10B981; font-size: 16px;"></i>
                                <div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Student Email</div>
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($studentProfile['email'] ?? ($user['email'] ?? '')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Fetch promotion settings and status
                $promStatus = $studentProfile['promotion_status'] ?? 'not_opted';
                $promOptIn = !empty($studentProfile['promotion_opt_in']);
                $currentSemNum = !empty($studentProfile['semester']) ? $studentProfile['semester'] : '1';
                $targetSemNum = !empty($studentProfile['promotion_target_sem']) ? $studentProfile['promotion_target_sem'] : (is_numeric($currentSemNum) ? (string)((int)$currentSemNum + 1) : '2');

                $promWindowOpen = true;
                $promYear = '2026-2027';
                if ($db) {
                    $setRes = $db->query("SELECT `key`, `value` FROM `system_settings` WHERE `key` IN ('semester_promotion_open', 'semester_promotion_academic_year')");
                    if ($setRes) {
                        while ($sRow = $setRes->fetch_assoc()) {
                            if ($sRow['key'] === 'semester_promotion_open' && $sRow['value'] === '0') {
                                $promWindowOpen = false;
                            }
                            if ($sRow['key'] === 'semester_promotion_academic_year') {
                                $promYear = $sRow['value'];
                            }
                        }
                    }
                }
                ?>

                <!-- Semester Promotion Opt-In Feature Card -->
                <div class="card" style="margin-bottom: 24px; border-left: 4px solid <?php echo $promStatus === 'promoted' ? 'var(--purple, #8B5CF6)' : ($promStatus === 'opted_in' ? 'var(--success)' : ($promWindowOpen ? 'var(--primary)' : 'var(--border-color)')); ?>;">
                    <div class="card-body" style="padding: 20px 24px;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                            <div style="display: flex; align-items: flex-start; gap: 16px; max-width: 680px;">
                                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?php echo $promStatus === 'promoted' ? 'rgba(139, 92, 246, 0.15)' : ($promStatus === 'opted_in' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(37, 99, 235, 0.12)'); ?>; color: <?php echo $promStatus === 'promoted' ? '#8B5CF6' : ($promStatus === 'opted_in' ? 'var(--success)' : 'var(--primary)'); ?>; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">
                                    <?php if ($promStatus === 'promoted'): ?>
                                        <i class="fas fa-trophy"></i>
                                    <?php elseif ($promStatus === 'opted_in'): ?>
                                        <i class="fas fa-user-check"></i>
                                    <?php else: ?>
                                        <i class="fas fa-level-up-alt"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px;">
                                        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0;">Semester Promotion (Academic Progression)</h3>
                                        <?php if ($promStatus === 'promoted'): ?>
                                            <span class="badge badge-purple" style="font-weight: 700;"><i class="fas fa-check-double"></i> Promoted to Semester <?php echo htmlspecialchars($currentSemNum); ?></span>
                                        <?php elseif ($promStatus === 'opted_in'): ?>
                                            <span class="badge badge-success" style="font-weight: 700;"><i class="fas fa-check-circle"></i> Opt-In Active (Semester <?php echo htmlspecialchars($currentSemNum); ?> &rarr; <?php echo htmlspecialchars($targetSemNum); ?>)</span>
                                        <?php elseif ($promStatus === 'rejected'): ?>
                                            <span class="badge badge-danger" style="font-weight: 700;"><i class="fas fa-exclamation-triangle"></i> Promotion Deferred</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="font-weight: 600;"><i class="fas fa-clock"></i> Not Opted In</span>
                                        <?php endif; ?>

                                        <?php if ($promWindowOpen): ?>
                                            <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10B981; font-size: 11px; font-weight: 600;">
                                                <i class="fas fa-calendar-check"></i> Promotion Window Open (<?php echo htmlspecialchars($promYear); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: var(--danger); font-size: 11px; font-weight: 600;">
                                                <i class="fas fa-lock"></i> Promotion Window Closed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="font-size: 13.5px; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                                        <?php if ($promStatus === 'promoted'): ?>
                                            Congratulations! Your academic progression to <strong>Semester <?php echo htmlspecialchars($currentSemNum); ?> (<?php echo htmlspecialchars($stuDept); ?>)</strong> has been finalized by the administration.
                                            <?php if (!empty($studentProfile['promoted_at'])): ?>
                                                <span style="display: block; font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                                                    <i class="fas fa-calendar-alt"></i> Promoted on <?php echo date('M d, Y h:i A', strtotime($studentProfile['promoted_at'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($promStatus === 'opted_in'): ?>
                                            You have officially <strong>opted in</strong> for promotion from <strong>Semester <?php echo htmlspecialchars($currentSemNum); ?></strong> to <strong>Semester <?php echo htmlspecialchars($targetSemNum); ?></strong>. Your application is in the queue for Admin review and semester batch processing.
                                            <?php if (!empty($studentProfile['promotion_requested_at'])): ?>
                                                <span style="display: block; font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                                                    <i class="fas fa-clock"></i> Opt-in submitted on <?php echo date('M d, Y h:i A', strtotime($studentProfile['promotion_requested_at'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($promStatus === 'rejected'): ?>
                                            Your promotion request was deferred or rejected. Reason: <em><?php echo htmlspecialchars($studentProfile['promotion_notes'] ?? 'Pending administrative verification'); ?></em>. You may re-submit your opt-in if resolved.
                                        <?php else: ?>
                                            Are you completing Semester <?php echo htmlspecialchars($currentSemNum); ?>? Opt in below to register your academic intent for advancement to <strong>Semester <?php echo htmlspecialchars($targetSemNum); ?> (<?php echo htmlspecialchars($stuDept); ?>)</strong>.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($promStatus === 'opted_in'): ?>
                                    <form method="POST" action="dashboard.php" onsubmit="return confirm('Are you sure you want to withdraw your semester promotion opt-in request?');">
                                        <input type="hidden" name="action" value="opt_out_promotion">
                                        <button type="submit" class="btn btn-outline" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.4); font-size: 13px;">
                                            <i class="fas fa-undo"></i> Withdraw / Opt-Out
                                        </button>
                                    </form>
                                <?php elseif ($promStatus === 'not_opted' || $promStatus === 'rejected'): ?>
                                    <?php if ($promWindowOpen): ?>
                                        <form method="POST" action="dashboard.php">
                                            <input type="hidden" name="action" value="opt_in_promotion">
                                            <button type="submit" class="btn btn-primary" style="font-size: 13px; font-weight: 600;">
                                                <i class="fas fa-arrow-circle-up"></i> Opt-In for Semester Promotion
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled style="font-size: 13px; opacity: 0.65;">
                                            <i class="fas fa-lock"></i> Opt-In Window Closed
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($promStatus === 'promoted'): ?>
                                    <span style="font-size: 13px; font-weight: 700; color: var(--success); display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-check-circle"></i> Enrollment Updated
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($todayClasses); ?></span>
                            <span class="stat-label">Today's Classes</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($pendingAssignments); ?></span>
                            <span class="stat-label">Pending Assignments</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($upcomingExams); ?></span>
                            <span class="stat-label">Upcoming Exams</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">
                                <?php 
                                $totalAttendance = 0;
                                if (!empty($attendanceSummary)) {
                                    foreach ($attendanceSummary as $att) {
                                        $totalAttendance += $att['percentage'];
                                    }
                                    $totalAttendance = round($totalAttendance / count($attendanceSummary));
                                }
                                echo $totalAttendance; ?>%
                            </span>
                            <span class="stat-label">Overall Attendance</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="dashboard-grid">
                    <!-- Today's Schedule -->
                    <div class="card schedule-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                            <a href="schedule.php" class="link">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($todayClasses)): ?>
                                <?php foreach ($todayClasses as $class): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time">
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?>
                                        </div>
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($class['faculty_name']); ?> • Room <?php echo htmlspecialchars($class['room']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-plus"></i>
                                    <p>No classes scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Assignments -->
                    <div class="card assignments-card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-alt"></i> Pending Assignments</h3>
                            <a href="assignments.php" class="link">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendingAssignments)): ?>
                                <?php foreach ($pendingAssignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-info">
                                            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                        </div>
                                        <div class="assignment-deadline">
                                            <span class="deadline-label">Due:</span>
                                            <span class="deadline-date <?php echo isOverdue($assignment['deadline']) ? 'overdue' : ''; ?>">
                                                <?php echo date('M d, Y', strtotime($assignment['deadline'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No pending assignments. Great job! 🎉</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Overview -->
                <div class="card attendance-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-check"></i> Attendance Overview</h3>
                        <a href="attendance.php" class="link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendanceSummary)): ?>
                            <div class="attendance-grid">
                                <?php foreach ($attendanceSummary as $att): ?>
                                    <div class="attendance-item">
                                        <span class="subject-name"><?php echo htmlspecialchars($att['subject_name'] ?? $att['name'] ?? 'Subject'); ?></span>
                                        <div class="attendance-bar">
                                            <div class="attendance-fill <?php echo $att['percentage'] < 75 ? 'danger' : ($att['percentage'] < 85 ? 'warning' : 'success'); ?>" 
                                                 style="width: <?php echo $att['percentage']; ?>%"></div>
                                        </div>
                                        <span class="attendance-percent"><?php echo $att['percentage']; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <p>No attendance records found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- AI Recommendations -->
                <div class="card recommendations-card">
                    <div class="card-header">
                        <h3><i class="fas fa-robot"></i> AI Recommendations</h3>
                        <a href="ai-recommendations.php" class="link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php 
                            $recList = $recommendations['recommendations'] ?? $recommendations['data'] ?? $recommendations;
                        ?>
                        <?php if (!empty($recList)): ?>
                            <?php foreach (array_slice($recList, 0, 3) as $rec): ?>
                                <div class="recommendation-item">
                                    <div class="rec-icon">
                                        <i class="fas fa-<?php echo htmlspecialchars($rec['icon'] ?? 'lightbulb'); ?>"></i>
                                    </div>
                                    <div class="rec-content">
                                        <h4><?php echo htmlspecialchars($rec['title'] ?? 'Recommendation'); ?></h4>
                                        <p><?php echo htmlspecialchars($rec['suggestion'] ?? $rec['description'] ?? ''); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-robot"></i>
                                <p>AI recommendations will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="studentPasswordModal">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fas fa-key" style="color: #F59E0B; margin-right: 8px;"></i> Change Account Password</h3>
                <button type="button" class="modal-close" onclick="closeModal('studentPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="dashboard.php" id="studentPasswordForm">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body" style="padding: var(--spacing-lg);">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="current_password">Current Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="current_password" id="current_password" class="form-control has-toggle" placeholder="Enter current password" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('current_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="new_password">New Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" name="new_password" id="new_password" class="form-control has-toggle" placeholder="Minimum 8 characters" minlength="8" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('new_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Must be at least 8 characters long</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 8px;">
                        <label for="confirm_password">Confirm New Password <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control has-toggle" placeholder="Re-enter new password" minlength="8" required>
                            <button type="button" class="toggle-password" onclick="togglePassVisibility('confirm_password', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: var(--spacing-lg); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('studentPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        function togglePassVisibility(inputId, btn) {
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

        document.getElementById('studentPasswordForm').addEventListener('submit', function(e) {
            const newP = document.getElementById('new_password').value;
            const confP = document.getElementById('confirm_password').value;
            if (newP.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                return false;
            }
            if (newP !== confP) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
        });

        // Auto-refresh notifications
        setInterval(function() {
            fetchNotifications();
        }, 30000);
    </script>
</body>
</html>