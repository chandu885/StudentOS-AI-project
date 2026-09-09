<?php
// frontend/admin/schedules.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delId = (int)($_POST['schedule_id'] ?? 0);
        if ($delId > 0 && $db) {
            $del = $db->prepare("DELETE FROM class_schedules WHERE id = ?");
            $del->bind_param("i", $delId);
            if ($del->execute()) {
                $successMsg = 'Timetable slot removed successfully.';
            } else {
                $errorMsg = 'Failed to delete slot: ' . $db->error;
            }
            $del->close();
        }
    } elseif (isset($_POST['subject_id'], $_POST['day_of_week'])) {
        $subjectId = (int)$_POST['subject_id'];
        $facultyId = (int)$_POST['faculty_id'];
        $dayOfWeek = sanitize($_POST['day_of_week']);
        $startTime = sanitize($_POST['start_time'] ?? '09:00:00');
        $endTime = sanitize($_POST['end_time'] ?? '10:00:00');
        $room = sanitize($_POST['room_number'] ?? 'LH-101');
        $semester = sanitize($_POST['semester'] ?? '1');
        $section = sanitize($_POST['section'] ?? 'A');

        if ($subjectId <= 0 || empty($dayOfWeek) || empty($room)) {
            $errorMsg = 'Subject, Day of Week, and Room Number are required.';
        } elseif ($db) {
            // Fetch subject metadata
            $sInfo = $db->query("SELECT course_id, department_id, faculty_id FROM subjects WHERE id = $subjectId")->fetch_assoc();
            $courseId = $sInfo['course_id'] ?? 1;
            $deptId = $sInfo['department_id'] ?? 1;
            if ($facultyId <= 0) {
                $facultyId = !empty($sInfo['faculty_id']) ? (int)$sInfo['faculty_id'] : $userId;
            }

            $stmt = $db->prepare(
                "INSERT INTO class_schedules (subject_id, faculty_id, department_id, course_id, semester, section, day_of_week, start_time, end_time, room_number, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            if ($stmt) {
                $stmt->bind_param("iiiissssss", $subjectId, $facultyId, $deptId, $courseId, $semester, $section, $dayOfWeek, $startTime, $endTime, $room);
                if ($stmt->execute()) {
                    $successMsg = "Timetable slot for $dayOfWeek ($room) created successfully!";
                } else {
                    $errorMsg = 'Failed to save timetable slot: ' . $db->error;
                }
                $stmt->close();
            }
        }
    }
}

// Fetch dropdown data
$subjects = [];
$facultyList = [];
if ($db) {
    $sRes = $db->query("SELECT id, code, name FROM subjects ORDER BY code ASC");
    if ($sRes) $subjects = $sRes->fetch_all(MYSQLI_ASSOC);

    $fRes = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role_id = 3 AND deleted_at IS NULL ORDER BY first_name ASC");
    if ($fRes) $facultyList = $fRes->fetch_all(MYSQLI_ASSOC);
}

// Filter by day
$filterDay = isset($_GET['day']) ? sanitize($_GET['day']) : '';

// Fetch schedules
$slots = [];
if ($db) {
    $where = "";
    if (!empty($filterDay) && in_array($filterDay, ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'])) {
        $where = "WHERE cs.day_of_week = '" . $db->real_escape_string($filterDay) . "'";
    }
    $q = "SELECT cs.*, 
                 s.name AS subject_name, s.code AS subject_code,
                 CONCAT(u.first_name, ' ', u.last_name) AS faculty_name,
                 COALESCE(d.name, 'General') AS dept_name
          FROM class_schedules cs
          JOIN subjects s ON cs.subject_id = s.id
          JOIN users u ON cs.faculty_id = u.id
          LEFT JOIN departments d ON cs.department_id = d.id
          $where
          ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC";
    $res = $db->query($q);
    if ($res) {
        $slots = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Schedules - StudentOS AI</title>
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
                        <h1>Master Timetable Schedules</h1>
                        <p class="page-subtitle">Coordinate institutional lecture periods, laboratories, and classroom allocations</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('addSlotModal')">
                            <i class="fas fa-plus"></i> Add Time Slot
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

                <!-- Day Filter Buttons -->
                <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                    <a href="schedules.php" class="btn <?php echo empty($filterDay) ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;">All Days</a>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d): ?>
                        <a href="schedules.php?day=<?php echo $d; ?>" class="btn <?php echo $filterDay === $d ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;"><?php echo $d; ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-calendar-alt"></i> Allocated Time Slots (<?php echo count($slots); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time Slot</th>
                                        <th>Subject</th>
                                        <th>Cohort</th>
                                        <th>Assigned Instructor</th>
                                        <th>Room / Hall</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($slots)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No timetable slots found<?php echo !empty($filterDay) ? " for $filterDay" : ''; ?>. Click "Add Time Slot" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($slots as $s): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($s['day_of_week']); ?></span></td>
                                                <td>
                                                    <span style="color: var(--primary); font-weight: 500;">
                                                        <?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($s['subject_code']); ?></strong>
                                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                                </td>
                                                <td><span class="badge badge-purple">Sem <?php echo htmlspecialchars($s['semester']); ?> (<?php echo htmlspecialchars($s['section']); ?>)</span></td>
                                                <td><i class="fas fa-user-tie" style="color: var(--primary); margin-right: 4px;"></i> <?php echo htmlspecialchars($s['faculty_name']); ?></td>
                                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($s['room_number']); ?></span></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule slot?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="schedule_id" value="<?php echo (int)$s['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px; color: var(--danger);" title="Delete slot">
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

    <!-- Add Time Slot Modal -->
    <div class="modal-backdrop" id="addSlotModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Allocate Timetable Slot</h3>
                <button class="modal-close" onclick="closeModal('addSlotModal')">&times;</button>
            </div>
            <form method="POST" action="schedules.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="slSubj">Subject *</label>
                        <select name="subject_id" id="slSubj" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $sb): ?>
                                <option value="<?php echo (int)$sb['id']; ?>">
                                    <?php echo htmlspecialchars($sb['code']); ?> - <?php echo htmlspecialchars($sb['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slDay">Day of Week *</label>
                            <select name="day_of_week" id="slDay" class="form-control" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="slFac">Instructor</label>
                            <select name="faculty_id" id="slFac" class="form-control">
                                <option value="">Auto (Subject Faculty)</option>
                                <?php foreach ($facultyList as $fc): ?>
                                    <option value="<?php echo (int)$fc['id']; ?>">
                                        <?php echo htmlspecialchars($fc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slStart">Start Time</label>
                            <input type="time" name="start_time" id="slStart" class="form-control" value="09:00" required>
                        </div>
                        <div class="form-group">
                            <label for="slEnd">End Time</label>
                            <input type="time" name="end_time" id="slEnd" class="form-control" value="10:00" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slRoom">Room / Hall *</label>
                            <input type="text" name="room_number" id="slRoom" class="form-control" placeholder="LH-201" required>
                        </div>
                        <div class="form-group">
                            <label for="slSem">Semester</label>
                            <input type="number" name="semester" id="slSem" class="form-control" value="5" min="1" max="8">
                        </div>
                        <div class="form-group">
                            <label for="slSec">Section</label>
                            <input type="text" name="section" id="slSec" class="form-control" value="A">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSlotModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Slot</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
