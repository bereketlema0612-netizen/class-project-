// Simple Student Script (Class Project Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    logout: APP_BASE + '/backend/auth/logout.php',
    dashboard: APP_BASE + '/backend/student/get_dashboard.php',
    grades: APP_BASE + '/backend/student/get_grades.php',
    announcements: APP_BASE + '/backend/student/get_announcements.php',
    profile: APP_BASE + '/backend/student/get_profile.php'
};

const state = {
    user: null,
    dashboard: null,
    grades: [],
    announcements: [],
    profile: null
};

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'student') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });
    document.getElementById('announcementFilter')?.addEventListener('change', renderAnnouncementsPage);

    await loadStudentData();
});

async function loadStudentData() {
    try {
        const [dashboard, profile, announcements] = await Promise.all([
            apiGet(API.dashboard),
            apiGet(API.profile),
            apiGet(API.announcements)
        ]);
        state.dashboard = dashboard;
        state.profile = profile.student || null;
        state.announcements = announcements.announcements || [];

        renderDashboard();
        renderProfile();
        renderAnnouncementsPage();
        await loadGrades();
    } catch (e) {
        alert('Failed to load student data: ' + e.message);
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch (_) { throw new Error('Invalid JSON'); }
    if (!body || !body.success) throw new Error(body?.message || 'Request failed');
    return body.data || {};
}

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));
    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');

    const title = document.getElementById('pageTitle');
    if (title) {
        const map = {
            dashboard: 'Dashboard',
            grades: 'Grades',
            announcements: 'Announcements'
        };
        title.textContent = map[pageName] || 'Dashboard';
    }

    if (pageName === 'announcements') renderAnnouncementsPage();
    if (pageName === 'grades') renderGradesTable();
}

function openMyProfile() {
    switchPage('dashboard');
}

function renderDashboard() {
    const d = state.dashboard || {};
    setText('dashboardGPA', Number(d.gpa || 0).toFixed(2));
    setText('dashboardPending', d.pending_tasks || 0);
    setText('dashboardAnnouncements', d.announcements_count || 0);
    setText('announcementBadge', d.announcements_count || 0);

    const gradesBox = document.getElementById('recentGradesContainer');
    if (gradesBox) {
        const recent = d.recent_grades || [];
        gradesBox.innerHTML = '';
        if (!recent.length) {
            gradesBox.innerHTML = '<div class="grade-item"><div class="grade-info"><p class="subject">No grades yet</p></div><span class="grade-badge F">-</span></div>';
        } else {
            recent.forEach((g) => {
                const item = document.createElement('div');
                item.className = 'grade-item';
                const letter = String(g.letter_grade || 'F');
                item.innerHTML = `
                    <div class="grade-info">
                        <p class="subject">${escapeHtml(g.subject || '')}</p>
                        <p class="assessment">${escapeHtml(g.assessment || 'Assessment')}</p>
                    </div>
                    <span class="grade-badge ${escapeHtml(letter)}">${escapeHtml(letter)}</span>
                `;
                gradesBox.appendChild(item);
            });
        }
    }

    const annBox = document.getElementById('recentAnnouncementsContainer');
    if (annBox) {
        const recent = d.recent_announcements || [];
        annBox.innerHTML = '';
        if (!recent.length) {
            annBox.innerHTML = '<div class="announcement-item"><div class="announcement-header"><p class="announcement-title">No announcements</p></div></div>';
        } else {
            recent.forEach((a) => {
                const item = document.createElement('div');
                item.className = 'announcement-item';
                item.innerHTML = `
                    <div class="announcement-header">
                        <p class="announcement-title">${escapeHtml(a.title || '')}</p>
                        <p class="announcement-date">${escapeHtml(formatDate(a.created_at))}</p>
                    </div>
                    <p class="announcement-text">${escapeHtml(a.message || '')}</p>
                `;
                annBox.appendChild(item);
            });
        }
    }
}

async function loadGrades() {
    try {
        const data = await apiGet(API.grades);
        state.grades = data.grades || [];
        setText('dashboardGPA', Number(data.gpa || 0).toFixed(2));
        renderGradesTable();
    } catch (e) {
        const body = document.getElementById('gradesTableBody');
        if (body) body.innerHTML = `<tr><td colspan="4">Failed to load grades: ${escapeHtml(e.message)}</td></tr>`;
    }
}

function renderGradesTable() {
    const body = document.getElementById('gradesTableBody');
    if (!body) return;
    body.innerHTML = '';

    if (!state.grades.length) {
        body.innerHTML = '<tr><td colspan="4">No grades found.</td></tr>';
        return;
    }

    let rows = '';
    state.grades.forEach((g) => {
        rows += `
            <tr>
                <td>${escapeHtml(g.subject || '')}</td>
                <td>${escapeHtml(g.term || '')}</td>
                <td>${escapeHtml(String(g.marks ?? 0))}</td>
                <td>${escapeHtml(g.letter_grade || 'F')}</td>
            </tr>
        `;
    });
    body.innerHTML = rows;
}

function renderAnnouncementsPage() {
    const filter = (document.getElementById('announcementFilter')?.value || '').trim();
    const box = document.getElementById('announcementsContainer');
    if (!box) return;

    let list = [...state.announcements];
    if (filter === 'school') list = list.filter((a) => String(a.source || '') === 'director');
    if (filter === 'teacher') list = list.filter((a) => String(a.source || '') === 'teacher');

    const hidden = getHiddenAnnouncements();
    list = list.filter((a) => hidden.indexOf(announcementKey(a)) === -1);

    box.innerHTML = '';
    if (!list.length) {
        box.innerHTML = '<div class="card announcement-card"><p class="announcement-body">No announcements found.</p></div>';
        return;
    }

    list.forEach((a) => {
        const key = announcementKey(a);
        const card = document.createElement('div');
        card.className = 'card announcement-card';
        card.innerHTML = `
            <div class="announcement-header">
                <h3>${escapeHtml(a.title || '')}</h3>
                <p class="announcement-date">${escapeHtml(formatDate(a.created_at))}</p>
            </div>
            <p class="announcement-category">${escapeHtml((a.source || 'general').toUpperCase())}</p>
            <p class="announcement-body">${escapeHtml(a.message || '')}</p>
            <button type="button" class="announcement-close" data-hide-key="${escapeHtml(key)}">×</button>
        `;
        box.appendChild(card);
    });

    box.querySelectorAll('.announcement-close').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-hide-key') || '';
            if (!key) return;
            hideAnnouncement(key);
            renderAnnouncementsPage();
        });
    });
}

function renderProfile() {
    const p = state.profile || {};
    const fullName = [p.fname, p.mname, p.lname].filter(Boolean).join(' ') || state.user?.username || 'Student';
    setText('profileName', fullName);
    setText('profileID', p.username || state.user?.username || '-');
    setText('profileEmail', p.email || state.user?.email || '-');
    setText('profileGrade', p.grade_level ? ('Grade ' + p.grade_level) : 'Grade -');
    setText('profileDOB', p.date_of_birth || '-');
    setText('profileContact', p.parent_phone || '-');
    setText('profileAddress', p.address || '-');
    setText('gradeStudentName', fullName);
}

async function logout() {
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (_) {}
    localStorage.removeItem('currentUser');
    sessionStorage.clear();
    window.location.replace(FRONT_BASE + '/login.html');
}

function formatDate(v) {
    const s = String(v || '').trim();
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return s;
    return d.toLocaleDateString();
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

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function announcementKey(a) {
    const src = String(a.source || '');
    const title = String(a.title || '');
    const time = String(a.created_at || '');
    return src + '|' + title + '|' + time;
}

function getHiddenAnnouncements() {
    const raw = localStorage.getItem('hiddenAnnouncements') || '';
    if (!raw) return [];
    return raw.split('||').filter((x) => x !== '');
}

function hideAnnouncement(key) {
    const list = getHiddenAnnouncements();
    if (list.indexOf(key) === -1) list.push(key);
    localStorage.setItem('hiddenAnnouncements', list.join('||'));
}
