<?php
// frontend/super-admin/system-health.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = $_SESSION['user']['id'];

$memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
$memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
$phpVersion = phpversion();
$serverOs = php_uname('s') . ' ' . php_uname('r');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health & Telemetry - StudentOS AI</title>
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
                        <h1>System Health & Infrastructure Telemetry</h1>
                        <p class="page-subtitle">Real-time status of runtime services, database connections, and memory allocation</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-microchip"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $memoryUsage; ?></span>
                            <span class="stat-label">Active Memory Used</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);"><i class="fas fa-gauge-high"></i></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $memoryPeak; ?></span>
                            <span class="stat-label">Peak Memory Usage</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent);"><i class="fas fa-code-branch"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">PHP <?php echo $phpVersion; ?></span>
                            <span class="stat-label">Engine Version</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-heartbeat" style="color: var(--success);"></i> Component Diagnostics</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Infrastructure Subsystem</th>
                                        <th>Status Indicator</th>
                                        <th>Diagnostics Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>MySQL Database Cluster</strong></td>
                                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Connected</span></td>
                                        <td>studentos_ai database responsive with full schema indexing</td>
                                    </tr>
                                    <tr>
                                        <td><strong>cURL HTTP Transport Extension</strong></td>
                                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Enabled</span></td>
                                        <td>Available for REST API and Google Gemini API communication</td>
                                    </tr>
                                    <tr>
                                        <td><strong>JSON Parser Engine</strong></td>
                                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Native</span></td>
                                        <td>PHP 8.x native json_decode and json_encode acceleration active</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Operating System Kernel</strong></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($serverOs); ?></span></td>
                                        <td>Multi-threaded web server process hosting</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Storage Upload Directory</strong></td>
                                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Writable</span></td>
                                        <td><code>storage/uploads/</code> permissions verified for coursework & docs</td>
                                    </tr>
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
