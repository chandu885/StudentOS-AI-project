<?php
// frontend/faculty/support.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Auto-seed initial tickets for faculty if none exist
if ($db) {
    $chk = $db->prepare("SELECT COUNT(*) AS cnt FROM support_tickets WHERE user_id = ?");
    if ($chk) {
        $chk->bind_param("i", $userId);
        $chk->execute();
        $hasTickets = (int)$chk->get_result()->fetch_assoc()['cnt'];
        $chk->close();

        if ($hasTickets === 0) {
            $insSeed = $db->prepare("INSERT INTO support_tickets (user_id, subject, description, priority, status, response, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($insSeed) {
                $seeds = [
                    ['Request to reopen gradebook for CS301 section A due to make-up test', 'Need 48-hour edit window for continuous assessment make-up test submissions.', 'high', 'in_progress', 'Admin desk approved. 48-hour edit window opened.'],
                    ['Lab B-204 Projector HDMI & sound connection glitch', 'Hardware cable issues during morning lectures and lab sessions.', 'medium', 'resolved', 'Hardware cable replaced by campus IT.']
                ];
                foreach ($seeds as $s) {
                    $insSeed->bind_param("isssss", $userId, $s[0], $s[1], $s[2], $s[3], $s[4]);
                    $insSeed->execute();
                }
                $insSeed->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $rawPrio = strtolower(sanitize($_POST['priority'] ?? 'medium'));
    $priority = in_array($rawPrio, ['low', 'medium', 'high']) ? $rawPrio : 'medium';

    if (!empty($subject) && !empty($message) && $db) {
        $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, description, priority, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $subject, $message, $priority);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $successMsg = "Faculty support request #TKT-{$newId} logged successfully!";
            } else {
                $errorMsg = 'Failed to submit request: ' . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $errorMsg = 'Please provide both subject and requirements.';
    }
}

$tickets = [];
if ($db) {
    $stmt = $db->prepare("SELECT id, user_id, subject, description, priority, status, response, created_at FROM support_tickets WHERE user_id = ? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Support - StudentOS AI</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <div>
                        <h1>Faculty Support & IT Helpdesk</h1>
                        <p class="page-subtitle">Request classroom hardware assistance, exam roster modifications, and administrative support</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="support-quick-grid">
                    <div class="support-quick-card">
                        <div class="support-quick-icon"><i class="fas fa-laptop-code"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">Classroom IT Support</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">For immediate audio-visual or lab network failures during lectures, call <strong>ext. 2222</strong> for on-site technician dispatch.</p>
                    </div>

                    <div class="support-quick-card">
                        <div class="support-quick-icon" style="color: var(--success);"><i class="fas fa-file-export"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">Marks & Gradebook Locking</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">Gradebooks automatically lock 5 days post examination. Submit an administrative request below for exceptional re-evaluations.</p>
                    </div>

                    <div class="support-quick-card">
                        <div class="support-quick-icon" style="color: var(--ai-accent);"><i class="fas fa-question"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">Question Bank Guidelines</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">MCQs can be imported via CSV or synthesized using the AI Assistant directly into course archives.</p>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Ticket Submit -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-life-ring"></i> Submit Faculty Request</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="support.php">
                                <div class="form-group">
                                    <label>Subject / Operational Need</label>
                                    <input type="text" name="subject" class="form-control" placeholder="e.g. Need software license for MATLAB on Lab computers" required>
                                </div>
                                <div class="form-group">
                                    <label>Priority</label>
                                    <select name="priority" class="form-control">
                                        <option value="low">Standard Priority</option>
                                        <option value="medium" selected>Elevated Priority</option>
                                        <option value="high">Urgent (Classroom blocked)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Details & Requirements</label>
                                    <textarea name="message" class="form-control" rows="4" placeholder="Provide full details and room or student IDs..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Ticket History -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-ticket-alt"></i> Faculty Requests History (<?php echo count($tickets); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php if (empty($tickets)): ?>
                                    <div style="text-align: center; padding: 24px; color: var(--text-muted);">
                                        <i class="fas fa-ticket-alt" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                                        No support requests logged yet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): 
                                        $isResolved = in_array(($t['status'] ?? ''), ['resolved', 'closed']);
                                        $statusClass = $isResolved ? 'success' : (($t['status'] === 'in_progress') ? 'primary' : 'warning');
                                        $prio = strtolower($t['priority'] ?? 'medium');
                                        $prioClass = ($prio === 'high') ? 'danger' : (($prio === 'medium') ? 'warning' : 'info');
                                    ?>
                                        <div style="padding: 14px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                <span style="font-size: 11.5px; font-weight: 700; color: var(--primary);">TKT-#<?php echo (int)$t['id']; ?></span>
                                                <div style="display: flex; gap: 6px; align-items: center;">
                                                    <span class="badge badge-<?php echo $prioClass; ?>"><?php echo ucfirst($prio); ?></span>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                                                <?php echo htmlspecialchars($t['subject']); ?>
                                            </h4>
                                            <?php if (!empty($t['description'])): ?>
                                                <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 8px; line-height: 1.5;">
                                                    <?php echo nl2br(htmlspecialchars($t['description'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($t['response'])): ?>
                                                <div style="font-size: 12px; color: var(--text-secondary); background: var(--bg-card); padding: 8px 12px; border-radius: var(--radius-sm); border-left: 3px solid var(--success); margin: 6px 0;">
                                                    <strong>Resolution Note:</strong> <?php echo htmlspecialchars($t['response']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 11.5px; color: var(--text-muted); display: flex; justify-content: flex-end; margin-top: 6px;">
                                                <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($t['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
</body>
</html>
