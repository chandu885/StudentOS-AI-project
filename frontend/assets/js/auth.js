/**
 * frontend/assets/js/auth.js - StudentOS AI Authentication Client
 */

function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        if (icon) icon.className = 'fas fa-eye';
    }
}

function handleClientLogout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('session_token');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('session_token');
    window.location.href = '/StudentOS-AI-project/frontend/login.php';
}
