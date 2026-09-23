<?php
// frontend/super-admin/assignments.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_assignment') {
        $title = sanitize($_POST['title'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $semester = sanitize($_POST['semester'] ?? '');
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $deadline = sanitize($_POST['deadline'] ?? date('Y-m-d H:i:s', strtotime('+7 days')));
        $maxMarks = (int)($_POST['max_marks'] ?? 50);

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
                        $successMsg = 'Assignment created and published institution-wide with problem statement PDF!';
                    } else {
                        $errorMsg = 'Failed to save assignment: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $errorMsg = 'Please complete all required fields (Department, Semester, Subject, Title, Deadline).';
            }
        }
    } elseif ($action === 'delete_assignment') {
        $deleteId = (int)($_POST['assignment_id'] ?? 0);
        if ($deleteId > 0 && $db) {
            $stmt = $db->prepare("UPDATE assignments SET deleted_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $deleteId);
                if ($stmt->execute()) {
                    $successMsg = 'Assignment removed successfully.';
                } else {
                    $errorMsg = 'Failed to remove assignment: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

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
    $subRes = $db->query("SELECT id, name, code, department_id, semester FROM subjects WHERE status = 'active' ORDER BY name ASC");
    if ($subRes) {
        $allSubjects = $subRes->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch all assignments across all departments
$assignments = [];
$totalSubmissionsCount = 0;
$uniqueDepts = [];

if ($db) {
    $res = $db->query(
        "SELECT a.id, a.title, a.deadline, a.max_marks, a.status, a.attachment_path,
                s.name AS subject_name, s.code AS subject_code,
                COALESCE(d.name, dept_s.name, 'Academics') AS department_name,
                COALESCE(d.code, dept_s.code, 'ACAD') AS department_code,
                COALESCE(a.semester, s.semester) AS assignment_semester,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System Administrator') AS creator_name,
                r.name AS creator_role,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'graded') AS graded_count
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         LEFT JOIN departments d ON a.department_id = d.id
         LEFT JOIN departments dept_s ON s.department_id = dept_s.id
         LEFT JOIN users u ON a.faculty_id = u.id
         LEFT JOIN roles r ON u.role_id = r.id
         WHERE a.deleted_at IS NULL
         ORDER BY a.deadline DESC"
    );
    if ($res) {
        $assignments = $res->fetch_all(MYSQLI_ASSOC);
        foreach ($assignments as $a) {
            $totalSubmissionsCount += (int)$a['submission_count'];
            $uniqueDepts[$a['department_code']] = true;
        }
    }
}
?>
<?php
$pageTitle = 'Master Coursework & Assignments - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h1><i class="fas fa-file-signature" style="color: var(--primary); margin-right: 8px;"></i> Master Coursework &amp; Assignments</h1>
                        <p class="page-subtitle">Universal assignment authority: Assign tasks with instructions &amp; problem statement PDFs for specific semesters and departments</p>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
                        <a href="submissions.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-inbox"></i> View Submissions
                        </a>
                        <button class="btn btn-primary" onclick="openModal('createAsgModal')" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-plus-circle"></i> Create Assignment
                        </button>
                    </div>
                </div>

                <!-- Operational Metrics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Coursework</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo count($assignments); ?></div>
                        </div>
                    </div>

                    <a href="submissions.php" class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-upload"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Submissions</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo $totalSubmissionsCount; ?></div>
                        </div>
                    </a>

                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6, 182, 212, 0.12); color: #06B6D4; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Departments Covered</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?php echo count($uniqueDepts); ?></div>
                        </div>
                    </div>

                    <div class="card" style="padding: 18px; display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">PDF Problem Sheets</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2;">
                                <?php 
                                $pdfCount = 0;
                                foreach ($assignments as $a) {
                                    if (!empty($a['attachment_path'])) $pdfCount++;
                                }
                                echo $pdfCount;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-list-check"></i> Institution-Wide Coursework (<?php echo count($assignments); ?>)</h3>
                        <span style="font-size: 12px; color: var(--text-muted);">Super Administrator View</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Assignment Title</th>
                                        <th>Subject</th>
                                        <th>Target Scope</th>
                                        <th>Assigned By</th>
                                        <th>Problem PDF</th>
                                        <th>Submission Due</th>
                                        <th>Submissions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assignments)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                                <i class="fas fa-file-alt" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                                No active coursework assignments recorded. Click "Create Assignment" to assign tasks.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assignments as $asg): 
                                            $subCount = (int)$asg['submission_count'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($asg['title']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Max Marks: <?php echo (int)$asg['max_marks']; ?> pts</div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($asg['subject_code'] ?? $asg['subject_name']); ?></span>
                                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($asg['subject_name']); ?></div>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                        <span class="badge badge-primary" style="font-size: 11px;">Sem <?php echo htmlspecialchars($asg['assignment_semester']); ?></span>
                                                        <span class="badge badge-info" style="font-size: 11px;"><?php echo htmlspecialchars($asg['department_code']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($asg['creator_name']); ?></span>
                                                    <?php if (!empty($asg['creator_role'])): ?>
                                                        <div style="margin-top: 2px;">
                                                            <span class="badge badge-dark" style="font-size: 10px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $asg['creator_role']))); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($asg['attachment_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars(storageUrl($asg['attachment_path'])); ?>" target="_blank" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px; color: #EF4444; border-color: #EF4444; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px;">
                                                            <i class="fas fa-file-pdf"></i> View PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="font-size: 12px; color: var(--text-muted);">No PDF</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span style="font-size: 12.5px;"><?php echo date('M d, Y h:i A', strtotime($asg['deadline'])); ?></span>
                                                </td>
                                                <td>
                                                    <a href="submissions.php?assignment_id=<?php echo (int)$asg['id']; ?>" class="badge badge-info" style="text-decoration: none; padding: 5px 9px; display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px;" title="View Submissions">
                                                        <i class="fas fa-inbox"></i> <?php echo $subCount; ?> submitted
                                                    </a>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                    <a href="submissions.php?assignment_id=<?php echo (int)$asg['id']; ?>" class="btn btn-sm btn-outline" style="color: var(--primary); border-color: var(--primary); padding: 4px 8px; font-size: 11px; margin-right: 4px;" title="View Submissions">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <form method="POST" action="assignments.php" onsubmit="return confirm('Are you sure you want to remove this assignment?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?php echo (int)$asg['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger); padding: 4px 8px; font-size: 11px;" title="Delete Assignment">
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
                        <input type="text" name="title" id="asgTitle" class="form-control" placeholder="e.g. Midterm Problem Set: Database Architecture &amp; Indexing" required>
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
                            Upload the task document (.pdf, max 25MB) containing instructions and problem statements.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="asgDesc">Instructions / Problem Summary (Optional)</label>
                        <textarea name="description" id="asgDesc" class="form-control" rows="3" placeholder="Additional notes, submission guidelines, or rubrics..."></textarea>
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
            const fallback = allSubjects.filter(s => String(s.department_id) === String(deptId));
            if (fallback.length > 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- No exact sem ' + sem + ' match; select department subject: --';
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
