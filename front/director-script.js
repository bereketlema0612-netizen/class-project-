// Simple Director Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php'
};

const state = {
    user: null
};

document.addEventListener('DOMContentLoaded', () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'director') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });

    switchPage('dashboard');
});

function switchPage(page) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(page)?.classList.add('active');
    document.querySelector(`[data-page="${page}"]`)?.classList.add('active');

    const titleMap = {
        dashboard: 'Dashboard',
        'user-management': 'User Management',
        announcements: 'Announcements',
        settings: 'School Settings'
    };

    const title = document.getElementById('pageTitle');
    if (title) title.textContent = titleMap[page] || 'Dashboard';
}

function switchUserTab(tab) {
    document.querySelectorAll('.user-content').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.user-tab').forEach((t) => t.classList.remove('active'));

    if (tab === 'students') {
        document.getElementById('students-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[0]?.classList.add('active');
    } else if (tab === 'teachers') {
        document.getElementById('teachers-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[1]?.classList.add('active');
    } else {
        document.getElementById('admins-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[2]?.classList.add('active');
    }
}

async function handleCreateAdmin(event) {
    event.preventDefault();
    alert('Simple class version: create admin feature not connected yet.');
}

async function handleCreateAnnouncement(event) {
    event.preventDefault();
    alert('Simple class version: announcement feature not connected yet.');
}

function openDirectorAnnouncementFilePicker() {
    document.getElementById('directorAnnouncementFile')?.click();
}

function onDirectorAnnouncementFileSelected() {
    // Kept simple for class project.
}

async function handleSaveSettings(event) {
    event.preventDefault();
    alert('Simple class version: settings save not connected yet.');
}

async function handleSaveAcademicCalendar(event) {
    event.preventDefault();
    alert('Simple class version: calendar save not connected yet.');
}

function closeModal() {
    document.getElementById('detailsModal')?.classList.remove('active');
}

function closeStatusConfirmModal() {
    document.getElementById('statusConfirmModal')?.classList.remove('active');
}

function confirmStatusChange() {
    closeStatusConfirmModal();
    alert('Simple class version: status change not connected yet.');
}

function openMyProfile() {
    const user = state.user || {};
    setProfileText('myProfileName', user.fullName || user.username || 'Director');
    setProfileText('myProfileUsername', user.username || '-');
    setProfileText('myProfileRole', String(user.role || 'director').toUpperCase());
    setProfileText('myProfileEmail', user.email || '-');
    setProfileText('myProfileSession', user.session_id || '-');
    document.getElementById('myProfileModal')?.classList.add('active');
}

function closeMyProfileModal() {
    document.getElementById('myProfileModal')?.classList.remove('active');
}

async function logout() {
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (_) {}
    localStorage.removeItem('currentUser');
    sessionStorage.clear();
    window.location.replace(FRONT_BASE + '/login.html');
}

function getCurrentUser() {
    const raw = localStorage.getItem('currentUser');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
}

function setProfileText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value ?? '');
}

