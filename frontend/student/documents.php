<?php
// frontend/student/documents.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';

$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['doc_file']['name'])) {
    $uploadDir = BASE_PATH . '/storage/documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $origName = basename($_FILES['doc_file']['name']);
    $fileName = time() . '_' . $origName;
    $fileSize = (int)$_FILES['doc_file']['size'];
    $fileType = $_FILES['doc_file']['type'] ?? 'application/pdf';
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetPath)) {
        if ($db) {
            $stmt = $db->prepare("INSERT INTO `documents` (user_id, subject_id, title, file_path, file_size, file_type, description, is_public, created_at, updated_at) VALUES (?, 1, ?, ?, ?, ?, 'Uploaded Course Document', 1, NOW(), NOW())");
            if ($stmt) {
                $relPath = 'storage/documents/' . $fileName;
                $stmt->bind_param("issis", $userId, $origName, $relPath, $fileSize, $fileType);
                $stmt->execute();
            }
        }
        $successMsg = 'Document uploaded successfully!';
    }
}

// Fetch documents from database
$documents = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT d.*, s.name as subject_name 
         FROM `documents` d 
         LEFT JOIN `subjects` s ON d.subject_id = s.id 
         WHERE d.user_id = ? OR d.is_public = 1 OR d.subject_id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
         ORDER BY d.created_at DESC"
    );
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = [
                'id' => $row['id'],
                'name' => $row['title'],
                'size' => $row['file_size'],
                'type' => strtoupper(pathinfo($row['title'], PATHINFO_EXTENSION) ?: 'PDF'),
                'subject' => $row['subject_name'] ?? 'General Resource',
                'uploaded_at' => $row['created_at']
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
    <title>Course Documents - StudentOS AI</title>
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
                        <h1>Documents & Textbooks</h1>
                        <p class="page-subtitle">Syllabus PDFs, lecture slides, and AI-searchable course materials</p>
                    </div>
                    <div class="header-actions">
                        <a href="pdf-qa.php" class="btn btn-outline"><i class="fas fa-file-pdf"></i> Ask Document (RAG)</a>
                        <button class="btn btn-primary" onclick="openModal('uploadDocModal')"><i class="fas fa-upload"></i> Upload PDF</button>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-folder-open"></i> Course Documents (<?php echo count($documents); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                                <i class="fas fa-file-alt" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                                <strong style="color: var(--text-primary);">No Course Documents Uploaded</strong>
                                <p style="font-size: 13px; margin-top: 4px; margin-bottom: 16px;">Upload course textbook chapters or syllabus PDFs to search with RAG.</p>
                                <button class="btn btn-primary" onclick="openModal('uploadDocModal')"><i class="fas fa-upload"></i> Upload PDF</button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Document Name</th>
                                            <th>Subject</th>
                                            <th>File Size</th>
                                            <th>Uploaded Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <i class="fas fa-file-pdf" style="color: var(--danger); font-size: 20px;"></i>
                                                        <strong style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($doc['name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($doc['subject']); ?></span></td>
                                                <td><?php echo formatFileSize($doc['size']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                                <td>
                                                    <a href="pdf-qa.php?doc_id=<?php echo $doc['id']; ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 11px;">
                                                        <i class="fas fa-robot"></i> Chat with Doc
                                                    </a>
                                                </td>
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

    <!-- Upload Modal -->
    <div class="modal-backdrop" id="uploadDocModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Upload Course Document</h3>
                <button class="modal-close" onclick="closeModal('uploadDocModal')">&times;</button>
            </div>
            <form method="POST" action="documents.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="doc_file">Select PDF File</label>
                        <input type="file" name="doc_file" id="doc_file" class="form-control" accept=".pdf,.doc,.docx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
