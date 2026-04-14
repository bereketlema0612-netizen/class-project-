// Simple Admin Script

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php',

    dashboardStats: APP_BASE + '/backend/admin/get_dashboard_stats.php',
    updateUserStatus: APP_BASE + '/backend/admin/update_user_status.php',

    registerStudent: APP_BASE + '/backend/admin/register_student.php',
    registerTeacher: APP_BASE + '/backend/admin/register_teacher.php',
    studentRecords: APP_BASE + '/backend/admin/get_student_records.php',
    teacherList: APP_BASE + '/backend/admin/get_teachers.php',

    classList: APP_BASE + '/backend/admin/get_classes.php',
    assignTeacher: APP_BASE + '/backend/admin/assign_teacher.php',
    assignedTeachers: APP_BASE + '/backend/admin/get_assigned_teachers.php'
};

const state = {
    user: null,
    teachers: [],
    classes: [],
    assignedTeachers: []
};

document.addEventListener('DOMContentLoaded', () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'admin') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });

    document.querySelectorAll('.role-btn').forEach((btn) => {
        btn.addEventListener('click', () => setRegisterRole(btn.getAttribute('data-role')));
    });

    setupGradeFilter();
    setRegisterRole('student');
    switchPage('dashboard');
});

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');

    const title = document.getElementById('pageTitle');
    const map = {
        dashboard: 'Dashboard',
        register: 'Register',
        assign: 'Assign Users',
        'all-registrations': 'All Registrations'
    };
    if (title) title.textContent = map[pageName] || 'Dashboard';

    if (pageName === 'dashboard') loadDashboard();
    if (pageName === 'assign') loadAssignData();
    if (pageName === 'all-registrations') {
        loadStudentRecords();
        loadTeacherRecords();
    }
}

function setupGradeFilter() {
    const grade = document.getElementById('studentGradeFilter');
    if (!grade) return;
    grade.innerHTML = '<option value="">All Grades</option>';
    ['9', '10', '11', '12'].forEach((g) => {
        const op = document.createElement('option');
        op.value = g;
        op.textContent = 'Grade ' + g;
        grade.appendChild(op);
    });
}

function setRegisterRole(role) {
    const studentFields = document.getElementById('studentFields');
    const teacherFields = document.getElementById('teacherFields');
    const roleSelect = document.getElementById('registerRole');
    if (roleSelect) roleSelect.value = role;

    if (studentFields) studentFields.style.display = role === 'student' ? '' : 'none';
    if (teacherFields) teacherFields.style.display = role === 'teacher' ? '' : 'none';

    document.querySelectorAll('.role-btn').forEach((btn) => {
        const isActive = btn.getAttribute('data-role') === role;
        btn.classList.toggle('active', isActive);
    });
}

async function loadDashboard() {
    try {
        const d = await apiGet(API.dashboardStats);
        setText('totalStudents', d.total_students || 0);
        setText('totalTeachers', d.total_teachers || 0);
        setText('dashPendingCount', d.total_registrations || 0);
        setText('totalApproved', d.total_approved || 0);
        setText('totalAdmins', d.total_admins || 0);
        setText('registrationBadge', d.total_registrations || 0);
    } catch (e) {
        showFormMessage('#registerMessage', 'Failed to load dashboard: ' + e.message, false);
    }
}

async function handleRegistration(event) {
    event.preventDefault();
    const form = event.target;
    const role = (document.getElementById('registerRole')?.value || 'student').trim();
    const data = Object.fromEntries(new FormData(form).entries());

    try {
        if (role === 'teacher') {
            const out = await apiPost(API.registerTeacher, data);
            showFormMessage('#registerMessage', `Teacher registered. Username: ${out.username}, Password: ${out.password}`, true);
        } else {
            const out = await apiPost(API.registerStudent, data);
            showFormMessage('#registerMessage', `Student registered. Username: ${out.username}, Password: ${out.password}`, true);
        }
        form.reset();
        setRegisterRole(role);
    } catch (e) {
        showFormMessage('#registerMessage', 'Registration failed: ' + e.message, false);
    }
}

function switchAllRecordsTab(tab) {
    const studentTabBtn = document.getElementById('recordsTabStudents');
    const teacherTabBtn = document.getElementById('recordsTabTeachers');
    const studentPanel = document.getElementById('studentRecordsPanel');
    const teacherPanel = document.getElementById('teacherRecordsPanel');

    if (studentPanel) studentPanel.classList.toggle('active', tab === 'students');
    if (teacherPanel) teacherPanel.classList.toggle('active', tab === 'teachers');
    if (studentTabBtn) studentTabBtn.classList.toggle('active', tab === 'students');
    if (teacherTabBtn) teacherTabBtn.classList.toggle('active', tab === 'teachers');
}

async function loadStudentRecords() {
    const grade = (document.getElementById('studentGradeFilter')?.value || '').trim();
    const search = (document.getElementById('studentSearchInput')?.value || '').trim();

    const qs = new URLSearchParams();
    if (grade) qs.set('grade', grade);
    if (search) qs.set('search', search);

    try {
        const data = await apiGet(API.studentRecords + (qs.toString() ? '?' + qs.toString() : ''));
        const list = data.students || [];
        const card = document.getElementById('studentRecordsTableCard');
        const body = document.getElementById('studentRecordsTableBody');
        if (!body || !card) return;

        card.style.display = '';
        body.innerHTML = '';
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No students found</td></tr>';
            return;
        }

        list.forEach((s) => {
            const status = s.status || 'active';
            const next = status === 'active' ? 'inactive' : 'active';
            const btnLabel = next === 'active' ? 'Activate' : 'Deactivate';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(s.username)}</td>
                <td>${escapeHtml(s.full_name || '')}</td>
                <td>${escapeHtml(s.grade_level || '-')}</td>
                <td><span class="status-badge ${escapeHtml(status)}">${escapeHtml(status)}</span></td>
                <td><button class="btn btn-secondary btn-small" onclick="toggleUserStatus('${escapeJs(s.username || '')}', '${escapeJs(next)}')">${btnLabel}</button></td>
            `;
            body.appendChild(tr);
        });
    } catch (e) {
        showFormMessage('#studentManageMessage', 'Load students failed: ' + e.message, false);
    }
}

async function loadTeacherRecords() {
    try {
        const data = await apiGet(API.teacherList);
        const list = data.teachers || [];
        const body = document.getElementById('teacherRecordsTableBody');
        if (!body) return;
        body.innerHTML = '';

        if (!list.length) {
            body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No teachers found</td></tr>';
            return;
        }

        list.forEach((t) => {
            const status = t.status || 'active';
            const next = status === 'active' ? 'inactive' : 'active';
            const btnLabel = next === 'active' ? 'Activate' : 'Deactivate';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(t.username || '')}</td>
                <td>${escapeHtml(t.full_name || '')}</td>
                <td>${escapeHtml(t.department || '-')}</td>
                <td><span class="status-badge ${escapeHtml(status)}">${escapeHtml(status)}</span></td>
                <td><button class="btn btn-secondary btn-small" onclick="toggleUserStatus('${escapeJs(t.username || '')}', '${escapeJs(next)}')">${btnLabel}</button></td>
            `;
            body.appendChild(tr);
        });
    } catch (e) {
        showFormMessage('#teacherManageMessage', 'Load teachers failed: ' + e.message, false);
    }
}

async function toggleUserStatus(username, status) {
    try {
        await apiPost(API.updateUserStatus, { username, status });
        showFormMessage('#studentManageMessage', 'User status updated.', true);
        showFormMessage('#teacherManageMessage', 'User status updated.', true);
        await loadStudentRecords();
        await loadTeacherRecords();
        await loadDashboard();
    } catch (e) {
        showFormMessage('#studentManageMessage', 'Status update failed: ' + e.message, false);
    }
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

    classSelect.onchange = updateSubjectsForClass;
    classSelect.oninput = updateSubjectsForClass;
    updateSubjectsForClass();
}

function updateSubjectsForClass() {
    const classSelect = document.getElementById('assignClassId');
    const subjectSelect = document.getElementById('assignSubjectName');
    if (!classSelect || !subjectSelect) return;

    const classId = String(classSelect.value || '');
    const current = state.classes.find((c) => String(c.id || '') === classId);
    const name = String(current?.name || '').toLowerCase();
    const section = String(current?.section || '').toLowerCase();
    let grade = parseInt(current?.grade_level || '0', 10);
    if (!grade) {
        const gradeMatch = name.match(/(\d+)/);
        grade = gradeMatch ? parseInt(gradeMatch[1], 10) : 0;
    }
    const isNatural = section.includes('natural') || name.includes('natural');
    const isSocial = section.includes('social') || name.includes('social');

    const subjects_9_10 = ['Physics', 'Chemistry', 'Biology', 'English', 'Mathematics', 'Civic', 'Sport'];
    const subjects_11_12_natural = ['Physics', 'Chemistry', 'Biology', 'English', 'Mathematics', 'IT'];
    const subjects_11_12_social = ['Physics', 'Chemistry', 'Biology', 'English', 'Mathematics', 'Economics'];

    let list = [];
    if (!classId) list = [];
    else if (grade === 9 || grade === 10) list = subjects_9_10;
    else if (grade === 11 || grade === 12) {
        if (isSocial) list = subjects_11_12_social;
        else list = subjects_11_12_natural;
    } else list = subjects_9_10;

    subjectSelect.innerHTML = '<option value="">-- Select subject --</option>';
    list.forEach((s) => {
        const op = document.createElement('option');
        op.value = s;
        op.textContent = s;
        subjectSelect.appendChild(op);
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
    el.className = 'form-success-message';
    if (!message) return;
    el.classList.add('show');
    el.classList.add(success ? 'success' : 'error');
}

async function handleAdminLogout(event) {
    if (event?.preventDefault) event.preventDefault();
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (_) { }
    localStorage.removeItem('currentUser');
    sessionStorage.clear();
    window.location.replace(FRONT_BASE + '/login.html');
}

function getCurrentUser() {
    const raw = localStorage.getItem('currentUser');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
}

function showFormMessage(selector, message, ok) {
    const el = document.querySelector(selector);
    if (!el) return;
    el.textContent = message || '';
    el.className = 'form-success-message';
    if (!message) return;
    el.classList.add('show');
    el.classList.add(ok ? 'success' : 'error');
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value ?? '');
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

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeJs(v) {
    return String(v ?? '').replaceAll('\\', '\\\\').replaceAll("'", "\\'");
}
