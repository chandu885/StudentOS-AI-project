/**
 * frontend/assets/js/theme.js
 * Universal Theme Manager & Header Opt-In Controller
 * Supports: ☀ Day Mode | ◐ Deep Mode | ☾ Night Mode
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'studentos_theme';
    const OPTIN_STORAGE_KEY = 'header_optin_dismissed';

    // 1. Immediately resolve and apply theme to prevent Flash of Unstyled Content (FOUC)
    function getStoredTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored && ['day', 'deep', 'night', 'auto', 'light', 'dark', 'middle'].includes(stored)) {
                if (stored === 'light') return 'day';
                if (stored === 'dark') return 'night';
                if (stored === 'auto' || stored === 'middle') return 'deep';
                return stored;
            }
            return 'day'; // Default to ☀ Day Mode for clean professional new colors
        } catch (e) {
            return 'day';
        }
    }

    function applyThemeAttribute(theme) {
        const root = document.documentElement;
        const normalized = (theme === 'auto' || theme === 'middle') ? 'deep' : theme;
        root.setAttribute('data-theme', normalized);
        
        // Also update body if body is already available
        if (document.body) {
            document.body.setAttribute('data-theme', normalized);
        }
    }

    // Apply immediately during head execution
    const initialTheme = getStoredTheme();
    applyThemeAttribute(initialTheme);

    // Check opt-in dismissal state immediately
    try {
        if (localStorage.getItem(OPTIN_STORAGE_KEY) === '1') {
            document.documentElement.classList.add('optin-dismissed');
        }
    } catch (e) {}

    // 2. UI Update for segmented switcher pills
    function updateSwitcherUI(activeTheme) {
        const normalized = (activeTheme === 'auto' || activeTheme === 'middle') ? 'deep' : activeTheme;
        const switchers = document.querySelectorAll('.theme-switcher-pill');
        switchers.forEach(switcher => {
            const buttons = switcher.querySelectorAll('.theme-btn');
            buttons.forEach(btn => {
                const btnTheme = btn.getAttribute('data-theme-val');
                const isBtnDeep = (btnTheme === 'deep' || btnTheme === 'auto' || btnTheme === 'middle');
                const isCurrentDeep = (normalized === 'deep');
                const isActive = (btnTheme === normalized) || 
                                 (isBtnDeep && isCurrentDeep) ||
                                 (normalized === 'day' && btnTheme === 'light') ||
                                 (normalized === 'night' && btnTheme === 'dark');
                
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
            });
        });
    }

    // 3. Public Theme Switch Function
    window.setTheme = function (themeName) {
        if (!['day', 'deep', 'auto', 'night', 'middle', 'light', 'dark'].includes(themeName)) {
            themeName = 'day';
        }

        // Normalize naming
        let canonicalTheme = themeName;
        if (themeName === 'light') canonicalTheme = 'day';
        if (themeName === 'auto' || themeName === 'middle') canonicalTheme = 'deep';
        if (themeName === 'dark') canonicalTheme = 'night';

        try {
            localStorage.setItem(STORAGE_KEY, canonicalTheme);
        } catch (e) {}

        applyThemeAttribute(canonicalTheme);
        updateSwitcherUI(canonicalTheme);

        // Notify other components
        window.dispatchEvent(new CustomEvent('studentos:themechange', {
            detail: { theme: canonicalTheme }
        }));
    };

    window.getTheme = function () {
        return getStoredTheme();
    };

    window.cycleTheme = function () {
        const current = getStoredTheme();
        if (current === 'day') window.setTheme('deep');
        else if (current === 'deep' || current === 'auto') window.setTheme('night');
        else window.setTheme('day');
    };

    // Cross-tab synchronization
    window.addEventListener('storage', function(e) {
        if (e.key === STORAGE_KEY && e.newValue) {
            applyThemeAttribute(e.newValue);
            updateSwitcherUI(e.newValue);
        }
    });

    // 5. Header Opt-In Handling
    window.dismissHeaderOptin = function (e) {
        if (e) e.preventDefault();
        try {
            localStorage.setItem(OPTIN_STORAGE_KEY, '1');
        } catch (err) {}

        document.documentElement.classList.add('optin-dismissed');
        if (document.body) document.body.classList.add('optin-dismissed');

        const bar = document.getElementById('headerOptinBar');
        if (bar) {
            bar.style.opacity = '0';
            bar.style.transform = 'translateY(-100%)';
            setTimeout(() => {
                bar.style.display = 'none';
            }, 250);
        }
    };

    window.handleOptinSubmit = function (e) {
        e.preventDefault();
        const form = e.target;
        const emailInput = form.querySelector('input[type="email"]');
        const email = emailInput ? emailInput.value.trim() : '';

        if (!email || !email.includes('@')) {
            if (typeof showToast === 'function') {
                showToast('Please enter a valid email address.', 'warning');
            } else {
                alert('Please enter a valid email address.');
            }
            return false;
        }

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
        }

        setTimeout(() => {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i> Access Claimed!';
            }
            if (typeof showToast === 'function') {
                showToast('🎉 Access Granted! 50,000 AI Credits allocated to ' + email, 'success');
            } else {
                alert('🎉 Access Granted! 50,000 AI Credits allocated to ' + email);
            }
            setTimeout(() => {
                window.dismissHeaderOptin();
            }, 1200);
        }, 600);

        return false;
    };

    // 6. DOM Initialization
    function initThemeDOM() {
        const currentTheme = getStoredTheme();
        applyThemeAttribute(currentTheme);
        updateSwitcherUI(currentTheme);

        // Bind click events on switcher buttons
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.onclick = function (e) {
                e.preventDefault();
                const themeVal = this.getAttribute('data-theme-val');
                if (themeVal) {
                    window.setTheme(themeVal);
                }
            };
        });

        // Sync optin dismissed class on body
        try {
            if (localStorage.getItem(OPTIN_STORAGE_KEY) === '1') {
                document.documentElement.classList.add('optin-dismissed');
                if (document.body) document.body.classList.add('optin-dismissed');
                const bar = document.getElementById('headerOptinBar');
                if (bar) bar.style.display = 'none';
            }
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeDOM);
    } else {
        initThemeDOM();
    }
})();
