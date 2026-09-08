<?php
// frontend/components/navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? [
    'first_name' => 'Guest',
    'last_name' => 'User',
    'email' => 'guest@studentos.ai',
    'role_id' => 4,
    'role_name' => 'Student'
];
$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$portalPrefix = '';
$roleSlug = 'student';
if (($user['role_id'] ?? 4) == 1) {
    $portalPrefix = '/super-admin';
    $roleSlug = 'superadmin';
} elseif (($user['role_id'] ?? 4) == 2) {
    $portalPrefix = '/admin';
    $roleSlug = 'admin';
} elseif (($user['role_id'] ?? 4) == 3) {
    $portalPrefix = '/faculty';
    $roleSlug = 'faculty';
} else {
    $portalPrefix = '/student';
    $roleSlug = 'student';
}
?>
<header class="top-navbar">
    <div class="navbar-left">
        <button class="nav-action-btn sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar Menu" aria-label="Toggle Sidebar Menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search anything... (Ctrl+K)" id="globalSearchInput" onkeydown="if(event.key==='Enter') window.location.href='<?php echo htmlspecialchars(url($portalPrefix . '/search.php')); ?>?q=' + encodeURIComponent(this.value)">
            <span class="shortcut-badge">⌘K</span>
        </div>
    </div>

    <div class="navbar-right">
        <!-- Quick AI Access -->
        <?php if (($user['role_id'] ?? 4) == 4): ?>
        <a href="<?php echo htmlspecialchars(url('/student/ai-assistant.php')); ?>" class="btn btn-outline" style="padding: 6px 14px; font-size: 13px; height: 38px;">
            <i class="fas fa-robot" style="color: var(--ai-accent);"></i> AI Assistant
        </a>
        <?php endif; ?>

        <!-- Notification Bell -->
        <div style="position: relative;">
            <button class="nav-action-btn" id="notification-btn" onclick="toggleNotificationDropdown()" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="badge-dot" id="navbar-notif-badge" style="display: none;"></span>
            </button>
            <div class="dropdown-menu" id="notification-dropdown">
                <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 13px; color: var(--text-primary);">Notifications</strong>
                    <a href="<?php echo htmlspecialchars(url($portalPrefix . '/notifications.php')); ?>" style="font-size: 11px; color: var(--primary);">View All</a>
                </div>
                <div id="navbar-notif-list" style="max-height: 280px; overflow-y: auto;">
                    <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">No new notifications</div>
                </div>
            </div>
        </div>

        <?php if (!empty($_SESSION['auth_token']) && !empty($_SESSION['user'])): ?>
        <!-- Dedicated Logout Button -->
        <a href="<?php echo htmlspecialchars(url('/login.php?action=logout')); ?>" class="nav-logout-btn btn btn-danger" role="button" title="Sign Out of Account">
            <i class="fas fa-sign-out-alt"></i>
            <span class="hide-mobile">Logout</span>
        </a>
        <?php else: ?>
        <!-- Dedicated Login Button -->
        <a href="<?php echo htmlspecialchars(url('/login.php')); ?>" class="btn btn-primary nav-login-btn" role="button" title="Sign In">
            <i class="fas fa-sign-in-alt"></i>
            <span>Sign In</span>
        </a>
        <?php endif; ?>

        <!-- User Profile Dropdown -->
        <div style="position: relative;">
            <div class="nav-profile-menu" onclick="toggleUserDropdown(event)">
                <div class="nav-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div style="display: flex; flex-direction: column; text-align: left;" class="hide-mobile">
                    <span style="font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.2;">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </span>
                    <span style="font-size: 11px; color: var(--text-muted);">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role_name'] ?? 'User'))); ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--text-muted); margin-left: 4px;"></i>
            </div>
            
            <div class="dropdown-menu" id="user-dropdown">
                <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                </div>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/profile.php')); ?>" class="dropdown-item">
                    <i class="fas fa-user-circle"></i> View Profile
                </a>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/tasks.php')); ?>" class="dropdown-item">
                    <i class="fas fa-tasks"></i> My Tasks
                </a>
                <a href="<?php echo htmlspecialchars(url($portalPrefix . '/support.php')); ?>" class="dropdown-item">
                    <i class="fas fa-life-ring"></i> Get Help & Support
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo htmlspecialchars(url('/login.php?action=logout')); ?>" class="dropdown-item" style="color: var(--danger); font-weight: 600;">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</header>

<script>
function toggleUserDropdown(e) {
    if (e) e.stopPropagation();
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (notifDropdown) notifDropdown.classList.remove('show');
    if (userDropdown) userDropdown.classList.toggle('show');
}

function toggleNotificationDropdown(e) {
    if (e) e.stopPropagation();
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (userDropdown) userDropdown.classList.remove('show');
    if (notifDropdown) notifDropdown.classList.toggle('show');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    const userDropdown = document.getElementById('user-dropdown');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (userDropdown && !e.target.closest('.nav-profile-menu')) {
        userDropdown.classList.remove('show');
    }
    if (notifDropdown && !e.target.closest('#notification-btn')) {
        notifDropdown.classList.remove('show');
    }
});

// Shortcut listener for Ctrl+K global search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const search = document.getElementById('globalSearchInput');
        if (search) search.focus();
    }
});

// Synchronize session tokens to localStorage for client-side API calls
(function() {
    try {
        <?php if (!empty($_SESSION['auth_token'])): ?>
            localStorage.setItem('auth_token', <?php echo json_encode($_SESSION['auth_token']); ?>);
            <?php if (!empty($_SESSION['session_token'])): ?>
            localStorage.setItem('session_token', <?php echo json_encode($_SESSION['session_token']); ?>);
            <?php endif; ?>
        <?php else: ?>
            localStorage.removeItem('auth_token');
            localStorage.removeItem('session_token');
        <?php endif; ?>
    } catch(e) {}
})();
</script>
