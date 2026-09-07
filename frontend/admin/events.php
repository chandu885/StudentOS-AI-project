<?php
// frontend/admin/events.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'Campus event created and added to university calendar!';
}

$events = [
    ['id' => 1, 'title' => 'Generative AI & LLM Systems Workshop', 'date' => '2026-03-18', 'venue' => 'Auditorium Hall 1', 'reg_count' => 180, 'max' => 200],
    ['id' => 2, 'title' => 'Annual 24-Hour Hackathon 2026', 'date' => '2026-03-25', 'venue' => 'Innovation Hub & Labs', 'reg_count' => 240, 'max' => 300]
];
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
                        <p class="page-subtitle">Schedule workshops, competitions, seminars, and manage registration limits</p>
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

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-star"></i> Scheduled Events</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Registered Participants</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $ev): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ev['title']); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($ev['date'])); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($ev['venue']); ?></span></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $ev['reg_count']; ?> / <?php echo $ev['max']; ?> Registered</span>
                                            </td>
                                            <td><span class="badge badge-success">Active</span></td>
                                        </tr>
                                    <?php endforeach; ?>
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
                        <label for="evTitle">Event Name</label>
                        <input type="text" name="title" id="evTitle" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="evDate">Date</label>
                            <input type="date" name="date" id="evDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="evVenue">Venue</label>
                            <input type="text" name="venue" id="evVenue" class="form-control" placeholder="Auditorium" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="evCapacity">Max Capacity</label>
                        <input type="number" name="capacity" id="evCapacity" class="form-control" value="200">
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
