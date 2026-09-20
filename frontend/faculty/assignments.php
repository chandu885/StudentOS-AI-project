<?php
// frontend/faculty/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('faculty');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Fetch departments for dropdown
$departments = [];
if ($db) {
    $depRes = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC");
    if ($depRes) {
        $departments = $depRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch all active subjects with department and semester
$allSubjects = [];
if ($db) {
    $subRes = $db->query("SELECT id, name, code, department_id, semester, faculty_id FROM subjects WHERE status = 'active' ORDER BY name ASC");
    if ($subRes) {
        $allSubjects = $subRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_assignment') {
    $title = sanitize($_POST['title'] ?? '');
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $semester = sanitize($_POST['semester'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $deadline = sanitize($_POST['deadline'] ?? date('Y-m-d H:i:s', strtotime('+7 days')));
    $maxMarks = (int)($_POST['max_marks'] ?? 20);

    // Validate PDF file upload
    $attachmentPath = null;
    if (!isset($_FILES['problem_pdf']) || $_FILES['problem_pdf']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please upload a PDF containing the instructions and problem statement.';
    } else {
        $file = $_FILES['problem_pdf'];
        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $errorMsg = 'Invalid file format. Only PDF documents (.pdf) are allowed.';
        } elseif ($file['size'] <= 0) {
            $errorMsg = 'The uploaded PDF file is empty.';
        } elseif ($file['size'] > 25 * 1024 * 1024) {
            $errorMsg = 'Uploaded PDF exceeds maximum 25MB file size limit.';
        } else {
            $uploadDir = BASE_PATH . '/storage/assignments/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $safeName = 'asg_' . time() . '_' . substr($safeBase, 0, 20) . '_' . bin2hex(random_bytes(3)) . '.pdf';
            $dest = $uploadDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $attachmentPath = 'storage/assignments/' . $safeName;
            } else {
                $errorMsg = 'Failed to write uploaded PDF file to server storage.';
            }
        }
    }

    if (empty($errorMsg)) {
        if (!empty($title) && $subjectId > 0 && !empty($semester) && $departmentId > 0 && $db) {
            $stmt = $db->prepare(
                "INSERT INTO assignments 
                 (subject_id, department_id, semester, faculty_id, title, description, instructions, deadline, max_marks, attachment_path, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())"
            );
            if ($stmt) {
                $stmt->bind_param("iisissssis", $subjectId, $departmentId, $semester, $userId, $title, $description, $description, $deadline, $maxMarks, $attachmentPath);
                if ($stmt->execute()) {
                    $successMsg = 'Assignment created and published to students with problem statement PDF!';
                } else {
                    $errorMsg = 'Failed to save assignment in database: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $errorMsg = 'Please complete all required fields (Department, Semester, Subject, Title, Deadline).';
        }
    }
}

// Fetch published assignments for faculty
$assignments = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT a.*, s.name as subject_name, s.code as subject_code,
                COALESCE(d.name, dept_s.name, 'Academics') as department_name,
                COALESCE(d.code, dept_s.code, 'ACAD') as department_code,
                COALESCE(a.semester, s.semester) as assignment_semester,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submissions_count,
                (SELECT COUNT(DISTINCT student_id) FROM student_subjects WHERE subject_id = a.subject_id) as total_students
         FROM assignments a 
         JOIN subjects s ON a.subject_id = s.id 
         LEFT JOIN departments d ON a.department_id = d.id
         LEFT JOIN departments dept_s ON s.department_id = dept_s.id
         WHERE (a.faculty_id = ? OR s.faculty_id = ?) AND a.deleted_at IS NULL
         ORDER BY a.deadline DESC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<?php
$pageTitle = 'Manage Assignments - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header">
                    <div>
                        <h1>Assignment Management</h1>
                        <p class="page-subtitle">Publish coursework with problem statement PDFs, specify departments &amp; semesters, and monitor student submissions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('createAsgModal')">
                            <i class="fas fa-plus"></i> Create Assignment
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

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-file-alt"></i> Published Coursework (<?php echo count($assignments); ?>)</h3>
                        <span style="font-size: 12px; color: var(--text-muted);">Scoped by Department &amp; Semester</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($assignments)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title &amp; Subject</th>
                                        <th>Target Scope</th>
                                        <th>Problem PDF</th>
                                        <th>Deadline</th>
                                        <th>Submissions</th>
                                        <th>Max Marks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $asg): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($asg['subject_code']); ?></span>
                                                    <?php echo htmlspecialchars($asg['subject_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                    <span class="badge badge-primary" style="font-size: 11px;">Sem <?php echo htmlspecialchars($asg['assignment_semester']); ?></span>
                                                    <span class="badge badge-info" style="font-size: 11px;"><?php echo htmlspecialchars($asg['department_code']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($asg['attachment_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars(storageUrl($asg['attachment_path'])); ?>" target="_blank" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #EF4444; border-color: #EF4444; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="fas fa-file-pdf"></i> View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 12px; color: var(--text-muted);">No PDF</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 500; font-size: 13px;"><?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo (int)($asg['submissions_count'] ?? 0); ?> Submitted</span>
                                            </td>
                                            <td><strong><?php echo $asg['max_marks']; ?></strong> pts</td>
                                            <td>
                                                <a href="submissions.php?assignment_id=<?php echo $asg['id']; ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                                                    <i class="fas fa-eye"></i> View Submissions
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="empty-state" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-file-alt" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                <strong style="color: var(--text-primary);">No Assignments Published Yet</strong>
                                <p style="font-size: 13px; margin-top: 4px;">Click "Create Assignment" above to assign coursework with an instructions PDF to your students.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal-backdrop" id="createAsgModal">
        <div class="modal-card" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-file-upload" style="color: var(--primary);"></i> Create New Assignment</h3>
                <button class="modal-close" onclick="closeModal('createAsgModal')">&times;</button>
            </div>
            <form method="POST" action="assignments.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_assignment">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="asgDepartment">Department *</label>
                            <select name="department_id" id="asgDepartment" class="form-control" onchange="filterSubjects()" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="asgSemester">Semester *</label>
                            <select name="semester" id="asgSemester" class="form-control" onchange="filterSubjects()" required>
                                <option value="">-- Select Semester --</option>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?php echo $s; ?>">Semester <?php echo $s; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="asgSubject">Specific Subject *</label>
                        <select name="subject_id" id="asgSubject" class="form-control" required>
                            <option value="">-- Choose Department &amp; Semester first --</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 3px; display: block;">Displays only subjects belonging to the selected department &amp; semester.</small>
                    </div>

                    <div class="form-group">
                        <label for="asgTitle">Assignment Title *</label>
                        <input type="text" name="title" id="asgTitle" class="form-control" placeholder="e.g. Relational Calculus &amp; 3NF Normalization Proofs" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label for="asgDeadline">Submission Deadline *</label>
                            <input type="datetime-local" name="deadline" id="asgDeadline" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="asgMarks">Maximum Points *</label>
                            <input type="number" name="max_marks" id="asgMarks" class="form-control" value="50" min="5" max="100" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="asgPdf">
                            <i class="fas fa-file-pdf" style="color: #EF4444;"></i> Instructions &amp; Problem Statement PDF *
                        </label>
                        <input type="file" name="problem_pdf" id="asgPdf" class="form-control" accept=".pdf,application/pdf" required>
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 3px; display: block;">
                            Upload the task document (.pdf, max 25MB) containing problems and instructions for students.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="asgDesc">Instructions / Problem Summary (Optional)</label>
                        <textarea name="description" id="asgDesc" class="form-control" rows="3" placeholder="Brief overview or additional student reminders..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createAsgModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    // Master subject catalog for dynamic filtering
    const allSubjects = <?php echo json_encode($allSubjects); ?>;

    function filterSubjects() {
        const deptId = document.getElementById('asgDepartment').value;
        const sem = document.getElementById('asgSemester').value;
        const subSelect = document.getElementById('asgSubject');

        subSelect.innerHTML = '';

        if (!deptId || !sem) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- Choose Department & Semester first --';
            subSelect.appendChild(opt);
            return;
        }

        const filtered = allSubjects.filter(s => {
            const matchDept = String(s.department_id) === String(deptId);
            const matchSem = String(s.semester) === String(sem);
            return matchDept && matchSem;
        });

        if (filtered.length === 0) {
            // Check if any subject matches just department or just semester
            const fallback = allSubjects.filter(s => String(s.department_id) === String(deptId));
            if (fallback.length > 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- No exact sem ' + sem + ' match; select course subject: --';
                subSelect.appendChild(opt);
                fallback.forEach(s => {
                    const o = document.createElement('option');
                    o.value = s.id;
                    o.textContent = s.name + ' (' + s.code + ') [Sem ' + s.semester + ']';
                    subSelect.appendChild(o);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- No subjects configured for this Department & Semester --';
                subSelect.appendChild(opt);
            }
        } else {
            const defOpt = document.createElement('option');
            defOpt.value = '';
            defOpt.textContent = '-- Select Subject (' + filtered.length + ' available) --';
            subSelect.appendChild(defOpt);

            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name + ' (' + s.code + ')';
                subSelect.appendChild(opt);
            });
        }
    }
    </script>
</body>
</html>
