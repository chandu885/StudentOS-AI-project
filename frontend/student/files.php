<?php
// frontend/student/files.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$files = [
    ['name' => 'Syllabus_2026_Semester_6.pdf', 'size' => 1250000, 'type' => 'PDF', 'date' => '2026-01-10'],
    ['name' => 'Lab_Manual_Operating_Systems.pdf', 'size' => 3800000, 'type' => 'PDF', 'date' => '2026-01-18'],
    ['name' => 'Sample_Exam_Questions_DBMS.pdf', 'size' => 950000, 'type' => 'PDF', 'date' => '2026-02-05'],
    ['name' => 'Project_Guidelines_Template.docx', 'size' => 450000, 'type' => 'DOCX', 'date' => '2026-02-12']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Repository - StudentOS AI</title>
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
                        <h1>Academic Files & Resources</h1>
                        <p class="page-subtitle">Central archive of handouts, templates, and reference materials</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-archive"></i> Repository Files</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Added</th>
                                        <th>Download</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $f): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <i class="fas fa-file-alt" style="color: var(--primary);"></i>
                                                    <strong style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($f['name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($f['type']); ?></span></td>
                                            <td><?php echo formatFileSize($f['size']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($f['date'])); ?></td>
                                            <td>
                                                <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="showToast('File download started', 'info')">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
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

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
