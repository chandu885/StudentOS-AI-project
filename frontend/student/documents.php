<?php
// frontend/student/documents.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['doc_file']['name'])) {
    $uploadDir = BASE_PATH . '/storage/documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
    if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir . $fileName)) {
        $successMsg = 'Document uploaded successfully!';
    }
}

$documents = [
    ['id' => 1, 'name' => 'DBMS_Unit_3_Normalization_Complete.pdf', 'size' => 4500000, 'type' => 'PDF', 'subject' => 'Database Management Systems', 'uploaded_at' => '2026-02-15'],
    ['id' => 2, 'name' => 'Data_Structures_Algorithms_Lectures_1_to_10.pdf', 'size' => 8200000, 'type' => 'PDF', 'subject' => 'Data Structures & Algorithms', 'uploaded_at' => '2026-02-20'],
    ['id' => 3, 'name' => 'OS_Concurrency_Deadlocks_Slides.pdf', 'size' => 3100000, 'type' => 'PDF', 'subject' => 'Operating Systems', 'uploaded_at' => '2026-03-01']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Documents - StudentOS AI</title>
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
                        <h3><i class="fas fa-folder-open"></i> Uploaded Course Documents</h3>
                    </div>
                    <div class="card-body">
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
