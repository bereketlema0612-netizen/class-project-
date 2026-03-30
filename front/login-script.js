// Simple Login Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const BACKEND_BASE = APP_BASE + '/backend';
const LOGIN_API_URL = BACKEND_BASE + '/auth/login.php';

function switchLoginMode(mode) {
    const studentToggle = document.getElementById('studentToggle');
    const staffToggle = document.getElementById('staffToggle');
    const studentForm = document.getElementById('studentForm');
    const staffForm = document.getElementById('staffForm');

    if (!studentToggle || !staffToggle || !studentForm || !staffForm) return;

    if (mode === 'student') {
        studentToggle.classList.add('active');
        staffToggle.classList.remove('active');
        studentForm.classList.add('active');
        staffForm.classList.remove('active');
    } else {
        staffToggle.classList.add('active');
        studentToggle.classList.remove('active');
        staffForm.classList.add('active');
        studentForm.classList.remove('active');
    }
}

async function handleLogin(event, mode) {
    event.preventDefault();

    const username = mode === 'student'
        ? (document.getElementById('studentUsername')?.value || '').trim()
        : (document.getElementById('staffUsername')?.value || '').trim();

    const password = mode === 'student'
        ? (document.getElementById('studentPassword')?.value || '').trim()
        : (document.getElementById('staffPassword')?.value || '').trim();

    if (!username || !password) {
        showError('Please fill all fields');
        return;
    }

    try {
        const res = await fetch(LOGIN_API_URL, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const text = await res.text();
        const body = JSON.parse(text);

        if (!body.success || !body.data) {
            showError(body.message || 'Login failed');
            return;
        }

        localStorage.setItem('currentUser', JSON.stringify(body.data));

        const role = String(body.data.role || '');
        if (role === 'student') window.location.href = FRONT_BASE + '/student-dashboard.html';
        else if (role === 'teacher') window.location.href = FRONT_BASE + '/teacher-dashboard.html';
        else if (role === 'director') window.location.href = FRONT_BASE + '/director.html';
        else if (role === 'admin') window.location.href = FRONT_BASE + '/register.html';
        else showError('Unknown role');
    } catch (e) {
        showError('Login failed. Check server/API and try again.');
    }
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function showError(msg) {
    alert(msg);
}
