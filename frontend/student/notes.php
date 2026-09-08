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

$db = getDbConnection();

// Handle Note actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        $tags = sanitize($_POST['tags'] ?? '');

        if (!empty($title) && !empty($content) && $db) {
            $stmt = $db->prepare("INSERT INTO `notes` (user_id, subject_id, title, content, tags, is_pinned, is_favorite, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param("iisss", $userId, $subjectId, $title, $content, $tags);
                if ($stmt->execute()) {
                    $successMsg = 'Note created successfully!';
                } else {
                    $errorMsg = 'Failed to create note.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $noteId = (int)($_POST['note_id'] ?? 0);
        if ($noteId > 0 && $db) {
            $stmt = $db->prepare("DELETE FROM `notes` WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $noteId, $userId);
                $stmt->execute();
                $successMsg = 'Note deleted.';
            }
        }
    }
}

// Fetch user notes directly from database
$notes = [];
if ($db) {
    $stmt = $db->prepare("SELECT n.*, s.name as subject_name FROM `notes` n LEFT JOIN `subjects` s ON n.subject_id = s.id WHERE n.user_id = ? ORDER BY n.is_pinned DESC, n.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $notes[] = $row;
        }
    }
}

// Fetch enrolled subjects for student dropdown
$subjects = [];
if ($db) {
    $subStmt = $db->prepare("SELECT s.id, s.name, s.code FROM `student_subjects` ss JOIN `subjects` s ON ss.subject_id = s.id WHERE ss.student_id = ? ORDER BY s.name ASC");
    if ($subStmt) {
        $subStmt->bind_param("i", $userId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($sr = $subRes->fetch_assoc()) {
            $subjects[] = $sr;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Study Notes - StudentOS AI</title>
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

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
                    <?php if (empty($notes)): ?>
                        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 48px;">
                            <i class="fas fa-sticky-note" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Study Notes Created Yet</h3>
                            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Capture lecture points, formula sheets, or study summaries to prepare for examinations.</p>
                            <button class="btn btn-primary" onclick="openModal('addNoteModal')">
                                <i class="fas fa-plus"></i> Create Your First Note
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                                <div class="card-header">
                                    <div>
                                        <h3 style="font-size: 15px; margin-bottom: 4px;"><?php echo htmlspecialchars($note['title']); ?></h3>
                                        <?php if (!empty($note['subject_name'])): ?>
                                            <span class="badge badge-primary" style="font-size: 11px;"><?php echo htmlspecialchars($note['subject_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="notes.php" style="margin: 0;" onsubmit="return confirm('Delete this note?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                        <button type="submit" style="color: var(--text-muted); cursor: pointer; background: none; border: none;" title="Delete Note">
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
                    <?php endif; ?>
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
                    <?php if (!empty($subjects)): ?>
                    <div class="form-group">
                        <label for="noteSubject">Subject (Optional)</label>
                        <select name="subject_id" id="noteSubject" class="form-control">
                            <option value="">-- General / No Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['code'] . ' - ' . $s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
