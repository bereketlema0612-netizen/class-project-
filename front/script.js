// Simple Student Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php'
};

document.addEventListener('DOMContentLoaded', () => {
    const user = getCurrentUser();
    if (!user || user.role !== 'student') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });

    const nameEl = document.getElementById('studentName');
    if (nameEl) nameEl.textContent = user.fullName || user.username || 'Student';
    setText('profileName', user.fullName || user.username || 'Student');
    setText('profileID', user.username || '-');
    setText('profileEmail', user.email || '-');
    setText('profileGrade', 'Grade -');
});

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));
    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');
    const title = document.getElementById('pageTitle');
    if (title) title.textContent = pageName ? pageName.charAt(0).toUpperCase() + pageName.slice(1) : 'Dashboard';
}

function openMyProfile() {
    switchPage('profile');
}

function toggleSubmissionForm() {
    const form = document.getElementById('submissionFormContainer');
    if (!form) return;
    const isHidden = form.style.display === 'none' || !form.style.display;
    form.style.display = isHidden ? 'block' : 'none';
}

function openSubmissionFilePicker() {
    document.getElementById('submissionFile')?.click();
}

function removeSelectedFile() {
    const input = document.getElementById('submissionFile');
    if (input) input.value = '';
}

function clearSubmissionForm() {
    document.getElementById('submissionTitle') && (document.getElementById('submissionTitle').value = '');
    document.getElementById('submissionDescription') && (document.getElementById('submissionDescription').value = '');
    removeSelectedFile();
}

function submitAssignment() {
    alert('Simple class version: submit assignment not connected yet.');
}

function updateTeacherDropdown() {
    // Kept as a no-op for class project compatibility.
}

function downloadFile(filename) {
    if (!filename) return;
    window.open(filename, '_blank', 'noopener');
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

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value ?? '');
}

