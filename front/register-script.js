// Simple Registration Admin Script (Beginner Class Project)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php',

    // registration
    registerStudent: APP_BASE + '/backend/registration/register_student.php',
    registerTeacher: APP_BASE + '/backend/registration/register_teacher.php',
    dashboardCounts: APP_BASE + '/backend/registration/get_dashboard_counts.php',
    recentRegistrations: APP_BASE + '/backend/registration/get_recent_registrations.php',

    // records
    studentRecords: APP_BASE + '/backend/registration/get_student_records.php',
    studentProfile: APP_BASE + '/backend/registration/get_student_profile.php',
    updateStudentProfile: APP_BASE + '/backend/registration/update_student_profile.php',
    deleteStudent: APP_BASE + '/backend/registration/delete_student.php',

    teacherList: APP_BASE + '/backend/registration/get_teachers.php',
    teacherProfile: APP_BASE + '/backend/registration/get_teacher_profile.php',
    updateTeacherProfile: APP_BASE + '/backend/registration/update_teacher_profile.php',

    // assign
    classList: APP_BASE + '/backend/registration/get_classes.php',
    assignTeacher: APP_BASE + '/backend/registration/assign_teacher.php',
    assignedTeachers: APP_BASE + '/backend/registration/get_assigned_teachers.php'
};

const state = {
    user: null,
    teachers: [],
    classes: [],
    assignedTeachers: [],
    selectedStudentUsername: '',
    selectedTeacherUsername: ''
};

document.addEventListener('DOMContentLoaded', async () => {
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

    setupGradeFilter();
    switchPage('dashboard');
});

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

async function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');

    const title = document.getElementById('pageTitle');
    const map = {
        dashboard: 'Dashboard',
        'register-student': 'Register Student',
        'register-teacher': 'Register Teacher',
        assign: 'Assign Users',
        'all-registrations': 'All Registrations'
    };
    if (title) title.textContent = map[pageName] || 'Dashboard';

    if (pageName === 'dashboard') await loadDashboard();
    if (pageName === 'assign') await loadAssignData();
    if (pageName === 'all-registrations') {
        await loadStudentRecords();
        await loadTeacherRecords();
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch (_) { throw new Error('Invalid JSON from server'); }
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
    try { body = JSON.parse(text); } catch (_) { throw new Error('Invalid JSON from server'); }
    if (!body || !body.success) throw new Error(body?.message || 'Request failed');
    return body.data || {};
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

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function openModal(id) {
    document.getElementById(id)?.classList.add('show');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');
}

async function loadDashboard() {
    try {
        const [counts, recent] = await Promise.all([
            apiGet(API.dashboardCounts),
            apiGet(API.recentRegistrations + '?limit=8')
        ]);

        setText('totalStudents', counts.total_students || 0);
        setText('totalTeachers', counts.total_teachers || 0);
        setText('totalAdmins', counts.total_admins || 0);
        setText('totalPending', counts.total_registrations || 0);
        setText('registrationBadge', counts.total_registrations || 0);

        renderRecentRegistrations(recent.registrations || []);
        renderRegistrationStatus(counts);
    } catch (e) {
        showFormMessage('#studentManageMessage', 'Failed to load dashboard: ' + e.message, false);
    }
}

function renderRecentRegistrations(items) {
    const box = document.getElementById('recentRegistrationsContainer');
    if (!box) return;
    box.innerHTML = '';
    if (!items.length) {
        box.innerHTML = '<div class="status-item"><span>No recent registrations</span></div>';
        return;
    }
    items.forEach((it) => {
        const row = document.createElement('div');
        row.className = 'status-item';
        row.innerHTML = `<span>${escapeHtml(it.username)} (${escapeHtml(it.role)})</span><span>${escapeHtml(it.status)}</span>`;
        box.appendChild(row);
    });
}

function renderRegistrationStatus(c) {
    const box = document.getElementById('registrationStatusContainer');
    if (!box) return;
    box.innerHTML = '';
    const lines = [
        ['Students', c.total_students || 0],
        ['Teachers', c.total_teachers || 0],
        ['Admins', c.total_admins || 0],
        ['Total', c.total_registrations || 0]
    ];
    lines.forEach((x) => {
        const row = document.createElement('div');
        row.className = 'status-item';
        row.innerHTML = `<span class="class-name">${x[0]}</span><span class="badge">${x[1]}</span>`;
        box.appendChild(row);
    });
}

async function handleStudentRegistration(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());

    try {
        const out = await apiPost(API.registerStudent, data);
        showFormMessage('[data-form-success="student"]', `Student registered. Username: ${out.username}, Password: ${out.password}`, true);
        form.reset();
    } catch (e) {
        showFormMessage('[data-form-success="student"]', 'Student registration failed: ' + e.message, false);
    }
}

async function handleTeacherRegistration(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());

    try {
        const out = await apiPost(API.registerTeacher, data);
        showFormMessage('[data-form-success="teacher"]', `Teacher registered. Username: ${out.username}, Password: ${out.password}`, true);
        form.reset();
        await loadTeacherRecords();
    } catch (e) {
        showFormMessage('[data-form-success="teacher"]', 'Teacher registration failed: ' + e.message, false);
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
            body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">No students found</td></tr>';
            return;
        }

        list.forEach((s) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.innerHTML = `
                <td>${escapeHtml(s.username)}</td>
                <td>${escapeHtml(s.full_name || '')}</td>
                <td>${escapeHtml(s.grade_level || '-')}</td>
            `;
            tr.addEventListener('click', () => openStudentProfile(s.username));
            body.appendChild(tr);
        });
    } catch (e) {
        showFormMessage('#studentManageMessage', 'Load students failed: ' + e.message, false);
    }
}

async function openStudentProfile(username) {
    try {
        const data = await apiGet(API.studentProfile + '?username=' + encodeURIComponent(username));
        const s = data.student || {};
        state.selectedStudentUsername = String(s.username || username || '');

        setInputValue('profileUsername', s.username || '');
        setInputValue('profileEmail', s.email || '');
        setInputValue('profileFname', s.fname || '');
        setInputValue('profileMname', s.mname || '');
        setInputValue('profileLname', s.lname || '');
        setInputValue('profileDob', s.date_of_birth || '');
        setInputValue('profileAge', s.age || '');
        setInputValue('profileSex', s.sex || '');
        setInputValue('profileGrade', s.grade_level || '');
        setInputValue('profileStream', s.stream || '');
        setInputValue('profileSection', '-');
        setInputValue('profileAddress', s.address || '');
        setInputValue('profileParentName', s.parent_name || '');
        setInputValue('profileParentPhone', s.parent_phone || '');

        setStudentEditable(false);
        document.getElementById('studentDetailsPanel').style.display = '';
        showFormMessage('#studentProfileMessage', '', true);
        openModal('studentProfileModal');
    } catch (e) {
        showFormMessage('#studentManageMessage', 'Open student failed: ' + e.message, false);
    }
}

function setStudentEditable(on) {
    document.querySelectorAll('.profile-editable').forEach((el) => {
        el.disabled = !on;
    });
}

function enableStudentProfileEdit() { setStudentEditable(true); }
function cancelStudentProfileEdit() { setStudentEditable(false); }

async function saveSelectedStudentProfile() {
    if (!state.selectedStudentUsername) return;
    const payload = {
        username: state.selectedStudentUsername,
        email: getInputValue('profileEmail'),
        fname: getInputValue('profileFname'),
        mname: getInputValue('profileMname'),
        lname: getInputValue('profileLname'),
        date_of_birth: getInputValue('profileDob'),
        age: Number(getInputValue('profileAge') || 0),
        sex: getInputValue('profileSex'),
        grade_level: getInputValue('profileGrade'),
        stream: getInputValue('profileStream'),
        address: getInputValue('profileAddress'),
        parent_name: getInputValue('profileParentName'),
        parent_phone: getInputValue('profileParentPhone')
    };

    try {
        await apiPost(API.updateStudentProfile, payload);
        setStudentEditable(false);
        showFormMessage('#studentProfileMessage', 'Student updated.', true);
        await loadStudentRecords();
    } catch (e) {
        showFormMessage('#studentProfileMessage', 'Update failed: ' + e.message, false);
    }
}

async function deleteSelectedStudentProfile() {
    if (!state.selectedStudentUsername) return;
    if (!confirm('Delete this student?')) return;

    try {
        await apiPost(API.deleteStudent, { username: state.selectedStudentUsername });
        closeStudentProfileModal();
        await loadStudentRecords();
        showFormMessage('#studentManageMessage', 'Student deleted.', true);
    } catch (e) {
        showFormMessage('#studentProfileMessage', 'Delete failed: ' + e.message, false);
    }
}

function closeStudentProfileModal() { closeModal('studentProfileModal'); }
function handleStudentProfileModalBackdrop(event) { if (event.target?.id === 'studentProfileModal') closeStudentProfileModal(); }

async function loadTeacherRecords() {
    try {
        const data = await apiGet(API.teacherList);
        const list = data.teachers || [];
        const body = document.getElementById('teacherRecordsTableBody');
        if (!body) return;
        body.innerHTML = '';

        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">No teachers found</td></tr>';
            return;
        }

        list.forEach((t) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.innerHTML = `
                <td>${escapeHtml(t.username || '')}</td>
                <td>${escapeHtml(t.full_name || '')}</td>
                <td>${escapeHtml(t.department || '-')}</td>
            `;
            tr.addEventListener('click', () => openTeacherProfile(t.username));
            body.appendChild(tr);
        });
    } catch (e) {
        showFormMessage('#teacherManageMessage', 'Load teachers failed: ' + e.message, false);
    }
}

async function openTeacherProfile(username) {
    try {
        const data = await apiGet(API.teacherProfile + '?username=' + encodeURIComponent(username));
        const t = data.teacher || {};
        state.selectedTeacherUsername = String(t.username || username || '');

        setInputValue('teacherProfileUsername', t.username || '');
        setInputValue('teacherProfileEmail', t.email || '');
        setInputValue('teacherProfileStatus', t.status || 'active');
        setInputValue('teacherProfileFname', t.fname || '');
        setInputValue('teacherProfileMname', t.mname || '');
        setInputValue('teacherProfileLname', t.lname || '');
        setInputValue('teacherProfileDob', t.date_of_birth || '');
        setInputValue('teacherProfileAge', t.age || '');
        setInputValue('teacherProfileSex', t.sex || '');
        setInputValue('teacherProfileDepartment', t.department || '');
        setInputValue('teacherProfileSubject', t.subject || '');
        setInputValue('teacherProfileAddress', t.address || '');
        setInputValue('teacherProfileOfficeRoom', t.office_room || '');
        setInputValue('teacherProfileOfficePhone', t.office_phone || '');

        setTeacherEditable(false);
        document.getElementById('teacherDetailsPanel').style.display = '';
        showFormMessage('#teacherProfileMessage', '', true);
        openModal('teacherProfileModal');
    } catch (e) {
        showFormMessage('#teacherManageMessage', 'Open teacher failed: ' + e.message, false);
    }
}

function setTeacherEditable(on) {
    document.querySelectorAll('.teacher-profile-editable').forEach((el) => {
        el.disabled = !on;
    });
}

function enableTeacherProfileEdit() { setTeacherEditable(true); }
function cancelTeacherProfileEdit() { setTeacherEditable(false); }

async function saveSelectedTeacherProfile() {
    if (!state.selectedTeacherUsername) return;
    const payload = {
        username: state.selectedTeacherUsername,
        email: getInputValue('teacherProfileEmail'),
        status: getInputValue('teacherProfileStatus'),
        fname: getInputValue('teacherProfileFname'),
        mname: getInputValue('teacherProfileMname'),
        lname: getInputValue('teacherProfileLname'),
        date_of_birth: getInputValue('teacherProfileDob'),
        age: Number(getInputValue('teacherProfileAge') || 0),
        sex: getInputValue('teacherProfileSex'),
        department: getInputValue('teacherProfileDepartment'),
        subject: getInputValue('teacherProfileSubject'),
        address: getInputValue('teacherProfileAddress'),
        office_room: getInputValue('teacherProfileOfficeRoom'),
        office_phone: getInputValue('teacherProfileOfficePhone')
    };

    try {
        await apiPost(API.updateTeacherProfile, payload);
        setTeacherEditable(false);
        showFormMessage('#teacherProfileMessage', 'Teacher updated.', true);
        await loadTeacherRecords();
    } catch (e) {
        showFormMessage('#teacherProfileMessage', 'Update failed: ' + e.message, false);
    }
}

function closeTeacherProfileModal() { closeModal('teacherProfileModal'); }
function handleTeacherProfileModalBackdrop(event) { if (event.target?.id === 'teacherProfileModal') closeTeacherProfileModal(); }

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
    el.className = 'form-success-message';
    if (!message) return;
    el.classList.add('show');
    el.classList.add(success ? 'success' : 'error');
}

function openMyProfile() {
    const user = state.user || {};
    setText('myProfileName', user.fullName || user.username || 'Admin');
    setText('myProfileUsername', user.username || '-');
    setText('myProfileRole', String(user.role || 'admin').toUpperCase());
    setText('myProfileEmail', user.email || '-');
    setText('myProfileSession', user.session_id || '-');
    openModal('myProfileModal');
}

function closeMyProfileModal() { closeModal('myProfileModal'); }

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

function getInputValue(id) {
    const el = document.getElementById(id);
    return el ? String(el.value ?? '').trim() : '';
}

function setInputValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}
