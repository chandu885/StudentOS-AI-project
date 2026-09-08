<?php
// frontend/student/study-sessions.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';

$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = sanitize($_POST['topic'] ?? 'Deep Study');
    $duration = (int)($_POST['duration'] ?? 25);
    $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    $notes = sanitize($_POST['notes'] ?? '');
    $sessionDate = date('Y-m-d');

    if (!empty($topic) && $db) {
        $stmt = $db->prepare("INSERT INTO `study_sessions` (user_id, subject_id, topic, duration_minutes, notes, session_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iisis", $userId, $subjectId, $topic, $duration, $notes, $sessionDate);
            if ($stmt->execute()) {
                $successMsg = 'Study session logged successfully!';
            }
        }
    }
}

// Fetch sessions directly from database
$sessions = [];
if ($db) {
    $stmt = $db->prepare("SELECT ss.*, s.name as subject_name FROM `study_sessions` ss LEFT JOIN `subjects` s ON ss.subject_id = s.id WHERE ss.user_id = ? ORDER BY ss.session_date DESC, ss.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $sessions[] = $row;
        }
    }
}

// Fetch enrolled subjects for dropdown
$subjects = [];
if ($db) {
    $subStmt = $db->prepare("SELECT s.id, s.name FROM `student_subjects` ss JOIN `subjects` s ON ss.subject_id = s.id WHERE ss.student_id = ? ORDER BY s.name ASC");
    if ($subStmt) {
        $subStmt->bind_param("i", $userId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($sr = $subRes->fetch_assoc()) {
            $subjects[] = $sr;
        }
    }
}

$totalMinutes = 0;
foreach ($sessions as $s) $totalMinutes += ($s['duration_minutes'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus & Study Sessions - StudentOS AI</title>
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
                        <h1>Focus Timer & Study Logs</h1>
                        <p class="page-subtitle">Track focused deep-work sessions with the built-in Pomodoro timer</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <!-- Pomodoro Timer Card -->
                    <div class="card" style="text-align: center; padding: 32px 24px;">
                        <span class="badge badge-purple" style="margin-bottom: 16px;">Pomodoro Focus Mode</span>
                        <div id="timerDisplay" style="font-size: 56px; font-weight: 800; color: var(--text-primary); font-family: monospace; margin-bottom: 24px;">
                            25:00
                        </div>
                        <div style="display: flex; justify-content: center; gap: 12px; margin-bottom: 24px;">
                            <button class="btn btn-primary" id="startTimerBtn" onclick="toggleTimer()">
                                <i class="fas fa-play"></i> Start Focus
                            </button>
                            <button class="btn btn-secondary" onclick="resetTimer()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                        <p style="font-size: 12px; color: var(--text-muted);">25 minutes focus • 5 minutes break</p>
                    </div>

                    <!-- Log Session Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-pen-fancy"></i> Log Study Session</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="study-sessions.php">
                                <div class="form-group">
                                    <label for="topic">Study Topic</label>
                                    <input type="text" name="topic" id="topic" class="form-control" placeholder="e.g. Graph Algorithms & Dijkstra" required>
                                </div>
                                <?php if (!empty($subjects)): ?>
                                <div class="form-group">
                                    <label for="sessionSubject">Subject</label>
                                    <select name="subject_id" id="sessionSubject" class="form-control">
                                        <option value="">-- General Study --</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label for="duration">Duration (minutes)</label>
                                    <input type="number" name="duration" id="duration" class="form-control" value="25" min="5" max="360" required>
                                </div>
                                <div class="form-group">
                                    <label for="notes">Session Notes / Key Learnings</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Summary of what you covered..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-save"></i> Save Session
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Study History</h3>
                        <span class="badge badge-primary">Total: <?php echo round($totalMinutes / 60, 1); ?> hrs</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions)): ?>
                            <div style="text-align: center; padding: 36px; color: var(--text-muted);">
                                <i class="fas fa-stopwatch" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                                <strong style="color: var(--text-primary);">No Study Sessions Logged Yet</strong>
                                <p style="font-size: 13px; margin-top: 4px;">Use the Pomodoro timer or manually record your study sprints above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Topic</th>
                                            <th>Duration</th>
                                            <th>Notes</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $ses): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ses['topic']); ?></strong>
                                                    <?php if (!empty($ses['subject_name'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($ses['subject_name']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($ses['duration_minutes']); ?> mins</span></td>
                                                <td style="color: var(--text-muted); font-size: 13px;"><?php echo htmlspecialchars($ses['notes'] ?? '—'); ?></td>
                                                <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($ses['session_date'] ?? $ses['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    let timerInterval = null;
    let secondsLeft = 25 * 60;
    let isRunning = false;

    function updateDisplay() {
        const m = Math.floor(secondsLeft / 60).toString().padStart(2, '0');
        const s = (secondsLeft % 60).toString().padStart(2, '0');
        document.getElementById('timerDisplay').textContent = `${m}:${s}`;
    }

    function toggleTimer() {
        const btn = document.getElementById('startTimerBtn');
        if (isRunning) {
            clearInterval(timerInterval);
            isRunning = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Resume';
        } else {
            isRunning = true;
            btn.innerHTML = '<i class="fas fa-pause"></i> Pause';
            timerInterval = setInterval(() => {
                if (secondsLeft > 0) {
                    secondsLeft--;
                    updateDisplay();
                } else {
                    clearInterval(timerInterval);
                    isRunning = false;
                    showToast('Focus session completed! Great job! 🎉', 'success');
                    btn.innerHTML = '<i class="fas fa-play"></i> Start Focus';
                    secondsLeft = 25 * 60;
                    updateDisplay();
                }
            }, 1000);
        }
    }

    function resetTimer() {
        clearInterval(timerInterval);
        isRunning = false;
        secondsLeft = 25 * 60;
        updateDisplay();
        document.getElementById('startTimerBtn').innerHTML = '<i class="fas fa-play"></i> Start Focus';
    }
    </script>
</body>
</html>
