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
            $sentences = preg_split('/(?<=[.?!])\s+/', trim($inputText));
            $cleanSentences = array_filter(array_map('trim', $sentences));
            if (!empty($cleanSentences)) {
                $takeaways = array_slice($cleanSentences, 0, 5);
                $summaryOutput = "### Key Summary\n- " . implode("\n- ", $takeaways);
            } else {
                $summaryOutput = "No summary could be generated for the provided text. Please provide valid text content.";
            }
        }
    }
}
?>
<?php
$pageTitle = 'AI Lecture Summarizer - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
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
