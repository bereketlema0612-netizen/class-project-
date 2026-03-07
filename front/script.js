// Student portal (real API version)

const API = {
    profile: '../backend/student/get_students_profile.php',
    dashboard: '../backend/student/get_student_dashboard.php',
    grades: '../backend/student/get_student_grades.php',
    schedule: '../backend/student/get_student_schedule.php',
    announcements: '../backend/student/get_announcements.php',
    resources: '../backend/student/get_resources.php',
    submissions: '../backend/student/get_submissions.php',
    submitAssignment: '../backend/student/submit_assignment.php'
};

const state = {
    profile: null,
    dashboard: null,
    grades: [],
    schedule: [],
    announcements: [],
    assignments: [],
    submissions: [],
    selectedSemester: '',
    hasViewedGrades: false
};

document.addEventListener('DOMContentLoaded', async () => {
    const user = getCurrentUser();
    if (!user || user.role !== 'student') {
        window.location.href = 'login.html';
        return;
    }

    bindNav();
    bindActions();

    await loadAll();
    renderAll();
});

async function apiGet(url) {
    const res = await fetch(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include'
    });

    let body = null;
    try {
        const text = await res.text();
        body = text ? JSON.parse(text) : null;
    } catch (_) {
        throw new Error(`Invalid server response from ${url}`);
    }

    if (!body || typeof body !== 'object') {
        throw new Error(`Empty or invalid JSON body from ${url}`);
    }

    if (!res.ok || !body.success) {
        if (res.status === 401 || res.status === 403) {
            localStorage.removeItem('currentUser');
            window.location.href = 'login.html';
            return null;
        }
        throw new Error(body.message || `Request failed (${url})`);
    }
    return body.data || {};
}

async function loadAll() {
    try {
        const [profile, dashboard, grades, schedule, announcements, resources, submissions] = await Promise.all([
            apiGet(API.profile),
            apiGet(API.dashboard),
            apiGet(API.grades),
            apiGet(API.schedule),
            apiGet(API.announcements),
            apiGet(API.resources),
            apiGet(API.submissions)
        ]);

        if (!profile || !dashboard || !grades || !schedule || !announcements || !resources || !submissions) return;

        state.profile = profile.profile || null;
        state.dashboard = dashboard || null;
        state.grades = grades.grades || [];
        state.schedule = schedule.enrollments || [];
        state.announcements = announcements.announcements || [];
        state.assignments = resources.resources || [];
        state.submissions = submissions.submissions || [];
    } catch (err) {
        const msg = err && typeof err === 'object' && 'message' in err
            ? String(err.message)
            : String(err || 'Unknown error');
        alert('Failed to load student data: ' + msg);
    }
}

function renderAll() {
    renderUserHeader();
    renderDashboard();
    renderGradesPrompt();
    renderSchedule();
    renderAnnouncements();
    renderAssessments();
    renderResources();
    renderProfile();
}

function renderUserHeader() {
    if (!state.profile) return;
    setText('userName', state.profile.full_name || state.profile.username);
    setText('userClass', `Grade ${state.profile.grade_level || '-'}`);
    setText('gradeStudentName', state.profile.full_name || state.profile.username);
}

function renderDashboard() {
    const stats = state.dashboard?.statistics || {};
    const cgpaReady = Boolean(stats.cgpa_ready);
    const cgpaValue = cgpaReady && stats.overall_cgpa !== null && stats.overall_cgpa !== undefined
        ? Number(stats.overall_cgpa).toFixed(2)
        : 'Pending';
    const pendingAssignments = (state.assignments || []).filter((a) => {
        const due = new Date(a.due_date || '');
        return !isNaN(due.getTime()) && due.getTime() >= new Date().setHours(0, 0, 0, 0);
    }).length;

    setText('dashboardGPA', cgpaValue);
    setText('dashboardPending', String(pendingAssignments));
    setText('dashboardAnnouncements', String(state.announcements.length));

    const recentGradesContainer = document.getElementById('recentGradesContainer');
    if (recentGradesContainer) {
        recentGradesContainer.innerHTML = '';
        const recent = [...state.grades].slice(0, 5);
        if (!recent.length) {
            recentGradesContainer.innerHTML = '<div class="grade-item"><div class="grade-info"><p class="subject">No grades yet</p><p class="assessment">Empty</p></div><span class="grade-badge">-</span></div>';
        }
        recent.forEach((g) => {
            const item = document.createElement('div');
            item.className = 'grade-item';
            item.innerHTML = `
                <div class="grade-info">
                    <p class="subject">${escapeHtml(g.subject_name || g.subject || '-')}</p>
                    <p class="assessment">${escapeHtml(g.term || '-')}</p>
                </div>
                <span class="grade-badge ${escapeHtml(g.letter_grade || '-')}">${escapeHtml(g.letter_grade || '-')}</span>
            `;
            recentGradesContainer.appendChild(item);
        });
    }

    const recentAnnouncementsContainer = document.getElementById('recentAnnouncementsContainer');
    if (recentAnnouncementsContainer) {
        recentAnnouncementsContainer.innerHTML = '';
        const recentAnnouncements = state.announcements.slice(0, 5);
        if (!recentAnnouncements.length) {
            recentAnnouncementsContainer.innerHTML = '<div class="announcement-item"><div class="announcement-header"><p class="announcement-title">No announcements</p><p class="announcement-date">-</p></div><p class="announcement-text">Empty</p></div>';
        }
        recentAnnouncements.forEach((a) => {
            const item = document.createElement('div');
            item.className = 'announcement-item';
            const attachmentHtml = buildAnnouncementAttachmentHtml(a);
            item.innerHTML = `
                <div class="announcement-header">
                    <p class="announcement-title">${escapeHtml(a.title || '-')}</p>
                    <p class="announcement-date">${formatDate(a.created_at)}</p>
                </div>
                <p class="announcement-text">${escapeHtml(a.content || a.message || '')}</p>
                ${attachmentHtml}
            `;
            recentAnnouncementsContainer.appendChild(item);
        });
    }

    const upcomingEventsContainer = document.getElementById('upcomingEventsContainer');
    if (upcomingEventsContainer) {
        upcomingEventsContainer.innerHTML = '';
        const events = state.dashboard?.upcoming_schedule || [];
        if (!events.length) {
            upcomingEventsContainer.innerHTML = '<div class="event-item"><div class="event-date"><p class="date-day">-</p><p class="date-month">-</p></div><div class="event-info"><p class="event-name">No upcoming classes</p><p class="event-time">Empty</p></div></div>';
        }
        events.slice(0, 5).forEach((e) => {
            const day = (e.day || '').substring(0, 3) || 'Day';
            const event = document.createElement('div');
            event.className = 'event-item';
            event.innerHTML = `
                <div class="event-date">
                    <p class="date-day">${escapeHtml(day)}</p>
                    <p class="date-month">${escapeHtml((e.start_time || '').substring(0, 5))}</p>
                </div>
                <div class="event-info">
                    <p class="event-name">${escapeHtml(e.subject_name || '-')}</p>
                    <p class="event-time">${escapeHtml((e.class_name || '') + ' ' + (e.section ? `(${e.section})` : ''))}</p>
                </div>
            `;
            upcomingEventsContainer.appendChild(event);
        });
    }
}

function renderGradesPrompt(message = 'Select semester and click "View Grades" to load the grade list.') {
    const container = document.getElementById('subjectsContainer');
    if (!container) return;
    container.innerHTML = `
        <div class="card subject-section">
            <div class="subject-header"><div><h3>${escapeHtml(message)}</h3></div></div>
        </div>
    `;
}

function renderGrades(semesterValue) {
    const container = document.getElementById('subjectsContainer');
    if (!container) return;
    container.innerHTML = '';
    const semesterFilter = String(semesterValue || '').toLowerCase();
    if (!semesterFilter) {
        renderGradesPrompt('Please select a semester first.');
        return;
    }

    const gradeLevel = state.profile?.grade_level ? `Grade ${state.profile.grade_level}` : 'Grade';
    const title = document.getElementById('gradesPageTitle');
    if (title) {
        title.textContent = `Current Grades - ${gradeLevel} (${semesterFilter === 'second' ? 'Second Semester' : 'First Semester'})`;
    }

    const isFirstSemester = semesterFilter === 'first';
    const filteredGrades = (state.grades || []).filter((g) => {
        const termNum = Number(String(g.term || '').replace(/[^0-9]/g, '')) || 0;
        if (!termNum) return true;
        return isFirstSemester ? termNum <= 2 : termNum >= 3;
    });

    const grouped = {};
    filteredGrades.forEach((g) => {
        const key = g.subject_name || g.subject || 'Unknown';
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(g);
    });

    if (!Object.keys(grouped).length) {
        container.innerHTML = '<div class="card subject-section"><div class="subject-header"><div><h3>No grades found for this semester</h3></div></div></div>';
        return;
    }

    Object.keys(grouped).sort().forEach((subject) => {
        const section = document.createElement('div');
        section.className = 'card subject-section collapsed';

        const assessmentColumns = [];
        grouped[subject].forEach((g) => {
            (g.assessment_items || []).forEach((item) => {
                const key = String(item.id || item.name || '');
                if (!assessmentColumns.some((col) => col.key === key)) {
                    assessmentColumns.push({
                        key,
                        name: item.name || 'Assessment',
                        max_points: Number(item.max_points || 0),
                        order: Number(item.order || 0)
                    });
                }
            });
        });
        assessmentColumns.sort((a, b) => a.order - b.order || a.name.localeCompare(b.name));

        const rows = grouped[subject].map((g) => `
            <tr>
                <td>${escapeHtml(g.term || '-')}</td>
                <td>${escapeHtml(g.academic_year || '-')}</td>
                ${assessmentColumns.map((col) => {
                    const item = (g.assessment_items || []).find((entry) => String(entry.id || entry.name || '') === col.key);
                    if (!item) return '<td>-</td>';
                    if (item.score === null || item.score === undefined || item.score === '') return '<td></td>';
                    return `<td>${escapeHtml(String(item.score))}</td>`;
                }).join('')}
                <td>${g.is_complete ? escapeHtml(String(g.marks ?? '')) : ''}</td>
                <td>${g.is_complete ? escapeHtml(g.letter_grade || '') : ''}</td>
                <td>${escapeHtml(g.teacher_name || '-')}</td>
            </tr>
        `).join('');

        const assessmentHeaderHtml = assessmentColumns.map((col) => `
            <th>${escapeHtml(col.name)}${col.max_points > 0 ? `<br><small>(/${escapeHtml(String(col.max_points))})</small>` : ''}</th>
        `).join('');

        section.innerHTML = `
            <div class="subject-header">
                <div><h3>${escapeHtml(subject)}</h3></div>
                <span class="help-icon">▼</span>
            </div>
            <div class="table-content hidden">
                <table class="assessments-table">
                    <thead>
                        <tr>
                            <th>Term</th>
                            <th>Academic Year</th>
                            ${assessmentHeaderHtml}
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;

        section.querySelector('.subject-header').addEventListener('click', () => {
            section.classList.toggle('collapsed');
            section.querySelector('.table-content').classList.toggle('hidden');
        });

        container.appendChild(section);
    });
}

function renderSchedule() {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const slots = {};
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    state.schedule.forEach((enroll) => {
        (enroll.schedule || []).forEach((s) => {
            const time = `${(s.start_time || '').substring(0, 5)} - ${(s.end_time || '').substring(0, 5)}`;
            if (!slots[time]) slots[time] = {};
            if (!slots[time][s.day]) slots[time][s.day] = [];
            const location = formatLocationLabel(s.room_number);
            const teacher = String(s.session_teacher_name || '').trim() || 'TBS';
            slots[time][s.day].push(
                `${escapeHtml(s.subject_name || '-')}` +
                `${location ? `<br><small>${escapeHtml(location)}</small>` : ''}` +
                `<br><small>Teacher: ${escapeHtml(teacher)}</small>`
            );
        });
    });

    const times = Object.keys(slots).sort();
    if (times.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No schedule found</td></tr>';
        return;
    }

    times.forEach((time) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="time">${escapeHtml(time)}</td>`;
        days.forEach((d) => {
            const cell = document.createElement('td');
            cell.className = 'class-cell';
            cell.innerHTML = (slots[time][d] || ['-']).join('<hr style="border:none;border-top:1px solid #eee;">');
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

function renderAnnouncements() {
    const container = document.getElementById('announcementsContainer');
    if (container) {
        container.innerHTML = '';
        state.announcements.forEach((a) => {
            const card = document.createElement('div');
            card.className = 'card announcement-card';
            const attachmentHtml = buildAnnouncementAttachmentHtml(a);
            card.innerHTML = `
                <div class="announcement-header">
                    <h3>${escapeHtml(a.title || '-')}</h3>
                    <p class="announcement-date">${formatDate(a.created_at)}</p>
                </div>
                <p class="announcement-category">${escapeHtml(a.priority || 'Normal')}</p>
                <p class="announcement-body">${escapeHtml(a.content || a.message || '')}</p>
                ${attachmentHtml}
            `;
            container.appendChild(card);
        });
    }
    setText('announcementBadge', String(state.announcements.length));
}

function renderAssessments() {
    const container = document.getElementById('downloadAssignmentsContainer');
    if (container) {
        container.innerHTML = '';
        state.assignments.forEach((a) => {
            const card = document.createElement('div');
            card.className = 'download-card';
            const href = `../backend/${encodeURI(String(a.file_path || '').replace(/^\/+/, ''))}`;
            card.innerHTML = `
                <div class="download-header">
                    <i class="fas fa-file"></i>
                    <div>
                        <h4>${escapeHtml(a.title || a.file_name || 'Resource')}</h4>
                        <p class="teacher">Teacher: ${escapeHtml(a.teacher_name || '-')}</p>
                    </div>
                </div>
                <p class="subject-tag">${escapeHtml(capitalizeText(a.resource_type || 'resource'))}</p>
                <p class="due-date">Due: ${escapeHtml(a.due_date || 'No deadline')}</p>
                <p class="due-date">${escapeHtml(a.description || '-')}</p>
                <button class="btn btn-secondary" onclick="downloadFile('${escapeHtml(href)}', '${escapeHtml(a.file_name || a.title || 'file')}')">
                    <i class="fas fa-download"></i> Download Assignment
                </button>
            `;
            container.appendChild(card);
        });
        if (!state.assignments.length) {
            container.innerHTML = '<div class="download-card"><p>No assessment resources yet.</p></div>';
        }
    }

    populateAssessmentDropdown();
    updateTeacherDropdown();

    const history = document.getElementById('submissionHistoryContainer');
    if (history) {
        history.innerHTML = '';
        if (!state.submissions.length) {
            history.innerHTML = '<div class="history-item submitted"><div class="history-header"><div><h4>No submissions yet</h4><p class="history-date">Submit your first assignment</p></div><span class="status-badge submitted">Empty</span></div></div>';
        } else {
            state.submissions.forEach((s) => {
                const href = `../backend/${encodeURI(String(s.file_path || '').replace(/^\/+/, ''))}`;
                const item = document.createElement('div');
                const status = String(s.status || 'submitted').toLowerCase();
                item.className = `history-item ${status}`;
                item.innerHTML = `
                    <div class="history-header">
                        <div>
                            <h4>${escapeHtml(s.resource_title || '-')}</h4>
                            <p class="history-date">Submitted: ${escapeHtml(formatDate(s.submitted_at))}</p>
                        </div>
                        <span class="status-badge ${escapeHtml(status)}">${escapeHtml(capitalizeText(status))}</span>
                    </div>
                    <p class="history-feedback">Teacher: ${escapeHtml(s.teacher_name || '-')} | Type: ${escapeHtml(capitalizeText(s.resource_type || 'resource'))}</p>
                    <p class="history-feedback">${escapeHtml(s.notes || '-')}</p>
                    <a class="btn btn-secondary" href="${href}" target="_blank" rel="noopener">Open Submitted File</a>
                `;
                history.appendChild(item);
            });
        }
    }

    setText('assessmentBadge', String(state.assignments.length));
}

function renderResources() {
    const container = document.getElementById('resourcesContainer');
    if (!container) return;
    container.innerHTML = '';

    state.assignments.forEach((a) => {
        const card = document.createElement('div');
        card.className = 'card resource-card';
        const href = `../backend/${encodeURI(String(a.file_path || '').replace(/^\/+/, ''))}`;
        card.innerHTML = `
            <div class="resource-header">
                <i class="fas fa-file"></i>
                <div>
                    <h3>${escapeHtml(a.title || a.file_name || 'Resource')}</h3>
                    <p class="resource-subject">${escapeHtml(capitalizeText(a.resource_type || 'resource'))}</p>
                </div>
            </div>
            <p class="resource-teacher">Shared by: ${escapeHtml(a.teacher_name || '-')}</p>
            <p class="resource-date">${formatDate(a.created_at)}</p>
            <p class="resource-date">${escapeHtml(a.description || '-')}</p>
            <button class="btn btn-secondary" onclick="downloadFile('${escapeHtml(href)}', '${escapeHtml(a.file_name || a.title || 'resource')}')">Download</button>
        `;
        container.appendChild(card);
    });
    if (!state.assignments.length) {
        container.innerHTML = '<div class="card resource-card"><p>No resources available yet.</p></div>';
    }
}

function renderProfile() {
    if (!state.profile) return;
    setText('profileName', state.profile.full_name || '-');
    setText('profileGrade', `Grade ${state.profile.grade_level || '-'}`);
    setText('profileGPA', `Average: ${(state.dashboard?.statistics?.average_marks ?? 0)}`);
    setText('profileID', state.profile.student_id || '-');
    setText('profileDOB', state.profile.date_of_birth || '-');
    setText('profileEmail', state.profile.email || '-');
    setText('profileContact', state.profile.parent_phone || '-');
    setText('profileAddress', state.profile.address || '-');
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

    document.getElementById('logoutBtn')?.addEventListener('click', () => {
        localStorage.removeItem('currentUser');
        window.location.href = 'login.html';
    });

    document.getElementById('viewGradesBtn')?.addEventListener('click', () => {
        const semester = String(document.getElementById('semesterFilter')?.value || '').toLowerCase();
        state.selectedSemester = semester;
        if (!semester) {
            state.hasViewedGrades = false;
            renderGradesPrompt('Please select a semester, then click "View Grades".');
            return;
        }
        state.hasViewedGrades = true;
        renderGrades(semester);
    });
    document.getElementById('semesterFilter')?.addEventListener('change', () => {
        state.selectedSemester = String(document.getElementById('semesterFilter')?.value || '').toLowerCase();
        state.hasViewedGrades = false;
        renderGradesPrompt('Semester selected. Click "View Grades" to load the grade list.');
    });

    document.getElementById('fileUploadArea')?.addEventListener('click', () => {
        openSubmissionFilePicker();
    });
    document.getElementById('submissionFile')?.addEventListener('change', onSubmissionFileSelected);
    document.getElementById('assessmentSelect')?.addEventListener('change', updateTeacherDropdown);
}

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        grades: 'Grades',
        schedule: 'Schedule',
        announcements: 'Announcements',
        assessments: 'Assessments',
        resources: 'Resources',
        profile: 'Profile'
    };
    setText('pageTitle', titles[pageName] || 'Dashboard');

    if (pageName === 'grades') {
        if (state.hasViewedGrades && state.selectedSemester) {
            renderGrades(state.selectedSemester);
        } else {
            renderGradesPrompt();
        }
    } else if (pageName === 'dashboard') {
        renderDashboard();
    }
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function formatDate(v) {
    if (!v) return '-';
    const d = new Date(v);
    if (isNaN(d.getTime())) return String(v);
    return d.toLocaleDateString();
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

function escapeHtml(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function buildAnnouncementAttachmentHtml(a) {
    const path = String(a?.attachment_path || '').trim();
    if (!path) return '';
    const cleaned = path.replace(/^\/+/, '');
    const href = `../backend/${encodeURI(cleaned)}`;
    const label = String(a?.attachment_name || 'Download attachment');
    return `
        <div class="announcement-attachment" style="margin-top:8px;">
            <a href="${href}" target="_blank" rel="noopener" class="btn btn-secondary" style="padding:6px 10px;font-size:12px;">
                <i class="fas fa-paperclip"></i> ${escapeHtml(label)}
            </a>
        </div>
    `;
}

function downloadFile(path, name = 'file') {
    if (!path) {
        alert('File path is missing.');
        return;
    }
    window.open(path, '_blank', 'noopener');
}

async function submitAssignment() {
    const assessmentSelect = document.getElementById('assessmentSelect');
    const teacherSelect = document.getElementById('teacherSelect');
    const submissionFile = document.getElementById('submissionFile');
    const submissionNotes = document.getElementById('submissionNotes');

    const resourceId = Number(assessmentSelect?.value || 0);
    const file = submissionFile?.files?.[0];
    const notes = String(submissionNotes?.value || '').trim();

    if (!resourceId) {
        showSubmissionInlineMessage('Please select an assignment first.', 'error');
        return;
    }
    if (!file) {
        showSubmissionInlineMessage('Please choose a file to submit.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('resource_id', String(resourceId));
    formData.append('notes', notes);
    formData.append('submission_file', file);

    try {
        const res = await fetch(API.submitAssignment, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const text = await res.text();
        const body = text ? JSON.parse(text) : null;
        if (!res.ok || !body || !body.success) {
            throw new Error(body?.message || 'Failed to submit assignment');
        }
        showSubmissionInlineMessage('Assignment submitted successfully.', 'success');
        clearSubmissionForm();
        const submissions = await apiGet(API.submissions);
        if (submissions) {
            state.submissions = submissions.submissions || [];
            renderAssessments();
        }
    } catch (err) {
        showSubmissionInlineMessage('Failed to submit assignment: ' + (err?.message || 'Unknown error'), 'error');
    }
}

function clearSubmissionForm() {
    const assessmentSelect = document.getElementById('assessmentSelect');
    const teacherSelect = document.getElementById('teacherSelect');
    const submissionFile = document.getElementById('submissionFile');
    const submissionNotes = document.getElementById('submissionNotes');
    if (assessmentSelect) assessmentSelect.value = '';
    if (teacherSelect) teacherSelect.value = '';
    if (submissionFile) submissionFile.value = '';
    if (submissionNotes) submissionNotes.value = '';
    removeSelectedFile();
}

function toggleSubmissionForm(forceOpen = null) {
    const container = document.getElementById('submissionFormContainer');
    const button = document.getElementById('toggleSubmissionFormBtn');
    if (!container || !button) return;

    const shouldOpen = forceOpen === null ? container.style.display === 'none' : Boolean(forceOpen);
    container.style.display = shouldOpen ? 'flex' : 'none';
    button.innerHTML = shouldOpen
        ? '<i class="fas fa-times"></i> Hide Submission Form'
        : '<i class="fas fa-upload"></i> Load Submission Form';
}

function populateAssessmentDropdown() {
    const assessmentSelect = document.getElementById('assessmentSelect');
    if (!assessmentSelect) return;
    const current = String(assessmentSelect.value || '');
    assessmentSelect.innerHTML = '<option value="">-- Choose an assignment --</option>';
    (state.assignments || []).forEach((a) => {
        if (!isSubmittableResource(a)) return;
        const option = document.createElement('option');
        option.value = String(a.id);
        option.textContent = `${a.title || a.file_name || 'Assignment'}${a.due_date ? ` (Due: ${a.due_date})` : ''}`;
        option.dataset.teacher = String(a.teacher_name || '');
        option.dataset.teacherUsername = String(a.teacher_username || '');
        assessmentSelect.appendChild(option);
    });
    if (current && [...assessmentSelect.options].some((o) => o.value === current)) {
        assessmentSelect.value = current;
    }
}

function prefillSubmission(resourceId) {
    switchPage('assessments');
    toggleSubmissionForm(true);
    const assessmentSelect = document.getElementById('assessmentSelect');
    if (!assessmentSelect) return;
    assessmentSelect.value = String(resourceId);
    updateTeacherDropdown();
    document.getElementById('submissionFormContainer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function updateTeacherDropdown() {
    const assessmentSelect = document.getElementById('assessmentSelect');
    const teacherSelect = document.getElementById('teacherSelect');
    if (!assessmentSelect || !teacherSelect) return;
    const resourceId = Number(assessmentSelect.value || 0);
    teacherSelect.innerHTML = '<option value="">-- Select a teacher --</option>';
    if (!resourceId) return;
    const selected = (state.assignments || []).find((a) => Number(a.id) === resourceId);
    if (!selected) return;
    const opt = document.createElement('option');
    opt.value = String(selected.teacher_username || '');
    opt.textContent = String(selected.teacher_name || selected.teacher_username || 'Teacher');
    opt.selected = true;
    teacherSelect.appendChild(opt);
}

function openSubmissionFilePicker() {
    document.getElementById('submissionFile')?.click();
}

function onSubmissionFileSelected(event) {
    const file = event?.target?.files?.[0];
    const preview = document.getElementById('filePreview');
    const area = document.getElementById('fileUploadArea');
    const nameEl = document.getElementById('selectedFileName');
    if (!file) {
        if (preview) preview.style.display = 'none';
        if (area) area.style.display = 'block';
        return;
    }
    if (nameEl) nameEl.textContent = file.name;
    if (preview) preview.style.display = 'flex';
    if (area) area.style.display = 'none';
}

function removeSelectedFile() {
    const input = document.getElementById('submissionFile');
    const preview = document.getElementById('filePreview');
    const area = document.getElementById('fileUploadArea');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
    if (area) area.style.display = 'block';
}

function isSubmittableResource(resource) {
    const t = String(resource?.resource_type || '').toLowerCase();
    return t === 'assignment' || t === 'project' || t === 'worksheet';
}

function capitalizeText(v) {
    const s = String(v || '').trim();
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function showSubmissionInlineMessage(message, type = 'success', hideAfterMs = 2200) {
    const box = document.getElementById('submissionInlineMessage');
    if (!box) return;
    if (box.__msgTimer) {
        clearTimeout(box.__msgTimer);
        box.__msgTimer = null;
    }
    box.textContent = String(message || '');
    box.style.display = 'block';
    box.style.padding = '8px 10px';
    box.style.borderRadius = '8px';
    box.style.fontSize = '13px';
    box.style.fontWeight = '600';
    if (type === 'error') {
        box.style.background = '#fee2e2';
        box.style.color = '#991b1b';
        box.style.border = '1px solid #fecaca';
    } else {
        box.style.background = '#dcfce7';
        box.style.color = '#166534';
        box.style.border = '1px solid #bbf7d0';
    }
    if (hideAfterMs > 0) {
        box.__msgTimer = setTimeout(() => {
            box.style.display = 'none';
            box.textContent = '';
            box.__msgTimer = null;
        }, hideAfterMs);
    }
}

