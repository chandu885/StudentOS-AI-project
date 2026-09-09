<?php
// frontend/super-admin/reports.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);

// Handle Security Audit CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'security_audit') {
    $conn = getDbConnection();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=security_audit_logs_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'User ID', 'Email', 'Status', 'IP Address', 'User Agent', 'Failure Reason', 'Session ID', 'Timestamp']);
    if ($conn) {
        $sql = "SELECT l.id, l.user_id, COALESCE(u.email, 'unknown') as email, l.success, l.ip_address, l.user_agent, l.failure_reason, l.session_id, l.created_at
                FROM login_logs l
                LEFT JOIN users u ON l.user_id = u.id
                ORDER BY l.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($output, [
                    $r['id'],
                    $r['user_id'] ?: 'N/A',
                    $r['email'],
                    ((int)$r['success'] === 1) ? 'SUCCESS' : 'FAILED',
                    $r['ip_address'],
                    $r['user_agent'],
                    $r['failure_reason'] ?: 'None',
                    $r['session_id'],
                    $r['created_at']
                ]);
            }
        }
    }
    fclose($output);
    exit;
}

// Handle AI Usage CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'ai_usage') {
    $conn = getDbConnection();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ai_token_consumption_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'User ID', 'User Name', 'Email', 'Feature', 'Prompt Tokens', 'Response Tokens', 'Total Tokens', 'Model', 'Cost (USD)', 'Timestamp']);
    if ($conn) {
        $sql = "SELECT a.id, a.user_id, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                       COALESCE(u.email, 'N/A') as email, a.feature, a.prompt_tokens, a.response_tokens,
                       (a.prompt_tokens + a.response_tokens) as total_tokens, a.model, a.cost, a.created_at
                FROM ai_usage_logs a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($output, [
                    $r['id'],
                    $r['user_id'],
                    trim($r['user_name']) ?: 'Unknown',
                    $r['email'],
                    $r['feature'],
                    $r['prompt_tokens'],
                    $r['response_tokens'],
                    $r['total_tokens'],
                    $r['model'],
                    '$' . number_format((float)$r['cost'], 6),
                    $r['created_at']
                ]);
            }
        }
    }
    fclose($output);
    exit;
}

// Handle Admin Compliance Audit CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'admin_compliance') {
    $conn = getDbConnection();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admin_compliance_audit_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Audit ID', 'Actor ID', 'Actor Name', 'Email', 'Action Event', 'Resource', 'Resource ID', 'Details', 'IP Address', 'Timestamp']);
    if ($conn) {
        $sql = "SELECT a.id, a.user_id, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                       COALESCE(u.email, 'System') as email, a.action, a.resource, a.resource_id, a.details, a.ip_address, a.created_at
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($output, [
                    $r['id'],
                    $r['user_id'] ?: 'System',
                    trim($r['user_name']) ?: 'System Worker',
                    $r['email'],
                    $r['action'],
                    $r['resource'],
                    $r['resource_id'] ?: 'N/A',
                    $r['details'],
                    $r['ip_address'],
                    $r['created_at']
                ]);
            }
        }
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System & Security Reports - StudentOS AI</title>
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
                        <h1>Executive & Security Reports</h1>
                        <p class="page-subtitle">Export compliance ledgers, user audit records, and AI usage statements</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--primary); margin-bottom: 16px;"><i class="fas fa-shield-alt"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Security & Authentication Audit</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Complete history of IP logins, failed brute-force attempts, and revoked tokens.</p>
                            <a href="reports.php?export=security_audit" class="btn btn-secondary" style="width: 100%; justify-content: center; text-decoration: none;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1); margin-bottom: 16px;"><i class="fas fa-brain"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">AI Token Consumption Ledger</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Detailed usage log across Assistant, RAG, Summarizer, and Quiz generation.</p>
                            <a href="reports.php?export=ai_usage" class="btn btn-secondary" style="width: 100%; justify-content: center; text-decoration: none;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body">
                            <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1); margin-bottom: 16px;"><i class="fas fa-file-shield"></i></div>
                            <h3 style="font-size: 16px; margin-bottom: 6px; color: var(--text-primary);">Administrative Compliance Report</h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Audit journal of privilege grants, role modifications, and system configuration updates.</p>
                            <a href="reports.php?export=admin_compliance" class="btn btn-secondary" style="width: 100%; justify-content: center; text-decoration: none;">
                                <i class="fas fa-file-csv"></i> Download CSV
                            </a>
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
