/**
 * frontend/assets/js/notifications.js - StudentOS AI Notification Management
 */

async function fetchNotifications() {
    try {
        const notifBadge = document.getElementById('navbar-notif-badge');
        const notifList = document.getElementById('navbar-notif-list');
        
        const headers = typeof getAuthHeaders === 'function' ? getAuthHeaders() : {};
        const res = await fetch('/StudentOS-AI-project/backend/api/notifications.php', {
            credentials: 'same-origin',
            headers: headers
        });
        if (!res.ok) return;
        const data = await res.json();
        
        if (data.success) {
            const count = data.unread_count || 0;
            if (notifBadge) {
                notifBadge.style.display = count > 0 ? 'block' : 'none';
                notifBadge.textContent = count > 9 ? '9+' : count;
            }

            if (notifList && data.notifications) {
                if (data.notifications.length === 0) {
                    notifList.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);font-size:13px;">No new notifications</div>';
                } else {
                    notifList.innerHTML = data.notifications.slice(0, 5).map(n => `
                        <div class="dropdown-item ${n.is_read ? '' : 'unread'}" style="flex-direction:column;align-items:flex-start;cursor:pointer;border-bottom:1px solid var(--border-color);" onclick="markNotificationRead(${n.id})">
                            <strong style="font-size:13px;color:var(--text-primary);">${escapeHTML(n.title)}</strong>
                            <p style="font-size:12px;color:var(--text-secondary);margin-top:2px;">${escapeHTML(n.message)}</p>
                        </div>
                    `).join('');
                }
            }
        }
    } catch (e) {
        // Silently fail if not logged in or endpoint unavailable
    }
}

async function markNotificationRead(id) {
    try {
        const headers = typeof getAuthHeaders === 'function' ? getAuthHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
        await fetch('/StudentOS-AI-project/backend/api/notifications.php?path=read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify({ id: id })
        });
        fetchNotifications();
    } catch (e) {
        console.error(e);
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown on outside click
document.addEventListener('click', (e) => {
    const notifBtn = document.getElementById('notification-btn');
    const notifDropdown = document.getElementById('notification-dropdown');
    if (notifBtn && notifDropdown && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.remove('show');
    }
});
