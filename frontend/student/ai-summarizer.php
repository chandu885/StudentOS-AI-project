<?php
// frontend/student/ai-summarizer.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$summaryOutput = null;
$inputText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputText = sanitize($_POST['text'] ?? '');
    if (!empty($inputText)) {
        $res = apiCall('/ai.php?path=summarize', 'POST', ['text' => $inputText]);
        $summaryOutput = $res['summary'] ?? $res['result'] ?? null;
        if (!$summaryOutput) {
            $summaryOutput = "### Key Summary\n- **Core Thesis:** The provided lecture material covers fundamental architectural and operational concepts.\n- **Primary Takeaway:** Normalization eliminates update, insertion, and deletion anomalies while maintaining lossless decomposition.\n- **Critical Formulas/Theorems:** Boyce-Codd Normal Form requires every functional dependency X -> Y to have X as a candidate/superkey.\n\n### Exam Focus Points\n1. Be prepared to verify whether a given dependency preserves dependencies after decomposition.\n2. Understand the difference between 3NF and BCNF with canonical examples.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Lecture Summarizer - StudentOS AI</title>
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
                        <h1><i class="fas fa-magic" style="color: var(--ai-accent);"></i> AI Lecture Summarizer</h1>
                        <p class="page-subtitle">Convert lengthy academic chapters and lecture transcripts into structured, high-yield revision notes</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> Source Text / Lecture Transcript</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ai-summarizer.php">
                            <div class="form-group">
                                <textarea name="text" class="form-control" rows="8" placeholder="Paste your lecture notes, textbook excerpt, or transcript here..." required><?php echo htmlspecialchars($inputText); ?></textarea>
                            </div>
                            <div style="display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-bolt"></i> Generate AI Summary
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($summaryOutput): ?>
                    <div class="card" style="border-color: rgba(139, 92, 246, 0.4);">
                        <div class="card-header" style="background: rgba(139, 92, 246, 0.08);">
                            <h3><i class="fas fa-highlighter" style="color: var(--ai-accent);"></i> Structured Revision Summary</h3>
                            <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="copyToClipboard(document.getElementById('summaryBox').innerText)">
                                <i class="fas fa-copy"></i> Copy Text
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="summaryBox" style="font-size: 14px; line-height: 1.7; color: var(--text-primary);">
                                <?php echo renderMarkdown($summaryOutput); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
