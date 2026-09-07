<?php
// frontend/student/notes.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';

// Handle Note actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        $tags = sanitize($_POST['tags'] ?? '');

        $res = apiCall('/notes.php', 'POST', [
            'title' => $title,
            'content' => $content,
            'subject_id' => $subjectId,
            'tags' => $tags
        ]);
        if (!empty($res['success'])) {
            $successMsg = 'Note created successfully!';
        } else {
            $errorMsg = 'Failed to create note.';
        }
    } elseif ($action === 'delete') {
        $noteId = (int)$_POST['note_id'];
        apiCall("/notes.php?id={$noteId}", 'DELETE');
        $successMsg = 'Note deleted.';
    }
}

$notesRes = apiCall('/notes.php', 'GET');
$notes = $notesRes['notes'] ?? [
    ['id' => 1, 'title' => 'Relational Normalization & Boyce-Codd Normal Form', 'content' => 'A relation R is in BCNF if for every functional dependency X -> Y, X is a superkey of R. BCNF eliminates all redundancy based on functional dependencies.', 'tags' => 'dbms, normalization, bcnf', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['id' => 2, 'title' => 'Operating Systems: Critical Section Problem', 'content' => 'Mutual Exclusion: If process Pi is executing in its critical section, then no other processes can be executing in their critical sections. Progress and Bounded Waiting are also required.', 'tags' => 'os, synchronization', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],
    ['id' => 3, 'title' => 'Time Complexity Cheat Sheet', 'content' => 'QuickSort: Average O(n log n), Worst O(n^2). MergeSort: Always O(n log n). Binary Search: O(log n). Hash Map lookup: Average O(1).', 'tags' => 'dsa, algorithms', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Study Notes - StudentOS AI</title>
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
                        <h1>Study Notes & Summaries</h1>
                        <p class="page-subtitle">Your personal revision notes, lecture takeaways, and concept summaries</p>
                    </div>
                    <div class="header-actions">
                        <a href="ai-summarizer.php" class="btn btn-outline"><i class="fas fa-magic"></i> AI Summarizer</a>
                        <button class="btn btn-primary" onclick="openModal('addNoteModal')">
                            <i class="fas fa-plus"></i> Create Note
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
                    <?php foreach ($notes as $note): ?>
                        <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                            <div class="card-header">
                                <h3 style="font-size: 15px;"><?php echo htmlspecialchars($note['title']); ?></h3>
                                <form method="POST" action="notes.php" style="margin: 0;" onsubmit="return confirm('Delete this note?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="submit" style="color: var(--text-muted); cursor: pointer; background: none; border: none;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="card-body">
                                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px;">
                                    <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                                </p>
                                <?php if (!empty($note['tags'])): ?>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <?php foreach (explode(',', $note['tags']) as $tag): ?>
                                            <span class="badge badge-secondary">#<?php echo trim(htmlspecialchars($tag)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 10px 18px; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-muted); background: var(--bg-primary);">
                                <?php echo date('M d, Y', strtotime($note['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Create Note Modal -->
    <div class="modal-backdrop" id="addNoteModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Create New Note</h3>
                <button class="modal-close" onclick="closeModal('addNoteModal')">&times;</button>
            </div>
            <form method="POST" action="notes.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="noteTitle">Note Title</label>
                        <input type="text" name="title" id="noteTitle" class="form-control" placeholder="e.g. BCNF vs 3NF Comparison" required>
                    </div>
                    <div class="form-group">
                        <label for="noteContent">Content</label>
                        <textarea name="content" id="noteContent" class="form-control" rows="6" placeholder="Write your notes here..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="noteTags">Tags (comma separated)</label>
                        <input type="text" name="tags" id="noteTags" class="form-control" placeholder="dbms, theory, exam-prep">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
