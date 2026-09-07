<?php
// frontend/student/support.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if (!isset($_SESSION['student_tickets'])) {
    $_SESSION['student_tickets'] = [
        ['id' => 'TKT-1042', 'subject' => 'Discrepancy in Attendance for OS Lab on Feb 24', 'category' => 'Attendance', 'priority' => 'medium', 'status' => 'in_progress', 'created_at' => '2026-02-25', 'response' => 'Department coordinator is reviewing the laboratory biometric logs.'],
        ['id' => 'TKT-0988', 'subject' => 'Access error downloading syllabus PDF for CS304', 'category' => 'Technical', 'priority' => 'low', 'status' => 'resolved', 'created_at' => '2026-02-10', 'response' => 'Permissions refreshed. PDF is now publicly accessible in your documents portal.']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject'] ?? '');
    $category = sanitize($_POST['category'] ?? 'academic');
    $message = sanitize($_POST['message'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');

    if (!empty($subject) && !empty($message)) {
        $newTicket = [
            'id' => 'TKT-' . rand(1100, 9999),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'status' => 'pending',
            'created_at' => date('Y-m-d'),
            'response' => 'Ticket received by campus IT helpdesk. Average response time is 2-4 hours.'
        ];
        
        apiCall('/support.php', 'POST', $newTicket);
        array_unshift($_SESSION['student_tickets'], $newTicket);
        $successMsg = 'Support ticket #' . $newTicket['id'] . ' logged successfully! Helpdesk has been notified.';
    }
}

$tickets = $_SESSION['student_tickets'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - StudentOS AI</title>
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
                        <h1>Student Helpdesk & Academic Support</h1>
                        <p class="page-subtitle">Resolve grading questions, report portal glitches, and browse institutional guidelines</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Help Search Bar -->
                <div class="support-hero-search">
                    <h2 style="font-size: 22px; font-weight: 700; color: var(--text-primary);">How can we assist you today?</h2>
                    <p style="font-size: 14px; color: var(--text-secondary); margin-top: 6px;">Search documentation, attendance dispute procedures, and FAQs</p>
                    <div class="support-search-input">
                        <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="helpSearch" class="form-control" placeholder="Type a keyword (e.g. attendance, hall ticket, RAG)..." style="padding-left: 44px; height: 44px; font-size: 14px;" onkeyup="filterFaqs()">
                    </div>
                </div>

                <!-- 3 Quick Category Cards -->
                <div class="support-quick-grid">
                    <div class="support-quick-card">
                        <div class="support-quick-icon"><i class="fas fa-calendar-check"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">Attendance Guidelines</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">University regulations mandate a minimum of 75% attendance in each registered subject to sit for semester examinations.</p>
                    </div>

                    <div class="support-quick-card">
                        <div class="support-quick-icon" style="color: var(--success);"><i class="fas fa-robot"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">AI Assistant Quota</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">Your student plan includes unlimited concept chats, up to 25 daily PDF RAG uploads, and unlimited quiz synthesis.</p>
                    </div>

                    <div class="support-quick-card">
                        <div class="support-quick-icon" style="color: var(--warning);"><i class="fas fa-phone-alt"></i></div>
                        <h4 style="color: var(--text-primary); font-size: 16px;">Emergency Academic Hotline</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">Contact Academic Dean office at <strong>ext. 4022</strong> or email <code>support@studentos.ai</code> for urgent examination disputes.</p>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Submit Support Ticket Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-life-ring"></i> Submit Support Request</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="support.php">
                                <div class="form-group">
                                    <label for="subject">Issue Summary / Subject *</label>
                                    <input type="text" name="subject" id="subject" class="form-control" placeholder="e.g. Discrepancy in Midterm Grade for Database Systems" required>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select name="category" id="category" class="form-control">
                                            <option value="Academic & Grading">Academic & Grading</option>
                                            <option value="Attendance Dispute">Attendance Correction</option>
                                            <option value="Examination">Exam Registration / Hall Ticket</option>
                                            <option value="Technical Bug">Technical Bug / Portal Error</option>
                                            <option value="AI Assistant">AI Assistant & PDF Q&A</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="priority">Priority Level</label>
                                        <select name="priority" id="priority" class="form-control">
                                            <option value="low">Low Priority</option>
                                            <option value="medium" selected>Medium Priority</option>
                                            <option value="high">High / Urgent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="message">Detailed Description *</label>
                                    <textarea name="message" id="message" class="form-control" rows="4" placeholder="Detail the situation clearly and mention specific dates, courses, or error messages..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-paper-plane"></i> Submit Ticket to Administration
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Tickets & FAQ -->
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-lg);">
                        <!-- Ticket History -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-ticket-alt"></i> My Ticket Status (<?php echo count($tickets); ?>)</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 14px;">
                                    <?php foreach ($tickets as $t): 
                                        $isResolved = ($t['status'] ?? '') === 'resolved';
                                        $prio = strtolower($t['priority'] ?? 'medium');
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
                                                <div style="font-size: 12px; color: var(--text-secondary); background: var(--bg-card); padding: 8px 12px; border-radius: var(--radius-sm); border-left: 3px solid var(--primary); margin: 6px 0;">
                                                    <strong>Staff Update:</strong> <?php echo htmlspecialchars($t['response']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 11.5px; color: var(--text-muted); display: flex; justify-content: space-between; margin-top: 6px;">
                                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($t['category']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($t['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Accordion -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                            </div>
                            <div class="card-body" style="padding: var(--spacing-md);">
                                <div class="faq-accordion" style="max-width: 100%;">
                                    <div class="faq-item active">
                                        <button class="faq-question" onclick="toggleFaq(this)">
                                            <span>How do I submit an assignment revision?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            Navigate to <strong>Assignments</strong>, locate the submission, and click "Resubmit Assignment" before the deadline.
                                        </div>
                                    </div>
                                    <div class="faq-item">
                                        <button class="faq-question" onclick="toggleFaq(this)">
                                            <span>Why did the PDF Q&A say "information not found"?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            StudentOS AI uses RAG grounded retrieval. If the uploaded document lacks relevant material, the AI alerts you rather than fabricating facts.
                                        </div>
                                    </div>
                                </div>
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
    function toggleFaq(btn) {
        const item = btn.parentElement;
        const isActive = item.classList.contains('active');
        document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('active'));
        if (!isActive) item.classList.add('active');
    }

    function filterFaqs() {
        const query = (document.getElementById('helpSearch').value || '').toLowerCase();
        document.querySelectorAll('.faq-item').forEach(el => {
            const text = el.innerText.toLowerCase();
            el.style.display = text.includes(query) ? 'block' : 'none';
        });
    }
    </script>
</body>
</html>
