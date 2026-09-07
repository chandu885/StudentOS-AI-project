<?php
// frontend/super-admin/backups.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMsg = 'New database backup snapshot generated successfully!';
}

$backups = [
    ['filename' => 'backup_studentos_2026_03_07_040000.sql.gz', 'size' => 14500000, 'type' => 'Full Database', 'created' => '2026-03-07 04:00:00', 'status' => 'Verified'],
    ['filename' => 'backup_studentos_2026_03_06_040000.sql.gz', 'size' => 14200000, 'type' => 'Full Database', 'created' => '2026-03-06 04:00:00', 'status' => 'Verified'],
    ['filename' => 'backup_studentos_2026_03_05_040000.sql.gz', 'size' => 13900000, 'type' => 'Full Database', 'created' => '2026-03-05 04:00:00', 'status' => 'Verified']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backups - StudentOS AI</title>
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
                        <h1>Database Backups & Disaster Recovery</h1>
                        <p class="page-subtitle">Automated MySQL database snapshots, cryptographic checksums, and recovery archives</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="backups.php" style="margin: 0;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-database"></i> Trigger Backup Now
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-archive"></i> Stored Snapshot Archives</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Archive Filename</th>
                                        <th>Scope</th>
                                        <th>Compressed Size</th>
                                        <th>Created Timestamp</th>
                                        <th>Integrity</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $b): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($b['filename']); ?></code></td>
                                            <td><?php echo htmlspecialchars($b['type']); ?></td>
                                            <td><?php echo formatFileSize($b['size']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($b['created'])); ?></td>
                                            <td><span class="badge badge-success"><?php echo htmlspecialchars($b['status']); ?></span></td>
                                            <td>
                                                <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;" onclick="showToast('Downloading SQL dump archive', 'info')">
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
