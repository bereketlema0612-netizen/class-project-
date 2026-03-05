const API = {
    dashboard: '../backend/director/get_dashboard_overview.php',
    users: '../backend/director/get_users.php',
    userStatus: '../backend/director/mange_user_status.php',
    createAnnouncement: '../backend/director/create%20annoucement.php',
    announcements: '../backend/director/get_annoucements.php',
    reports: '../backend/director/generate_report.php',
    schoolSettings: '../backend/director/get_school_settings.php',
    updateSettings: '../backend/director/update_school_settings.php',
    auditLogs: '../backend/director/get_audit_logs.php',
    registrationAdmins: '../backend/director/get_registration_admins.php',
    createAdmin: '../backend/director/create_registeration_admin.php'
};

const state = {
    user: null,
    dashboard: null,
    settings: null,
    pendingStatusAction: null
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
        loadAuditLogs(),
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

    document.getElementById('logFilter')?.addEventListener('change', () => loadAuditLogs());

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
        'system-overview': 'System Overview',
        'user-management': 'User Management',
        announcements: 'Announcements',
        reports: 'Reports & Analytics',
        settings: 'School Settings',
        'audit-logs': 'Audit & Logs'
    };
    setText('pageTitle', titles[page] || 'Dashboard');

    if (page === 'system-overview') {
        loadSystemOverview();
    } else if (page === 'user-management') {
        switchUserTab('students');
    } else if (page === 'announcements') {
        loadAnnouncementsList();
    } else if (page === 'audit-logs') {
        loadAuditLogs();
    } else if (page === 'reports') {
        loadReportQuickStats();
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

async function loadDashboardStats() {
    try {
        const data = await apiGet(API.dashboard);
        state.dashboard = data;

        setText('totalStudents', data.statistics?.total_students ?? 0);
        setText('totalTeachers', data.statistics?.total_teachers ?? 0);
        setText('dashPendingCount', data.statistics?.total_registrations ?? 0);
        setText('totalApproved', data.statistics?.active_users ?? 0);
        setText('totalAdmins', data.statistics?.active_admins ?? 0);
        setText('totalAnnouncements', data.statistics?.active_announcements ?? 0);

        loadSystemOverview();
    } catch (err) {
        showPageMessage('Failed to load dashboard: ' + err.message, 'error', true);
    }
}

function loadSystemOverview() {
    const d = state.dashboard;
    if (!d) return;
    setText('pending-count', d.status_breakdown?.inactive ?? 0);
    setText('approved-count', d.status_breakdown?.active ?? 0);
    setText('rejected-count', d.status_breakdown?.pending ?? 0);

    const gradeMap = {};
    (d.grade_distribution || []).forEach((g) => {
        gradeMap[String(g.grade_level)] = g.count;
    });
    setText('grade9-count', gradeMap['9'] ?? gradeMap['Grade 9'] ?? 0);
    setText('grade10-count', gradeMap['10'] ?? gradeMap['Grade 10'] ?? 0);
    setText('grade11-count', gradeMap['11'] ?? gradeMap['Grade 11'] ?? 0);
    setText('grade12-count', gradeMap['12'] ?? gradeMap['Grade 12'] ?? 0);
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
    const payload = {
        title: String(fd.get('title') || '').trim(),
        message: String(fd.get('message') || '').trim(),
        audience: String(fd.get('sendTo') || '').trim(),
        priority: priorityMap[String(fd.get('priority') || 'normal').toLowerCase()] || 'Normal'
    };
    try {
        await apiPost(API.createAnnouncement, payload);
        showPageMessage('Announcement sent successfully', 'success');
        form.reset();
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
            item.innerHTML = `
                <div class="announcement-header">
                    <div>
                        <div class="announcement-title">${escapeHtml(a.title || '-')}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:4px;">
                            To: <strong>${escapeHtml(a.audience || '-')}</strong> | Priority: <strong>${escapeHtml(a.priority || '-')}</strong>
                        </div>
                    </div>
                    <div class="announcement-time">${escapeHtml(formatDate(a.created_at))}</div>
                </div>
                <div class="announcement-message">${escapeHtml(a.message || '')}</div>
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

function mapReportType(v) {
    if (v === 'enrollment') return 'enrollment';
    if (v === 'by-grade') return 'grades';
    if (v === 'certifications') return 'certifications';
    if (v === 'promotions') return 'promotions';
    if (v === 'by-department') return 'grades';
    return 'registrations';
}

async function handleGenerateReport(event) {
    event.preventDefault();
    const form = event.target;
    const fd = new FormData(form);
    const type = mapReportType(String(fd.get('reportType') || 'enrollment'));
    const range = String(fd.get('dateRange') || 'all');
    try {
        const data = await apiGet(`${API.reports}?type=${encodeURIComponent(type)}&range=${encodeURIComponent(range)}`);
        showPageMessage(`Report generated: ${data.report_type} (${formatDate(data.generated_at)})`, 'success');
        loadReportQuickStats();
    } catch (err) {
        showPageMessage('Failed to generate report: ' + err.message, 'error', true);
    }
}

function loadReportQuickStats() {
    const d = state.dashboard?.statistics || {};
    setText('report-total-registrations', d.total_registrations ?? 0);
    setText('report-approved-month', d.active_users ?? 0);
    setText('report-certificates', '-');
    setText('report-promotions', '-');
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

async function loadAuditLogs() {
    const table = document.getElementById('auditLogsTable');
    if (!table) return;
    table.innerHTML = '';
    const filter = document.getElementById('logFilter')?.value || 'all';
    const actionMap = {
        approvals: 'APPROVAL',
        rejections: 'REJECTION',
        'user-actions': '',
        'admin-actions': ''
    };
    const action = actionMap[filter] ?? '';

    try {
        const url = action ? `${API.auditLogs}?page=1&limit=50&action=${encodeURIComponent(action)}` : `${API.auditLogs}?page=1&limit=50`;
        const data = await apiGet(url);
        let logs = data.logs || [];
        if (filter === 'user-actions') {
            logs = logs.filter((l) => ['APPROVAL', 'REJECTION', 'USER_ACTIVATE', 'USER_DEACTIVATE'].includes(l.action));
        } else if (filter === 'admin-actions') {
            logs = logs.filter((l) => l.action.startsWith('CREATE_') || l.action.includes('SETTINGS') || l.action.includes('REPORT'));
        }

        logs.forEach((l) => {
            const tr = document.createElement('tr');
            const fullName = [l.fname, l.lname].filter(Boolean).join(' ') || l.username || '-';
            tr.innerHTML = `
                <td>${escapeHtml(formatDate(l.timestamp))}</td>
                <td>${escapeHtml(fullName)}</td>
                <td>${escapeHtml(l.action)}</td>
                <td>${escapeHtml(l.description)}</td>
                <td><span class="status-badge ${l.status === 'success' ? 'approved' : 'pending'}">${escapeHtml(l.status)}</span></td>
            `;
            table.appendChild(tr);
        });
        if (!logs.length) {
            table.innerHTML = '<tr><td colspan="5" style="text-align:center;">No logs found</td></tr>';
        }
    } catch (err) {
        table.innerHTML = `<tr><td colspan="5">${escapeHtml(err.message)}</td></tr>`;
        showPageMessage('Failed to load audit logs: ' + err.message, 'error');
    }
}

function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'admin_login.html';
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
