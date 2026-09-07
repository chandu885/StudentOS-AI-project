<?php
// frontend/faculty/support.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if (!isset($_SESSION['faculty_tickets'])) {
    $_SESSION['faculty_tickets'] = [
        ['id' => 'FAC-701', 'subject' => 'Request to reopen gradebook for CS301 section A due to make-up test', 'category' => 'Gradebook Admin', 'priority' => 'high', 'status' => 'in_progress', 'created_at' => '2026-02-28', 'response' => 'Admin desk approved. 48-hour edit window opened.'],
        ['id' => 'FAC-682', 'subject' => 'Lab B-204 Projector HDMI & sound connection glitch', 'category' => 'Classroom IT', 'priority' => 'medium', 'status' => 'resolved', 'created_at' => '2026-02-14', 'response' => 'Hardware cable replaced by campus IT.']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject'] ?? '');
    $category = sanitize($_POST['category'] ?? 'General');
    $message = sanitize($_POST['message'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');

    if (!empty($subject) && !empty($message)) {
        $newTicket = [
            'id' => 'FAC-' . rand(710, 999),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'status' => 'pending',
            'created_at' => date('Y-m-d'),
            'response' => 'Ticket assigned to Academic Registrar & Campus Operations.'
        ];
        array_unshift($_SESSION['faculty_tickets'], $newTicket);
        $successMsg = 'Faculty support request #' . $newTicket['id'] . ' logged successfully!';
    }
}

$tickets = $_SESSION['faculty_tickets'];
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
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
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
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <select name="category" class="form-control">
                                            <option value="Gradebook Admin">Gradebook Modification</option>
                                            <option value="Classroom IT">Classroom Hardware & Network</option>
                                            <option value="Curriculum & Syllabus">Syllabus / Course Catalog</option>
                                            <option value="Exam Logistics">Exam Hall & Question Bank</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Priority</label>
                                        <select name="priority" class="form-control">
                                            <option value="low">Standard</option>
                                            <option value="medium" selected>Elevated</option>
                                            <option value="high">Urgent (Classroom blocked)</option>
                                        </select>
                                    </div>
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
                            <h3><i class="fas fa-ticket-alt"></i> Faculty Requests History</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php foreach ($tickets as $t): 
                                    $isResolved = ($t['status'] ?? '') === 'resolved';
                                ?>
                                    <div style="padding: 14px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <span style="font-size: 11.5px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($t['id']); ?></span>
                                            <span class="badge badge-<?php echo $isResolved ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                                            </span>
                                        </div>
                                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($t['subject']); ?>
                                        </h4>
                                        <?php if (!empty($t['response'])): ?>
                                            <div style="font-size: 12px; color: var(--text-secondary); background: var(--bg-card); padding: 8px 12px; border-radius: var(--radius-sm); border-left: 3px solid var(--success); margin: 6px 0;">
                                                <strong>Resolution Note:</strong> <?php echo htmlspecialchars($t['response']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 11.5px; color: var(--text-muted); display: flex; justify-content: space-between; margin-top: 6px;">
                                            <span><?php echo htmlspecialchars($t['category']); ?></span>
                                            <span><?php echo date('M d, Y', strtotime($t['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
