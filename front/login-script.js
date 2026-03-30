// ============================================================
// LOGIN PAGE FUNCTIONALITY
// ============================================================

// Track current login mode
let currentLoginMode = 'student';

const LOGIN_API_URL = '../backend/auth/login.php';

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    initializeLoginPage();
});

function initializeLoginPage() {
    // Keep login page stable: do not auto-redirect from localStorage.
    // This avoids blink/loop when stale user data exists.
    localStorage.removeItem('currentUser');

    // Load saved username if "Remember me" was checked
    loadSavedCredentials();
}

// ============================================================
// LOGIN MODE SWITCHING
// ============================================================

function switchLoginMode(mode) {
    // Update current mode
    currentLoginMode = mode;

    // Update toggle buttons
    const studentToggle = document.getElementById('studentToggle');
    const staffToggle = document.getElementById('staffToggle');

    if (mode === 'student') {
        studentToggle.classList.add('active');
        staffToggle.classList.remove('active');
    } else {
        staffToggle.classList.add('active');
        studentToggle.classList.remove('active');
    }

    // Show/hide forms
    const studentForm = document.getElementById('studentForm');
    const staffForm = document.getElementById('staffForm');

    if (mode === 'student') {
        studentForm.classList.add('active');
        staffForm.classList.remove('active');
        // Clear staff form
        staffForm.reset();
    } else {
        staffForm.classList.add('active');
        studentForm.classList.remove('active');
        // Clear student form
        studentForm.reset();
    }

    // Clear any error messages
    clearErrorMessages();
}

// ============================================================
// LOGIN HANDLING
// ============================================================

async function handleLogin(event, mode) {
    event.preventDefault();

    let username, password;

    if (mode === 'student') {
        username = document.getElementById('studentUsername').value.trim();
        password = document.getElementById('studentPassword').value.trim();
    } else {
        username = document.getElementById('staffUsername').value.trim();
        password = document.getElementById('staffPassword').value.trim();
    }

    // Validate inputs
    if (!username || !password) {
        showErrorMessage('Please fill in all fields', mode);
        return;
    }

    // Add loading state to button
    const buttonText = event.target.querySelector('span');
    const originalText = buttonText.textContent;
    buttonText.textContent = 'Logging in...';
    event.target.disabled = true;

    try {
        const response = await fetch(LOGIN_API_URL, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });

        const result = await response.json();

        if (!response.ok || !result.success || !result.data) {
            showErrorMessage(result.message || 'Invalid username or password', mode);
            event.target.disabled = false;
            buttonText.textContent = originalText;
            return;
        }

        const user = result.data;

        // Enforce page mode: student tab accepts only student, staff tab accepts teacher/admin/director
        const allowedStaffRoles = ['teacher', 'admin', 'director'];
        if (mode === 'student' && user.role !== 'student') {
            showErrorMessage('Please use the Student button for this account', mode);
            event.target.disabled = false;
            buttonText.textContent = originalText;
            return;
        }

        if (mode === 'staff' && !allowedStaffRoles.includes(user.role)) {
            showErrorMessage('Please use the Staff button for this account', mode);
            event.target.disabled = false;
            buttonText.textContent = originalText;
            return;
        }

        // Save user session payload locally for frontend pages
        localStorage.setItem('currentUser', JSON.stringify({
            id: user.id,
            username: user.username,
            email: user.email,
            role: user.role,
            fullName: user.fullName || user.username,
            session_id: user.session_id,
            loginTime: new Date().toISOString()
        }));

        showSuccessMessage(`Welcome, ${user.fullName || user.username}!`);
        setTimeout(() => {
            redirectToPortal({ userType: user.role });
        }, 500);
    } catch (error) {
        showErrorMessage('Login failed. Check server/API and try again.', mode);
        event.target.disabled = false;
        buttonText.textContent = originalText;
    }
}

function redirectToPortal(user) {
    const role = user?.userType || user?.role || '';
    const destination = getPortalPath(role);
    if (!destination) {
        window.location.href = 'login.html';
        return;
    }
    window.location.href = destination;
}

function getPortalPath(role) {
    if (role === 'student') return 'student-dashboard.html';
    if (role === 'teacher') return 'teacher-dashboard.html';
    if (role === 'director') return 'director.html';
    if (role === 'admin') return 'register.html';
    return '';
}

// ============================================================
// PASSWORD VISIBILITY TOGGLE
// ============================================================

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = event.target.closest('.password-toggle');
    const icon = button.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ============================================================
// ERROR & SUCCESS MESSAGES
// ============================================================

function showErrorMessage(message, mode) {
    const form = mode === 'student' ? document.getElementById('studentForm') : document.getElementById('staffForm');
    
    // Remove existing error if present
    const existingError = form.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        <span>${message}</span>
    `;

    form.insertBefore(errorDiv, form.firstChild);

    // Auto-remove error after 5 seconds
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

function showSuccessMessage(message) {
    const card = document.querySelector('.login-card');
    
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message';
    successDiv.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
    `;

    card.appendChild(successDiv);

    // Remove after 2 seconds
    setTimeout(() => {
        successDiv.remove();
    }, 2000);
}

function clearErrorMessages() {
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(msg => msg.remove());
}

function saveCredentials(mode, username) {
    localStorage.setItem(`${mode}_username`, username);
    localStorage.setItem(`${mode}_remember`, '1');
}

function loadSavedCredentials() {
    const studentRemember = localStorage.getItem('student_remember') === '1';
    const staffRemember = localStorage.getItem('staff_remember') === '1';
    const studentUsername = localStorage.getItem('student_username') || '';
    const staffUsername = localStorage.getItem('staff_username') || '';

    if (studentRemember && document.getElementById('studentUsername')) {
        document.getElementById('studentUsername').value = studentUsername;
    }
    if (staffRemember && document.getElementById('staffUsername')) {
        document.getElementById('staffUsername').value = staffUsername;
    }
}



function clearSavedCredentials(mode) {
    localStorage.removeItem(`${mode}_username`);
    localStorage.removeItem(`${mode}_remember`);
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================

document.addEventListener('keydown', function(e) {
    // Tab key to switch between login modes
    if (e.key === 'Tab' && e.shiftKey) {
        e.preventDefault();
        if (currentLoginMode === 'student') {
            switchLoginMode('staff');
        } else {
            switchLoginMode('student');
        }
    }
});

// ============================================================
// SESSION MANAGEMENT
// ============================================================

function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'login.html';
}

function isUserLoggedIn() {
    return localStorage.getItem('currentUser') !== null;
}

function getCurrentUser() {
    const user = localStorage.getItem('currentUser');
    return user ? JSON.parse(user) : null;
}
