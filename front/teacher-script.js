const API = {
    dashboard: '../backend/teacher/get_teacher_dashboard.php',
    profile: '../backend/teacher/get_teacher_profile.php',
    announcements: '../backend/teacher/get_announcements.php',
    createAnnouncement: '../backend/teacher/create_announcement.php',
    classStudents: '../backend/teacher/get_class_students.php',
    classSubjects: '../backend/teacher/get_class_subjects.php',
    classSchedule: '../backend/teacher/get_class_schedule.php',
    teacherSchedule: '../backend/teacher/get_teacher_schedule.php',
    classGrades: '../backend/teacher/get_class_grades.php',
    enterGrades: '../backend/teacher/enter_grades.php',
    saveAssessmentStructure: '../backend/teacher/save_assessment_structure.php',
    getAssessmentStructure: '../backend/teacher/get_assessment_structure.php',
    saveAssessmentScores: '../backend/teacher/save_assessment_scores.php',
    getAssessmentScores: '../backend/teacher/get_assessment_scores.php',
    deleteAssessmentSnapshot: '../backend/teacher/delete_assessment_snapshot.php',
    generateReport: '../backend/teacher/generate_report.php',
    reports: '../backend/teacher/get_reports.php',
    resources: '../backend/teacher/get_resources.php',
    uploadResource: '../backend/teacher/upload_resource.php',
    submissions: '../backend/teacher/get_submissions.php',
    markSubmissionSeen: '../backend/teacher/mark_submission_seen.php'
};

const state = {
    user: null,
    dashboard: null,
    profile: null,
    announcements: [],
    resources: [],
    submissions: [],
    classes: [],
    schedule: [],
    selectedSubmissionClass: null,
    selectedSubmission: null,
    assessmentStructures: {},
    currentSection: '',
    currentStudents: [],
    currentClassSubjects: [],
    currentAssessmentItems: [],
    currentAssessmentContext: {
        class_id: 0,
        subject: '',
        term: 'Term1'
    },
    currentHistoryTables: [],
    pendingHistoryDecisionResolver: null,
    derived: {
        pendingGrading: 0,
        recentGrades: [],
        classGradeStatus: []
    }
};

const TEACHER_SCHEDULE_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const TEACHER_SCHEDULE_SLOTS = [
    { start: '08:00', end: '08:55' },
    { start: '09:00', end: '09:55' },
    { start: '10:15', end: '11:10' },
    { start: '11:15', end: '12:15' },
    { start: '13:30', end: '14:25' },
    { start: '14:30', end: '15:25' }
];

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'teacher') {
        window.location.href = 'login.html';
        return;
    }

    bindNav();
    bindActions();
    await initializeData();
    renderDashboard();
});

async function initializeData() {
    try {
        const [dashboardRes, profileRes, annRes, scheduleRes] = await Promise.all([
            apiGet(`${API.dashboard}`),
            apiGet(`${API.profile}`),
            apiGet(`${API.announcements}?page=1&limit=20`),
            apiGet(`${API.teacherSchedule}`)
        ]);

        state.dashboard = dashboardRes;
        state.profile = profileRes.teacher || null;
        state.announcements = annRes.announcements || [];
        state.classes = dashboardRes.assigned_classes || [];
        state.schedule = scheduleRes.schedule || [];
        await loadDerivedTeacherMetrics();

        renderUserInfo();
        populateClassSelects();
    } catch (err) {
        showPageMessage('Failed to load teacher data: ' + err.message, 'error');
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const body = await parseResponseJson(res);
    if (!res.ok || !body.success) {
        throw new Error(body.message || 'Request failed');
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
    const body = await parseResponseJson(res);
    if (!res.ok || !body.success) {
        throw new Error(body.message || 'Request failed');
    }
    return body.data || {};
}

async function parseResponseJson(res) {
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (_) {
        throw new Error('Server returned invalid JSON');
    }
}

function renderUserInfo() {
    const p = state.profile;
    if (!p) return;

    setText('userName', [p.fname, p.lname].filter(Boolean).join(' ') || state.user.username);
    setText('userDept', p.department || 'Teacher');
    setText('profileName', [p.fname, p.lname].filter(Boolean).join(' ') || state.user.username);
    setText('profileDept', p.department || '-');
    setText('profileEmpID', p.employee_id_generated || p.username || '-');
    setText('profileEmail', p.email || '-');
    setText('profilePhone', p.office_phone || '-');
    setText('profileOffice', p.office_room || '-');
    setText('profileOfficeHours', 'Not set');
    setText('profileExperience', 'Not set');
}

function renderDashboard() {
    const d = state.dashboard;
    if (!d) return;

    setText('totalClasses', d.statistics?.total_classes ?? 0);
    setText('pendingSubmissions', state.derived.pendingGrading ?? 0);
    setText('pendingGrading', state.derived.pendingGrading ?? 0);
    setText('totalStudents', d.statistics?.total_students ?? 0);
    setText('submissionBadge', state.derived.pendingGrading ?? 0);

    const recentSubmissionsContainer = document.getElementById('recentSubmissionsContainer');
    if (recentSubmissionsContainer) {
        recentSubmissionsContainer.innerHTML = '';
        const recent = state.derived.recentGrades || [];
        if (!recent.length) {
            recentSubmissionsContainer.innerHTML = '<div class="submission-item"><div class="submission-item-info"><p class="name">No recent grade activity</p><p class="assignment">No grade has been entered yet.</p></div><span class="status-badge pending">Pending</span></div>';
        } else {
            recent.forEach((r) => {
                const item = document.createElement('div');
                item.className = 'submission-item';
                item.innerHTML = `
                    <div class="submission-item-info">
                        <p class="name">${escapeHtml(r.full_name || r.student_username)}</p>
                        <p class="assignment">${escapeHtml(r.subject || 'Subject')} - ${escapeHtml(r.class_name || 'Class')}</p>
                    </div>
                    <span class="status-badge graded">${escapeHtml(r.letter_grade || 'Graded')}</span>
                `;
                recentSubmissionsContainer.appendChild(item);
            });
        }
    }

    const gradingStatusContainer = document.getElementById('gradingStatusContainer');
    if (gradingStatusContainer) {
        gradingStatusContainer.innerHTML = '';
        (state.derived.classGradeStatus || []).forEach((c) => {
            const item = document.createElement('div');
            item.className = 'status-item';
            item.innerHTML = `
                <span class="class-name">${escapeHtml(c.name || `Class ${c.class_id}`)}</span>
                <span class="badge">Pending: ${escapeHtml(String(c.pending))} / Graded: ${escapeHtml(String(c.graded))}</span>
            `;
            gradingStatusContainer.appendChild(item);
        });
        if (!state.derived.classGradeStatus.length) {
            gradingStatusContainer.innerHTML = '<div class="status-item"><span class="class-name">No class grade status yet</span><span class="badge">No data</span></div>';
        }
    }
}

function bindNav() {
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
            document.querySelector('.sidebar')?.classList.remove('open');
        });
    });
}

function bindActions() {
    document.getElementById('hamburgerBtn')?.addEventListener('click', () => {
        document.querySelector('.sidebar')?.classList.toggle('open');
    });

    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    document.getElementById('classFilterSubmissions')?.addEventListener('change', loadSubmissions);
    document.getElementById('statusFilterSubmissions')?.addEventListener('change', loadSubmissions);
    document.getElementById('classFilterResources')?.addEventListener('change', loadResources);
    document.getElementById('classSelectReports')?.addEventListener('change', onReportsClassChange);
    document.getElementById('announcementFile')?.addEventListener('change', onAnnouncementFileSelected);
    document.getElementById('resourceFile')?.addEventListener('change', onResourceFileSelected);
    document.getElementById('announcementFileUploadArea')?.addEventListener('click', () => {
        openAnnouncementFilePicker();
    });
    document.getElementById('resourceUploadArea')?.addEventListener('click', () => {
        openResourceFilePicker();
    });
}

function onReportsClassChange() {
    const classId = Number(document.getElementById('classSelectReports')?.value || 0);
    const sectionSelect = document.getElementById('sectionSelectReports');
    if (!sectionSelect) return;
    if (!classId) {
        sectionSelect.value = '';
        return;
    }
    const selectedClass = (state.classes || []).find((c) => Number(c.class_id) === classId);
    if (selectedClass?.section) {
        const sec = String(selectedClass.section).toUpperCase();
        if ([...sectionSelect.options].some((o) => o.value === sec)) {
            sectionSelect.value = sec;
        } else {
            sectionSelect.value = '';
        }
    }
}

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((page) => page.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        submissions: 'Student Submissions',
        grading: 'Grading Management',
        classes: 'My Classes',
        schedule: 'My Teaching Schedule',
        announcements: 'Announcements',
        resources: 'Course Resources',
        reports: 'Grade Reports',
        profile: 'Teacher Profile'
    };
    setText('pageTitle', titles[pageName] || 'Dashboard');

    if (pageName === 'submissions') {
        loadSubmissions();
    } else if (pageName === 'classes') {
        loadClasses();
    } else if (pageName === 'schedule') {
        loadTeacherSchedule();
    } else if (pageName === 'announcements') {
        loadAnnouncements();
    } else if (pageName === 'resources') {
        loadResources();
    } else if (pageName === 'grading') {
        loadGradingPage();
    } else if (pageName === 'reports') {
        loadReports();
    }
}

function populateClassSelects() {
    const selectIds = [
        'classFilterSubmissions',
        'classSelectReports',
        'announcementClassSelect',
        'announcementClassMultiSelect',
        'resourceClassSelect',
        'resourceClassMultiSelect',
        'classFilterResources'
    ];

    selectIds.forEach((id) => {
        const select = document.getElementById(id);
        if (!select) return;
        const isMultiAnnouncement = id === 'announcementClassMultiSelect' || id === 'resourceClassMultiSelect';
        const first = isMultiAnnouncement
            ? ''
            : (select.querySelector('option')?.outerHTML || '<option value="">-- Choose --</option>');
        select.innerHTML = first;
        state.classes.forEach((c) => {
            const option = document.createElement('option');
            option.value = c.class_id;
            option.textContent = c.name || `Class ${c.class_id}`;
            select.appendChild(option);
        });
    });

    populateGradeSelectsForGrading();
}

function normalizeGradeValue(raw) {
    const text = String(raw || '').trim();
    const match = text.match(/\d+/);
    return match ? match[0] : text;
}

function normalizeSectionValue(raw) {
    return String(raw || '').trim().toUpperCase();
}

function normalizeSubjectValue(raw) {
    return String(raw || '').trim();
}

function gradeLabel(raw) {
    const n = normalizeGradeValue(raw);
    return n ? `Grade ${n}` : 'Unknown Grade';
}

function populateGradeSelectsForGrading() {
    const grades = [...new Set(state.classes.map((c) => normalizeGradeValue(c.grade_level)).filter(Boolean))];
    const ids = ['classSelectGrading', 'classSelectForGrading'];
    ids.forEach((id) => {
        const select = document.getElementById(id);
        if (!select) return;
        const isStep1 = id === 'classSelectGrading';
        select.innerHTML = isStep1 ? '<option value="">-- Choose a grade --</option>' : '<option value="">-- Choose a grade --</option>';
        grades.forEach((g) => {
            const option = document.createElement('option');
            option.value = g;
            option.textContent = gradeLabel(g);
            select.appendChild(option);
        });
    });
}

function classesForSelectedGrade(selectedGrade) {
    const g = normalizeGradeValue(selectedGrade);
    return state.classes.filter((c) => normalizeGradeValue(c.grade_level) === g);
}

function classesForSelectedGradeAndSection(selectedGrade, selectedSection) {
    const g = normalizeGradeValue(selectedGrade);
    const s = normalizeSectionValue(selectedSection);
    return state.classes.filter(
        (c) => normalizeGradeValue(c.grade_level) === g && normalizeSectionValue(c.section) === s
    );
}

function classForSelectedGradeAndSection(selectedGrade, selectedSection) {
    return classesForSelectedGradeAndSection(selectedGrade, selectedSection)[0] || null;
}

function assessmentStructureKey(grade, subject) {
    return `grade_${normalizeGradeValue(grade)}_subject_${normalizeSubjectValue(subject).toLowerCase()}`;
}

function assessmentStorageKey() {
    const username = state.user?.username || 'unknown';
    return `teacher_assessment_structures_${username}`;
}

function loadAssessmentStructures() {
    try {
        const raw = localStorage.getItem(assessmentStorageKey());
        const parsed = raw ? JSON.parse(raw) : {};
        state.assessmentStructures = parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
        state.assessmentStructures = {};
    }
}

function persistAssessmentStructures() {
    try {
        localStorage.setItem(assessmentStorageKey(), JSON.stringify(state.assessmentStructures || {}));
    } catch (_) {}
}

function populateSectionsForSelectedGrade(selectedGrade) {
    const sectionSelect = document.getElementById('sectionSelectForGrading');
    if (!sectionSelect) return;

    if (!selectedGrade) {
        sectionSelect.innerHTML = '<option value="">-- Choose grade first --</option>';
        return;
    }

    const sections = [...new Set(classesForSelectedGrade(selectedGrade).map((c) => normalizeSectionValue(c.section)).filter(Boolean))];
    sectionSelect.innerHTML = '<option value="">-- Choose section --</option>';
    sections.forEach((s) => {
        const option = document.createElement('option');
        option.value = s;
        option.textContent = `Section ${s}`;
        sectionSelect.appendChild(option);
    });
}

function populateSectionsForStepOne(selectedGrade) {
    const sectionSelect = document.getElementById('sectionSelectGrading');
    if (!sectionSelect) return;

    if (!selectedGrade) {
        sectionSelect.innerHTML = '<option value="">-- Choose grade first --</option>';
        return;
    }

    const sections = [...new Set(classesForSelectedGrade(selectedGrade).map((c) => normalizeSectionValue(c.section)).filter(Boolean))];
    sectionSelect.innerHTML = '<option value="">-- Choose section --</option>';
    sections.forEach((s) => {
        const option = document.createElement('option');
        option.value = s;
        option.textContent = `Section ${s}`;
        sectionSelect.appendChild(option);
    });
}

async function loadSubmissions() {
    const tbody = document.getElementById('submissionsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

    const classId = document.getElementById('classFilterSubmissions')?.value || '';
    const statusFilter = document.getElementById('statusFilterSubmissions')?.value || '';

    try {
        const qs = new URLSearchParams();
        if (classId) qs.set('class_id', classId);
        const normalizedStatus = statusFilter === 'pending' ? 'submitted' : statusFilter;
        if (normalizedStatus && ['submitted', 'seen', 'graded'].includes(normalizedStatus)) qs.set('status', normalizedStatus);
        const data = await apiGet(`${API.submissions}?${qs.toString()}`);
        state.submissions = data.submissions || [];
        tbody.innerHTML = '';
        state.submissions.forEach((s) => {
            const status = String(s.status || 'submitted').toLowerCase();
            const statusLabel = status === 'graded' ? 'Graded' : (status === 'seen' ? 'Seen' : 'Submitted');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(s.student_name || s.student_username || '-')}</td>
                <td>${escapeHtml(capitalizeText(s.resource_type || 'submission'))}</td>
                <td>${escapeHtml(s.resource_title || '-')}</td>
                <td>${escapeHtml(formatDate(s.submitted_at))}</td>
                <td><span class="status-badge ${status}">${escapeHtml(statusLabel)}</span></td>
                <td>
                    <button class="btn btn-small btn-primary" onclick="viewSubmission(${Number(s.id || 0)})"><i class="fas fa-eye"></i> View</button>
                </td>
            `;
            tbody.appendChild(row);
        });
        if (!tbody.children.length) {
            tbody.innerHTML = '<tr><td colspan="6">No submissions found</td></tr>';
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6">${escapeHtml(err.message)}</td></tr>`;
    }
}

function viewSubmission(submissionId) {
    const selected = (state.submissions || []).find((s) => Number(s.id) === Number(submissionId));
    state.selectedSubmission = selected || null;
    const details = document.getElementById('submissionDetails');
    if (details) {
        if (!selected) {
            details.innerHTML = '<p>Submission not found.</p>';
        } else {
            const fileHref = selected.file_path ? `../backend/${encodeURI(String(selected.file_path).replace(/^\/+/, ''))}` : '';
            details.innerHTML = `
                <p><strong>Student:</strong> ${escapeHtml(selected.student_name || selected.student_username || '-')}</p>
                <p><strong>Class:</strong> ${escapeHtml(selected.class_name || '-')}</p>
                <p><strong>Type:</strong> ${escapeHtml(capitalizeText(selected.resource_type || 'submission'))}</p>
                <p><strong>Task:</strong> ${escapeHtml(selected.resource_title || '-')}</p>
                <p><strong>Submitted:</strong> ${escapeHtml(formatDate(selected.submitted_at))}</p>
                <p><strong>Status:</strong> ${escapeHtml(capitalizeText(selected.status || 'submitted'))}</p>
                <p><strong>Notes:</strong> ${escapeHtml(selected.notes || '-')}</p>
                ${
                    fileHref
                        ? `<p><a class="btn btn-secondary" href="${fileHref}" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open Submitted File (${escapeHtml(selected.file_name || 'Download')})</a></p>`
                        : '<p>No file attached.</p>'
                }
            `;
        }
    }
    document.getElementById('submissionModal')?.classList.add('show');
}

function closeSubmissionModal() {
    document.getElementById('submissionModal')?.classList.remove('show');
}

async function markSubmissionAsSeen() {
    const id = Number(state.selectedSubmission?.id || 0);
    if (!id) {
        showPageMessage('Please open a submission first.', 'error', 'submissions');
        return;
    }
    try {
        await apiPost(API.markSubmissionSeen, { submission_id: id });
        closeSubmissionModal();
        await loadSubmissions();
        showPageMessage('Submission marked as seen.', 'success', 'submissions');
    } catch (err) {
        showPageMessage(err.message || 'Failed to mark submission as seen.', 'error', 'submissions');
    }
}

function openGradingPanel() {
    closeSubmissionModal();
    switchPage('grading');
}

function loadClasses() {
    const container = document.getElementById('classesContainer');
    if (!container) return;
    container.innerHTML = '';
    state.classes.forEach((c) => {
        const card = document.createElement('div');
        card.className = 'card class-card';
        card.innerHTML = `
            <h3>${escapeHtml(c.name || `Class ${c.class_id}`)}</h3>
            <div class="class-info">
                <p><strong>Grade:</strong> ${escapeHtml(c.grade_level || '-')}</p>
                <p><strong>Section:</strong> ${escapeHtml(c.section || '-')}</p>
                <p><strong>Subject:</strong> ${escapeHtml(c.assigned_subjects || '-')}</p>
                <p><strong>Stream:</strong> ${escapeHtml((c.stream || '-').toString())}</p>
                <p><strong>Class ID:</strong> ${escapeHtml(String(c.class_id))}</p>
            </div>
        `;
        container.appendChild(card);
    });
}

async function loadTeacherSchedule() {
    const tbody = document.getElementById('teacherScheduleTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">Loading schedule...</td></tr>';

    try {
        const data = await apiGet(`${API.teacherSchedule}`);
        state.schedule = data.schedule || [];
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#b91c1c;">${escapeHtml(err.message || 'Failed to load schedule')}</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    renderTeacherScheduleGrid();
}

function renderTeacherScheduleGrid() {
    const tbody = document.getElementById('teacherScheduleTableBody');
    if (!tbody) return;

    const slotMap = {};
    state.schedule.forEach((item) => {
        const day = String(item.day || '').trim();
        const start = String(item.start_time || '').slice(0, 5);
        if (!day || !start) return;
        const key = `${day}|${start}`;
        if (!slotMap[key]) slotMap[key] = [];
        slotMap[key].push(item);
    });

    if (!state.schedule.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">No schedule found</td></tr>';
        return;
    }

    TEACHER_SCHEDULE_SLOTS.forEach((slot) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="time">${escapeHtml(`${slot.start} - ${slot.end}`)}</td>`;

        TEACHER_SCHEDULE_DAYS.forEach((day) => {
            const cell = document.createElement('td');
            cell.className = 'class-cell';
            const key = `${day}|${slot.start}`;
            const entries = slotMap[key] || [];

            if (!entries.length) {
                cell.innerHTML = '-';
            } else {
                const blocks = entries.map((item) => {
                    const classLabel = item.class_name || `Grade ${item.grade_level || '-'} - ${item.section || '-'}`;
                    const place = formatLocationLabel(item.room_number) || '-';
                    return `${escapeHtml(item.subject_name || '-')}` +
                        `<br><small>${escapeHtml(classLabel)}</small>` +
                        `<br><small>${escapeHtml(place)}</small>`;
                });
                cell.innerHTML = blocks.join('<hr style="border:none;border-top:1px solid #eee;">');
            }
            tr.appendChild(cell);
        });
        tbody.appendChild(tr);
    });
}

function formatLocationLabel(raw) {
    const text = String(raw || '').trim();
    if (!text) return '';
    const idx = text.indexOf('|');
    if (idx > 0) {
        const type = text.slice(0, idx).toLowerCase();
        const value = text.slice(idx + 1).trim();
        if (type === 'lab') return `Lab-${value || '-'}`;
        if (type === 'field') return `Field-${value || '-'}`;
        return `Lec-${value || '-'}`;
    }
    if (/^(lab|lec|field)[-_]/i.test(text)) return text;
    return text;
}

function loadAnnouncements() {
    const container = document.getElementById('announcementsContainer');
    if (!container) return;
    container.innerHTML = '';
    state.announcements.forEach((a) => {
        const card = document.createElement('div');
        card.className = 'card announcement-card';
        card.innerHTML = `
            <h4>${escapeHtml(a.title || '-')}</h4>
            <div class="meta">${escapeHtml(formatDate(a.created_at))}</div>
            <div class="content">${escapeHtml(a.content || '')}</div>
            ${
                a.attachment_path
                    ? `<div class="meta" style="margin-top:8px;"><a href="../backend/${escapeHtml(a.attachment_path)}" target="_blank" rel="noopener">Attachment: ${escapeHtml(a.attachment_name || 'Download')}</a></div>`
                    : ''
            }
        `;
        container.appendChild(card);
    });
}

function loadResources() {
    const container = document.getElementById('resourcesContainer');
    if (!container) return;
    container.innerHTML = '<div class="card resource-card"><h4>Loading resources...</h4></div>';
    const classId = String(document.getElementById('classFilterResources')?.value || '').trim();
    const qs = new URLSearchParams();
    if (classId) qs.set('class_id', classId);
    apiGet(`${API.resources}?${qs.toString()}`)
        .then((data) => {
            state.resources = data.resources || [];
            container.innerHTML = '';
            if (!state.resources.length) {
                container.innerHTML = '<div class="card resource-card"><h4>No resources yet</h4><div class="meta">Upload resource/assignment/project files to share with students.</div></div>';
                return;
            }
            state.resources.forEach((r) => {
                const targetCount = String(r.target_class_ids || '').trim() === ''
                    ? 'All classes'
                    : `${String(r.target_class_ids).split(',').filter(Boolean).length} class(es)`;
                const href = `../backend/${encodeURI(String(r.file_path || '').replace(/^\/+/, ''))}`;
                const card = document.createElement('div');
                card.className = 'card resource-card';
                card.innerHTML = `
                    <h4>${escapeHtml(r.title || '-')}</h4>
                    <div class="meta">${escapeHtml(capitalizeText(r.resource_type || 'resource'))} | ${escapeHtml(formatDate(r.created_at))}</div>
                    <div class="meta">Target: ${escapeHtml(targetCount)}</div>
                    <div class="meta">${escapeHtml(r.description || '-')}</div>
                    <div class="meta">${r.due_date ? `Deadline: ${escapeHtml(r.due_date)}` : 'No deadline'}</div>
                    <div style="margin-top:10px;">
                        <a class="btn btn-secondary" href="${href}" target="_blank" rel="noopener"><i class="fas fa-download"></i> ${escapeHtml(r.file_name || 'Download')}</a>
                    </div>
                `;
                container.appendChild(card);
            });
        })
        .catch((err) => {
            container.innerHTML = `<div class="card resource-card"><h4>Failed to load resources</h4><div class="meta">${escapeHtml(err.message || 'Unknown error')}</div></div>`;
        });
}

function loadReports() {
    const container = document.getElementById('reportsContainer');
    if (!container) return;
    container.innerHTML = '<div class="card report-item"><div class="report-item-info"><h4>Loading reports...</h4><p>Please wait</p></div><span class="status">Loading</span></div>';

    apiGet(`${API.reports}?page=1&limit=20`)
        .then((data) => {
            const list = data.reports || [];
            container.innerHTML = '';
            if (!list.length) {
                container.innerHTML = `
                    <div class="card report-item">
                        <div class="report-item-info">
                            <h4>No reports yet</h4>
                            <p>Generate a class report and it will appear here.</p>
                        </div>
                        <span class="status">Empty</span>
                    </div>
                `;
                return;
            }
            list.forEach((r) => {
                const summary = r.summary || {};
                const card = document.createElement('div');
                card.className = 'card report-item';
                card.innerHTML = `
                    <div class="report-item-info">
                        <h4>${escapeHtml(`Grade ${r.grade_level} - Section ${r.section}`)}</h4>
                        <p>${escapeHtml(`Students: ${summary.total_students ?? 0}, Graded: ${summary.graded_students ?? 0}, Pending: ${summary.pending_students ?? 0}, Avg: ${summary.class_average ?? 0}`)}</p>
                        <p>${escapeHtml(`Generated: ${formatDate(r.generated_at)}`)}</p>
                    </div>
                    <span class="status">${escapeHtml(r.status || 'submitted')}</span>
                `;
                container.appendChild(card);
            });
        })
        .catch((err) => {
            container.innerHTML = `
                <div class="card report-item">
                    <div class="report-item-info">
                        <h4>Failed to load reports</h4>
                        <p>${escapeHtml(err.message || 'Unknown error')}</p>
                    </div>
                    <span class="status">Error</span>
                </div>
            `;
        });
}

function loadGradingPage() {
    switchGradingStep('step2');
}

async function loadDerivedTeacherMetrics() {
    const statuses = [];
    const recentRows = [];
    let totalPending = 0;

    for (const c of state.classes) {
        try {
            const [studentsData, gradesData] = await Promise.all([
                apiGet(`${API.classStudents}?class_id=${c.class_id}`),
                apiGet(`${API.classGrades}?class_id=${c.class_id}`)
            ]);
            const students = studentsData.students || [];
            const grades = gradesData.grades || [];
            const gradedUsers = new Set(grades.map((g) => g.student_username));
            const gradedCount = gradedUsers.size;
            const pendingCount = Math.max(0, students.length - gradedCount);
            totalPending += pendingCount;
            statuses.push({
                class_id: c.class_id,
                name: c.name || `Class ${c.class_id}`,
                graded: gradedCount,
                pending: pendingCount
            });
            grades.forEach((g) => recentRows.push({
                ...g,
                class_id: c.class_id,
                class_name: c.name || `Class ${c.class_id}`
            }));
        } catch (_) {
            statuses.push({
                class_id: c.class_id,
                name: c.name || `Class ${c.class_id}`,
                graded: 0,
                pending: 0
            });
        }
    }

    recentRows.sort((a, b) => new Date(b.entered_at || 0) - new Date(a.entered_at || 0));
    state.derived.pendingGrading = totalPending;
    state.derived.classGradeStatus = statuses;
    state.derived.recentGrades = recentRows.slice(0, 5);
}

async function onGradingClassChange() {
    populateSectionsForSelectedGrade(document.getElementById('classSelectForGrading')?.value || '');
    const subjectSelect = document.getElementById('gradingSubjectSelect');
    if (subjectSelect) {
        subjectSelect.innerHTML = '<option value="">-- Choose grade and section first --</option>';
    }
    const sectionEl = document.getElementById('gradingTableSection');
    if (sectionEl) sectionEl.style.display = 'none';
    renderHistoryTables([]);
    hideGradingDecisionPrompt();
    showGradingScoresMessage('', 'info', false);
}

async function onGradingSectionChange() {
    await loadClassSubjectsForGrading();
    const sectionEl = document.getElementById('gradingTableSection');
    if (sectionEl) sectionEl.style.display = 'none';
    renderHistoryTables([]);
    hideGradingDecisionPrompt();
    showGradingScoresMessage('', 'info', false);
}

function onGradingSubjectChange() {
    const sectionEl = document.getElementById('gradingTableSection');
    if (sectionEl) sectionEl.style.display = 'none';
    renderHistoryTables([]);
    hideGradingDecisionPrompt();
    showGradingScoresMessage('', 'info', false);
}

async function loadClassSubjectsForGrading() {
    const selectedGrade = document.getElementById('classSelectForGrading')?.value || '';
    const selectedSection = document.getElementById('sectionSelectForGrading')?.value || '';
    const subjectSelect = document.getElementById('gradingSubjectSelect');
    if (!subjectSelect) return;

    subjectSelect.innerHTML = '<option value="">-- Choose grade and section first --</option>';
    state.currentClassSubjects = [];

    if (!selectedGrade || !selectedSection) return;

    const classes = classesForSelectedGradeAndSection(selectedGrade, selectedSection);
    if (!classes.length) {
        subjectSelect.innerHTML = '<option value="">-- No assigned classes for grade/section --</option>';
        return;
    }

    try {
        const allSubjects = new Set();
        for (const c of classes) {
            const data = await apiGet(`${API.classSubjects}?class_id=${c.class_id}`);
            (data.subjects || []).forEach((s) => allSubjects.add(s));
        }
        const subjects = [...allSubjects];
        state.currentClassSubjects = subjects;
        subjectSelect.innerHTML = '<option value="">-- Choose subject --</option>';
        if (!subjects.length) {
            showPageMessage('No subject found for selected grade/section. Please check teacher subject assignment.', 'error', 'grading');
            return;
        }
        subjects.forEach((subject) => {
            const option = document.createElement('option');
            option.value = subject;
            option.textContent = subject;
            subjectSelect.appendChild(option);
        });
    } catch (err) {
        subjectSelect.innerHTML = '<option value="">-- No subject available --</option>';
    }
}

function addAssessmentItem() {
    const container = document.getElementById('assessmentItemsContainer');
    if (!container) return;
    container.appendChild(buildAssessmentItemRow({ name: '', points: '' }));
}

function removeAssessmentItem(btn) {
    btn.parentElement.remove();
    const container = document.getElementById('assessmentItemsContainer');
    if (container && container.children.length === 0) {
        container.appendChild(buildAssessmentItemRow({ name: '', points: '' }));
    }
    updateTotalPoints();
}

function updateTotalPoints() {
    const items = document.querySelectorAll('.assessment-item');
    let total = 0;
    items.forEach((item) => {
        total += parseInt(item.querySelector('.assessment-points')?.value || '0', 10);
    });
    setText('totalPointsDisplay', total);
}

async function saveAssessmentStructure() {
    const selectedGrade = document.getElementById('classSelectGrading')?.value || '';
    const selectedSection = document.getElementById('sectionSelectGrading')?.value || '';
    const selectedSubject = document.getElementById('subjectSelectGrading')?.value || '';
    if (!selectedGrade) {
        showAssessmentStructureMessage('Please select grade first.', 'error');
        return;
    }
    if (!selectedSection) {
        showAssessmentStructureMessage('Please select section first.', 'error');
        return;
    }
    if (!selectedSubject) {
        showAssessmentStructureMessage('Please select subject first.', 'error');
        return;
    }
    const selectedClass = classForSelectedGradeAndSection(selectedGrade, selectedSection);
    if (!selectedClass?.class_id) {
        showAssessmentStructureMessage('Selected grade/section class not found.', 'error');
        return;
    }

    const items = [...document.querySelectorAll('.assessment-item')]
        .map((item) => ({
            name: item.querySelector('.assessment-name')?.value?.trim(),
            points: Number(item.querySelector('.assessment-points')?.value || '0')
        }))
        .filter((x) => x.name && x.points > 0);

    if (!items.length) {
        showAssessmentStructureMessage('Add at least one assessment type.', 'error');
        return;
    }

    try {
        await apiPost(API.saveAssessmentStructure, {
            class_id: Number(selectedClass.class_id),
            grade_level: normalizeGradeValue(selectedGrade),
            subject: selectedSubject,
            term: 'Term1',
            status: 'active',
            items
        });
        showAssessmentStructureMessage('Assessment structure saved to database.', 'success');
    } catch (err) {
        showAssessmentStructureMessage(err.message, 'error');
    }
}

async function loadStudentsForGrading(showLoadedMessage = true) {
    const selectedGrade = document.getElementById('classSelectForGrading')?.value || '';
    const selectedSection = document.getElementById('sectionSelectForGrading')?.value || '';
    const subject = document.getElementById('gradingSubjectSelect')?.value || '';
    const sectionEl = document.getElementById('gradingTableSection');
    if (!selectedGrade) {
        showGradingScoresMessage('Please choose grade first.', 'error', true);
        if (sectionEl) sectionEl.style.display = 'none';
        return;
    }
    if (!selectedSection) {
        showGradingScoresMessage('Please choose section first.', 'error', true);
        if (sectionEl) sectionEl.style.display = 'none';
        return;
    }
    if (!subject) {
        showGradingScoresMessage('Please choose subject first.', 'error', true);
        if (sectionEl) sectionEl.style.display = 'none';
        return;
    }

    try {
        const selectedClass = classForSelectedGradeAndSection(selectedGrade, selectedSection);
        if (!selectedClass?.class_id) {
            showGradingScoresMessage('Class not found for selected grade/section.', 'error', true);
            if (sectionEl) sectionEl.style.display = 'none';
            renderHistoryTables([]);
            return;
        }

        const baseUrl = `${API.getAssessmentScores}?class_id=${selectedClass.class_id}&subject=${encodeURIComponent(subject)}&term=Term1`;
        let data = await apiGet(baseUrl);

        // Ask only when teacher explicitly loads students from Step 2.
        if (showLoadedMessage && Number(data.history_count || 0) > 0) {
            const decision = await askHistoryDecision(Number(data.history_count || 0));
            if (decision === 'include') {
                data = await apiGet(`${baseUrl}&include_history=1`);
            } else if (decision === 'delete') {
                await apiPost(API.deleteAssessmentSnapshot, {
                    snapshot_id: 0,
                    class_id: Number(selectedClass.class_id),
                    subject,
                    term: 'Term1'
                });
                data = await apiGet(baseUrl);
                showGradingScoresMessage('Previous assessment table(s) deleted. New table loaded.', 'success', true);
            }
            hideGradingDecisionPrompt();
        }

        if (!data.has_structure) {
            showGradingScoresMessage('Setup assessment structure first (Step 1) for this class and subject.', 'error', true);
            if (sectionEl) sectionEl.style.display = 'none';
            renderHistoryTables([]);
            return;
        }

        state.currentStudents = data.students || [];
        state.currentAssessmentItems = data.items || [];
        state.currentAssessmentContext = {
            class_id: Number(selectedClass.class_id),
            subject,
            term: 'Term1'
        };
        buildGradingTable(state.currentAssessmentItems, state.currentStudents, data.scores || {});
        state.currentHistoryTables = data.history_tables || [];
        renderHistoryTables(state.currentHistoryTables, state.currentStudents);
        if (sectionEl) sectionEl.style.display = 'block';
        if (showLoadedMessage) {
            showGradingScoresMessage(`Loaded ${state.currentStudents.length} students.`, 'success', true);
        }
    } catch (err) {
        showGradingScoresMessage(err.message, 'error', true);
        renderHistoryTables([]);
    }
}

function buildGradingTable(items, students, scoreMap) {
    const header = document.getElementById('gradingTableHeader');
    const body = document.getElementById('gradingTableBody');
    if (!header || !body) return;

    let totalPoints = 0;
    header.innerHTML = '<th>Student ID</th><th>Student Name</th>';
    items.forEach((a) => {
        const maxPoints = Number(a.max_points ?? a.points ?? 0);
        totalPoints += maxPoints;
        header.innerHTML += `<th>${escapeHtml(a.name)}<br><small>(/${maxPoints})</small></th>`;
    });
    header.innerHTML += `<th>Total<br><small>(/${totalPoints})</small></th><th>Grade Letter</th><th>Action</th>`;

    body.innerHTML = '';
    const currentClassId = Number(state.currentAssessmentContext.class_id || 0);
    students.forEach((s) => {
        const tr = document.createElement('tr');
        tr.id = `student_row_${currentClassId}_${s.student_username}`;
        tr.dataset.classId = String(currentClassId);
        tr.dataset.studentUsername = String(s.student_username || '');
        let html = `<td>${escapeHtml(s.student_username)}</td><td>${escapeHtml(s.full_name || s.student_username)}</td>`;
        let studentTotal = 0;
        items.forEach((a) => {
            const itemId = String(a.id);
            const maxPoints = Number(a.max_points ?? a.points ?? 0);
            const existingScore = Number(scoreMap?.[s.student_username]?.[itemId] ?? 0);
            studentTotal += existingScore;
            html += `<td><input type="number" class="grade-input" min="0" max="${maxPoints}" data-item-id="${itemId}" data-max-points="${maxPoints}" value="${existingScore}" oninput="updateStudentTotal(this, false)" onchange="updateStudentTotal(this, true)"></td>`;
        });
        const letter = toLetter(studentTotal);
        html += `<td><span class="total-grade">${studentTotal}</span></td><td><span class="grade-letter-display ${letter === '--' ? 'empty' : letter}">${letter}</span></td><td><button class="btn-update-grade" onclick="updateSingleStudent('${tr.id}')">Update</button><div class="teacher-inline-message row-action-message" style="display:none;margin-top:6px;"></div></td>`;
        tr.innerHTML = html;
        body.appendChild(tr);
    });
}

function renderHistoryTables(historyTables = [], students = []) {
    const container = document.getElementById('gradingHistoryTables');
    if (!container) return;
    container.innerHTML = '';

    if (!Array.isArray(historyTables) || historyTables.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    historyTables.forEach((table) => {
        const card = document.createElement('div');
        card.className = 'card grading-card';
        const snapshotId = Number(table.snapshot_id || 0);
        const items = Array.isArray(table.items) ? table.items : [];
        const scores = table.scores || {};
        const totalPoints = Number(table.total_points || 0);

        let headerHtml = '<tr><th>Student ID</th><th>Student Name</th>';
        items.forEach((i) => {
            const maxPoints = Number(i.max_points || 0);
            headerHtml += `<th>${escapeHtml(i.name || 'Item')}<br><small>(/${maxPoints})</small></th>`;
        });
        headerHtml += `<th>Total<br><small>(/${totalPoints})</small></th><th>Grade</th></tr>`;

        let bodyHtml = '';
        students.forEach((s) => {
            let rowTotal = 0;
            let row = `<tr><td>${escapeHtml(s.student_username)}</td><td>${escapeHtml(s.full_name || s.student_username)}</td>`;
            items.forEach((i) => {
                const itemOrder = String(i.order || 0);
                const sourceItemId = String(i.source_item_id || '');
                const val = Number(scores?.[s.student_username]?.[sourceItemId] ?? scores?.[s.student_username]?.[itemOrder] ?? 0);
                rowTotal += val;
                row += `<td>${escapeHtml(String(val))}</td>`;
            });
            const letter = toLetter(rowTotal);
            row += `<td>${escapeHtml(String(rowTotal))}</td><td>${escapeHtml(letter)}</td></tr>`;
            bodyHtml += row;
        });
        if (!students.length) {
            bodyHtml = '<tr><td colspan="99" style="text-align:center;color:#6b7280;">No students found</td></tr>';
        }

        card.innerHTML = `
            <div class="setup-header" style="margin-bottom:10px;">
                <h4>${escapeHtml(table.label || 'Previous assessment table')}</h4>
                <p class="subtitle">Saved at ${escapeHtml(formatDate(table.created_at))}</p>
                <div class="grading-actions" style="margin-top:8px;">
                    <button type="button" class="btn btn-secondary" onclick="deleteHistoryTable(${snapshotId})">
                        <i class="fas fa-trash"></i> Delete Old Table
                    </button>
                </div>
                <div id="historyDeletePrompt_${snapshotId}" class="teacher-inline-message teacher-inline-error" style="display:none;margin-top:8px;"></div>
            </div>
            <div class="table-container">
                <table class="grading-table">
                    <thead>${headerHtml}</thead>
                    <tbody>${bodyHtml}</tbody>
                </table>
            </div>
        `;
        container.appendChild(card);
    });
}

function askHistoryDecision(historyCount) {
    return new Promise((resolve) => {
        state.pendingHistoryDecisionResolver = resolve;
        const box = document.getElementById('gradingDecisionPrompt');
        if (!box) {
            resolve('include');
            return;
        }
        box.classList.remove('teacher-inline-success', 'teacher-inline-error');
        box.classList.add('teacher-inline-info');
        box.innerHTML = `
            <div style="margin-bottom:8px;">Found ${historyCount} previous assessment table(s). Choose what to do:</div>
            <div class="grading-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary" onclick="resolveHistoryDecision('include')">Load Old + New</button>
                <button type="button" class="btn btn-secondary" onclick="resolveHistoryDecision('new')">Load New Only</button>
                <button type="button" class="btn btn-primary" onclick="resolveHistoryDecision('delete')">Delete Old + Load New</button>
            </div>
        `;
        box.style.display = 'block';
    });
}

function resolveHistoryDecision(choice) {
    const resolver = state.pendingHistoryDecisionResolver;
    state.pendingHistoryDecisionResolver = null;
    if (resolver) {
        resolver(choice || 'new');
    }
}

function hideGradingDecisionPrompt() {
    const box = document.getElementById('gradingDecisionPrompt');
    if (!box) return;
    box.innerHTML = '';
    box.style.display = 'none';
    state.pendingHistoryDecisionResolver = null;
}

async function deleteHistoryTable(snapshotId, forceDelete = false) {
    const classId = Number(state.currentAssessmentContext.class_id || 0);
    const subject = state.currentAssessmentContext.subject || String(document.getElementById('gradingSubjectSelect')?.value || '').trim();
    if (!snapshotId || !classId || !subject) {
        showGradingScoresMessage('Unable to delete old table in current context.', 'error', true);
        return;
    }
    const prompt = document.getElementById(`historyDeletePrompt_${snapshotId}`);
    if (!forceDelete) {
        if (prompt) {
            prompt.classList.remove('teacher-inline-success', 'teacher-inline-info');
            prompt.classList.add('teacher-inline-error');
            prompt.innerHTML = `
                <div style="margin-bottom:6px;">Delete this old assessment table permanently?</div>
                <div class="grading-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-primary" onclick="deleteHistoryTable(${snapshotId}, true)">Yes, Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelDeleteHistoryTable(${snapshotId})">Cancel</button>
                </div>
            `;
            prompt.style.display = 'block';
        }
        return;
    }

    try {
        await apiPost(API.deleteAssessmentSnapshot, {
            snapshot_id: snapshotId,
            class_id: classId,
            subject,
            term: state.currentAssessmentContext.term || 'Term1'
        });
        if (prompt) {
            prompt.innerHTML = '';
            prompt.style.display = 'none';
        }
        showGradingScoresMessage('Old assessment table deleted.', 'success', true);
        await loadStudentsForGrading(false);
    } catch (err) {
        if (prompt) {
            prompt.classList.remove('teacher-inline-success', 'teacher-inline-info');
            prompt.classList.add('teacher-inline-error');
            prompt.textContent = err.message;
            prompt.style.display = 'block';
        } else {
            showGradingScoresMessage(err.message, 'error', true);
        }
    }
}

function cancelDeleteHistoryTable(snapshotId) {
    const prompt = document.getElementById(`historyDeletePrompt_${snapshotId}`);
    if (!prompt) return;
    prompt.innerHTML = '';
    prompt.style.display = 'none';
}

function validateGradeInputValue(input, showMessage = false) {
    const maxPoints = Number(input?.dataset?.maxPoints ?? input?.getAttribute('max') ?? 0);
    let value = Number(input?.value || 0);
    if (!Number.isFinite(value)) value = 0;
    if (value < 0) value = 0;
    if (maxPoints > 0 && value > maxPoints) {
        value = maxPoints;
        if (showMessage) {
            showGradingScoresMessage(`Score cannot be greater than ${maxPoints} for this assessment item.`, 'error', true);
        }
    }
    if (input) input.value = String(value);
    return value;
}

function validateGradeInputsInRow(row, showMessage = false) {
    if (!row) return true;
    let valid = true;
    row.querySelectorAll('.grade-input').forEach((input) => {
        const original = Number(input.value || 0);
        const corrected = validateGradeInputValue(input, showMessage);
        if (!Number.isFinite(original) || corrected !== original) {
            valid = false;
        }
    });
    return valid;
}

function updateStudentTotal(input, showValidationMessage = false) {
    const row = input.closest('tr');
    if (!row) return;
    validateGradeInputValue(input, showValidationMessage);
    const inputs = row.querySelectorAll('.grade-input');
    let total = 0;
    inputs.forEach((i) => { total += validateGradeInputValue(i, false); });
    row.querySelector('.total-grade').textContent = total;

    const letter = toLetter(total);
    const letterEl = row.querySelector('.grade-letter-display');
    letterEl.textContent = letter;
    letterEl.className = `grade-letter-display ${letter === '--' ? 'empty' : letter}`;
}

function toLetter(total) {
    if (total >= 90) return 'A+';
    if (total >= 80) return 'A';
    if (total >= 70) return 'B';
    if (total >= 60) return 'C';
    if (total >= 50) return 'D';
    if (total >= 0) return 'F';
    return '--';
}

function gradingScaleIdFromMarks(marks) {
    if (marks >= 90) return 1;
    if (marks >= 80) return 2;
    if (marks >= 70) return 3;
    if (marks >= 60) return 4;
    if (marks >= 50) return 5;
    return 6;
}

async function updateSingleStudent(rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;
    const studentUsername = row.dataset.studentUsername || '';
    const classId = Number(row.dataset.classId || '0');
    const subject = state.currentAssessmentContext.subject || String(document.getElementById('gradingSubjectSelect')?.value || '').trim();
    if (!subject) {
        showRowActionMessage(row, 'Select subject first.', 'error', true);
        return;
    }
    const scores = {};
    const rowIsValid = validateGradeInputsInRow(row, true);
    if (!rowIsValid) {
        updateStudentTotal(row.querySelector('.grade-input'), false);
        return;
    }
    row.querySelectorAll('.grade-input').forEach((input) => {
        const itemId = String(input.dataset.itemId || '');
        if (!itemId) return;
        const value = validateGradeInputValue(input, false);
        scores[itemId] = value;
    });
    if (Object.keys(scores).length === 0) {
        showRowActionMessage(row, 'No assessment scores to save.', 'error', true);
        return;
    }
    if (!studentUsername || !classId) {
        showRowActionMessage(row, 'Invalid student/class mapping in selected grade.', 'error', true);
        return;
    }

    try {
        await apiPost(API.saveAssessmentScores, {
            class_id: classId,
            term: state.currentAssessmentContext.term || 'Term1',
            subject,
            rows: [{ student_username: studentUsername, scores }]
        });
        showRowActionMessage(row, `Saved grade for ${studentUsername}.`, 'success', true);
        // Keep row-level feedback visible near the clicked Update button.
    } catch (err) {
        showRowActionMessage(row, err.message, 'error', true);
    }
}

async function submitAllGrades() {
    const subject = state.currentAssessmentContext.subject || String(document.getElementById('gradingSubjectSelect')?.value || '').trim();
    if (!subject) {
        showGradingBulkMessage('Select subject first.', 'error', true);
        return;
    }

    const rows = [...document.querySelectorAll('#gradingTableBody tr')];
    const payloadRows = [];
    for (const row of rows) {
        const studentUsername = row.dataset.studentUsername || '';
        const classId = Number(row.dataset.classId || '0');
        if (!studentUsername || !classId) continue;
        const rowIsValid = validateGradeInputsInRow(row, true);
        if (!rowIsValid) {
            showGradingBulkMessage(`Please correct marks for ${studentUsername}. Score cannot exceed item limit.`, 'error', true);
            return;
        }
        const scores = {};
        row.querySelectorAll('.grade-input').forEach((input) => {
            const itemId = String(input.dataset.itemId || '');
            if (!itemId) return;
            scores[itemId] = validateGradeInputValue(input, false);
        });
        payloadRows.push({ student_username: studentUsername, scores });
    }
    if (!payloadRows.length) {
        showGradingBulkMessage('No students to submit.', 'error', true);
        return;
    }

    try {
        await apiPost(API.saveAssessmentScores, {
            class_id: state.currentAssessmentContext.class_id,
            term: state.currentAssessmentContext.term || 'Term1',
            subject,
            rows: payloadRows
        });
    } catch (err) {
        showGradingBulkMessage(`Failed to submit grades: ${err.message}`, 'error', true);
        return;
    }
    showGradingBulkMessage('All grades submitted successfully.', 'success', true);
    await loadStudentsForGrading(false);
}

function openAnnouncementModal() {
    const titleEl = document.getElementById('announcementTitle');
    const contentEl = document.getElementById('announcementContent');
    const targetModeEl = document.getElementById('announcementTargetMode');
    if (titleEl) titleEl.value = '';
    if (contentEl) contentEl.value = '';
    if (targetModeEl) targetModeEl.value = 'single';
    const classSelect = document.getElementById('announcementClassSelect');
    if (classSelect) classSelect.value = '';
    const multi = document.getElementById('announcementClassMultiSelect');
    if (multi) {
        [...multi.options].forEach((o) => { o.selected = false; });
    }
    onAnnouncementTargetModeChange();
    removeAnnouncementFile();
    document.getElementById('announcementModal')?.classList.add('show');
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal')?.classList.remove('show');
}

async function saveAnnouncement() {
    const targetMode = String(document.getElementById('announcementTargetMode')?.value || 'single').trim();
    const classId = String(document.getElementById('announcementClassSelect')?.value || '').trim();
    const classMulti = document.getElementById('announcementClassMultiSelect');
    const classIds = classMulti
        ? [...classMulti.selectedOptions].map((o) => String(o.value || '').trim()).filter(Boolean)
        : [];
    const title = String(document.getElementById('announcementTitle')?.value || '').trim();
    const content = String(document.getElementById('announcementContent')?.value || '').trim();
    const fileInput = document.getElementById('announcementFile');

    if (!title || !content) {
        showPageMessage('Please enter title and content.', 'error', 'announcements');
        return;
    }
    if (targetMode === 'single' && !classId) {
        showPageMessage('Please choose one class.', 'error', 'announcements');
        return;
    }
    if (targetMode === 'multiple' && classIds.length === 0) {
        showPageMessage('Please choose one or more classes.', 'error', 'announcements');
        return;
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    formData.append('class_id', classId);
    formData.append('target_mode', targetMode);
    if (classIds.length) {
        formData.append('class_ids', classIds.join(','));
    }
    if (fileInput && fileInput.files && fileInput.files[0]) {
        formData.append('attachment', fileInput.files[0]);
    }

    try {
        const res = await fetch(API.createAnnouncement, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const body = await parseResponseJson(res);
        if (!res.ok || !body.success) {
            throw new Error(body.message || 'Request failed');
        }
        await refreshTeacherData();
        const annRes = await apiGet(`${API.announcements}?page=1&limit=20`);
        state.announcements = annRes.announcements || [];
        loadAnnouncements();
        closeAnnouncementModal();
        showPageMessage('Announcement posted successfully.', 'success', 'announcements');
    } catch (err) {
        showPageMessage(err.message || 'Failed to post announcement.', 'error', 'announcements');
    }
}

function onAnnouncementTargetModeChange() {
    const mode = String(document.getElementById('announcementTargetMode')?.value || 'single').trim();
    const singleGroup = document.getElementById('announcementSingleClassGroup');
    const multiGroup = document.getElementById('announcementMultiClassGroup');
    if (singleGroup) {
        singleGroup.style.display = mode === 'single' ? '' : 'none';
    }
    if (multiGroup) {
        multiGroup.style.display = mode === 'multiple' ? '' : 'none';
    }
}

function removeAnnouncementFile() {
    const input = document.getElementById('announcementFile');
    const preview = document.getElementById('announcementFilePreview');
    const area = document.getElementById('announcementFileUploadArea');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
    if (area) area.style.display = 'block';
}

function openAnnouncementFilePicker() {
    const input = document.getElementById('announcementFile');
    if (input) input.click();
}

function onAnnouncementFileSelected(event) {
    const file = event?.target?.files?.[0];
    const preview = document.getElementById('announcementFilePreview');
    const area = document.getElementById('announcementFileUploadArea');
    const nameEl = document.getElementById('announcementFileName');
    if (!file) {
        if (preview) preview.style.display = 'none';
        if (area) area.style.display = 'block';
        return;
    }
    if (nameEl) nameEl.textContent = file.name;
    if (preview) preview.style.display = 'flex';
    if (area) area.style.display = 'none';
}

function openResourceModal() {
    const titleEl = document.getElementById('resourceTitle');
    const descEl = document.getElementById('resourceDescription');
    const dueEl = document.getElementById('resourceDueDate');
    const typeEl = document.getElementById('resourceType');
    const modeEl = document.getElementById('resourceTargetMode');
    const classEl = document.getElementById('resourceClassSelect');
    const multiEl = document.getElementById('resourceClassMultiSelect');
    if (titleEl) titleEl.value = '';
    if (descEl) descEl.value = '';
    if (dueEl) dueEl.value = '';
    if (typeEl) typeEl.value = 'resource';
    if (modeEl) modeEl.value = 'single';
    if (classEl) classEl.value = '';
    if (multiEl) {
        [...multiEl.options].forEach((o) => { o.selected = false; });
    }
    onResourceTargetModeChange();
    removeResourceFile();
    document.getElementById('resourceModal')?.classList.add('show');
}

function closeResourceModal() {
    document.getElementById('resourceModal')?.classList.remove('show');
}

function openResourceFilePicker() {
    const input = document.getElementById('resourceFile');
    if (input) input.click();
}

function onResourceTargetModeChange() {
    const mode = String(document.getElementById('resourceTargetMode')?.value || 'single').trim();
    const singleGroup = document.getElementById('resourceSingleClassGroup');
    const multiGroup = document.getElementById('resourceMultiClassGroup');
    if (singleGroup) singleGroup.style.display = mode === 'single' ? '' : 'none';
    if (multiGroup) multiGroup.style.display = mode === 'multiple' ? '' : 'none';
}

function removeResourceFile() {
    const input = document.getElementById('resourceFile');
    const preview = document.getElementById('resourceFilePreview');
    const area = document.getElementById('resourceUploadArea');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
    if (area) area.style.display = 'block';
}

function onResourceFileSelected(event) {
    const file = event?.target?.files?.[0];
    const preview = document.getElementById('resourceFilePreview');
    const area = document.getElementById('resourceUploadArea');
    const nameEl = document.getElementById('resourceFileName');
    if (!file) {
        if (preview) preview.style.display = 'none';
        if (area) area.style.display = 'block';
        return;
    }
    if (nameEl) nameEl.textContent = file.name;
    if (preview) preview.style.display = 'flex';
    if (area) area.style.display = 'none';
}

async function saveResource() {
    const targetMode = String(document.getElementById('resourceTargetMode')?.value || 'single').trim();
    const classId = String(document.getElementById('resourceClassSelect')?.value || '').trim();
    const multi = document.getElementById('resourceClassMultiSelect');
    const classIds = multi ? [...multi.selectedOptions].map((o) => String(o.value || '').trim()).filter(Boolean) : [];
    const title = String(document.getElementById('resourceTitle')?.value || '').trim();
    const description = String(document.getElementById('resourceDescription')?.value || '').trim();
    const resourceType = String(document.getElementById('resourceType')?.value || 'resource').trim();
    const dueDate = String(document.getElementById('resourceDueDate')?.value || '').trim();
    const fileInput = document.getElementById('resourceFile');
    const file = fileInput?.files?.[0];

    if (!title) {
        showPageMessage('Please enter resource title.', 'error', 'resources');
        return;
    }
    if (targetMode === 'single' && !classId) {
        showPageMessage('Please choose one class.', 'error', 'resources');
        return;
    }
    if (targetMode === 'multiple' && classIds.length === 0) {
        showPageMessage('Please choose one or more classes.', 'error', 'resources');
        return;
    }
    if (!file) {
        showPageMessage('Please choose a file to upload.', 'error', 'resources');
        return;
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', description);
    formData.append('resource_type', resourceType);
    formData.append('due_date', dueDate);
    formData.append('target_mode', targetMode);
    formData.append('class_id', classId);
    if (classIds.length) {
        formData.append('class_ids', classIds.join(','));
    }
    formData.append('resource_file', file);

    try {
        const res = await fetch(API.uploadResource, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const body = await parseResponseJson(res);
        if (!res.ok || !body.success) {
            throw new Error(body.message || 'Failed to upload resource');
        }
        closeResourceModal();
        await loadResources();
        showPageMessage('Resource uploaded successfully.', 'success', 'resources');
    } catch (err) {
        showPageMessage(err.message || 'Failed to upload resource.', 'error', 'resources');
    }
}

async function generateReport() {
    const classId = Number(document.getElementById('classSelectReports')?.value || 0);
    const section = String(document.getElementById('sectionSelectReports')?.value || '').trim();
    if (!classId) {
        showPageMessage('Please choose a class before generating report.', 'error', 'reports');
        return;
    }

    try {
        const data = await apiPost(API.generateReport, {
            class_id: classId,
            section
        });
        showPageMessage(`Report generated successfully. Report ID: ${data.report_id}`, 'success', 'reports');
        loadReports();
    } catch (err) {
        showPageMessage(`Failed to generate report: ${err.message}`, 'error', 'reports');
    }
}

function downloadFile(filename) {
    if (!filename) return;
    window.open(filename, '_blank', 'noopener');
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

function capitalizeText(v) {
    const s = String(v || '').trim();
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function refreshTeacherData() {
    try {
        const dashboardRes = await apiGet(`${API.dashboard}`);
        state.dashboard = dashboardRes;
        state.classes = dashboardRes.assigned_classes || [];
        await loadDerivedTeacherMetrics();
        renderDashboard();
        populateClassSelects();
    } catch (_) {}
}

function showPageMessage(message, type = 'info', targetPage = '') {
    const el = document.getElementById('teacherPageMessage');
    if (!el) return;
    const activePage = document.querySelector('.page.active')?.id || '';
    if (targetPage && activePage !== targetPage) {
        // Switch to page so message appears in the correct workflow context.
        switchPage(targetPage);
    }

    el.classList.remove('teacher-msg-success', 'teacher-msg-error', 'teacher-msg-info');
    if (type === 'success') {
        el.classList.add('teacher-msg-success');
    } else if (type === 'error') {
        el.classList.add('teacher-msg-error');
    } else {
        el.classList.add('teacher-msg-info');
    }
    el.textContent = message;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function switchGradingStep(step) {
    const tabStep2 = document.getElementById('gradingTabStep2');
    const tabStep1 = document.getElementById('gradingTabStep1');
    const panelStep2 = document.getElementById('gradingStepTwoPanel');
    const panelStep1 = document.getElementById('gradingStepOnePanel');

    const isStep2 = step === 'step2';
    if (tabStep2) tabStep2.classList.toggle('active', isStep2);
    if (tabStep1) tabStep1.classList.toggle('active', !isStep2);
    if (panelStep2) panelStep2.classList.toggle('active', isStep2);
    if (panelStep1) panelStep1.classList.toggle('active', !isStep2);
}

function showAssessmentStructureMessage(message, type = 'success') {
    const box = document.getElementById('assessmentStructureMessage');
    if (!box) return;
    showInlineMessage(box, message, type, true, 2200);
}

function showGradingScoresMessage(message, type = 'success', visible = true) {
    const box = document.getElementById('gradingScoresMessage');
    if (!box) return;
    showInlineMessage(box, message, type, visible, 2200);
}

function showGradingBulkMessage(message, type = 'success', visible = true) {
    const box = document.getElementById('gradingBulkMessage');
    if (!box) return;
    showInlineMessage(box, message, type, visible, 2200);
}

function showRowActionMessage(row, message, type = 'success', visible = true) {
    if (!row) return;
    const box = row.querySelector('.row-action-message');
    if (!box) return;
    showInlineMessage(box, message, type, visible, 2200);
}

function showInlineMessage(box, message, type = 'success', visible = true, hideAfterMs = 2200) {
    if (!box) return;
    if (box.__msgTimer) {
        clearTimeout(box.__msgTimer);
        box.__msgTimer = null;
    }
    if (!visible || !message) {
        box.classList.remove('teacher-inline-success', 'teacher-inline-error', 'teacher-inline-info');
        box.textContent = '';
        box.style.display = 'none';
        return;
    }
    box.classList.remove('teacher-inline-success', 'teacher-inline-error', 'teacher-inline-info');
    if (type === 'success') {
        box.classList.add('teacher-inline-success');
    } else if (type === 'error') {
        box.classList.add('teacher-inline-error');
    } else {
        box.classList.add('teacher-inline-info');
    }
    box.textContent = message;
    box.style.display = 'block';
    if (hideAfterMs > 0) {
        box.__msgTimer = setTimeout(() => {
            box.classList.remove('teacher-inline-success', 'teacher-inline-error', 'teacher-inline-info');
            box.textContent = '';
            box.style.display = 'none';
            box.__msgTimer = null;
        }, hideAfterMs);
    }
}

function buildAssessmentItemRow(item) {
    const row = document.createElement('div');
    row.className = 'assessment-item';
    row.innerHTML = `
        <input type="text" placeholder="Assessment Type" class="assessment-name" value="${escapeHtml(item?.name || '')}">
        <input type="number" placeholder="Points" class="assessment-points" min="0" onchange="updateTotalPoints()" value="${escapeHtml(String(item?.points ?? ''))}">
        <button type="button" class="btn-remove-item" onclick="removeAssessmentItem(this)">Remove</button>
    `;
    return row;
}

async function onStepOneGradeChange() {
    const grade = document.getElementById('classSelectGrading')?.value || '';
    const sectionSelect = document.getElementById('sectionSelectGrading');
    const subjectSelect = document.getElementById('subjectSelectGrading');
    const editor = document.getElementById('assessmentEditorPanel');
    const msg = document.getElementById('assessmentStructureMessage');
    if (msg) msg.style.display = 'none';
    if (editor) editor.style.display = 'none';
    if (!subjectSelect || !sectionSelect) return;

    if (!grade) {
        sectionSelect.innerHTML = '<option value="">-- Choose grade first --</option>';
        subjectSelect.innerHTML = '<option value="">-- Choose grade first --</option>';
        return;
    }

    populateSectionsForStepOne(grade);
    subjectSelect.innerHTML = '<option value="">-- Choose grade and section first --</option>';
}

async function onStepOneSectionChange() {
    const grade = document.getElementById('classSelectGrading')?.value || '';
    const section = document.getElementById('sectionSelectGrading')?.value || '';
    const subjectSelect = document.getElementById('subjectSelectGrading');
    const editor = document.getElementById('assessmentEditorPanel');
    if (!subjectSelect) return;
    if (editor) editor.style.display = 'none';

    if (!grade || !section) {
        subjectSelect.innerHTML = '<option value="">-- Choose grade and section first --</option>';
        return;
    }

    const selectedClass = classForSelectedGradeAndSection(grade, section);
    if (!selectedClass?.class_id) {
        subjectSelect.innerHTML = '<option value="">-- No assigned class for grade/section --</option>';
        return;
    }

    try {
        const data = await apiGet(`${API.classSubjects}?class_id=${selectedClass.class_id}`);
        const subjects = [...new Set((data.subjects || []).filter(Boolean))];
        subjectSelect.innerHTML = '<option value="">-- Choose subject --</option>';
        subjects.forEach((s) => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            subjectSelect.appendChild(opt);
        });
        if (!subjects.length) {
            subjectSelect.innerHTML = '<option value="">-- No subject assigned for this class --</option>';
        }
    } catch (_) {
        subjectSelect.innerHTML = '<option value="">-- No subject assigned for this class --</option>';
    }
}

async function onStepOneSubjectChange() {
    const grade = document.getElementById('classSelectGrading')?.value || '';
    const section = document.getElementById('sectionSelectGrading')?.value || '';
    const subject = document.getElementById('subjectSelectGrading')?.value || '';
    const editor = document.getElementById('assessmentEditorPanel');
    const container = document.getElementById('assessmentItemsContainer');
    const msg = document.getElementById('assessmentStructureMessage');
    if (msg) msg.style.display = 'none';
    if (!editor || !container) return;

    if (!grade || !section || !subject) {
        editor.style.display = 'none';
        return;
    }

    const selectedClass = classForSelectedGradeAndSection(grade, section);
    if (!selectedClass?.class_id) {
        showAssessmentStructureMessage('Class not found for selected grade/section.', 'error');
        editor.style.display = 'none';
        return;
    }

    container.innerHTML = '';
    try {
        const data = await apiGet(`${API.getAssessmentStructure}?class_id=${selectedClass.class_id}&subject=${encodeURIComponent(subject)}&term=Term1`);
        if (data.exists && Array.isArray(data.items) && data.items.length) {
            data.items.forEach((item) => container.appendChild(buildAssessmentItemRow({ name: item.name, points: item.max_points })));
            showAssessmentStructureMessage('Loaded existing structure from database. You can update and save.', 'info');
        } else {
            container.appendChild(buildAssessmentItemRow({ name: '', points: '' }));
        }
    } catch (_) {
        container.appendChild(buildAssessmentItemRow({ name: '', points: '' }));
    }
    editor.style.display = 'block';
    updateTotalPoints();
}
