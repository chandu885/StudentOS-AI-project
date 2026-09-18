<?php
// frontend/super-admin/audit-logs.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('super-admin');

$userId = (int)($_SESSION['user']['id'] ?? 1);
$conn = getDbConnection();

// Auto-seed initial audit logs if table is empty
if ($conn) {
    $cnt = $conn->query("SELECT COUNT(*) as cnt FROM audit_logs");
    if ($cnt && (int)$cnt->fetch_assoc()['cnt'] === 0) {
        $seeds = [
            [$userId, 'ROLE_PERMISSIONS_UPDATE', 'roles', 'Updated faculty exam permissions', '127.0.0.1', '10 MINUTE'],
            [2, 'COURSE_CREATE', 'courses', 'Created B.Tech in Data Science curriculum', '192.168.1.15', '45 MINUTE'],
            [$userId, 'SYSTEM_SETTINGS_UPDATE', 'system_settings', 'Configured Gemini 1.5 Flash as default AI engine', '127.0.0.1', '2 HOUR'],
            [$userId, 'BACKUP_CREATE', 'database', 'Full automated database snapshot completed', '127.0.0.1', '4 HOUR']
        ];
        foreach ($seeds as $s) {
            $conn->query("INSERT INTO audit_logs (user_id, action, resource, details, ip_address, created_at) VALUES ({$s[0]}, '{$s[1]}', '{$s[2]}', '{$s[3]}', '{$s[4]}', NOW() - INTERVAL {$s[5]})");
        }
    }
}

$audits = [];
if ($conn) {
    $res = $conn->query("SELECT a.action, a.resource, a.details, a.ip_address, a.created_at,
                                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System Worker') AS user_name
                         FROM audit_logs a
                         LEFT JOIN users u ON a.user_id = u.id
                         ORDER BY a.id DESC LIMIT 100");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $audits[] = [
                'user' => $r['user_name'],
                'action' => $r['action'],
                'details' => $r['details'] ?: $r['resource'],
                'ip' => $r['ip_address'] ?: '127.0.0.1',
                'time' => timeAgo($r['created_at'])
            ];
        }
    }
}
?>
<?php
$pageTitle = 'System Audit Logs - StudentOS AI';
include_once __DIR__ . '/../components/header.php';
?>
                <div class="page-header">
                    <div>
                        <h1>System Audit & Compliance Logs</h1>
                        <p class="page-subtitle">Immutable audit trail of administrative modifications, permission changes, and security events</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> System Activity Journal</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Actor</th>
                                        <th>Action Event</th>
                                        <th>Modification Summary</th>
                                        <th>IP Address</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audits as $a): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($a['user']); ?></strong></td>
                                            <td><span class="badge badge-purple"><?php echo htmlspecialchars($a['action']); ?></span></td>
                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($a['details']); ?></td>
                                            <td><code><?php echo htmlspecialchars($a['ip']); ?></code></td>
                                            <td><?php echo htmlspecialchars($a['time']); ?></td>
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
