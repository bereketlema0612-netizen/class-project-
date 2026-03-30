// Simple Director Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php',
    createAnnouncement: APP_BASE + '/backend/director/create_announcement.php',
    getAnnouncements: APP_BASE + '/backend/director/get_announcements.php',
    dashboardStats: APP_BASE + '/backend/director/get_dashboard_stats.php',
    getUsers: APP_BASE + '/backend/director/get_users.php',
    createAdmin: APP_BASE + '/backend/director/create_registration_admin.php',
    updateUserStatus: APP_BASE + '/backend/director/update_user_status.php',
    getSchoolSettings: APP_BASE + '/backend/director/get_school_settings.php',
    saveSchoolSettings: APP_BASE + '/backend/director/save_school_settings.php'
};

const state = {
    user: null,
    announcements: [],
    students: [],
    teachers: [],
    admins: []
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

    const sendTo = document.getElementById('directorSendTo');
    if (sendTo) sendTo.addEventListener('change', updateDirectorRecipientVisibility);

    const recipientSearch = document.getElementById('directorRecipientSearch');
    if (recipientSearch) {
        recipientSearch.addEventListener('input', () => {
            const hidden = document.getElementById('directorRecipientSelect');
            if (hidden) hidden.value = recipientSearch.value.trim();
        });
    }

    renderDirectorProfile();
    updateDirectorRecipientVisibility();
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
        settings: 'School Settings',
        profile: 'Profile'
    };
    setText('pageTitle', titleMap[page] || 'Dashboard');

    if (page === 'dashboard') loadDirectorDashboard();
    if (page === 'user-management') loadDirectorUsers();
    if (page === 'announcements') loadDirectorAnnouncements();
    if (page === 'settings') loadSchoolSettings();
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

async function loadDirectorDashboard() {
    try {
        const d = await apiGet(API.dashboardStats);
        setText('totalStudents', d.total_students || 0);
        setText('totalTeachers', d.total_teachers || 0);
        setText('dashPendingCount', d.total_registrations || 0);
        setText('totalApproved', d.total_approved || 0);
        setText('totalAdmins', d.total_admins || 0);
        setText('totalAnnouncements', d.total_announcements || 0);
    } catch (e) {
        showStatusMessage('Failed to load dashboard: ' + e.message, 'error');
    }
}

async function loadDirectorUsers() {
    try {
        const [students, teachers, admins] = await Promise.all([
            apiGet(API.getUsers + '?role=students'),
            apiGet(API.getUsers + '?role=teachers'),
            apiGet(API.getUsers + '?role=admins')
        ]);
        state.students = students.users || [];
        state.teachers = teachers.users || [];
        state.admins = admins.users || [];
        renderStudentsTable();
        renderTeachersTable();
        renderAdminsTable();
    } catch (e) {
        showStatusMessage('Failed to load users: ' + e.message, 'error');
    }
}

function renderStudentsTable() {
    const body = document.getElementById('studentsTable');
    if (!body) return;
    body.innerHTML = '';
    if (!state.students.length) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#64748b;">No students found</td></tr>';
        return;
    }
    state.students.forEach((u) => {
        const next = u.status === 'active' ? 'inactive' : 'active';
        const btnLabel = next === 'active' ? 'Activate' : 'Deactivate';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(u.username || '')}</td>
            <td>${escapeHtml(u.name || '')}</td>
            <td>${escapeHtml(u.email || '')}</td>
            <td>${escapeHtml(u.grade_level || '-')}</td>
            <td>${escapeHtml(u.section || '-')}</td>
            <td><span class="status-badge ${escapeHtml(u.status || 'active')}">${escapeHtml(u.status || 'active')}</span></td>
            <td><button class="btn btn-secondary" onclick="toggleUserStatus('${escapeJs(u.username || '')}', '${escapeJs(next)}')">${btnLabel}</button></td>
        `;
        body.appendChild(tr);
    });
}

function renderTeachersTable() {
    const body = document.getElementById('teachersTable');
    if (!body) return;
    body.innerHTML = '';
    if (!state.teachers.length) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#64748b;">No teachers found</td></tr>';
        return;
    }
    state.teachers.forEach((u) => {
        const next = u.status === 'active' ? 'inactive' : 'active';
        const btnLabel = next === 'active' ? 'Activate' : 'Deactivate';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(u.username || '')}</td>
            <td>${escapeHtml(u.name || '')}</td>
            <td>${escapeHtml(u.email || '')}</td>
            <td>${escapeHtml(u.department || '-')}</td>
            <td>${escapeHtml(u.subject || '-')}</td>
            <td><span class="status-badge ${escapeHtml(u.status || 'active')}">${escapeHtml(u.status || 'active')}</span></td>
            <td><button class="btn btn-secondary" onclick="toggleUserStatus('${escapeJs(u.username || '')}', '${escapeJs(next)}')">${btnLabel}</button></td>
        `;
        body.appendChild(tr);
    });
}

function renderAdminsTable() {
    const body = document.getElementById('adminsTable');
    if (!body) return;
    body.innerHTML = '';
    if (!state.admins.length) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#64748b;">No registration admins found</td></tr>';
        return;
    }
    state.admins.forEach((u) => {
        const next = u.status === 'active' ? 'inactive' : 'active';
        const btnLabel = next === 'active' ? 'Activate' : 'Deactivate';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(u.username || '')}</td>
            <td>${escapeHtml(u.name || '')}</td>
            <td>${escapeHtml(u.email || '')}</td>
            <td>${escapeHtml(u.department || '-')}</td>
            <td>${escapeHtml((u.created_at || '').split(' ')[0] || '-')}</td>
            <td><span class="status-badge ${escapeHtml(u.status || 'active')}">${escapeHtml(u.status || 'active')}</span></td>
            <td><button class="btn btn-secondary" onclick="toggleUserStatus('${escapeJs(u.username || '')}', '${escapeJs(next)}')">${btnLabel}</button></td>
        `;
        body.appendChild(tr);
    });
}

async function handleCreateAdmin(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    try {
        const out = await apiPost(API.createAdmin, data);
        form.reset();
        showStatusMessage(`Admin created. Username: ${out.username}, Password: ${out.password}`, 'success');
        await loadDirectorUsers();
        await loadDirectorDashboard();
    } catch (e) {
        showStatusMessage('Create admin failed: ' + e.message, 'error');
    }
}

async function toggleUserStatus(username, status) {
    try {
        await apiPost(API.updateUserStatus, { username, status });
        showStatusMessage('User status updated.', 'success');
        await loadDirectorUsers();
        await loadDirectorDashboard();
    } catch (e) {
        showStatusMessage('Status update failed: ' + e.message, 'error');
    }
}

async function handleCreateAnnouncement(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const title = String(formData.get('title') || '').trim();
    const message = String(formData.get('message') || '').trim();
    const sendTo = String(formData.get('sendTo') || '').trim();
    const priority = String(formData.get('priority') || 'normal').trim();
    const targetUsername = String(document.getElementById('directorRecipientSelect')?.value || '').trim();

    if (!title || !message) {
        showAnnouncementMessage('Please enter title and message.', false);
        return;
    }
    if (!sendTo) {
        showAnnouncementMessage('Please choose who to send to.', false);
        return;
    }
    if (sendTo.startsWith('individual_') && !targetUsername) {
        showAnnouncementMessage('Please type/select a target username.', false);
        return;
    }

    try {
        await apiPost(API.createAnnouncement, {
            title,
            message,
            send_to: sendTo,
            target_username: targetUsername,
            priority
        });

        form.reset();
        const fileName = document.getElementById('directorAnnouncementFileName');
        if (fileName) fileName.textContent = 'No file selected';
        const hidden = document.getElementById('directorRecipientSelect');
        if (hidden) hidden.value = '';
        updateDirectorRecipientVisibility();

        showAnnouncementMessage('Announcement sent successfully.', true);
        await loadDirectorAnnouncements();
        await loadDirectorDashboard();
    } catch (e) {
        showAnnouncementMessage('Failed to send announcement: ' + e.message, false);
    }
}

function openDirectorAnnouncementFilePicker() {
    document.getElementById('directorAnnouncementFile')?.click();
}

function onDirectorAnnouncementFileSelected(event) {
    const file = event?.target?.files?.[0];
    const name = document.getElementById('directorAnnouncementFileName');
    if (name) name.textContent = file ? file.name : 'No file selected';
}

function updateDirectorRecipientVisibility() {
    const sendTo = String(document.getElementById('directorSendTo')?.value || '');
    const group = document.getElementById('directorRecipientGroup');
    if (!group) return;
    const isIndividual = sendTo.startsWith('individual_');
    group.style.display = isIndividual ? '' : 'none';
    if (isIndividual) {
        const needsUsers = !state.students.length && !state.teachers.length && !state.admins.length;
        if (needsUsers) {
            loadDirectorUsers().then(() => loadRecipientOptions(sendTo)).catch(() => loadRecipientOptions(sendTo));
        } else {
            loadRecipientOptions(sendTo);
        }
    }
}

function loadRecipientOptions(sendTo) {
    const datalist = document.getElementById('directorRecipientOptions');
    if (!datalist) return;
    datalist.innerHTML = '';

    let source = [];
    if (sendTo === 'individual_student') source = state.students;
    if (sendTo === 'individual_teacher') source = state.teachers;
    if (sendTo === 'individual_admin') source = state.admins;

    source.forEach((u) => {
        const option = document.createElement('option');
        option.value = u.username || '';
        option.label = `${u.username || ''} - ${u.name || ''}`;
        datalist.appendChild(option);
    });
}

async function loadDirectorAnnouncements() {
    try {
        const data = await apiGet(API.getAnnouncements + '?limit=50');
        state.announcements = data.announcements || [];
        renderDirectorAnnouncements();
    } catch (e) {
        showAnnouncementMessage('Failed to load announcements: ' + e.message, false);
    }
}

function renderDirectorAnnouncements() {
    const box = document.getElementById('announcementsList');
    if (!box) return;
    box.innerHTML = '';

    if (!state.announcements.length) {
        box.innerHTML = '<div class="announcement-item"><p class="announcement-message">No announcements yet.</p></div>';
        return;
    }

    state.announcements.forEach((a) => {
        const item = document.createElement('div');
        item.className = 'announcement-item';
        item.innerHTML = `
            <div class="announcement-header">
                <span class="announcement-title">${escapeHtml(a.title || 'Untitled')}</span>
                <span class="announcement-time">${escapeHtml(a.created_at || '')}</span>
            </div>
            <p class="announcement-message">${escapeHtml(a.message || '')}</p>
            <p class="announcement-meta">To: ${escapeHtml(a.send_to || 'all')} | Priority: ${escapeHtml(a.priority || 'normal')}</p>
        `;
        box.appendChild(item);
    });
}

async function loadSchoolSettings() {
    try {
        const data = await apiGet(API.getSchoolSettings);
        const s = data.settings || {};
        setInputValue('schoolName', s.school_name || '');
        setInputValue('schoolEmail', s.school_email || '');
        setInputValue('schoolPhone', s.school_phone || '');
        setInputValue('schoolAddress', s.school_address || '');
        setInputValue('academicYear', s.academic_year || '');
        setInputValue('openingDate', s.opening_date || '');
        setInputValue('term1End', s.term1_end || '');
        setInputValue('closingDate', s.closing_date || '');
    } catch (e) {
        showStatusMessage('Failed to load settings: ' + e.message, 'error');
    }
}

async function handleSaveSettings(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    try {
        await apiPost(API.saveSchoolSettings, data);
        showStatusMessage('School info saved.', 'success');
    } catch (e) {
        showStatusMessage('Save failed: ' + e.message, 'error');
    }
}

async function handleSaveAcademicCalendar(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    try {
        await apiPost(API.saveSchoolSettings, data);
        showStatusMessage('Academic calendar saved.', 'success');
    } catch (e) {
        showStatusMessage('Save failed: ' + e.message, 'error');
    }
}

function closeModal() {
    document.getElementById('detailsModal')?.classList.remove('active');
}

function closeStatusConfirmModal() {
    document.getElementById('statusConfirmModal')?.classList.remove('active');
}

function confirmStatusChange() {
    closeStatusConfirmModal();
}

function openMyProfile() {
    switchPage('profile');
}

function renderDirectorProfile() {
    const user = state.user || {};
    setText('directorProfileName', user.fullName || user.username || 'Director');
    setText('directorProfileUsername', user.username || '-');
    setText('directorProfileRole', String(user.role || 'director').toUpperCase());
    setText('directorProfileRoleText', String(user.role || 'director').toUpperCase());
    setText('directorProfileEmail', user.email || '-');
    setText('directorProfileSession', user.session_id || '-');
}

function showAnnouncementMessage(message, success) {
    const el = document.getElementById('directorAnnouncementMessage');
    if (!el) return;
    if (!message) {
        el.textContent = '';
        el.className = 'form-inline-message';
        return;
    }
    el.textContent = message;
    el.className = 'form-inline-message show ' + (success ? 'success' : 'error');
}

function showStatusMessage(message, type) {
    const el = document.getElementById('directorStatusMessage');
    if (!el) return;
    if (!message) {
        el.textContent = '';
        el.className = 'status-message';
        return;
    }
    el.textContent = message;
    el.className = 'status-message show ' + (type || 'info');
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

function setInputValue(nameOrId, value) {
    const byName = document.querySelector(`[name="${nameOrId}"]`);
    if (byName) {
        byName.value = value ?? '';
        return;
    }
    const byId = document.getElementById(nameOrId);
    if (byId) byId.value = value ?? '';
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
