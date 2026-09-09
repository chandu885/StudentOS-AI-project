<?php
// frontend/admin/events.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['event_id'] ?? 0);
        if ($delId > 0 && $db) {
            $del = $db->prepare("DELETE FROM events WHERE id = ?");
            $del->bind_param("i", $delId);
            if ($del->execute()) {
                $successMsg = 'Campus event deleted successfully.';
            } else {
                $errorMsg = 'Failed to delete event: ' . $db->error;
            }
            $del->close();
        }
    } elseif (isset($_POST['title'], $_POST['date'])) {
        $title = sanitize($_POST['title'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $venue = sanitize($_POST['venue'] ?? '');
        $organizedBy = sanitize($_POST['organized_by'] ?? 'Administration');
        $description = sanitize($_POST['description'] ?? '');
        $startTime = !empty($_POST['start_time']) ? sanitize($_POST['start_time']) : '10:00:00';
        $endTime = !empty($_POST['end_time']) ? sanitize($_POST['end_time']) : '13:00:00';

        if (empty($title) || empty($date)) {
            $errorMsg = 'Event Title and Date are required.';
        } elseif ($db) {
            $stmt = $db->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, venue, organized_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sssssss", $title, $description, $date, $startTime, $endTime, $venue, $organizedBy);
                if ($stmt->execute()) {
                    $successMsg = 'Campus event scheduled and published to the university calendar!';
                } else {
                    $errorMsg = 'Failed to schedule event: ' . $db->error;
                }
                $stmt->close();
            }
        }
    }
}

// Fetch events with live registration count
$events = [];
if ($db) {
    $res = $db->query(
        "SELECT e.*, 
                (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) AS reg_count
         FROM events e
         ORDER BY e.event_date ASC"
    );
    if ($res) {
        $events = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - StudentOS AI</title>
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
                        <h1>Campus Events Coordination</h1>
                        <p class="page-subtitle">Schedule workshops, competitions, seminars, and track participant registrations</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addEventModal')">
                            <i class="fas fa-calendar-plus"></i> Create Event
                        </button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-calendar-star"></i> Scheduled Events (<?php echo count($events); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Event Details</th>
                                        <th>Date & Time</th>
                                        <th>Venue</th>
                                        <th>Organizer</th>
                                        <th>Registrations</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No campus events currently scheduled. Click "Create Event" to post one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $today = date('Y-m-d');
                                        foreach ($events as $ev): 
                                            $eventDate = $ev['event_date'];
                                            $isPast = $eventDate < $today;
                                            $isToday = $eventDate === $today;
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ev['title']); ?></strong>
                                                    <?php if (!empty($ev['description'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 320px;"><?php echo htmlspecialchars($ev['description']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><i class="fas fa-calendar-day" style="color: var(--primary);"></i> <?php echo date('M d, Y', strtotime($ev['event_date'])); ?></div>
                                                    <?php if (!empty($ev['start_time'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted);">
                                                            <?php echo date('h:i A', strtotime($ev['start_time'])); ?><?php echo !empty($ev['end_time']) ? ' - ' . date('h:i A', strtotime($ev['end_time'])) : ''; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($ev['venue'] ?? 'Campus Grounds'); ?></span></td>
                                                <td><?php echo htmlspecialchars($ev['organized_by'] ?? 'Administration'); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <i class="fas fa-users"></i> <?php echo (int)$ev['reg_count']; ?> Registered
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($isToday): ?>
                                                        <span class="badge badge-warning">Happening Today</span>
                                                    <?php elseif ($isPast): ?>
                                                        <span class="badge badge-secondary">Concluded</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Upcoming</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel and delete this event?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="event_id" value="<?php echo (int)$ev['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete Event">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Add Event Modal -->
    <div class="modal-backdrop" id="addEventModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Create New Event</h3>
                <button class="modal-close" onclick="closeModal('addEventModal')">&times;</button>
            </div>
            <form method="POST" action="events.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="evTitle">Event Name *</label>
                        <input type="text" name="title" id="evTitle" class="form-control" placeholder="e.g. Annual Campus Hackathon" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="evDate">Event Date *</label>
                            <input type="date" name="date" id="evDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="evVenue">Venue *</label>
                            <input type="text" name="venue" id="evVenue" class="form-control" placeholder="Auditorium Hall 1" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="evStart">Start Time</label>
                            <input type="time" name="start_time" id="evStart" class="form-control" value="10:00">
                        </div>
                        <div class="form-group">
                            <label for="evEnd">End Time</label>
                            <input type="time" name="end_time" id="evEnd" class="form-control" value="13:00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="evOrg">Organized By</label>
                        <input type="text" name="organized_by" id="evOrg" class="form-control" placeholder="e.g. Student Council & Tech Club">
                    </div>
                    <div class="form-group">
                        <label for="evDesc">Event Description</label>
                        <textarea name="description" id="evDesc" class="form-control" rows="2" placeholder="Describe the event format, speaker highlights, or agenda..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Event</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
