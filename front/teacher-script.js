// Teacher Script - Class Project (Simple Working Version)

const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';
const API = {
    dashboard: APP_BASE + '/backend/teacher/get_teacher_dashboard.php',
    profile: APP_BASE + '/backend/teacher/get_teacher_profile.php',
    announcements: APP_BASE + '/backend/teacher/get_announcements.php',
    createAnnouncement: APP_BASE + '/backend/teacher/create_announcement.php',
    resources: APP_BASE + '/backend/teacher/get_resources.php',
    uploadResource: APP_BASE + '/backend/teacher/upload_resource.php',
    classStudents: APP_BASE + '/backend/teacher/get_class_students.php',
    classSubjects: APP_BASE + '/backend/teacher/get_class_subjects.php',
    enterGrades: APP_BASE + '/backend/teacher/enter_grades.php',
    logout: APP_BASE + '/backend/auth/logout.php'
};

const state = {
    user: null,
    dashboard: null,
    profile: null,
    announcements: [],
    resources: [],
    classes: [],
    selectedClassIdForGrading: 0
};

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'teacher') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    const resourcesFilter = document.getElementById('classFilterResources');
    if (resourcesFilter) {
        resourcesFilter.addEventListener('change', function () {
            loadResources();
        });
    }
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });

    await initializeTeacherPage();
});

async function initializeTeacherPage() {
    try {
        const [dashboard, profile, ann] = await Promise.all([
            apiGet(API.dashboard),
            apiGet(API.profile),
            apiGet(API.announcements + '?page=1&limit=20')
        ]);

        state.dashboard = dashboard;
        state.profile = profile.teacher || null;
        state.announcements = ann.announcements || [];
        state.classes = dashboard.assigned_classes || [];

        renderBasicTeacherInfo();
        populateClassSelects();
        renderAnnouncements();
        await loadResources();
    } catch (e) {
        showPageMessage('Failed to load teacher data: ' + e.message, true);
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch (_) { throw new Error('Server returned invalid JSON: ' + text.slice(0, 120)); }
    if (!body || !body.success) {
        if (String(body?.message || '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
            throw new Error('Session expired. Please login again.');
        }
        throw new Error(body?.message || 'Request failed');
    }
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
    try { body = JSON.parse(text); } catch (_) { throw new Error('Server returned invalid JSON: ' + text.slice(0, 120)); }
    if (!body || !body.success) {
        if (String(body?.message || '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
            throw new Error('Session expired. Please login again.');
        }
        throw new Error(body?.message || 'Request failed');
    }
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
    try { body = JSON.parse(text); } catch (_) { throw new Error('Server returned invalid JSON: ' + text.slice(0, 120)); }
    if (!body || !body.success) {
        if (String(body?.message || '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
            throw new Error('Session expired. Please login again.');
        }
        throw new Error(body?.message || 'Request failed');
    }
    return body.data || {};
}

function handleUnauthorized() {
    localStorage.removeItem('currentUser');
    window.location.replace(FRONT_BASE + '/login.html');
}

function renderBasicTeacherInfo() {
    const p = state.profile || {};
    const name = [p.fname, p.lname].filter(Boolean).join(' ') || state.user.username;

    setText('profileName', name);
    setText('profileDept', p.department || 'Teacher');
    setText('profileEmpID', p.employee_id_generated || p.username || '-');
    setText('profileEmail', p.email || '-');
    setText('profilePhone', p.office_phone || '-');
    setText('profileOffice', p.office_room || '-');

    const totalClasses = Number(state.dashboard?.statistics?.total_classes || 0);
    const totalStudents = Number(state.dashboard?.statistics?.total_students || 0);
    setText('totalClasses', totalClasses);
    setText('totalStudents', totalStudents);
    setText('pendingGrading', 0);

    const status = document.getElementById('gradingStatusContainer');
    if (status) {
        status.innerHTML = '';
        if (!state.classes.length) {
            status.innerHTML = '<div class="status-item"><span class="class-name">No assigned class yet</span><span class="badge">0</span></div>';
        } else {
            state.classes.forEach((c) => {
                const item = document.createElement('div');
                item.className = 'status-item';
                item.innerHTML = `<span class="class-name">${escapeHtml(c.name || ('Class ' + c.class_id))}</span><span class="badge">Ready</span>`;
                status.appendChild(item);
            });
        }
    }
}

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');

    const titleMap = {
        dashboard: 'Dashboard',
        grading: 'Grading',
        announcements: 'Announcements',
        resources: 'Resources',
        profile: 'Profile'
    };
    setText('pageTitle', titleMap[pageName] || 'Dashboard');

    if (pageName === 'announcements') renderAnnouncements();
    if (pageName === 'resources') loadResources();
}

function openMyProfile() {
    switchPage('profile');
}

function classParts(name, classId) {
    const text = String(name || ('Class ' + classId));
    const m = text.match(/Grade\s*(\d+)\s*-\s*([A-Za-z0-9_-]+)/i);
    if (m) return { grade: m[1], section: m[2] };
    return { grade: 'General', section: String(classId || '') };
}

function populateClassSelects() {
    const classFilter = document.getElementById('classFilterResources');
    const annSingle = document.getElementById('announcementClassSelect');
    const annMulti = document.getElementById('announcementClassMultiSelect');
    const resSingle = document.getElementById('resourceClassSelect');
    const resMulti = document.getElementById('resourceClassMultiSelect');
    const gradeSelect = document.getElementById('classSelectForGrading');

    const allSelects = [classFilter, annSingle, annMulti, resSingle, resMulti];
    allSelects.forEach((sel) => {
        if (!sel) return;
        const first = sel.id.includes('Multi') ? '' : '<option value="">-- Choose --</option>';
        sel.innerHTML = first;
        state.classes.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = String(c.class_id || '');
            opt.textContent = c.name || ('Class ' + c.class_id);
            sel.appendChild(opt);
        });
    });

    if (gradeSelect) {
        gradeSelect.innerHTML = '<option value="">-- Choose a grade --</option>';
        const gradeSet = new Set();
        state.classes.forEach((c) => gradeSet.add(classParts(c.name, c.class_id).grade));
        [...gradeSet].forEach((g) => {
            const opt = document.createElement('option');
            opt.value = g;
            opt.textContent = g === 'General' ? 'General' : ('Grade ' + g);
            gradeSelect.appendChild(opt);
        });
    }
}

async function loadAnnouncements() {
    const data = await apiGet(API.announcements + '?page=1&limit=50');
    state.announcements = data.announcements || [];
    renderAnnouncements();
}

function renderAnnouncements() {
    const box = document.getElementById('announcementsContainer');
    if (!box) return;
    box.innerHTML = '';

    if (!state.announcements.length) {
        box.innerHTML = '<div class="card"><p>No announcements yet.</p></div>';
        return;
    }

    state.announcements.forEach((a) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
            <h3>${escapeHtml(a.title || 'Untitled')}</h3>
            <p>${escapeHtml(a.message || '')}</p>
            <small>${escapeHtml(a.created_at || '')}</small>
        `;
        box.appendChild(card);
    });
}

function openAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    if (!modal) return;
    modal.classList.add('show');
    modal.classList.add('active');
}
function closeAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.classList.remove('active');
}

function onAnnouncementTargetModeChange() {
    const mode = document.getElementById('announcementTargetMode')?.value || 'single';
    const s = document.getElementById('announcementSingleClassGroup');
    const m = document.getElementById('announcementMultiClassGroup');
    if (s) s.style.display = mode === 'single' ? '' : 'none';
    if (m) m.style.display = mode === 'multiple' ? '' : 'none';
}

function openAnnouncementFilePicker() { document.getElementById('announcementFile')?.click(); }
function removeAnnouncementFile() { const i = document.getElementById('announcementFile'); if (i) i.value = ''; }

async function saveAnnouncement() {
    const mode = document.getElementById('announcementTargetMode')?.value || 'single';
    const title = (document.getElementById('announcementTitle')?.value || '').trim();
    const message = (document.getElementById('announcementContent')?.value || '').trim();
    const singleClassId = Number(document.getElementById('announcementClassSelect')?.value || 0);

    if (!title || !message) {
        showPageMessage('Please enter title and content.', true);
        return;
    }

    let classIds = [];
    if (mode === 'single') {
        classIds = singleClassId > 0 ? [singleClassId] : [0];
    } else if (mode === 'multiple') {
        const sel = document.getElementById('announcementClassMultiSelect');
        classIds = sel ? [...sel.selectedOptions].map((o) => Number(o.value)).filter((v) => v > 0) : [];
    } else {
        classIds = state.classes.map((c) => Number(c.class_id)).filter((v) => v > 0);
    }

    if (!classIds.length) classIds = [0];

    try {
        for (const cid of classIds) {
            await apiPost(API.createAnnouncement, { title, message, class_id: cid });
        }
        showPageMessage('Announcement posted.', false);
        alert('Announcement posted successfully.');
        closeAnnouncementModal();
        await loadAnnouncements();
    } catch (e) {
        showPageMessage('Failed to post announcement: ' + e.message, true);
        alert('Failed to post announcement: ' + e.message);
    }
}

async function loadResources() {
    const classId = Number(document.getElementById('classFilterResources')?.value || 0);
    const url = classId > 0 ? `${API.resources}?class_id=${classId}` : API.resources;
    try {
        const data = await apiGet(url);
        state.resources = data.resources || [];
        renderResources();
    } catch (e) {
        showPageMessage('Failed to load resources: ' + e.message, true);
    }
}

function renderResources() {
    const box = document.getElementById('resourcesContainer');
    if (!box) return;
    box.innerHTML = '';
    if (!state.resources.length) {
        box.innerHTML = '<div class="card"><p>No resources yet.</p></div>';
        return;
    }

    state.resources.forEach((r) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
            <h3>${escapeHtml(r.title || 'Untitled')}</h3>
            <p>${escapeHtml(r.description || '')}</p>
            <p><small>Type: ${escapeHtml(r.type || 'resource')}</small></p>
            <p><small>Class: ${escapeHtml(String(r.class_id || '-'))}</small></p>
            ${r.file_url ? `<button class="btn btn-secondary" onclick="downloadFile('${escapeJs(r.file_url)}')">Open File</button>` : ''}
        `;
        box.appendChild(card);
    });
}

function openResourceModal() {
    const modal = document.getElementById('resourceModal');
    if (!modal) return;
    modal.classList.add('show');
    modal.classList.add('active');
}
function closeResourceModal() {
    const modal = document.getElementById('resourceModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.classList.remove('active');
}

function onResourceTargetModeChange() {
    const mode = document.getElementById('resourceTargetMode')?.value || 'single';
    const s = document.getElementById('resourceSingleClassGroup');
    const m = document.getElementById('resourceMultiClassGroup');
    if (s) s.style.display = mode === 'single' ? '' : 'none';
    if (m) m.style.display = mode === 'multiple' ? '' : 'none';
}

function openResourceFilePicker() { document.getElementById('resourceFile')?.click(); }
function removeResourceFile() { const i = document.getElementById('resourceFile'); if (i) i.value = ''; }

async function saveResource() {
    const mode = document.getElementById('resourceTargetMode')?.value || 'single';
    const title = (document.getElementById('resourceTitle')?.value || '').trim();
    const type = (document.getElementById('resourceType')?.value || 'resource').trim();
    const description = (document.getElementById('resourceDescription')?.value || '').trim();
    const dueDate = (document.getElementById('resourceDueDate')?.value || '').trim();
    const file = document.getElementById('resourceFile')?.files?.[0] || null;

    if (!title) {
        showPageMessage('Please enter resource title.', true);
        return;
    }

    let classIds = [];
    if (mode === 'single') {
        const one = Number(document.getElementById('resourceClassSelect')?.value || 0);
        classIds = one > 0 ? [one] : [0];
    } else if (mode === 'multiple') {
        const sel = document.getElementById('resourceClassMultiSelect');
        classIds = sel ? [...sel.selectedOptions].map((o) => Number(o.value)).filter((v) => v > 0) : [];
    } else {
        classIds = state.classes.map((c) => Number(c.class_id)).filter((v) => v > 0);
    }
    if (!classIds.length) classIds = [0];

    try {
        for (const cid of classIds) {
            const fd = new FormData();
            fd.append('title', title);
            fd.append('type', type);
            fd.append('description', description);
            fd.append('due_date', dueDate);
            fd.append('class_id', String(cid));
            if (file) fd.append('resource_file', file);
            await apiFormPost(API.uploadResource, fd);
        }
        showPageMessage('Resource uploaded.', false);
        alert('Resource uploaded successfully.');
        closeResourceModal();
        await loadResources();
    } catch (e) {
        showPageMessage('Failed to upload resource: ' + e.message, true);
        alert('Failed to upload resource: ' + e.message);
    }
}

function onGradingClassChange() {
    const grade = document.getElementById('classSelectForGrading')?.value || '';
    const sectionSel = document.getElementById('sectionSelectForGrading');
    const subjectSel = document.getElementById('gradingSubjectSelect');
    if (!sectionSel) return;

    sectionSel.innerHTML = '<option value="">-- Choose section --</option>';
    if (subjectSel) subjectSel.innerHTML = '<option value="">-- Choose section first --</option>';

    state.classes.forEach((c) => {
        const parts = classParts(c.name, c.class_id);
        if (String(parts.grade) === String(grade)) {
            const opt = document.createElement('option');
            opt.value = String(c.class_id || '');
            opt.textContent = parts.section || ('Class ' + c.class_id);
            sectionSel.appendChild(opt);
        }
    });
}

async function onGradingSectionChange() {
    const classId = Number(document.getElementById('sectionSelectForGrading')?.value || 0);
    state.selectedClassIdForGrading = classId;

    const subjectSel = document.getElementById('gradingSubjectSelect');
    if (!subjectSel) return;
    subjectSel.innerHTML = '<option value="">-- Loading subjects --</option>';

    if (!classId) {
        subjectSel.innerHTML = '<option value="">-- Choose section first --</option>';
        return;
    }

    try {
        const data = await apiGet(`${API.classSubjects}?class_id=${classId}`);
        const list = data.subjects || [];
        subjectSel.innerHTML = '<option value="">-- Choose subject --</option>';
        list.forEach((s) => {
            const opt = document.createElement('option');
            opt.value = String(s);
            opt.textContent = String(s);
            subjectSel.appendChild(opt);
        });
    } catch (e) {
        subjectSel.innerHTML = '<option value="">-- Failed to load subjects --</option>';
    }
}

function onGradingSubjectChange() {}
function onGradingTermChange() {}

async function loadSimpleStudentsForGrading() {
    const classId = Number(document.getElementById('sectionSelectForGrading')?.value || 0);
    const subject = (document.getElementById('gradingSubjectSelect')?.value || '').trim();
    if (!classId || !subject) {
        showInlineMessage('gradingScoresMessage', 'Choose section and subject first.', true);
        return;
    }

    try {
        const data = await apiGet(`${API.classStudents}?class_id=${classId}`);
        const students = data.students || [];
        const body = document.getElementById('gradingTableBody');
        const section = document.getElementById('gradingTableSection');
        if (!body || !section) return;

        body.innerHTML = '';
        students.forEach((s) => {
            const tr = document.createElement('tr');
            tr.dataset.student = s.student_username || '';
            tr.innerHTML = `
                <td><input type="checkbox" class="student-check"></td>
                <td>${escapeHtml(s.student_username || '')}</td>
                <td>${escapeHtml(s.full_name || '')}</td>
                <td>${escapeHtml((document.getElementById('classSelectForGrading')?.value || '').toString())}</td>
                <td><input type="number" class="score-ass" min="0" max="10" value="0"></td>
                <td><input type="number" class="score-mid" min="0" max="30" value="0"></td>
                <td><input type="number" class="score-fin" min="0" max="60" value="0"></td>
                <td class="score-total">0</td>
                <td class="score-letter">F</td>
            `;
            body.appendChild(tr);
        });

        body.querySelectorAll('tr').forEach((tr) => {
            const recalc = () => {
                const a = Number(tr.querySelector('.score-ass')?.value || 0);
                const m = Number(tr.querySelector('.score-mid')?.value || 0);
                const f = Number(tr.querySelector('.score-fin')?.value || 0);
                const t = Math.max(0, Math.min(100, a + m + f));
                tr.querySelector('.score-total').textContent = String(t);
                tr.querySelector('.score-letter').textContent = gradeLetter(t);
            };
            tr.querySelector('.score-ass')?.addEventListener('input', recalc);
            tr.querySelector('.score-mid')?.addEventListener('input', recalc);
            tr.querySelector('.score-fin')?.addEventListener('input', recalc);
            recalc();
        });

        section.style.display = '';
        showInlineMessage('gradingScoresMessage', `Loaded ${students.length} students.`, false);
    } catch (e) {
        showInlineMessage('gradingScoresMessage', 'Failed to load students: ' + e.message, true);
    }
}

function toggleSelectAllSimpleStudents(src) {
    const checked = !!src?.checked;
    document.querySelectorAll('#gradingTableBody .student-check').forEach((c) => { c.checked = checked; });
}

async function submitSimpleGrades() {
    const classId = Number(document.getElementById('sectionSelectForGrading')?.value || 0);
    const subject = (document.getElementById('gradingSubjectSelect')?.value || '').trim();
    const term = (document.getElementById('gradingTermSelect')?.value || 'Term1').trim();

    const rows = [...document.querySelectorAll('#gradingTableBody tr')].filter((tr) => tr.querySelector('.student-check')?.checked);
    if (!classId || !subject || !rows.length) {
        showInlineMessage('gradingBulkMessage', 'Select class, subject and at least one student.', true);
        return;
    }

    try {
        for (const tr of rows) {
            const student = tr.dataset.student || '';
            const total = Number(tr.querySelector('.score-total')?.textContent || 0);
            await apiPost(API.enterGrades, {
                student_username: student,
                class_id: classId,
                term,
                subject,
                marks: total
            });
        }
        showInlineMessage('gradingBulkMessage', 'Grades submitted successfully.', false);
    } catch (e) {
        showInlineMessage('gradingBulkMessage', 'Failed to submit grades: ' + e.message, true);
    }
}

function gradeLetter(total) {
    if (total >= 90) return 'A';
    if (total >= 80) return 'B';
    if (total >= 70) return 'C';
    if (total >= 60) return 'D';
    return 'F';
}

function showInlineMessage(id, msg, isError) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'block';
    el.textContent = msg;
    el.style.color = isError ? '#b91c1c' : '#166534';
}

function showPageMessage(msg, isError) {
    const el = document.getElementById('teacherPageMessage');
    if (!el) {
        alert(msg);
        return;
    }
    el.style.display = 'block';
    el.textContent = msg;
    el.style.color = isError ? '#b91c1c' : '#166534';
}

function downloadFile(filename) {
    if (!filename) return;
    window.open(filename, '_blank', 'noopener');
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

// Keep these available for inline onclick handlers in HTML.
Object.assign(window, {
    openMyProfile,
    openAnnouncementModal,
    closeAnnouncementModal,
    onAnnouncementTargetModeChange,
    openAnnouncementFilePicker,
    removeAnnouncementFile,
    saveAnnouncement,
    openResourceModal,
    closeResourceModal,
    onResourceTargetModeChange,
    openResourceFilePicker,
    removeResourceFile,
    saveResource,
    onGradingClassChange,
    onGradingSectionChange,
    onGradingSubjectChange,
    onGradingTermChange,
    loadSimpleStudentsForGrading,
    toggleSelectAllSimpleStudents,
    submitSimpleGrades,
    downloadFile
});
