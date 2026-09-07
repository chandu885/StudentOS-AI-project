/**
 * frontend/assets/js/api.js - StudentOS AI Frontend API Client
 */

const API_BASE = window.location.origin + '/StudentOS-AI-project/backend/api';

async function apiFetch(endpoint, method = 'GET', data = null) {
    const url = endpoint.startsWith('http') ? endpoint : (API_BASE + (endpoint.startsWith('/') ? '' : '/') + endpoint);
    
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };

    const authToken = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
    }

    const sessionToken = localStorage.getItem('session_token') || sessionStorage.getItem('session_token');
    if (sessionToken) {
        headers['X-Session-Token'] = sessionToken;
    }

    const options = {
        method,
        headers
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        options.body = JSON.stringify(data);
    }

    try {
        const res = await fetch(url, options);
        const json = await res.json();

        if (!res.ok) {
            throw new Error(json.error || json.message || `Request failed with status ${res.status}`);
        }
        return json;
    } catch (err) {
        console.error(`API Error [${method} ${url}]:`, err);
        throw err;
    }
}
