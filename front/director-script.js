const API = {
    users: '../backend/director/get_users.php',
    userStatus: '../backend/director/mange_user_status.php',
    createAnnouncement: '../backend/director/create%20annoucement.php',
    announcements: '../backend/director/get_annoucements.php',
    schoolSettings: '../backend/director/get_school_settings.php',
    updateSettings: '../backend/director/update_school_settings.php',
    registrationAdmins: '../backend/director/get_registration_admins.php',
    createAdmin: '../backend/director/create_registeration_admin.php',
    deleteAnnouncement: '../backend/director/delete_annoucement.php'
};

const state = {
    user: null,
    dashboard: null,
    settings: null,
    pendingStatusAction: null,
    recipientUsers: {
        student: [],
        teacher: [],
        admin: []
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'director') {
        window.location.href = 'login.html';
        return;
    }

    setupEventListeners();
    await initializeDashboard();
});

async function initializeDashboard() {
    await Promise.all([
        loadDashboardStats(),
        loadAnnouncementsList(),
        loadSchoolSettings()
    ]);
}

function setupEventListeners() {
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });

    document.getElementById('directorSendTo')?.addEventListener('change', onDirectorSendToChange);
    document.getElementById('directorRecipientSearch')?.addEventListener('input', syncDirectorRecipientSelection);

    document.querySelector('.menu-toggle')?.addEventListener('click', () => {
        document.querySelector('.sidebar')?.classList.toggle('open');
    });
    document.querySelector('.close-sidebar')?.addEventListener('click', () => {
        document.querySelector('.sidebar')?.classList.remove('open');
    });
}

function switchPage(page) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));
    document.getElementById(page)?.classList.add('active');
    document.querySelector(`[data-page="${page}"]`)?.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        'user-management': 'User Management',
        announcements: 'Announcements',
        settings: 'School Settings'
    };
    setText('pageTitle', titles[page] || 'Dashboard');

    if (page === 'user-management') {
        switchUserTab('students');
    } else if (page === 'announcements') {
        loadAnnouncementsList();
    } else if (page === 'settings') {
        loadSchoolSettings();
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const text = await res.text();
    let body = null;
    try {
        body = JSON.parse(text);
    } catch (_) {
        throw new Error('Server returned invalid JSON');
    }
    if (!res.ok || !body.success) throw new Error(body.message || 'Request failed');
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
    try {
        body = JSON.parse(text);
    } catch (_) {
        throw new Error('Server returned invalid JSON');
    }
    if (!res.ok || !body.success) throw new Error(body.message || 'Request failed');
    return body.data || {};
}

async function apiFormPost(url, formData) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        body: formData
    });
    const text = await res.text();
    let body = null;
    try {
        body = JSON.parse(text);
    } catch (_) {
        throw new Error('Server returned invalid JSON');
    }
    if (!res.ok || !body.success) throw new Error(body.message || 'Request failed');
    return body.data || {};
}

async function loadDashboardStats() {
    try {
        const [allUsers, students, teachers, admins, announcements] = await Promise.all([
            apiGet(`${API.users}?page=1&limit=10000`),
            apiGet(`${API.users}?role=student&page=1&limit=1`),
            apiGet(`${API.users}?role=teacher&page=1&limit=1`),
            apiGet(`${API.users}?role=admin&page=1&limit=1`),
            apiGet(`${API.announcements}?page=1&limit=1`)
        ]);
        state.dashboard = allUsers;

        setText('totalStudents', students.pagination?.total_records ?? 0);
        setText('totalTeachers', teachers.pagination?.total_records ?? 0);
        setText('dashPendingCount', allUsers.pagination?.total_records ?? 0);
        setText('totalApproved', (allUsers.users || []).filter((u) => String(u.status || '').toLowerCase() === 'active').length);
        setText('totalAdmins', admins.pagination?.total_records ?? 0);
        setText('totalAnnouncements', announcements.pagination?.total_records ?? 0);
    } catch (err) {
        showPageMessage('Failed to load dashboard: ' + err.message, 'error', true);
    }
}

// Registration approval flow removed.

function closeModal() {
    document.getElementById('detailsModal')?.classList.remove('active');
}

function switchUserTab(tab) {
    document.querySelectorAll('.user-content').forEach((x) => x.classList.remove('active'));
    document.querySelectorAll('.user-tab').forEach((x) => x.classList.remove('active'));

    if (tab === 'students') {
        document.getElementById('students-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[0]?.classList.add('active');
        loadStudentsTable();
    } else if (tab === 'teachers') {
        document.getElementById('teachers-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[1]?.classList.add('active');
        loadTeachersTable();
    } else {
        document.getElementById('admins-tab')?.classList.add('active');
        document.querySelectorAll('.user-tab')[2]?.classList.add('active');
        loadAdminsTable();
    }
}

async function loadStudentsTable() {
    const table = document.getElementById('studentsTable');
    if (!table) return;
    table.innerHTML = '';
    try {
        const data = await apiGet(`${API.users}?role=student&page=1&limit=500`);
        (data.users || []).forEach((u) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.full_name || '-')}</td>
                <td>${escapeHtml(u.email || '-')}</td>
                <td>${escapeHtml(u.grade_level || '-')}</td>
                <td>-</td>
                <td>${renderStatusBadge(u.status)}</td>
                <td>${renderUserActionButton(u.username, u.status)}</td>
            `;
            table.appendChild(tr);
        });
        if (!(data.users || []).length) {
            table.innerHTML = '<tr><td colspan="7" style="text-align:center;">No students</td></tr>';
        }
    } catch (err) {
        table.innerHTML = `<tr><td colspan="7">${escapeHtml(err.message)}</td></tr>`;
        showPageMessage('Failed to load students: ' + err.message, 'error');
    }
}

async function loadTeachersTable() {
    const table = document.getElementById('teachersTable');
    if (!table) return;
    table.innerHTML = '';
    try {
        const data = await apiGet(`${API.users}?role=teacher&page=1&limit=500`);
        (data.users || []).forEach((u) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.full_name || '-')}</td>
                <td>${escapeHtml(u.email || '-')}</td>
                <td>${escapeHtml(u.department || '-')}</td>
                <td>${escapeHtml(u.subject || '-')}</td>
                <td>${renderStatusBadge(u.status)}</td>
                <td>${renderUserActionButton(u.username, u.status)}</td>
            `;
            table.appendChild(tr);
        });
        if (!(data.users || []).length) {
            table.innerHTML = '<tr><td colspan="7" style="text-align:center;">No teachers</td></tr>';
        }
    } catch (err) {
        table.innerHTML = `<tr><td colspan="7">${escapeHtml(err.message)}</td></tr>`;
        showPageMessage('Failed to load teachers: ' + err.message, 'error');
    }
}

async function loadAdminsTable() {
    const table = document.getElementById('adminsTable');
    if (!table) return;
    table.innerHTML = '';
    try {
        const data = await apiGet(API.registrationAdmins);
        (data.admins || []).forEach((a) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(a.username)}</td>
                <td>${escapeHtml(a.full_name || '-')}</td>
                <td>${escapeHtml(a.email || '-')}</td>
                <td>Registration</td>
                <td>${escapeHtml(formatDate(a.created_at))}</td>
                <td>${renderStatusBadge(a.status)}</td>
                <td>${renderUserActionButton(a.username, a.status)}</td>
            `;
            table.appendChild(tr);
        });
        if (!(data.admins || []).length) {
            table.innerHTML = '<tr><td colspan="7" style="text-align:center;">No admins</td></tr>';
        }
    } catch (err) {
        table.innerHTML = `<tr><td colspan="7">${escapeHtml(err.message)}</td></tr>`;
        showPageMessage('Failed to load admins: ' + err.message, 'error');
    }
}

async function toggleUserStatus(username, action) {
    try {
        await apiPost(API.userStatus, { username, action });
        await Promise.all([loadStudentsTable(), loadTeachersTable(), loadAdminsTable(), loadDashboardStats()]);
        showPageMessage(`User ${username} is now ${action === 'activate' ? 'active' : 'inactive'}`, 'success');
    } catch (err) {
        showPageMessage('Failed to update user status: ' + err.message, 'error', true);
    }
}

function requestToggleUserStatus(username, action) {
    state.pendingStatusAction = { username, action };
    const text = document.getElementById('statusConfirmText');
    if (text) {
        const verb = action === 'activate' ? 'activate' : 'deactivate';
        text.textContent = `Are you sure you want to ${verb} user ${username}?`;
    }
    document.getElementById('statusConfirmModal')?.classList.add('active');
}

function closeStatusConfirmModal() {
    state.pendingStatusAction = null;
    document.getElementById('statusConfirmModal')?.classList.remove('active');
}

async function confirmStatusChange() {
    if (!state.pendingStatusAction) return;
    const { username, action } = state.pendingStatusAction;
    closeStatusConfirmModal();
    await toggleUserStatus(username, action);
}

async function handleCreateAdmin(event) {
    event.preventDefault();
    const form = event.target;
    const fd = new FormData(form);
    const fullName = String(fd.get('adminName') || '').trim();
    const parts = fullName.split(/\s+/);
    const fname = parts.shift() || '';
    const lname = parts.join(' ') || fname;
    const payload = {
        fname,
        mname: '',
        lname,
        email: String(fd.get('adminEmail') || '').trim(),
        role: 'admin'
    };
    try {
        const data = await apiPost(API.createAdmin, payload);
        showPageMessage(`Admin created. Username: ${data.username}. Temp password: ${data.temporary_password}`, 'success', true);
        form.reset();
        await Promise.all([loadAdminsTable(), loadDashboardStats()]);
    } catch (err) {
        showPageMessage('Failed to create admin: ' + err.message, 'error', true);
    }
}

async function handleCreateAnnouncement(event) {
    event.preventDefault();
    const form = event.target;
    const fd = new FormData(form);
    const priorityMap = { normal: 'Normal', high: 'High', urgent: 'Urgent' };
    const sendTo = String(fd.get('sendTo') || '').trim();
    const isIndividual = ['individual_student', 'individual_teacher', 'individual_admin'].includes(sendTo);
    if (isIndividual && !String(fd.get('targetUsername') || '').trim()) {
        showPageMessage('Please search and select a valid individual recipient.', 'error', true);
        return;
    }
    const payload = new FormData();
    payload.append('title', String(fd.get('title') || '').trim());
    payload.append('message', String(fd.get('message') || '').trim());
    payload.append('audience', sendTo);
    payload.append('priority', priorityMap[String(fd.get('priority') || 'normal').toLowerCase()] || 'Normal');
    payload.append('target_username', String(fd.get('targetUsername') || '').trim());
    const fileInput = document.getElementById('directorAnnouncementFile');
    if (fileInput?.files?.[0]) {
        payload.append('attachment', fileInput.files[0]);
    }
    try {
        await apiFormPost(API.createAnnouncement, payload);
        showPageMessage('Announcement sent successfully', 'success');
        form.reset();
        resetDirectorAnnouncementFile();
        onDirectorSendToChange();
        await Promise.all([loadAnnouncementsList(), loadDashboardStats()]);
    } catch (err) {
        showPageMessage('Failed to send announcement: ' + err.message, 'error', true);
    }
}

async function loadAnnouncementsList() {
    const list = document.getElementById('announcementsList');
    if (!list) return;
    list.innerHTML = '';
    try {
        const data = await apiGet(`${API.announcements}?page=1&limit=20`);
        (data.announcements || []).forEach((a) => {
            const item = document.createElement('div');
            item.className = 'announcement-item';
            const attachmentHtml = a.attachment_path
                ? `<div style="margin-top:10px;"><a class="btn btn-secondary btn-small" href="../backend/${encodeURI(String(a.attachment_path).replace(/^\/+/, ''))}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ${escapeHtml(a.attachment_name || 'Attachment')}</a></div>`
                : '';
            const targetLabel = escapeHtml(a.target_label || a.audience || '-');
            item.innerHTML = `
                <div class="announcement-header">
                    <div>
                        <div class="announcement-title-row">
                            <button type="button" class="announcement-toggle" onclick="toggleDirectorAnnouncement(${Number(a.id || 0)})">
                                <span class="announcement-title">${escapeHtml(a.title || '-')}</span>
                                <i class="fas fa-chevron-down" id="announcementChevron_${Number(a.id || 0)}"></i>
                            </button>
                            <button type="button" class="announcement-delete" onclick="deleteDirectorAnnouncement(${Number(a.id || 0)})" title="Delete announcement">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div style="font-size:12px;color:#6b7280;margin-top:4px;">
                            To: <strong>${targetLabel}</strong> | Priority: <strong>${escapeHtml(a.priority || '-')}</strong>
                        </div>
                    </div>
                    <div class="announcement-time">${escapeHtml(formatDate(a.created_at))}</div>
                </div>
                <div class="announcement-message" id="announcementBody_${Number(a.id || 0)}" style="display:none;">
                    <div>${escapeHtml(a.message || '')}</div>
                    ${attachmentHtml}
                </div>
            `;
            list.appendChild(item);
        });
        if (!(data.announcements || []).length) {
            list.innerHTML = '<p style="text-align:center;color:#6b7280;">No announcements yet</p>';
        }
    } catch (err) {
        list.innerHTML = `<p>${escapeHtml(err.message)}</p>`;
        showPageMessage('Failed to load announcements: ' + err.message, 'error');
    }
}

async function loadDirectorRecipients(role) {
    const normalized = String(role || '').trim();
    if (!normalized || (state.recipientUsers[normalized] || []).length) {
        return state.recipientUsers[normalized] || [];
    }
    const data = await apiGet(`${API.users}?role=${encodeURIComponent(normalized)}&page=1&limit=500`);
    state.recipientUsers[normalized] = data.users || [];
    return state.recipientUsers[normalized];
}

async function onDirectorSendToChange() {
    const sendTo = String(document.getElementById('directorSendTo')?.value || '').trim();
    const group = document.getElementById('directorRecipientGroup');
    const hiddenInput = document.getElementById('directorRecipientSelect');
    const searchInput = document.getElementById('directorRecipientSearch');
    const datalist = document.getElementById('directorRecipientOptions');
    if (!group || !hiddenInput || !searchInput || !datalist) return;

    const individualRoleMap = {
        individual_student: 'student',
        individual_teacher: 'teacher',
        individual_admin: 'admin'
    };
    const role = individualRoleMap[sendTo] || '';
    if (!role) {
        group.style.display = 'none';
        hiddenInput.value = '';
        searchInput.value = '';
        datalist.innerHTML = '';
        return;
    }

    group.style.display = '';
    hiddenInput.value = '';
    searchInput.value = '';
    datalist.innerHTML = '';
    try {
        const users = await loadDirectorRecipients(role);
        datalist.innerHTML = '';
        users.forEach((user) => {
            const option = document.createElement('option');
            option.value = `${String(user.full_name || user.username || '')} (${String(user.username || '')})`;
            option.dataset.username = String(user.username || '');
            datalist.appendChild(option);
        });
    } catch (err) {
        datalist.innerHTML = '';
        showPageMessage('Failed to load recipient list: ' + err.message, 'error');
    }
}

function syncDirectorRecipientSelection() {
    const sendTo = String(document.getElementById('directorSendTo')?.value || '').trim();
    const hiddenInput = document.getElementById('directorRecipientSelect');
    const searchInput = document.getElementById('directorRecipientSearch');
    if (!hiddenInput || !searchInput) return;

    const individualRoleMap = {
        individual_student: 'student',
        individual_teacher: 'teacher',
        individual_admin: 'admin'
    };
    const role = individualRoleMap[sendTo] || '';
    if (!role) {
        hiddenInput.value = '';
        return;
    }

    const typed = String(searchInput.value || '').trim();
    const users = state.recipientUsers[role] || [];
    const matched = users.find((user) => `${String(user.full_name || user.username || '')} (${String(user.username || '')})` === typed);
    hiddenInput.value = matched ? String(matched.username || '') : '';
}

function openDirectorAnnouncementFilePicker() {
    document.getElementById('directorAnnouncementFile')?.click();
}

function onDirectorAnnouncementFileSelected(event) {
    const file = event?.target?.files?.[0];
    const label = document.getElementById('directorAnnouncementFileName');
    if (!label) return;
    label.textContent = file ? file.name : 'No file selected';
}

function resetDirectorAnnouncementFile() {
    const input = document.getElementById('directorAnnouncementFile');
    const label = document.getElementById('directorAnnouncementFileName');
    if (input) input.value = '';
    if (label) label.textContent = 'No file selected';
}

function toggleDirectorAnnouncement(id) {
    const body = document.getElementById(`announcementBody_${id}`);
    const chevron = document.getElementById(`announcementChevron_${id}`);
    if (!body || !chevron) return;
    const willShow = body.style.display === 'none';
    body.style.display = willShow ? 'block' : 'none';
    chevron.classList.toggle('fa-chevron-down', !willShow);
    chevron.classList.toggle('fa-chevron-up', willShow);
}

async function deleteDirectorAnnouncement(id) {
    if (!id) return;
    try {
        await apiPost(API.deleteAnnouncement, { id });
        showPageMessage('Announcement deleted', 'success');
        await Promise.all([loadAnnouncementsList(), loadDashboardStats()]);
    } catch (err) {
        showPageMessage('Failed to delete announcement: ' + err.message, 'error', true);
    }
}


async function loadSchoolSettings() {
    try {
        const data = await apiGet(API.schoolSettings);
        state.settings = data.settings || null;
        if (!state.settings) return;
        const settingsForm = document.querySelector('#settings form[onsubmit*="handleSaveSettings"]');
        const calForm = document.querySelector('#settings form[onsubmit*="handleSaveAcademicCalendar"]');
        if (settingsForm) {
            settingsForm.elements.schoolName.value = state.settings.school_name || '';
            settingsForm.elements.schoolEmail.value = state.settings.email || '';
            settingsForm.elements.schoolPhone.value = state.settings.phone || '';
            settingsForm.elements.schoolAddress.value = state.settings.address || '';
        }
        if (calForm) {
            calForm.elements.academicYear.value = state.settings.current_academic_year || '';
            calForm.elements.openingDate.value = state.settings.school_opening_date || '';
            calForm.elements.term1End.value = state.settings.term1_end_date || '';
            calForm.elements.closingDate.value = state.settings.school_closing_date || '';
        }
    } catch (err) {
        showPageMessage('Failed to load school settings: ' + err.message, 'error');
    }
}

async function handleSaveSettings(event) {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = {
        school_name: String(fd.get('schoolName') || ''),
        email: String(fd.get('schoolEmail') || ''),
        phone: String(fd.get('schoolPhone') || ''),
        address: String(fd.get('schoolAddress') || ''),
        current_year: state.settings?.current_academic_year || '',
        opening_date: state.settings?.school_opening_date || '',
        closing_date: state.settings?.school_closing_date || '',
        term1_start: state.settings?.term1_start_date || '',
        term1_end: state.settings?.term1_end_date || '',
        term2_start: state.settings?.term2_start_date || '',
        term2_end: state.settings?.term2_end_date || ''
    };
    try {
        await apiPost(API.updateSettings, payload);
        showPageMessage('School information updated', 'success');
        await loadSchoolSettings();
    } catch (err) {
        showPageMessage('Failed to update school information: ' + err.message, 'error', true);
    }
}

async function handleSaveAcademicCalendar(event) {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = {
        school_name: state.settings?.school_name || '',
        email: state.settings?.email || '',
        phone: state.settings?.phone || '',
        address: state.settings?.address || '',
        current_year: String(fd.get('academicYear') || ''),
        opening_date: String(fd.get('openingDate') || ''),
        closing_date: String(fd.get('closingDate') || ''),
        term1_start: state.settings?.term1_start_date || '',
        term1_end: String(fd.get('term1End') || ''),
        term2_start: state.settings?.term2_start_date || '',
        term2_end: state.settings?.term2_end_date || ''
    };
    try {
        await apiPost(API.updateSettings, payload);
        showPageMessage('Academic calendar updated', 'success');
        await loadSchoolSettings();
    } catch (err) {
        showPageMessage('Failed to update academic calendar: ' + err.message, 'error', true);
    }
}


function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'login.html';
}

function getCurrentUser() {
    const raw = localStorage.getItem('currentUser');
    if (!raw) return null;
    try {
        return JSON.parse(raw);
    } catch (_) {
        return null;
    }
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value);
}

function formatDate(v) {
    if (!v) return '-';
    const d = new Date(v);
    return isNaN(d.getTime()) ? String(v) : d.toLocaleString();
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

function renderStatusBadge(status) {
    const normalized = String(status || '').toLowerCase();
    const cls = normalized === 'active'
        ? 'approved'
        : normalized === 'inactive'
            ? 'rejected'
            : 'pending';
    return `<span class="status-badge ${cls}">${escapeHtml(status || '-')}</span>`;
}

function renderUserActionButton(username, status) {
    const isActive = String(status || '').toLowerCase() === 'active';
    const action = isActive ? 'deactivate' : 'activate';
    const btnClass = isActive ? 'btn-danger' : 'btn-success';
    const icon = isActive ? 'fa-ban' : 'fa-check';
    const label = isActive ? 'Deactivate' : 'Activate';
    return `<button class="btn btn-small ${btnClass}" onclick="requestToggleUserStatus('${escapeJs(username)}','${action}')"><i class="fas ${icon}"></i> ${label}</button>`;
}

function showPageMessage(message, type = 'info', persistent = false) {
    const box = document.getElementById('directorStatusMessage');
    if (!box) return;
    box.textContent = String(message || '');
    box.className = `status-message show ${type}`;
    if (!persistent) {
        clearTimeout(showPageMessage._timer);
        showPageMessage._timer = setTimeout(() => {
            box.className = 'status-message';
            box.textContent = '';
        }, 4500);
    }
}
