<?php
// frontend/admin/support.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$userId = $_SESSION['user']['id'];
$successMsg = '';
$errorMsg = '';
$db = getDbConnection();

// Update ticket status & resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $tId = (int)$_POST['ticket_id'];
    $newStatus = sanitize($_POST['new_status'] ?? '');
    $resNote = sanitize($_POST['response_note'] ?? '');

    if ($tId > 0 && $db) {
        // If no explicit new status passed, toggle between resolved and in_progress
        if (empty($newStatus)) {
            $cur = $db->query("SELECT status, user_id FROM support_tickets WHERE id = $tId")->fetch_assoc();
            $newStatus = ($cur['status'] === 'resolved') ? 'in_progress' : 'resolved';
            $requesterId = (int)$cur['user_id'];
        } else {
            $cur = $db->query("SELECT user_id FROM support_tickets WHERE id = $tId")->fetch_assoc();
            $requesterId = (int)($cur['user_id'] ?? 0);
        }

        if (!empty($resNote)) {
            $stmt = $db->prepare("UPDATE support_tickets SET status = ?, response = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssii", $newStatus, $resNote, $userId, $tId);
        } else {
            $stmt = $db->prepare("UPDATE support_tickets SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $newStatus, $userId, $tId);
        }

        if ($stmt && $stmt->execute()) {
            $stmt->close();
            // Dispatch notification to requester
            if ($requesterId > 0) {
                $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Support Ticket Update', CONCAT('Your support ticket #TKT-', ?, ' status has been changed to: ', ?), 'info', 0, NOW())");
                if ($notifStmt) {
                    $notifStmt->bind_param("iis", $requesterId, $tId, $statusLabel);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
            }
            $successMsg = 'Support ticket #TKT-' . str_pad($tId, 4, '0', STR_PAD_LEFT) . ' marked as ' . ucfirst(str_replace('_', ' ', $newStatus)) . '!';
        } else {
            $errorMsg = 'Failed to update ticket: ' . $db->error;
        }
    }
}

// Filter
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Fetch support tickets
$tickets = [];
if ($db) {
    $where = "";
    if (!empty($filterStatus) && in_array($filterStatus, ['open','in_progress','resolved','closed'])) {
        $where = "WHERE st.status = '" . $db->real_escape_string($filterStatus) . "'";
    }

    $q = "SELECT st.*, 
                 CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                 u.email AS requester_email,
                 COALESCE(sp.roll_number, sp.student_id, CONCAT('ID-', u.id)) AS roll,
                 COALESCE(d.name, 'General') AS dept_name
          FROM support_tickets st
          JOIN users u ON st.user_id = u.id
          LEFT JOIN student_profiles sp ON sp.user_id = u.id
          LEFT JOIN departments d ON sp.department_id = d.id
          $where
          ORDER BY FIELD(st.status, 'open', 'in_progress', 'resolved', 'closed'), st.id DESC";
    $res = $db->query($q);
    if ($res) {
        $tickets = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Helpdesk - StudentOS AI</title>
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
                        <h1>Helpdesk Ticket Resolution</h1>
                        <p class="page-subtitle">Manage, investigate, and resolve student and faculty support queries in real time</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php endif; ?>

                <!-- Status Filter Badges -->
                <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                    <a href="support.php" class="btn <?php echo empty($filterStatus) ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;">All Tickets</a>
                    <a href="support.php?status=open" class="btn <?php echo $filterStatus === 'open' ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;">Open</a>
                    <a href="support.php?status=in_progress" class="btn <?php echo $filterStatus === 'in_progress' ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;">In Progress</a>
                    <a href="support.php?status=resolved" class="btn <?php echo $filterStatus === 'resolved' ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 12px; padding: 6px 14px;">Resolved</a>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3><i class="fas fa-life-ring"></i> Active Support Requests (<?php echo count($tickets); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Requester</th>
                                        <th>Subject & Details</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tickets)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                                No support tickets found<?php echo !empty($filterStatus) ? " matching status '$filterStatus'" : ''; ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tickets as $t): 
                                            $st = $t['status'];
                                            $isResolved = ($st === 'resolved' || $st === 'closed');
                                            $prioClass = $t['priority'] === 'high' ? 'danger' : ($t['priority'] === 'medium' ? 'warning' : 'info');
                                        ?>
                                            <tr>
                                                <td><strong style="color: var(--primary);">#TKT-<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($t['requester_name']); ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><span class="badge badge-secondary"><?php echo htmlspecialchars($t['roll']); ?></span></div>
                                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($t['dept_name']); ?></div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($t['subject']); ?></strong>
                                                    <?php if (!empty($t['description'])): ?>
                                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 320px; line-height: 1.4; margin-top: 3px;">
                                                            <?php echo htmlspecialchars($t['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($t['response'])): ?>
                                                        <div style="font-size: 11px; color: var(--success); margin-top: 4px;">
                                                            <i class="fas fa-reply"></i> Note: <?php echo htmlspecialchars($t['response']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-<?php echo $prioClass; ?>"><?php echo ucfirst(htmlspecialchars($t['priority'])); ?></span></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $isResolved ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                                    </span>
                                                </td>
                                                <td><span style="font-size: 12px; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></span></td>
                                                <td>
                                                    <form method="POST" action="support.php" style="margin: 0; display: inline;">
                                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;">
                                                            <i class="fas fa-<?php echo $isResolved ? 'undo' : 'check'; ?>"></i> <?php echo $isResolved ? 'Re-open' : 'Resolve'; ?>
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

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
