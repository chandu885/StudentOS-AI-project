<?php
// frontend/student/files.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$db = getDbConnection();
$files = [];
if ($db) {
    $res = $db->query("SELECT * FROM `files` ORDER BY `created_at` DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $files[] = [
                'name' => $row['original_name'],
                'size' => $row['file_size'],
                'type' => strtoupper(pathinfo($row['original_name'], PATHINFO_EXTENSION) ?: 'FILE'),
                'date' => $row['created_at']
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
    <title>File Repository - StudentOS AI</title>
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
                        <h1>Academic Files & Resources</h1>
                        <p class="page-subtitle">Central archive of handouts, templates, and reference materials</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-archive"></i> Repository Files (<?php echo count($files); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($files)): ?>
                            <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                                <i class="fas fa-folder-open" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                                <strong style="color: var(--text-primary);">No Repository Files Available</strong>
                                <p style="font-size: 13px; margin-top: 4px;">Shared templates and guidelines will appear here when posted by faculty or administration.</p>
                            </div>
                        <?php else: ?>
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
                        <?php endif; ?>
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
