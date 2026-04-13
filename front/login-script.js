const LOGIN_API_URL = '/bensa_school/backend/auth/login.php';
let currentMode = 'student';

function switchMode(mode) {
    currentMode = mode;
    document.getElementById('studentBtn').classList.toggle('active', mode === 'student');
    document.getElementById('staffBtn').classList.toggle('active', mode === 'staff');
    document.getElementById('usernameLabel').textContent = mode === 'student' ? 'Student ID / Username' : 'Staff Username';
    showMessage('');
}

async function handleLogin(event) {
    event.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    if (!username || !password) {
        showMessage('Enter username and password.');
        return;
    }

    try {
        const response = await fetch(LOGIN_API_URL, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const result = await response.json();
        if (!result.success || !result.data) {
            showMessage(result.message || 'Login failed.');
            return;
        }

        const role = result.data.role;
        const isStaff = role === 'teacher' || role === 'director' || role === 'admin';

        if (currentMode === 'student' && role !== 'student') {
            showMessage('This is a staff account. Switch to Staff.');
            return;
        }

        if (currentMode === 'staff' && !isStaff) {
            showMessage('This is a student account. Switch to Student.');
            return;
        }

        localStorage.setItem('currentUser', JSON.stringify(result.data));

        if (role === 'student') window.location.href = '/bensa_school/front/student-dashboard.html';
        else if (role === 'teacher') window.location.href = '/bensa_school/front/teacher-dashboard.html';
        else if (role === 'director') window.location.href = '/bensa_school/front/director.html';
        else if (role === 'admin') window.location.href = '/bensa_school/front/register.html';
        else showMessage('Unknown role.');
    } catch (error) {
        showMessage('Server error. Try again.');
    }
}

function showMessage(text) {
    const box = document.getElementById('loginMessage');
    box.style.display = text ? 'block' : 'none';
    box.textContent = text || '';
}
