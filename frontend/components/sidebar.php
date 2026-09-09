<?php
// frontend/components/sidebar.php

$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user']['role_id'] ?? 4;

$menuItems = [];

// Student menu
if ($userRole == 4) {
    $menuItems = [
        ['label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/student/dashboard.php'],
        ['label' => 'Profile', 'icon' => 'fa-user', 'url' => '/student/profile.php'],
        ['label' => 'Subjects', 'icon' => 'fa-book', 'url' => '/student/subjects.php'],
        ['label' => 'Schedule', 'icon' => 'fa-calendar', 'url' => '/student/schedule.php'],
        ['label' => 'Assignments', 'icon' => 'fa-file-alt', 'url' => '/student/assignments.php'],
        ['label' => 'Attendance', 'icon' => 'fa-user-check', 'url' => '/student/attendance.php'],
        ['label' => 'Exams', 'icon' => 'fa-pencil-alt', 'url' => '/student/exams.php'],
        ['label' => 'Results', 'icon' => 'fa-chart-bar', 'url' => '/student/results.php'],
        ['label' => 'Tasks', 'icon' => 'fa-tasks', 'url' => '/student/tasks.php'],
        ['label' => 'Notes', 'icon' => 'fa-sticky-note', 'url' => '/student/notes.php'],
        ['label' => 'AI Assistant', 'icon' => 'fa-robot', 'url' => '/student/ai-assistant.php'],
        ['label' => 'PDF Q&A', 'icon' => 'fa-file-pdf', 'url' => '/student/pdf-qa.php'],
        ['label' => 'Help & Support', 'icon' => 'fa-life-ring', 'url' => '/student/support.php']
    ];
}
// Faculty menu
elseif ($userRole == 3) {
    $menuItems = [
        ['label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/faculty/dashboard.php'],
        ['label' => 'Profile', 'icon' => 'fa-user', 'url' => '/faculty/profile.php'],
        ['label' => 'Subjects', 'icon' => 'fa-book', 'url' => '/faculty/subjects.php'],
        ['label' => 'Schedule', 'icon' => 'fa-calendar', 'url' => '/faculty/schedule.php'],
        ['label' => 'Students', 'icon' => 'fa-users', 'url' => '/faculty/students.php'],
        ['label' => 'Attendance', 'icon' => 'fa-user-check', 'url' => '/faculty/attendance.php'],
        ['label' => 'Assignments', 'icon' => 'fa-file-alt', 'url' => '/faculty/assignments.php'],
        ['label' => 'Submissions', 'icon' => 'fa-upload', 'url' => '/faculty/submissions.php'],
        ['label' => 'Exams', 'icon' => 'fa-pencil-alt', 'url' => '/faculty/exams.php'],
        ['label' => 'Tasks', 'icon' => 'fa-tasks', 'url' => '/faculty/tasks.php'],
        ['label' => 'Help & Support', 'icon' => 'fa-life-ring', 'url' => '/faculty/support.php']
    ];
}
// Admin menu
elseif ($userRole == 2) {
    $menuItems = [
        ['label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/admin/dashboard.php'],
        ['label' => 'Profile', 'icon' => 'fa-user', 'url' => '/admin/profile.php'],
        ['label' => 'Students', 'icon' => 'fa-user-graduate', 'url' => '/admin/students.php'],
        ['label' => 'Faculty', 'icon' => 'fa-chalkboard-teacher', 'url' => '/admin/faculty.php'],
        ['label' => 'Departments', 'icon' => 'fa-building', 'url' => '/admin/departments.php'],
        ['label' => 'Courses', 'icon' => 'fa-layer-group', 'url' => '/admin/courses.php'],
        ['label' => 'Semesters', 'icon' => 'fa-calendar-alt', 'url' => '/admin/semesters.php'],
        ['label' => 'Subjects', 'icon' => 'fa-book', 'url' => '/admin/subjects.php'],
        ['label' => 'Schedule', 'icon' => 'fa-calendar', 'url' => '/admin/schedules.php'],
        ['label' => 'Tasks', 'icon' => 'fa-tasks', 'url' => '/admin/tasks.php'],
        ['label' => 'Reports', 'icon' => 'fa-chart-line', 'url' => '/admin/reports.php'],
        ['label' => 'Help & Support', 'icon' => 'fa-life-ring', 'url' => '/admin/support.php']
    ];
}
// Super Admin menu
elseif ($userRole == 1) {
    $menuItems = [
        ['label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/super-admin/dashboard.php'],
        ['label' => 'Profile', 'icon' => 'fa-user', 'url' => '/super-admin/profile.php'],
        ['label' => 'Admins', 'icon' => 'fa-user-shield', 'url' => '/super-admin/admins.php'],
        ['label' => 'Users', 'icon' => 'fa-users', 'url' => '/super-admin/users.php'],
        ['label' => 'Semesters', 'icon' => 'fa-calendar-alt', 'url' => '/super-admin/semesters.php'],
        ['label' => 'Subjects', 'icon' => 'fa-book', 'url' => '/super-admin/subjects.php'],
        ['label' => 'Tasks & Operations', 'icon' => 'fa-tasks', 'url' => '/super-admin/tasks.php'],
        ['label' => 'Roles', 'icon' => 'fa-user-tag', 'url' => '/super-admin/roles.php'],
        ['label' => 'Permissions', 'icon' => 'fa-key', 'url' => '/super-admin/permissions.php'],
        ['label' => 'Security', 'icon' => 'fa-shield-alt', 'url' => '/super-admin/security.php'],
        ['label' => 'Audit Logs', 'icon' => 'fa-history', 'url' => '/super-admin/audit-logs.php'],
        ['label' => 'System Settings', 'icon' => 'fa-cogs', 'url' => '/super-admin/system-settings.php'],
        ['label' => 'System Health', 'icon' => 'fa-heartbeat', 'url' => '/super-admin/system-health.php'],
        ['label' => 'Help & Support', 'icon' => 'fa-life-ring', 'url' => '/super-admin/support.php']
    ];
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo htmlspecialchars(url(getDashboardUrl())); ?>" class="sidebar-brand" title="BSTUDENTOS Dashboard">
            <i class="fas fa-graduation-cap"></i>
            <span>BSTUDENTOS</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($menuItems as $item): ?>
                <?php 
                $isExternal = !empty($item['external']);
                $itemPath = parse_url($item['url'], PHP_URL_PATH);
                $isActive = !$isExternal && (basename($itemPath) === $currentPage); 
                ?>
                <li class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
                    <a href="<?php echo htmlspecialchars(url($item['url'])); ?>" 
                       class="nav-link <?php echo $isExternal ? 'sidebar-ext-link' : ''; ?>"
                       <?php if ($isExternal): ?>target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($item['label']); ?> (Opens in separate page)"<?php endif; ?>>
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['label']; ?></span>
                        <?php if ($isExternal): ?>
                            <i class="fas fa-external-link-alt sidebar-ext-icon" style="font-size: 10px; opacity: 0.55; margin-left: auto;"></i>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    
    if (window.innerWidth <= 992) {
        sidebar.classList.toggle('open');
        if (overlay) {
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        }
    } else {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        try {
            localStorage.setItem('studentos_sidebar_collapsed', isCollapsed ? '1' : '0');
        } catch (e) {}
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
}

// Restore sidebar preference on desktop
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth > 992) {
        try {
            if (localStorage.getItem('studentos_sidebar_collapsed') === '1') {
                sidebar.classList.add('collapsed');
            }
        } catch (e) {}
    }
});
</script>