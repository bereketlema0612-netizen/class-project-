function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.target.closest('button').querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function handleAdminLogin(event) {
    event.preventDefault();

    const username = document.getElementById('adminUsername').value.trim();
    const password = document.getElementById('adminPassword').value;
    const rememberMe = document.getElementById('rememberMe').checked;

    if (!username || !password) {
        setLoginMessage('Please fill in all fields', 'error');
        return;
    }

    const loginData = {
        username: username,
        password: password
    };

    console.log("[v0] Admin login attempt:", username);

    fetch('../backend/auth/login.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(loginData)
    })
    .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (_) {
            throw new Error('Server returned invalid JSON');
        }
        if (!response.ok) {
            throw new Error(data.message || 'Login request failed');
        }
        return data;
    })
    .then(data => {
        console.log("[v0] Login response:", data);

        if (data.success) {
            if (data.data.role !== 'admin' && data.data.role !== 'director') {
                setLoginMessage('Access denied: director/admin credentials required', 'error');
                return;
            }

            localStorage.setItem('currentUser', JSON.stringify({
                id: data.data.id,
                username: data.data.username,
                role: data.data.role,
                email: data.data.email,
                fullName: data.data.fullName,
                session_id: data.data.session_id,
                loginTime: new Date().toISOString()
            }));

            if (rememberMe) {
                localStorage.setItem('rememberMe', 'true');
                localStorage.setItem('rememberUsername', username);
            }

            setLoginMessage('Login successful. Redirecting...', 'success');
            if (data.data.role === 'director') {
                window.location.href = 'director.html';
            } else {
                window.location.href = 'register.html';
            }
        } else {
            setLoginMessage('Login failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error("[v0] Login error:", error);
        setLoginMessage(error.message || 'Login error. Please try again.', 'error');
    });
}

function setLoginMessage(message, type) {
    let box = document.getElementById('loginStatusMessage');
    if (!box) {
        const form = document.getElementById('adminLoginForm');
        box = document.createElement('div');
        box.id = 'loginStatusMessage';
        box.style.marginTop = '10px';
        box.style.padding = '10px 12px';
        box.style.borderRadius = '8px';
        box.style.fontSize = '14px';
        box.style.fontWeight = '600';
        form?.appendChild(box);
    }

    if (type === 'success') {
        box.style.background = '#dcfce7';
        box.style.color = '#166534';
        box.style.border = '1px solid #86efac';
    } else {
        box.style.background = '#fee2e2';
        box.style.color = '#991b1b';
        box.style.border = '1px solid #fca5a5';
    }
    box.textContent = message;
}

document.addEventListener('DOMContentLoaded', function() {
    const rememberUsername = localStorage.getItem('rememberUsername');
    if (rememberUsername) {
        document.getElementById('adminUsername').value = rememberUsername;
        document.getElementById('rememberMe').checked = true;
    }
});
