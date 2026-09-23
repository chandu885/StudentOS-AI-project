<?php
// frontend/super-admin/schedules.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../backend/models/SystemModel.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();
$sysModel = new SystemModel();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_schedule') {
        $delId = (int)($_POST['schedule_id'] ?? 0);
        if ($delId > 0 && $db) {
            $stmt = $db->prepare("DELETE FROM class_schedules WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $delId);
                if ($stmt->execute()) {
                    $successMsg = 'Timetable slot removed successfully.';
                    $sysModel->logAudit($userId, 'SUPER_ADMIN_DELETE_SCHEDULE', 'class_schedules', $delId, "Super Admin removed timetable slot #{$delId}.");
                } else {
                    $errorMsg = 'Failed to delete timetable slot: ' . $db->error;
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'add_schedule') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $dayOfWeek = sanitize($_POST['day_of_week'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '09:00:00');
        $endTime = sanitize($_POST['end_time'] ?? '10:00:00');
        $room = sanitize($_POST['room_number'] ?? 'LH-101');
        $semester = sanitize($_POST['semester'] ?? '1');
        $section = sanitize($_POST['section'] ?? 'A');

        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if ($subjectId <= 0 || empty($dayOfWeek) || empty($room)) {
            $errorMsg = 'Subject, Day of Week, and Room Number are required.';
        } elseif (!in_array($dayOfWeek, $allowedDays)) {
            $errorMsg = 'Invalid day of the week selected.';
        } elseif ($db) {
            // Fetch subject metadata
            $sStmt = $db->prepare("SELECT course_id, department_id, faculty_id, name, code FROM subjects WHERE id = ?");
            $sInfo = null;
            if ($sStmt) {
                $sStmt->bind_param("i", $subjectId);
                $sStmt->execute();
                $sInfo = $sStmt->get_result()->fetch_assoc();
                $sStmt->close();
            }

            $courseId = (int)($sInfo['course_id'] ?? 1);
            $deptId = (int)($sInfo['department_id'] ?? 1);
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
                    $newId = $stmt->insert_id;
                    $successMsg = "Timetable slot for $dayOfWeek ($room) created successfully!";
                    $sysModel->logAudit($userId, 'SUPER_ADMIN_CREATE_SCHEDULE', 'class_schedules', $newId, "Super Admin created timetable slot for subject '{$sInfo['name']}' on {$dayOfWeek}.");
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
$instructors = [];
$departments = [];
$roleMap = [1 => 'Super Admin', 2 => 'Admin', 3 => 'Faculty'];

if ($db) {
    // Subjects
    $sRes = $db->query("SELECT s.id, s.code, s.name, s.semester, s.department_id, s.faculty_id, d.name AS dept_name FROM subjects s LEFT JOIN departments d ON s.department_id = d.id WHERE s.status = 'active' ORDER BY s.code ASC");
    if ($sRes) $subjects = $sRes->fetch_all(MYSQLI_ASSOC);

    // Instructors across Super Admin, Admin, Faculty
    $fRes = $db->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.role_id, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.role_id IN (1, 2, 3) AND u.deleted_at IS NULL ORDER BY u.first_name ASC");
    if ($fRes) $instructors = $fRes->fetch_all(MYSQLI_ASSOC);

    // Departments
    $dRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($dRes) $departments = $dRes->fetch_all(MYSQLI_ASSOC);
}

// Filters
$filterDay = isset($_GET['day']) ? sanitize($_GET['day']) : '';
$filterDept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$filterSem = !empty($_GET['semester']) ? trim($_GET['semester']) : null;

// Fetch schedules
$slots = [];
$uniqueRooms = [];
$uniqueInstructors = [];
$dayCounts = [];

if ($db) {
    $whereParts = [];
    $params = [];
    $types = "";

    if (!empty($filterDay) && in_array($filterDay, ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'])) {
        $whereParts[] = "cs.day_of_week = ?";
        $params[] = $filterDay;
        $types .= "s";
    }

    if ($filterDept) {
        $whereParts[] = "cs.department_id = ?";
        $params[] = $filterDept;
        $types .= "i";
    }

    if ($filterSem) {
        $cleanSem = preg_replace('/[^0-9]/', '', $filterSem);
        $whereParts[] = "(cs.semester = ? OR cs.semester = ?)";
        $params[] = $filterSem;
        $params[] = $cleanSem;
        $types .= "ss";
    }

    $whereSql = !empty($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";

    $sql = "SELECT cs.*, 
                   s.name AS subject_name, s.code AS subject_code,
                   CONCAT(u.first_name, ' ', u.last_name) AS faculty_name,
                   r.name AS faculty_role_name,
                   u.role_id AS faculty_role_id,
                   COALESCE(d.name, 'General') AS dept_name,
                   COALESCE(d.code, 'GEN') AS dept_code
            FROM class_schedules cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN users u ON cs.faculty_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON cs.department_id = d.id
            $whereSql
            ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC";

    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) $slots = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        $res = $db->query($sql);
        if ($res) $slots = $res->fetch_all(MYSQLI_ASSOC);
    }

    // Compute metrics
    foreach ($slots as $slot) {
        if (!empty($slot['room_number'])) {
            $uniqueRooms[$slot['room_number']] = true;
        }
        if (!empty($slot['faculty_id'])) {
            $uniqueInstructors[$slot['faculty_id']] = true;
        }
        $day = $slot['day_of_week'] ?? 'Monday';
        $dayCounts[$day] = ($dayCounts[$day] ?? 0) + 1;
    }
}

$totalSlotsCount = count($slots);
$roomCount = count($uniqueRooms);
$instructorCount = count($uniqueInstructors);
$busiestDay = '—';
if (!empty($dayCounts)) {
    arsort($dayCounts);
    $busiestDay = array_key_first($dayCounts);
}

$pageTitle = 'Master Timetable Schedules - Super Admin - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h1><i class="fas fa-calendar-alt" style="color: var(--primary); margin-right: 8px;"></i> Master Timetable Schedules</h1>
        <p class="page-subtitle">Institution-wide schedule coordinator: Allocate lecture halls, class periods, and instructor assignments</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openModal('addSlotModal')" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add Time Slot
        </button>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php endif; ?>

<!-- Operational Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Allocated Slots</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalSlotsCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-door-open"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Classrooms / Halls</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $roomCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6, 182, 212, 0.12); color: #06B6D4; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Instructors Scheduled</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $instructorCount; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 22px;">
            <i class="fas fa-business-time"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Peak Class Day</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo htmlspecialchars($busiestDay); ?></div>
        </div>
    </div>
</div>

<!-- Day Filter Navigation -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
    <?php
    function buildScheduleUrl($dayVal, $deptVal, $semVal) {
        $p = [];
        if (!empty($dayVal)) $p['day'] = $dayVal;
        if (!empty($deptVal)) $p['department_id'] = $deptVal;
        if (!empty($semVal)) $p['semester'] = $semVal;
        return 'schedules.php' . (!empty($p) ? '?' . http_build_query($p) : '');
    }
    ?>
    <a href="<?php echo buildScheduleUrl('', $filterDept, $filterSem); ?>" class="btn <?php echo empty($filterDay) ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
        All Days
    </a>
    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $d): ?>
        <a href="<?php echo buildScheduleUrl($d, $filterDept, $filterSem); ?>" class="btn <?php echo $filterDay === $d ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
            <?php echo $d; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Schedule Table Card -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3><i class="fas fa-clock"></i> Allocated Timetable Slots (<?php echo count($slots); ?>)</h3>
        <span style="font-size: 12px; color: var(--text-muted);">
            <?php echo !empty($filterDay) ? "Filtered for $filterDay" : "All weekly class sessions"; ?>
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Day</th>
                        <th style="min-width: 140px;">Time Slot</th>
                        <th style="min-width: 200px;">Subject</th>
                        <th style="min-width: 140px;">Cohort Scope</th>
                        <th style="min-width: 180px;">Assigned Instructor</th>
                        <th style="min-width: 120px;">Room / Hall</th>
                        <th style="text-align: right; width: 90px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slots)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                <i class="fas fa-calendar-times" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                No timetable slots found<?php echo !empty($filterDay) ? " for $filterDay" : ''; ?>. Click "Add Time Slot" above to schedule a class session.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($slots as $s): 
                            $roleLabel = $roleMap[(int)($s['faculty_role_id'] ?? 3)] ?? 'Faculty';
                        ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" style="font-size: 12px; font-weight: 600;">
                                        <?php echo htmlspecialchars($s['day_of_week']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--primary); font-weight: 600; font-size: 13px;">
                                        <?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($s['subject_code']); ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        <?php echo htmlspecialchars($s['subject_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-purple" style="font-size: 11px;">
                                        Sem <?php echo htmlspecialchars($s['semester']); ?> (Sec <?php echo htmlspecialchars($s['section']); ?>)
                                    </span>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        <?php echo htmlspecialchars($s['dept_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-user-tie" style="color: var(--primary); font-size: 12px;"></i>
                                        <div>
                                            <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($s['faculty_name']); ?></span>
                                            <div style="font-size: 10px; color: var(--text-muted);"><?php echo htmlspecialchars($roleLabel); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 11.5px; font-weight: 700;">
                                        <i class="fas fa-map-marker-alt" style="margin-right: 3px;"></i> <?php echo htmlspecialchars($s['room_number']); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="schedules.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this timetable slot?');">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="schedule_id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger); padding: 4px 8px; font-size: 11px;" title="Delete slot">
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
        <div class="modal-card" style="max-width: 600px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus" style="color: var(--primary);"></i> Allocate Timetable Slot</h3>
                <button type="button" class="modal-close" onclick="closeModal('addSlotModal')">&times;</button>
            </div>
            <form method="POST" action="schedules.php">
                <input type="hidden" name="action" value="add_schedule">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="slSubj">Subject <span style="color: var(--danger);">*</span></label>
                        <select name="subject_id" id="slSubj" class="form-control" required onchange="onSubjectSelect(this)">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $sb): ?>
                                <option value="<?php echo (int)$sb['id']; ?>" data-sem="<?php echo htmlspecialchars($sb['semester'] ?? '1'); ?>" data-fac="<?php echo (int)($sb['faculty_id'] ?? 0); ?>">
                                    <?php echo htmlspecialchars($sb['code']); ?> - <?php echo htmlspecialchars($sb['name']); ?> (<?php echo htmlspecialchars($sb['dept_name'] ?? 'General'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slDay">Day of Week <span style="color: var(--danger);">*</span></label>
                            <select name="day_of_week" id="slDay" class="form-control" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="slFac">Assigned Instructor</label>
                            <select name="faculty_id" id="slFac" class="form-control">
                                <option value="">Auto (Subject Instructor)</option>
                                <?php foreach ($instructors as $inst): 
                                    $rName = $roleMap[(int)$inst['role_id']] ?? 'Instructor';
                                ?>
                                    <option value="<?php echo (int)$inst['id']; ?>">
                                        <?php echo htmlspecialchars($inst['name'] . ' (' . $rName . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slStart">Start Time <span style="color: var(--danger);">*</span></label>
                            <input type="time" name="start_time" id="slStart" class="form-control" value="09:00" required>
                        </div>
                        <div class="form-group">
                            <label for="slEnd">End Time <span style="color: var(--danger);">*</span></label>
                            <input type="time" name="end_time" id="slEnd" class="form-control" value="10:00" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="slRoom">Room / Hall <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="room_number" id="slRoom" class="form-control" placeholder="e.g. LH-201" required>
                        </div>
                        <div class="form-group">
                            <label for="slSem">Semester</label>
                            <input type="number" name="semester" id="slSem" class="form-control" value="1" min="1" max="12">
                        </div>
                        <div class="form-group">
                            <label for="slSec">Section</label>
                            <input type="text" name="section" id="slSec" class="form-control" value="A" placeholder="A">
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
<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.style.opacity = '';
            modal.style.pointerEvents = '';
            document.body.style.overflow = '';
        }
    }

    function onSubjectSelect(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        if (opt) {
            const sem = opt.getAttribute('data-sem');
            const fac = opt.getAttribute('data-fac');
            if (sem) {
                const cleanSem = sem.replace(/[^0-9]/g, '');
                if (cleanSem) document.getElementById('slSem').value = cleanSem;
            }
            if (fac && fac > 0) {
                document.getElementById('slFac').value = fac;
            }
        }
    }

    window.addEventListener('click', function(e) {
        const modal = document.getElementById('addSlotModal');
        if (modal && e.target === modal) {
            closeModal('addSlotModal');
        }
    });

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('addSlotModal');
        }
    });
</script>
</body>
</html>
