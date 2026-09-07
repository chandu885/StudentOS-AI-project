<?php
// frontend/student/ai-recommendations.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$recRes = apiCall('/ai.php?path=recommendations', 'GET');
$recommendations = $recRes['recommendations'] ?? [
    [
        'title' => 'Critical: Operating Systems Attendance Below 75%',
        'description' => 'Your current attendance in Operating Systems is 73.1%. You need to attend the next 3 consecutive classes to restore exam eligibility.',
        'category' => 'Attendance Alert',
        'icon' => 'exclamation-triangle',
        'priority' => 'high'
    ],
    [
        'title' => 'Upcoming Deadline: DBMS ER Diagram',
        'description' => 'The assignment is due in 3 days. Based on your study patterns, dedicating 45 minutes tonight will ensure on-time completion.',
        'category' => 'Coursework',
        'icon' => 'clock',
        'priority' => 'high'
    ],
    [
        'title' => 'Mastery Opportunity: Red-Black Trees',
        'description' => 'Based on your recent practice quiz performance, reviewing tree rotation cases 1 through 3 will boost your DSA exam score.',
        'category' => 'Study Focus',
        'icon' => 'brain',
        'priority' => 'medium'
    ],
    [
        'title' => 'Recommended Peer Group: InnoVision 2026 Hackathon',
        'description' => 'You have strong fundamentals in database design and web systems. Participating in the campus hackathon would enrich your portfolio.',
        'category' => 'Career',
        'icon' => 'trophy',
        'priority' => 'low'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Recommendations - StudentOS AI</title>
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
                        <h1><i class="fas fa-lightbulb" style="color: var(--warning);"></i> AI Personalized Recommendations</h1>
                        <p class="page-subtitle">Proactive academic insights derived from your grades, attendance, and study habits</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($recommendations as $rec): 
                        $prio = $rec['priority'] ?? 'medium';
                        $prioClass = $prio === 'high' ? 'danger' : ($prio === 'medium' ? 'warning' : 'info');
                    ?>
                        <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--<?php echo $prioClass; ?>);">
                            <div class="card-body" style="display: flex; gap: 18px; align-items: flex-start;">
                                <div class="rec-icon" style="background: rgba(var(--<?php echo $prioClass; ?>), 0.15); color: var(--<?php echo $prioClass; ?>);">
                                    <i class="fas fa-<?php echo htmlspecialchars($rec['icon'] ?? 'star'); ?>"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                        <h3 style="font-size: 15px; color: var(--text-primary); margin: 0;"><?php echo htmlspecialchars($rec['title']); ?></h3>
                                        <div style="display: flex; gap: 8px;">
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars($rec['category']); ?></span>
                                            <span class="badge badge-<?php echo $prioClass; ?>"><?php echo ucfirst($prio); ?> Priority</span>
                                        </div>
                                    </div>
                                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($rec['description']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
