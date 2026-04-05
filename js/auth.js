/**
 * auth.js — Shared auth utilities for MediTrack
 * Internal Medicine Clinic
 */

const API_BASE = '/meditrack/api';

/**
 * Check if a user is authenticated. Redirects to login if not.
 * @returns {object|null} user object or null
 */
function checkAuth() {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user) {
        window.location.href = '/meditrack/pages/login.html';
        return null;
    }
    return user;
}

/**
 * Log out the current user — calls the API, clears session, redirects to login.
 */
function logout() {
    fetch(`${API_BASE}/auth/logout.php`, { method: 'POST', credentials: 'include' })
        .finally(() => {
            sessionStorage.clear();
            window.location.href = '/meditrack/pages/login.html';
        });
}

/**
 * Return the currently stored user object without redirecting.
 * @returns {object|null}
 */
function getUser() {
    return JSON.parse(sessionStorage.getItem('user') || 'null');
}

/**
 * Wrapper around fetch that adds default headers, credentials, and handles 401.
 * @param {string} url  - path relative to API_BASE (e.g. "/auth/login.php")
 * @param {object} options - standard fetch options
 * @returns {object|null} parsed JSON response, or null on 401
 */
async function apiRequest(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include'
    };
    const config = { ...defaults, ...options };
    if (options.headers) config.headers = { ...defaults.headers, ...options.headers };

    const response = await fetch(`${API_BASE}${url}`, config);
    const data = await response.json();

    if (response.status === 401) {
        sessionStorage.clear();
        window.location.href = '/meditrack/pages/login.html';
        return null;
    }
    return data;
}
