<?php
// frontend/student/search.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$db = getDbConnection();
$query = sanitize($_GET['q'] ?? '');
$results = [];

if (!empty($query) && $db) {
    $searchPattern = '%' . $query . '%';

    // 1. Search across user notes
    $nStmt = $db->prepare("SELECT id, title, content FROM `notes` WHERE user_id = ? AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)");
    if ($nStmt) {
        $nStmt->bind_param("isss", $userId, $searchPattern, $searchPattern, $searchPattern);
        $nStmt->execute();
        $nRes = $nStmt->get_result();
        while ($note = $nRes->fetch_assoc()) {
            $results[] = [
                'type' => 'Note',
                'title' => $note['title'],
                'snippet' => truncate($note['content'], 120),
                'link' => 'notes.php',
                'icon' => 'fa-sticky-note',
                'badge' => 'badge-primary'
            ];
        }
    }

    // 2. Search user tasks
    $tStmt = $db->prepare("SELECT id, title, description, deadline FROM `tasks` WHERE user_id = ? AND (title LIKE ? OR description LIKE ?)");
    if ($tStmt) {
        $tStmt->bind_param("iss", $userId, $searchPattern, $searchPattern);
        $tStmt->execute();
        $tRes = $tStmt->get_result();
        while ($task = $tRes->fetch_assoc()) {
            $results[] = [
                'type' => 'Task',
                'title' => $task['title'],
                'snippet' => !empty($task['deadline']) ? 'Due ' . date('M d, Y', strtotime($task['deadline'])) : 'Pending task',
                'link' => 'tasks.php',
                'icon' => 'fa-tasks',
                'badge' => 'badge-warning'
            ];
        }
    }

    // 3. Search enrolled subjects
    $sStmt = $db->prepare("SELECT s.id, s.name, s.code, s.syllabus FROM `student_subjects` ss JOIN `subjects` s ON ss.subject_id = s.id WHERE ss.student_id = ? AND (s.name LIKE ? OR s.code LIKE ? OR s.syllabus LIKE ?)");
    if ($sStmt) {
        $sStmt->bind_param("isss", $userId, $searchPattern, $searchPattern, $searchPattern);
        $sStmt->execute();
        $sRes = $sStmt->get_result();
        while ($sub = $sRes->fetch_assoc()) {
            $results[] = [
                'type' => 'Subject',
                'title' => $sub['code'] . ' - ' . $sub['name'],
                'snippet' => truncate($sub['syllabus'] ?? 'Enrolled course subject', 120),
                'link' => 'subjects.php',
                'icon' => 'fa-book',
                'badge' => 'badge-purple'
            ];
        }
    }

    // 4. Search documents
    $dStmt = $db->prepare("SELECT id, title, description FROM `documents` WHERE (user_id = ? OR is_public = 1) AND (title LIKE ? OR description LIKE ?)");
    if ($dStmt) {
        $dStmt->bind_param("iss", $userId, $searchPattern, $searchPattern);
        $dStmt->execute();
        $dRes = $dStmt->get_result();
        while ($doc = $dRes->fetch_assoc()) {
            $results[] = [
                'type' => 'Document',
                'title' => $doc['title'],
                'snippet' => truncate($doc['description'] ?? 'Course material PDF', 120),
                'link' => 'documents.php',
                'icon' => 'fa-file-pdf',
                'badge' => 'badge-info'
            ];
        }
    }

    // 5. Search notices
    $noStmt = $db->prepare("SELECT id, title, content FROM `notices` WHERE title LIKE ? OR content LIKE ?");
    if ($noStmt) {
        $noStmt->bind_param("ss", $searchPattern, $searchPattern);
        $noStmt->execute();
        $noRes = $noStmt->get_result();
        while ($not = $noRes->fetch_assoc()) {
            $results[] = [
                'type' => 'Notice',
                'title' => $not['title'],
                'snippet' => truncate($not['content'], 120),
                'link' => 'notices.php',
                'icon' => 'fa-bullhorn',
                'badge' => 'badge-danger'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - StudentOS AI</title>
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
                        <h1>Search Portal</h1>
                        <p class="page-subtitle">Find notes, coursework, assignments, and tasks across your workspace</p>
                    </div>
                    <div class="header-actions">
                        <a href="ai-search.php?q=<?php echo urlencode($query); ?>" class="btn btn-primary">
                            <i class="fas fa-robot"></i> Try AI Semantic Search
                        </a>
                    </div>
                </div>

                <!-- Search Input Bar -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <form method="GET" action="search.php" style="display: flex; gap: 12px;">
                            <div class="input-group" style="flex: 1;">
                                <span class="input-icon"><i class="fas fa-search"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="Search keywords (e.g. Normalization, Midterms, Red-Black Trees)..." value="<?php echo htmlspecialchars($query); ?>" autofocus required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <?php if (!empty($query)): ?>
                    <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--text-primary);">
                        Results for "<span style="color: var(--primary);"><?php echo htmlspecialchars($query); ?></span>" (<?php echo count($results); ?> found)
                    </h3>

                    <?php if (!empty($results)): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($results as $res): ?>
                                <div class="card" style="margin-bottom: 0;">
                                    <div class="card-body" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--primary);">
                                                <i class="fas <?php echo $res['icon']; ?>"></i>
                                            </div>
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                    <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($res['title']); ?></strong>
                                                    <span class="badge <?php echo $res['badge']; ?>"><?php echo $res['type']; ?></span>
                                                </div>
                                                <p style="font-size: 13px; color: var(--text-muted); margin: 0;"><?php echo htmlspecialchars($res['snippet']); ?></p>
                                            </div>
                                        </div>
                                        <a href="<?php echo $res['link']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Open</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h4>No matching items found</h4>
                            <p>Try searching with another keyword or use AI Search to query across concepts.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
