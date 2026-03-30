// Simple Registration Admin Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php',
    teacherList: APP_BASE + '/backend/registration/get_teachers.php',
    classList: APP_BASE + '/backend/registration/get_classes.php',
    assignTeacher: APP_BASE + '/backend/registration/assign_teacher.php',
    assignedTeachers: APP_BASE + '/backend/registration/get_assigned_teachers.php'
};

const state = {
    user: null,
    teachers: [],
    classes: [],
    assignedTeachers: []
};

document.addEventListener('DOMContentLoaded', () => {
    state.user = getCurrentUser();
    if (!state.user || !['admin', 'director'].includes(state.user.role)) {
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
    updateUserInfo();
});

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');

    const title = document.getElementById('pageTitle');
    if (!title) return;

    const map = {
        dashboard: 'Dashboard',
        'register-student': 'Register Student',
        'register-teacher': 'Register Teacher',
        assign: 'Assign Users',
        'all-registrations': 'All Registrations'
    };
    title.textContent = map[pageName] || 'Dashboard';

    if (pageName === 'assign') {
        loadAssignData();
    }
}

function switchAllRecordsTab(tab) {
    const studentTab = document.getElementById('studentRecordsTab');
    const teacherTab = document.getElementById('teacherRecordsTab');

    if (studentTab) studentTab.style.display = tab === 'students' ? 'block' : 'none';
    if (teacherTab) teacherTab.style.display = tab === 'teachers' ? 'block' : 'none';
}

function openMyProfile() {
    const user = state.user || {};
    setProfileText('myProfileName', user.fullName || user.username || 'Admin');
    setProfileText('myProfileUsername', user.username || '-');
    setProfileText('myProfileRole', String(user.role || 'admin').toUpperCase());
    setProfileText('myProfileEmail', user.email || '-');
    setProfileText('myProfileSession', user.session_id || '-');
    document.getElementById('myProfileModal')?.classList.add('active');
}

function closeMyProfileModal() {
    document.getElementById('myProfileModal')?.classList.remove('active');
}

function loadStudentRecords() {
    alert('Simple class version: load student records not connected yet.');
}

async function handleStudentRegistration(event) {
    event.preventDefault();
    alert('Simple class version: student registration not connected yet.');
}

async function handleTeacherRegistration(event) {
    event.preventDefault();
    alert('Simple class version: teacher registration not connected yet.');
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch (_) { throw new Error('Invalid JSON'); }
    if (!body || !body.success) throw new Error(body?.message || 'Request failed');
    return body.data || {};
}

async function apiPost(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch (_) { throw new Error('Invalid JSON'); }
    if (!body || !body.success) throw new Error(body?.message || 'Request failed');
    return body.data || {};
}

async function loadAssignData() {
    try {
        const [teacherData, classData, assignedData] = await Promise.all([
            apiGet(API.teacherList),
            apiGet(API.classList),
            apiGet(API.assignedTeachers)
        ]);

        state.teachers = teacherData.teachers || [];
        state.classes = classData.classes || [];
        state.assignedTeachers = assignedData.assigned_teachers || [];

        fillAssignSelects();
        renderAssignedTeachers();
        showAssignMessage('');
    } catch (e) {
        showAssignMessage('Failed to load assign data: ' + e.message, false);
    }
}

function fillAssignSelects() {
    const teacherSelect = document.getElementById('assignTeacherUsername');
    const classSelect = document.getElementById('assignClassId');
    if (!teacherSelect || !classSelect) return;

    teacherSelect.innerHTML = '<option value="">-- Select teacher --</option>';
    classSelect.innerHTML = '<option value="">-- Select class --</option>';

    state.teachers.forEach((t) => {
        const opt = document.createElement('option');
        opt.value = String(t.username || '');
        opt.textContent = `${t.username} - ${t.full_name || t.username}`;
        teacherSelect.appendChild(opt);
    });

    state.classes.forEach((c) => {
        const opt = document.createElement('option');
        opt.value = String(c.id || '');
        opt.textContent = c.name || ('Class ' + c.id);
        classSelect.appendChild(opt);
    });
}

function renderAssignedTeachers() {
    const body = document.getElementById('assignedTeachersBody');
    if (!body) return;
    body.innerHTML = '';

    if (!state.assignedTeachers.length) {
        body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">No assignments yet</td></tr>';
        return;
    }

    state.assignedTeachers.forEach((a) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(a.teacher_username || '-')}</td>
            <td>${escapeHtml(a.class_name || ('Class ' + (a.class_id || '')))}</td>
            <td>${escapeHtml(a.subject_name || '-')}</td>
        `;
        body.appendChild(tr);
    });
}

async function handleAssignTeacher(event) {
    event.preventDefault();
    const teacher = (document.getElementById('assignTeacherUsername')?.value || '').trim();
    const classId = Number(document.getElementById('assignClassId')?.value || 0);
    const subjectName = (document.getElementById('assignSubjectName')?.value || '').trim();
    if (!teacher || !classId || !subjectName) {
        showAssignMessage('Choose teacher, class and subject.', false);
        return;
    }

    try {
        await apiPost(API.assignTeacher, { teacher_username: teacher, class_id: classId, subject_name: subjectName });
        showAssignMessage('Assigned successfully.', true);
        await loadAssignData();
    } catch (e) {
        showAssignMessage('Assign failed: ' + e.message, false);
    }
}

function showAssignMessage(message, success = true) {
    const el = document.getElementById('assignMessage');
    if (!el) return;
    el.textContent = message;
    if (!message) {
        el.className = 'form-success-message';
        return;
    }
    el.className = success ? 'form-success-message success' : 'form-success-message error';
}

function enableStudentProfileEdit() { alert('Simple class version.'); }
function cancelStudentProfileEdit() { alert('Simple class version.'); }
function saveSelectedStudentProfile() { alert('Simple class version.'); }
function deleteSelectedStudentProfile() { alert('Simple class version.'); }
function closeStudentProfileModal() { document.getElementById('studentProfileModal')?.classList.remove('active'); }
function handleStudentProfileModalBackdrop(event) { if (event.target?.id === 'studentProfileModal') closeStudentProfileModal(); }

function enableTeacherProfileEdit() { alert('Simple class version.'); }
function cancelTeacherProfileEdit() { alert('Simple class version.'); }
function saveSelectedTeacherProfile() { alert('Simple class version.'); }
function closeTeacherProfileModal() { document.getElementById('teacherProfileModal')?.classList.remove('active'); }
function handleTeacherProfileModalBackdrop(event) { if (event.target?.id === 'teacherProfileModal') closeTeacherProfileModal(); }

function updateTeacherDropdown() { }

async function handleAdminLogout(event) {
    if (event?.preventDefault) event.preventDefault();
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (_) {}
    localStorage.removeItem('currentUser');
    sessionStorage.clear();
    window.location.replace(FRONT_BASE + '/login.html');
}

function updateUserInfo() {
    const name = state.user?.fullName || state.user?.username || 'Admin';
    const role = (state.user?.role || 'admin').toUpperCase();

    const nameEl = document.getElementById('userName');
    const roleEl = document.getElementById('userRole');
    if (nameEl) nameEl.textContent = name;
    if (roleEl) roleEl.textContent = role;
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

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

