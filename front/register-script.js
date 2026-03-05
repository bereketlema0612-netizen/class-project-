
const API = {
    logout: '../backend/auth/logout.php',
    dashboard: '../backend/registration/get_dashboard.php',
    registrations: '../backend/registration/get_pending_registration.php',
    registerStudent: '../backend/student/register_student.php',
    registerTeacher: '../backend/teacher/register_teacher.php',
    studentsDirectory: '../backend/student/get_student_directory.php',
    teachersDirectory: '../backend/teacher/get_all_teachers.php',
    classes: '../backend/registration/get_classes.php',
    subjects: '../backend/registration/get_subjects.php',
    curriculumSubjects: '../backend/registration/get_curriculum_subjects.php',
    assignStudent: '../backend/registration/assign_student.php',
    assignedStudents: '../backend/registration/get_assigned_students.php',
    removeStudentAssignment: '../backend/registration/remove_student_assignment.php',
    assignTeacher: '../backend/registration/assign_teacher.php',
    assignedTeachers: '../backend/registration/get_assigned_teachers.php',
    removeTeacherAssignment: '../backend/registration/remove_teacher_assignment.php',
    blockTeacher: '../backend/registration/block_teacher.php',
    classSchedules: '../backend/registration/get_class_schedules.php',
    saveClassSchedule: '../backend/registration/save_class_schedule.php',
    deleteClassSchedule: '../backend/registration/delete_class_schedule.php',
    studentProfile: '../backend/registration/get_student_profile.php',
    updateStudentProfile: '../backend/registration/update_student_profile.php',
    deleteStudent: '../backend/registration/delete_student.php',
    teacherProfile: '../backend/registration/get_teacher_profile.php',
    updateTeacherProfile: '../backend/registration/update_teacher_profile.php',
    registrationSlipDetails: '../backend/registration/get_registration_slip_details.php',
    promotionCertificateCandidates: '../backend/registration/get_promotion_certificate_candidates.php',
    promotionCertificateData: '../backend/registration/get_promotion_certificate_data.php',
    evaluatePromotions: '../backend/registration/evaluate_promotions.php',
    processPromotions: '../backend/registration/process_promotions.php',
    createCertificate: '../backend/registration/create_certificate.php',
    certificates: '../backend/registration/get_all_certificates.php',
    createPromotion: '../backend/registration/create_promotion.php',
    promotions: '../backend/registration/get_all_promotions.php'
};

const state = {
    user: null,
    dashboard: null,
    registrations: [],
    filteredRegistrations: [],
    students: [],
    teachers: [],
    classes: [],
    subjects: [],
    assignedStudents: [],
    assignedTeachers: [],
    classSchedules: [],
    selectedScheduleClassId: 0,
    fixedScheduleEntries: {},
    scheduleSubjects: [],
    certificates: [],
    promotions: [],
    promotionCertCandidates: [],
    filteredStudents: [],
    selectedStudentProfile: null,
    filteredTeachers: [],
    selectedTeacherProfile: null,
    allRecordsTab: 'students',
    studentRecordsLoaded: false,
    promotionEvaluation: {
        ready_students: [],
        not_ready_students: [],
        missing_grade_students: [],
        next_grade: null
    }
};

const SCHEDULE_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const SCHEDULE_SLOTS = [
    { key: 'P1', label: '1st Class', start: '08:00', end: '08:55', break: false },
    { key: 'P2', label: '2nd Class', start: '09:00', end: '09:55', break: false },
    { key: 'BR1', label: 'Break', start: '10:00', end: '10:15', break: true },
    { key: 'P3', label: '3rd Class', start: '10:15', end: '11:10', break: false },
    { key: 'P4', label: '4th Class', start: '11:15', end: '12:15', break: false },
    { key: 'LUNCH', label: 'Lunch / Break', start: '12:15', end: '13:30', break: true },
    { key: 'P5', label: '5th Class', start: '13:30', end: '14:25', break: false },
    { key: 'P6', label: '6th Class', start: '14:30', end: '15:25', break: false }
];

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || !['admin', 'director'].includes(state.user.role)) {
        window.location.href = 'admin_login.html';
        return;
    }

    updateUserInfo();
    setupEventListeners();
    await initializeDashboard();
});

async function initializeDashboard() {
    const tasks = await Promise.allSettled([
        loadDashboard(),
        loadRegistrations(),
        loadStudentsDirectory(),
        loadTeachersDirectory(),
        loadClasses(),
        loadSubjects(),
        loadAssignedStudents(),
        loadAssignedTeachers(),
        loadCertificates(),
        loadPromotions()
    ]);

    applyRegistrationFilters();
    loadStudentSelects();
    loadTeacherSelects();
    populateScheduleFormOptions();
    buildScheduleGradeSectionOptions();
    loadReports();
    loadIssuedSlipsTable();
    loadIssuedPromotionCertsTable();
    loadGraduatedStudentsTable();
    populateStudentGradeFilter();
    renderStudentRecordsTable();
    renderTeacherRecordsTable();
    toggleStudentStreamField();
    toggleProfileStreamField();
    toggleAssignTeacherStreamField();
    togglePromotionTargetStreamField();

    const failures = tasks.filter((t) => t.status === 'rejected');
    if (failures.length) {
        const firstError = failures[0].reason?.message || 'Unknown initialization error';
        alert(`Initialization partially failed (${failures.length} request(s)): ${firstError}`);
    }
}

function setupEventListeners() {
    document.querySelectorAll('.nav-item').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
            document.querySelector('.sidebar')?.classList.remove('open');
        });
    });

    document.getElementById('hamburgerBtn')?.addEventListener('click', () => {
        document.querySelector('.sidebar')?.classList.toggle('open');
    });

    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
        try {
            await fetch(API.logout, { method: 'POST', credentials: 'include' });
        } catch (_) {}
        localStorage.removeItem('currentUser');
        window.location.href = 'admin_login.html';
    });

    document.getElementById('searchInput')?.addEventListener('input', () => {
        applyRegistrationFilters();
    });
    document.getElementById('studentGradeFilter')?.addEventListener('change', () => {
        prepareStudentRecordsAwaitingLoad();
    });
    document.getElementById('studentSearchInput')?.addEventListener('input', prepareStudentRecordsAwaitingLoad);
    document.getElementById('promotionGradeFilter')?.addEventListener('change', () => {
        togglePromotionTargetStreamField();
        updatePromotionSectionOptions();
        resetPromotionEvaluationTables();
    });
    document.getElementById('promotionCertGradeFilter')?.addEventListener('change', () => {
        loadPromotionCertificateCandidates();
    });
    document.getElementById('promotionCertSearchInput')?.addEventListener('input', () => {
        clearTimeout(window.__promotionCertSearchTimer);
        window.__promotionCertSearchTimer = setTimeout(() => {
            loadPromotionCertificateCandidates();
        }, 250);
    });
    document.getElementById('promotionCertSearchInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadPromotionCertificateCandidates();
        }
    });

    const teacherSelect = document.getElementById('teacherSelect');
    if (teacherSelect) {
        teacherSelect.addEventListener('change', () => {
            const teacher = state.teachers.find((t) => t.employee_id_generated === teacherSelect.value);
            setInputValue('teacherNameDisplay', teacher ? teacher.full_name : '');
        });
    }

    document.getElementById('assignStudentGradeFilter')?.addEventListener('change', renderGradeStudentsForAssign);
    document.getElementById('assignStudentsBatchBtn')?.addEventListener('click', submitGradeSectionAssignments);
    document.getElementById('assignTeacherGrade')?.addEventListener('change', () => {
        toggleAssignTeacherStreamField();
        refreshTeacherSubjectsForSelection().catch(() => {});
        updateTeacherSectionOptions();
    });
    document.getElementById('assignTeacherStream')?.addEventListener('change', () => {
        refreshTeacherSubjectsForSelection().catch(() => {});
        updateTeacherSectionOptions();
    });
    document.getElementById('scheduleGradeSelect')?.addEventListener('change', onScheduleGradeOrStreamChange);
    document.getElementById('scheduleStreamSelect')?.addEventListener('change', onScheduleGradeOrStreamChange);
    document.getElementById('studentGradeLevel')?.addEventListener('change', toggleStudentStreamField);
    document.getElementById('profileGrade')?.addEventListener('change', toggleProfileStreamField);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeStudentProfileModal();
            closeTeacherProfileModal();
        }
    });

    hookStudentPreview('studentSlipSelect', 'studentSlipName', 'studentSlipGrade');
    hookStudentPreview('studentPromotSelect', 'studentPromotName', 'studentCurrentGrade');
    hookStudentPreview('studentGradSelect', 'studentGradName', null);
}

function hookStudentPreview(selectId, nameId, gradeId) {
    const el = document.getElementById(selectId);
    if (!el) return;
    el.addEventListener('change', () => {
        const student = state.students.find((s) => s.student_id_generated === el.value);
        setInputValue(nameId, student ? student.full_name : '');
        if (gradeId) setInputValue(gradeId, student ? normalizeGradeLabel(student.grade_level) : '');
    });
}

function switchPage(pageName) {
    document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach((i) => i.classList.remove('active'));

    document.getElementById(pageName)?.classList.add('active');
    document.querySelector(`.nav-item[data-page="${pageName}"]`)?.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        'register-student': 'Register New Student',
        'register-teacher': 'Register New Teacher',
        assign: 'Assign Users',
        schedule: 'Class Schedule',
        'all-registrations': 'All Registrations',
        reports: 'Reports',
        certification: 'Certification',
        promote: 'Promote Students',
        settings: 'Settings'
    };
    setText('pageTitle', titles[pageName] || 'Dashboard');

    if (pageName === 'all-registrations') {
        populateStudentGradeFilter();
        switchAllRecordsTab('students');
        prepareStudentRecordsAwaitingLoad();
        renderTeacherRecordsTable();
    } else if (pageName === 'assign') {
        renderGradeStudentsForAssign();
        toggleAssignTeacherStreamField();
        toggleStudentStreamField();
        toggleProfileStreamField();
        refreshTeacherSubjectsForSelection().catch(() => {});
        updateTeacherSectionOptions();
        loadAssignedTeachersTable();
    } else if (pageName === 'schedule') {
        populateScheduleFormOptions();
        buildScheduleGradeSectionOptions();
        clearFixedScheduleTable();
    } else if (pageName === 'reports') {
        loadReports();
    } else if (pageName === 'certification') {
        loadIssuedSlipsTable();
        loadIssuedPromotionCertsTable();
        loadGraduatedStudentsTable();
    } else if (pageName === 'promote') {
        togglePromotionTargetStreamField();
        updatePromotionSectionOptions();
        resetPromotionEvaluationTables();
    }
}

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'include' });
    const raw = await res.text();
    let body = null;
    try {
        body = raw ? JSON.parse(raw) : null;
    } catch (_) {
        throw new Error(`Server returned invalid JSON (${url})`);
    }
    if (!body) {
        throw new Error(`Server returned empty response (${url})`);
    }
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
    const raw = await res.text();
    let body = null;
    try {
        body = raw ? JSON.parse(raw) : null;
    } catch (_) {
        throw new Error(`Server returned invalid JSON (${url})`);
    }
    if (!body) {
        throw new Error(`Server returned empty response (${url})`);
    }
    if (!res.ok || !body.success) {
        throw new Error(body.message || 'Request failed');
    }
    return body.data || {};
}

function updateUserInfo() {
    const name = state.user?.fullName || state.user?.username || 'Registration Admin';
    setText('userName', name);
    setText('userRole', (state.user?.role || 'admin').toUpperCase());
}

async function loadDashboard() {
    const data = await apiGet(API.dashboard);
    state.dashboard = data;

    setText('totalStudents', data.statistics?.total_students ?? 0);
    setText('totalTeachers', data.statistics?.total_teachers ?? 0);
    setText('totalAdmins', data.statistics?.total_admins ?? 0);
    setText('totalPending', data.statistics?.total_registrations ?? 0);

    const recentContainer = document.getElementById('recentRegistrationsContainer');
    if (recentContainer) {
        recentContainer.innerHTML = '';
        (data.recent_registrations || []).forEach((r) => {
            const item = document.createElement('div');
            item.className = 'registration-item';
            item.innerHTML = `
                <div class="registration-item-info">
                    <p class="name">${escapeHtml(r.full_name || r.username)}</p>
                    <p class="role">${escapeHtml((r.role || '').toUpperCase())} - ${escapeHtml(r.email || '')}</p>
                </div>
                <span class="status-badge ${escapeHtml(r.status || '')}">${escapeHtml(r.status || '')}</span>
            `;
            recentContainer.appendChild(item);
        });
    }

    const statusContainer = document.getElementById('registrationStatusContainer');
    if (statusContainer) {
        statusContainer.innerHTML = '';
        const statusSummary = data.status_summary || {};
        ['approved', 'pending', 'rejected'].forEach((s) => {
            const item = document.createElement('div');
            item.className = 'status-item';
            item.innerHTML = `
                <span class="class-name">${s.charAt(0).toUpperCase() + s.slice(1)}</span>
                <span class="badge">${Number(statusSummary[s] || 0)}</span>
            `;
            statusContainer.appendChild(item);
        });
    }
}

async function loadRegistrations() {
    const data = await apiGet(`${API.registrations}?status=all&role=all&page=1&limit=500`);
    state.registrations = (data.registrations || []).map((r) => ({
        ...r,
        role_label: mapRoleLabel(r.role)
    }));

    setText('registrationBadge', state.registrations.length);
}

async function loadStudentsDirectory() {
    const data = await apiGet(`${API.studentsDirectory}?page=1&limit=1000`);
    state.students = data.students || [];
}

async function loadTeachersDirectory() {
    const data = await apiGet(`${API.teachersDirectory}?page=1&limit=1000`);
    state.teachers = data.teachers || [];
    populateScheduleFormOptions();
}

async function loadClasses() {
    const data = await apiGet(API.classes);
    state.classes = data.classes || [];
    populateScheduleFormOptions();
    buildScheduleGradeSectionOptions();
}

async function loadSubjects() {
    const data = await apiGet(API.subjects);
    state.subjects = data.subjects || [];
    populateTeacherSubjectOptions();
    populateScheduleFormOptions();
}

async function loadAssignedStudents() {
    const data = await apiGet(API.assignedStudents);
    state.assignedStudents = data.assigned_students || [];
}

async function loadAssignedTeachers() {
    const data = await apiGet(API.assignedTeachers);
    state.assignedTeachers = data.assigned_teachers || [];
}

async function loadClassSchedules() {
    const filterClassId = Number(state.selectedScheduleClassId || 0);
    const url = filterClassId > 0 ? `${API.classSchedules}?class_id=${filterClassId}` : API.classSchedules;
    const data = await apiGet(url);
    state.classSchedules = data.schedules || [];
}

async function loadCertificates() {
    const data = await apiGet(API.certificates);
    state.certificates = data.certificates || [];
}

async function loadPromotions() {
    const data = await apiGet(API.promotions);
    state.promotions = data.promotions || [];
}

function applyRegistrationFilters() {
    const search = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();

    state.filteredRegistrations = state.registrations.filter((r) => {
        const target = `${r.username} ${r.full_name} ${r.email}`.toLowerCase();
        return !search || target.includes(search);
    });

    renderRegistrationsTable();
}

function renderRegistrationsTable() {
    const tbody = document.getElementById('studentRecordsTableBody');
    if (!tbody) return;
    const tableCard = document.getElementById('studentRecordsTableCard');

    if (!state.studentRecordsLoaded) {
        if (tableCard) tableCard.style.display = 'none';
        state.filteredStudents = [];
        clearStudentProfileForm(false);
        return;
    }

    if (tableCard) tableCard.style.display = 'block';
    const hasFilter = hasStudentRecordsFilterActive();
    if (!hasFilter) {
        state.filteredStudents = [];
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">Choose grade or search text, then click "Load Students".</td></tr>';
        clearStudentProfileForm(false);
        return;
    }
    const rows = getFilteredStudentsForRecords();
    state.filteredStudents = rows;
    if (state.selectedStudentProfile && !rows.some((s) => s.student_id_generated === state.selectedStudentProfile.username)) {
        clearStudentProfileForm(false);
    }
    tbody.innerHTML = '';
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">No students found</td></tr>';
        return;
    }

    rows.forEach((s) => {
        const row = document.createElement('tr');
        row.className = state.selectedStudentProfile?.username === s.student_id_generated ? 'selected-row' : '';
        row.addEventListener('click', () => {
            viewStudentProfile(s.student_id_generated);
        });
        row.innerHTML = `
            <td>${escapeHtml(s.student_id_generated)}</td>
            <td>${escapeHtml(s.full_name || '-')}</td>
            <td>${escapeHtml(normalizeGradeLabel(s.grade_level))}</td>
        `;
        tbody.appendChild(row);
    });
}

function renderStudentRecordsTable() {
    renderRegistrationsTable();
}

function prepareStudentRecordsAwaitingLoad() {
    state.studentRecordsLoaded = false;
    const tableCard = document.getElementById('studentRecordsTableCard');
    if (tableCard) tableCard.style.display = 'none';
    showStudentManageMessage('', true, false);
}

function loadStudentRecords() {
    if (!hasStudentRecordsFilterActive()) {
        state.studentRecordsLoaded = false;
        const tableCard = document.getElementById('studentRecordsTableCard');
        if (tableCard) tableCard.style.display = 'none';
        showStudentManageMessage('Choose at least grade or search text, then load.', false, true);
        return;
    }
    state.studentRecordsLoaded = true;
    showStudentManageMessage('', true, false);
    renderStudentRecordsTable();
}

function switchAllRecordsTab(tab) {
    const target = tab === 'teachers' ? 'teachers' : 'students';
    state.allRecordsTab = target;
    const studentPanel = document.getElementById('studentRecordsPanel');
    const teacherPanel = document.getElementById('teacherRecordsPanel');
    const studentBtn = document.getElementById('recordsTabStudents');
    const teacherBtn = document.getElementById('recordsTabTeachers');

    if (studentPanel) studentPanel.classList.toggle('active', target === 'students');
    if (teacherPanel) teacherPanel.classList.toggle('active', target === 'teachers');
    if (studentBtn) studentBtn.classList.toggle('active', target === 'students');
    if (teacherBtn) teacherBtn.classList.toggle('active', target === 'teachers');
}

function filterRegistrations() {
    renderRegistrationsTable();
}

function populateStudentGradeFilter() {
    const gradeSelect = document.getElementById('studentGradeFilter');
    if (!gradeSelect) return;
    const existing = gradeSelect.value;
    const grades = [...new Set(state.students.map((s) => normalizeGradeValue(s.grade_level)).filter(Boolean))]
        .sort((a, b) => Number(a) - Number(b));

    gradeSelect.innerHTML = '<option value="">All Grades</option>';
    grades.forEach((g) => {
        const option = document.createElement('option');
        option.value = String(g);
        option.textContent = normalizeGradeLabel(g);
        gradeSelect.appendChild(option);
    });
    gradeSelect.value = grades.includes(existing) ? existing : '';
}

function getFilteredStudentsForRecords() {
    const gradeFilter = normalizeGradeValue(document.getElementById('studentGradeFilter')?.value || '');
    const search = (document.getElementById('studentSearchInput')?.value || '').toLowerCase().trim();

    return state.students.filter((s) => {
        const gradeOk = !gradeFilter || normalizeGradeValue(s.grade_level) === gradeFilter;
        const section = findStudentSection(s.student_id_generated);
        const target = `${s.student_id_generated} ${s.fname || ''} ${s.full_name} ${s.email} ${s.grade_level} ${section}`.toLowerCase();
        const searchOk = !search || target.includes(search);
        return gradeOk && searchOk;
    });
}

function hasStudentRecordsFilterActive() {
    const gradeFilter = normalizeGradeValue(document.getElementById('studentGradeFilter')?.value || '');
    const search = String(document.getElementById('studentSearchInput')?.value || '').trim();
    return Boolean(gradeFilter || search);
}

function findStudentSection(studentUsername) {
    const assignment = state.assignedStudents.find((a) => a.student_username === studentUsername);
    return assignment?.section || '';
}

function showStudentManageMessage(message, success = true, visible = true) {
    const box = document.getElementById('studentManageMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function showStudentProfileMessage(message, success = true, visible = true) {
    const box = document.getElementById('studentProfileMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

async function viewStudentProfile(username) {
    clearStudentProfileForm(false);
    showStudentManageMessage('', true, false);
    try {
        const data = await apiGet(`${API.studentProfile}?username=${encodeURIComponent(username)}`);
        const p = data.profile || null;
        if (!p) {
            throw new Error('Student profile not found');
        }
        state.selectedStudentProfile = p;
        setInputValue('profileUsername', p.username || '');
        setInputValue('profileEmail', p.email || '');
        setInputValue('profileFname', p.fname || '');
        setInputValue('profileMname', p.mname || '');
        setInputValue('profileLname', p.lname || '');
        setInputValue('profileDob', p.DOB || '');
        setInputValue('profileAge', p.age || '');
        setInputValue('profileSex', p.sex || '');
        setInputValue('profileGrade', normalizeGradeValue(p.grade_level || ''));
        setInputValue('profileStream', String(p.stream || p.class_stream || '').toLowerCase());
        setInputValue('profileSection', p.section || findStudentSection(p.username) || '');
        setInputValue('profileAddress', p.address || '');
        setInputValue('profileParentName', p.parent_name || '');
        setInputValue('profileParentPhone', p.parent_phone || '');
        const detailsPanel = document.getElementById('studentDetailsPanel');
        if (detailsPanel) {
            detailsPanel.style.display = 'block';
        }
        setStudentProfileEditable(false);
        toggleProfileStreamField();
        openStudentProfileModal();
        showStudentProfileMessage(`Loaded profile for ${p.username}.`, true, true);
        renderStudentRecordsTable();
    } catch (err) {
        showStudentProfileMessage(err.message || 'Failed to load student profile.', false, true);
    }
}

function openStudentProfileModal() {
    const modal = document.getElementById('studentProfileModal');
    if (!modal) return;
    modal.classList.add('show');
}

function closeStudentProfileModal() {
    const modal = document.getElementById('studentProfileModal');
    if (!modal) return;
    modal.classList.remove('show');
}

function handleStudentProfileModalBackdrop(event) {
    const modal = document.getElementById('studentProfileModal');
    if (!modal) return;
    if (event.target === modal) {
        closeStudentProfileModal();
    }
}

function clearStudentProfileForm(clearMessage = true) {
    [
        'profileUsername', 'profileEmail', 'profileFname', 'profileMname', 'profileLname',
        'profileDob', 'profileAge', 'profileSex', 'profileGrade', 'profileStream', 'profileSection',
        'profileAddress', 'profileParentName', 'profileParentPhone'
    ].forEach((id) => setInputValue(id, ''));
    setInputValue('profileUsername', '');
    setInputValue('profileSection', '');
    const detailsPanel = document.getElementById('studentDetailsPanel');
    if (detailsPanel) {
        detailsPanel.style.display = 'none';
    }
    closeStudentProfileModal();
    setStudentProfileEditable(false);
    toggleProfileStreamField();
    state.selectedStudentProfile = null;
    if (clearMessage) {
        showStudentProfileMessage('', true, false);
    }
}

function setStudentProfileEditable(editable) {
    document.querySelectorAll('.profile-editable').forEach((el) => {
        el.disabled = !editable;
    });
}

function gradeNeedsStream(gradeValueRaw) {
    const g = Number(normalizeGradeValue(gradeValueRaw || ''));
    return g === 11 || g === 12;
}

function toggleStudentStreamField() {
    const gradeValue = document.getElementById('studentGradeLevel')?.value || '';
    const streamSelect = document.getElementById('studentStream');
    const streamGroup = document.getElementById('studentStreamGroup');
    if (!streamSelect) return;
    if (gradeNeedsStream(gradeValue)) {
        if (streamGroup) streamGroup.style.display = '';
        streamSelect.disabled = false;
        streamSelect.required = true;
    } else {
        if (streamGroup) streamGroup.style.display = 'none';
        streamSelect.value = '';
        streamSelect.disabled = true;
        streamSelect.required = false;
    }
}

function toggleProfileStreamField() {
    const gradeValue = document.getElementById('profileGrade')?.value || '';
    const streamSelect = document.getElementById('profileStream');
    if (!streamSelect) return;
    if (gradeNeedsStream(gradeValue)) {
        streamSelect.disabled = false;
    } else {
        streamSelect.value = '';
        streamSelect.disabled = true;
    }
}

function toggleAssignTeacherStreamField() {
    const gradeValue = document.getElementById('assignTeacherGrade')?.value || '';
    const streamSelect = document.getElementById('assignTeacherStream');
    if (!streamSelect) return;
    if (gradeNeedsStream(gradeValue)) {
        streamSelect.disabled = false;
        streamSelect.required = true;
    } else {
        streamSelect.value = '';
        streamSelect.disabled = true;
        streamSelect.required = false;
    }
}

function togglePromotionTargetStreamField() {
    const grade = normalizeGradeValue(document.getElementById('promotionGradeFilter')?.value || '');
    const group = document.getElementById('promotionStreamGroup');
    const select = document.getElementById('promotionTargetStream');
    if (!group || !select) return;
    if (grade === '10') {
        group.style.display = '';
        select.required = true;
    } else {
        group.style.display = 'none';
        select.required = false;
        select.value = '';
    }
}

function enableStudentProfileEdit() {
    if (!state.selectedStudentProfile?.username) {
        showStudentProfileMessage('Select a student first.', false, true);
        return;
    }
    setStudentProfileEditable(true);
    showStudentProfileMessage('Edit mode enabled.', true, true);
}

function cancelStudentProfileEdit() {
    if (!state.selectedStudentProfile?.username) {
        clearStudentProfileForm();
        return;
    }
    viewStudentProfile(state.selectedStudentProfile.username);
}

async function saveSelectedStudentProfile() {
    const username = String(document.getElementById('profileUsername')?.value || '').trim();
    if (!username) {
        showStudentProfileMessage('Select a student first.', false, true);
        return;
    }

    const payload = {
        username,
        email: String(document.getElementById('profileEmail')?.value || '').trim(),
        fname: String(document.getElementById('profileFname')?.value || '').trim(),
        mname: String(document.getElementById('profileMname')?.value || '').trim(),
        lname: String(document.getElementById('profileLname')?.value || '').trim(),
        DOB: String(document.getElementById('profileDob')?.value || '').trim(),
        age: Number(document.getElementById('profileAge')?.value || 0),
        sex: String(document.getElementById('profileSex')?.value || '').trim(),
        grade_level: String(document.getElementById('profileGrade')?.value || '').trim(),
        stream: String(document.getElementById('profileStream')?.value || '').trim().toLowerCase(),
        address: String(document.getElementById('profileAddress')?.value || '').trim(),
        parent_name: String(document.getElementById('profileParentName')?.value || '').trim(),
        parent_phone: String(document.getElementById('profileParentPhone')?.value || '').trim()
    };

    if (!payload.email || !payload.fname || !payload.lname || !payload.DOB || !payload.age || !payload.sex || !payload.grade_level || !payload.address || !payload.parent_name || !payload.parent_phone) {
        showStudentProfileMessage('Fill all required student fields before update.', false, true);
        return;
    }
    if (gradeNeedsStream(payload.grade_level) && !payload.stream) {
        showStudentProfileMessage('For Grade 11/12, stream is required.', false, true);
        return;
    }

    try {
        await apiPost(API.updateStudentProfile, payload);
        await Promise.all([loadStudentsDirectory(), loadAssignedStudents(), loadDashboard(), loadRegistrations()]);
        populateStudentGradeFilter();
        renderStudentRecordsTable();
        applyRegistrationFilters();
        loadReports();
        setStudentProfileEditable(false);
        await viewStudentProfile(username);
        showStudentProfileMessage('Student profile updated successfully.', true, true);
        showStudentManageMessage('Student profile updated.', true, true);
    } catch (err) {
        showStudentProfileMessage(err.message || 'Update failed.', false, true);
    }
}

async function deleteStudentRecord(username) {
    showStudentManageMessage('', true, false);
    try {
        await apiPost(API.deleteStudent, { username });
        if (state.selectedStudentProfile?.username === username) {
            clearStudentProfileForm();
        }
        await Promise.all([loadStudentsDirectory(), loadAssignedStudents(), loadDashboard(), loadRegistrations()]);
        populateStudentGradeFilter();
        renderStudentRecordsTable();
        applyRegistrationFilters();
        loadReports();
        showStudentManageMessage(`Student ${username} deleted successfully.`, true, true);
    } catch (err) {
        showStudentManageMessage(err.message || 'Failed to delete student.', false, true);
    }
}

async function deleteSelectedStudentProfile() {
    const username = String(document.getElementById('profileUsername')?.value || '').trim();
    if (!username) {
        showStudentProfileMessage('Select a student first.', false, true);
        return;
    }
    await deleteStudentRecord(username);
}

function showTeacherManageMessage(message, success = true, visible = true) {
    const box = document.getElementById('teacherManageMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function showTeacherProfileMessage(message, success = true, visible = true) {
    const box = document.getElementById('teacherProfileMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function renderTeacherRecordsTable() {
    const tbody = document.getElementById('teacherRecordsTableBody');
    if (!tbody) return;
    const rows = state.teachers || [];
    state.filteredTeachers = rows;
    tbody.innerHTML = '';
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#6b7280;">No teachers found</td></tr>';
        clearTeacherProfileForm(false);
        return;
    }

    rows.forEach((t) => {
        const username = t.employee_id_generated || t.username;
        const row = document.createElement('tr');
        row.className = state.selectedTeacherProfile?.username === username ? 'selected-row' : '';
        row.addEventListener('click', () => viewTeacherProfile(username));
        row.innerHTML = `
            <td>${escapeHtml(username || '-')}</td>
            <td>${escapeHtml(t.full_name || [t.fname, t.lname].filter(Boolean).join(' ') || '-')}</td>
            <td>${escapeHtml(t.department || '-')}</td>
        `;
        tbody.appendChild(row);
    });
}

async function viewTeacherProfile(username) {
    clearTeacherProfileForm(false);
    showTeacherManageMessage('', true, false);
    try {
        const data = await apiGet(`${API.teacherProfile}?username=${encodeURIComponent(username)}`);
        const p = data.profile || null;
        if (!p) {
            throw new Error('Teacher profile not found');
        }
        state.selectedTeacherProfile = p;
        setInputValue('teacherProfileUsername', p.username || '');
        setInputValue('teacherProfileEmail', p.email || '');
        setInputValue('teacherProfileStatus', p.status || 'active');
        setInputValue('teacherProfileFname', p.fname || '');
        setInputValue('teacherProfileMname', p.mname || '');
        setInputValue('teacherProfileLname', p.lname || '');
        setInputValue('teacherProfileDob', p.DOB || '');
        setInputValue('teacherProfileAge', p.age || '');
        setInputValue('teacherProfileSex', p.sex || '');
        setInputValue('teacherProfileDepartment', p.department || '');
        setInputValue('teacherProfileSubject', p.subject || '');
        setInputValue('teacherProfileAddress', p.address || '');
        setInputValue('teacherProfileOfficeRoom', p.office_room || '');
        setInputValue('teacherProfileOfficePhone', p.office_phone || '');
        const detailsPanel = document.getElementById('teacherDetailsPanel');
        if (detailsPanel) {
            detailsPanel.style.display = 'block';
        }
        setTeacherProfileEditable(false);
        openTeacherProfileModal();
        showTeacherProfileMessage(`Loaded profile for ${p.username}.`, true, true);
        renderTeacherRecordsTable();
    } catch (err) {
        showTeacherProfileMessage(err.message || 'Failed to load teacher profile.', false, true);
    }
}

function openTeacherProfileModal() {
    const modal = document.getElementById('teacherProfileModal');
    if (!modal) return;
    modal.classList.add('show');
}

function closeTeacherProfileModal() {
    const modal = document.getElementById('teacherProfileModal');
    if (!modal) return;
    modal.classList.remove('show');
}

function handleTeacherProfileModalBackdrop(event) {
    const modal = document.getElementById('teacherProfileModal');
    if (!modal) return;
    if (event.target === modal) {
        closeTeacherProfileModal();
    }
}

function clearTeacherProfileForm(clearMessage = true) {
    [
        'teacherProfileUsername', 'teacherProfileEmail', 'teacherProfileStatus', 'teacherProfileFname', 'teacherProfileMname',
        'teacherProfileLname', 'teacherProfileDob', 'teacherProfileAge', 'teacherProfileSex', 'teacherProfileDepartment',
        'teacherProfileSubject', 'teacherProfileAddress', 'teacherProfileOfficeRoom', 'teacherProfileOfficePhone'
    ].forEach((id) => setInputValue(id, ''));
    const detailsPanel = document.getElementById('teacherDetailsPanel');
    if (detailsPanel) detailsPanel.style.display = 'none';
    closeTeacherProfileModal();
    setTeacherProfileEditable(false);
    state.selectedTeacherProfile = null;
    if (clearMessage) showTeacherProfileMessage('', true, false);
}

function setTeacherProfileEditable(editable) {
    document.querySelectorAll('.teacher-profile-editable').forEach((el) => {
        el.disabled = !editable;
    });
}

function enableTeacherProfileEdit() {
    if (!state.selectedTeacherProfile?.username) {
        showTeacherProfileMessage('Select a teacher first.', false, true);
        return;
    }
    setTeacherProfileEditable(true);
    showTeacherProfileMessage('Edit mode enabled.', true, true);
}

function cancelTeacherProfileEdit() {
    if (!state.selectedTeacherProfile?.username) {
        clearTeacherProfileForm();
        return;
    }
    viewTeacherProfile(state.selectedTeacherProfile.username);
}

async function saveSelectedTeacherProfile() {
    const username = String(document.getElementById('teacherProfileUsername')?.value || '').trim();
    if (!username) {
        showTeacherProfileMessage('Select a teacher first.', false, true);
        return;
    }

    const payload = {
        username,
        email: String(document.getElementById('teacherProfileEmail')?.value || '').trim(),
        status: String(document.getElementById('teacherProfileStatus')?.value || '').trim().toLowerCase(),
        fname: String(document.getElementById('teacherProfileFname')?.value || '').trim(),
        mname: String(document.getElementById('teacherProfileMname')?.value || '').trim(),
        lname: String(document.getElementById('teacherProfileLname')?.value || '').trim(),
        DOB: String(document.getElementById('teacherProfileDob')?.value || '').trim(),
        age: Number(document.getElementById('teacherProfileAge')?.value || 0),
        sex: String(document.getElementById('teacherProfileSex')?.value || '').trim(),
        department: String(document.getElementById('teacherProfileDepartment')?.value || '').trim(),
        subject: String(document.getElementById('teacherProfileSubject')?.value || '').trim(),
        address: String(document.getElementById('teacherProfileAddress')?.value || '').trim(),
        office_room: String(document.getElementById('teacherProfileOfficeRoom')?.value || '').trim(),
        office_phone: String(document.getElementById('teacherProfileOfficePhone')?.value || '').trim()
    };

    if (!payload.email || !payload.fname || !payload.lname || !payload.DOB || !payload.age || !payload.sex || !payload.department || !payload.subject || !payload.address) {
        showTeacherProfileMessage('Fill all required teacher fields before update.', false, true);
        return;
    }

    try {
        await apiPost(API.updateTeacherProfile, payload);
        await Promise.all([loadTeachersDirectory(), loadAssignedTeachers(), loadDashboard(), loadRegistrations()]);
        loadAssignedTeachersTable();
        applyRegistrationFilters();
        loadReports();
        renderTeacherRecordsTable();
        setTeacherProfileEditable(false);
        await viewTeacherProfile(username);
        showTeacherProfileMessage('Teacher profile updated successfully.', true, true);
        showTeacherManageMessage('Teacher profile updated.', true, true);
    } catch (err) {
        showTeacherProfileMessage(err.message || 'Teacher update failed.', false, true);
    }
}

// Approval flow removed. Registrations are auto-approved at creation time.

async function refreshRegistrationViews() {
    await Promise.all([loadDashboard(), loadRegistrations()]);
    applyRegistrationFilters();
    loadReports();
}

async function handleStudentRegistration(event) {
    event.preventDefault();
    const form = event.target;
    clearFormErrors(form);
    showFormSuccessMessage(form, '', false);
    const fd = new FormData(form);
    const payload = {
        fname: String(fd.get('fname') || '').trim(),
        mname: String(fd.get('mname') || '').trim(),
        lname: String(fd.get('lname') || '').trim(),
        email: String(fd.get('email') || '').trim(),
        dateOfBirth: String(fd.get('dateOfBirth') || '').trim(),
        age: Number(fd.get('age') || 0),
        sex: String(fd.get('sex') || '').trim(),
        gradeLevel: normalizeGradeValue(String(fd.get('gradeLevel') || '').trim()),
        stream: String(fd.get('stream') || '').trim().toLowerCase(),
        address: String(fd.get('address') || '').trim(),
        parentName: String(fd.get('parentName') || '').trim(),
        parentPhone: String(fd.get('parentPhone') || '').trim()
    };
    if (gradeNeedsStream(payload.gradeLevel) && !payload.stream) {
        setFieldError(form, 'stream', 'For Grade 11/12, stream is required.');
        return;
    }

    try {
        const data = await apiPost(API.registerStudent, payload);
        showFormSuccessMessage(
            form,
            `Student registered successfully. Username: ${data.username}, Temp Password: ${data.temporary_password}`,
            true
        );
        form.reset();
        toggleStudentStreamField();
        await refreshRegistrationViews();
        await loadStudentsDirectory();
        await loadAssignedStudents();
        populateStudentGradeFilter();
        renderStudentRecordsTable();
        loadStudentSelects();
    } catch (err) {
        const msg = err.message || 'Student registration failed';
        applyRegistrationFieldError(form, msg, 'student');
    }
}

async function handleTeacherRegistration(event) {
    event.preventDefault();
    const form = event.target;
    clearFormErrors(form);
    showFormSuccessMessage(form, '', false);
    const fd = new FormData(form);
    const payload = {
        fname: String(fd.get('fname') || '').trim(),
        mname: String(fd.get('mname') || '').trim(),
        lname: String(fd.get('lname') || '').trim(),
        email: String(fd.get('email') || '').trim(),
        dateOfBirth: String(fd.get('dateOfBirth') || '').trim(),
        age: Number(fd.get('age') || 0),
        sex: String(fd.get('sex') || '').trim(),
        department: String(fd.get('department') || '').trim(),
        subject: String(fd.get('subject') || '').trim(),
        address: String(fd.get('address') || '').trim(),
        officeRoom: String(fd.get('officeRoom') || '').trim(),
        officePhone: String(fd.get('officePhone') || '').trim()
    };

    try {
        const data = await apiPost(API.registerTeacher, payload);
        showFormSuccessMessage(
            form,
            `Teacher registered successfully. Username: ${data.username}, Temp Password: ${data.temporary_password}`,
            true
        );
        form.reset();
        await refreshRegistrationViews();
        await loadTeachersDirectory();
        loadTeacherSelects();
    } catch (err) {
        const msg = err.message || 'Teacher registration failed';
        applyRegistrationFieldError(form, msg, 'teacher');
    }
}

function switchAssignTab(tab) {
    document.querySelectorAll('.assign-content').forEach((x) => x.classList.remove('active'));
    document.querySelectorAll('.assign-tab').forEach((x) => x.classList.remove('active'));

    if (tab === 'student') {
        document.getElementById('assign-student-tab')?.classList.add('active');
        document.querySelectorAll('.assign-tab')[0]?.classList.add('active');
    } else {
        document.getElementById('assign-teacher-tab')?.classList.add('active');
        document.querySelectorAll('.assign-tab')[1]?.classList.add('active');
    }
}

function loadStudentSelects() {
    const studentSelectIds = ['studentSlipSelect', 'studentPromotSelect', 'studentGradSelect'];
    studentSelectIds.forEach((id) => {
        const select = document.getElementById(id);
        if (!select) return;
        const firstOption = select.querySelector('option')?.outerHTML || '<option value="">-- Choose a Student --</option>';
        select.innerHTML = firstOption;
        state.students.forEach((s) => {
            const option = document.createElement('option');
            option.value = s.student_id_generated;
            option.textContent = `${s.student_id_generated} - ${s.full_name}`;
            select.appendChild(option);
        });
    });
}

function loadTeacherSelects() {
    const select = document.getElementById('teacherSelect');
    if (!select) return;
    const firstOption = select.querySelector('option')?.outerHTML || '<option value="">-- Choose a Teacher --</option>';
    select.innerHTML = firstOption;

    state.teachers.forEach((t) => {
        const option = document.createElement('option');
        option.value = t.employee_id_generated;
        option.textContent = `${t.employee_id_generated} - ${t.full_name}`;
        select.appendChild(option);
    });
}
async function handleAssignStudent(event) {
    // Old form-based assign student flow removed.
    event.preventDefault();
}

function populateTeacherSubjectOptions() {
    const select = document.getElementById('assignTeacherSubject');
    if (!select) return;
    const previousValue = select.value;
    select.innerHTML = '<option value="">-- Select Subject --</option>';
    state.subjects.forEach((s) => {
        const option = document.createElement('option');
        option.value = s.subject_name;
        option.textContent = s.subject_code ? `${s.subject_name} (${s.subject_code})` : s.subject_name;
        select.appendChild(option);
    });
    if (previousValue && state.subjects.some((s) => s.subject_name === previousValue)) {
        select.value = previousValue;
    }
}

async function refreshTeacherSubjectsForSelection() {
    const grade = normalizeGradeValue(document.getElementById('assignTeacherGrade')?.value || '');
    const stream = String(document.getElementById('assignTeacherStream')?.value || '').trim();
    const select = document.getElementById('assignTeacherSubject');
    if (!grade) {
        await loadSubjects();
        return;
    }
    if ((grade === '11' || grade === '12') && !stream) {
        state.subjects = [];
        if (select) {
            select.innerHTML = '<option value="">-- Select Stream First --</option>';
        }
        return;
    }
    const previousValue = select?.value || '';
    const qs = `grade=${encodeURIComponent(grade)}&stream=${encodeURIComponent(stream)}`;
    const data = await apiGet(`${API.subjects}?${qs}`);
    state.subjects = data.subjects || [];
    populateTeacherSubjectOptions();
    if (select && previousValue && state.subjects.some((s) => s.subject_name === previousValue)) {
        select.value = previousValue;
    }
}

async function updateTeacherSectionOptions() {
    const gradeValue = document.getElementById('assignTeacherGrade')?.value || '';
    const streamValue = String(document.getElementById('assignTeacherStream')?.value || '').trim();
    const sectionSelect = document.getElementById('assignTeacherSection');
    if (!sectionSelect) return;
    const grade = normalizeGradeValue(gradeValue);
    let sections = [];
    if (grade) {
        try {
            const data = await apiGet(`${API.classes}?grade=${encodeURIComponent(grade)}&stream=${encodeURIComponent(streamValue)}`);
            const set = new Set();
            (data.classes || []).forEach((c) => {
                if (c.section) set.add(String(c.section));
            });
            sections = Array.from(set).sort();
        } catch (_) {
            sections = [];
        }
    }
    sectionSelect.innerHTML = '';
    if (!grade) {
        sectionSelect.innerHTML = '<option value="">-- Select Grade First --</option>';
        return;
    }
    if (!sections.length) {
        sectionSelect.innerHTML = '<option value="">-- No Sections Found --</option>';
        return;
    }
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    sections.forEach((sec) => {
        const option = document.createElement('option');
        option.value = sec;
        option.textContent = `Section ${sec}`;
        sectionSelect.appendChild(option);
    });
}

function getAssignedSection(studentUsername) {
    const item = state.assignedStudents.find((x) => String(x.student_username) === String(studentUsername));
    return item?.section || '';
}

function renderGradeStudentsForAssign() {
    const gradeFilter = document.getElementById('assignStudentGradeFilter')?.value || '';
    const tbody = document.getElementById('assignGradeStudentsTable');
    if (!tbody) return;
    tbody.innerHTML = '';
    showAssignBatchMessage('', false);

    if (!gradeFilter) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">Select a grade to load students</td></tr>';
        return;
    }

    const list = state.students.filter((s) => normalizeGradeValue(s.grade_level) === gradeFilter);
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">No students found for this grade</td></tr>';
        return;
    }

    list.forEach((s) => {
        const currentSection = getAssignedSection(s.student_id_generated);
        const sectionInputId = `assignSection_${escapeJs(s.student_id_generated)}`.replace(/[^\w-]/g, '_');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(s.student_id_generated)}</td>
            <td>${escapeHtml(s.full_name || '-')}</td>
            <td>${escapeHtml(normalizeGradeLabel(s.grade_level))}</td>
            <td>
                <input
                    type="text"
                    class="form-control assign-section-input"
                    id="${sectionInputId}"
                    data-student="${escapeHtml(s.student_id_generated)}"
                    data-grade="${escapeHtml(normalizeGradeValue(s.grade_level))}"
                    data-stream="${escapeHtml(String(s.stream || '').toLowerCase())}"
                    value="${escapeHtml(currentSection || '')}"
                    placeholder="Enter section (e.g. A)"
                >
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function submitGradeSectionAssignments() {
    const inputs = Array.from(document.querySelectorAll('#assignGradeStudentsTable .assign-section-input'));
    if (!inputs.length) {
        showAssignBatchMessage('No students to assign for this grade.', false, true);
        return;
    }

    const payloads = inputs
        .map((input) => ({
            student_username: String(input.dataset.student || '').trim(),
            grade_level: String(input.dataset.grade || '').trim(),
            stream: String(input.dataset.stream || '').trim(),
            section: String(input.value || '').trim()
        }))
        .filter((p) => p.student_username && p.grade_level && p.section);

    if (!payloads.length) {
        showAssignBatchMessage('Enter at least one section before assigning.', false, true);
        return;
    }

    let successCount = 0;
    let failedCount = 0;

    for (const p of payloads) {
        try {
            await apiPost(API.assignStudent, {
                student_username: p.student_username,
                grade_level: p.grade_level,
                stream: p.stream,
                section: p.section,
                enrollment_date: new Date().toISOString().slice(0, 10)
            });
            successCount += 1;
        } catch (_) {
            failedCount += 1;
        }
    }

    await loadAssignedStudents();
    renderGradeStudentsForAssign();

    if (failedCount === 0) {
        showAssignBatchMessage(`Assigned ${successCount} student(s) successfully.`, true, false);
    } else {
        showAssignBatchMessage(`Assigned ${successCount} student(s), failed ${failedCount}. Check sections/classes and try again.`, false, true);
    }
}

async function handleAssignTeacher(event) {
    event.preventDefault();
    showAssignTeacherMessage('', true, false);
    const fd = new FormData(event.target);
    const payload = {
        teacher_username: String(fd.get('teacherId') || '').trim(),
        subject: String(fd.get('subject') || '').trim(),
        department: String(fd.get('department') || '').trim(),
        grade_level: String(fd.get('gradeLevel') || '').trim(),
        stream: String(fd.get('stream') || '').trim(),
        section: String(fd.get('section') || '').trim()
    };

    try {
        await apiPost(API.assignTeacher, payload);
        showAssignTeacherMessage('Teacher assigned successfully.', true, true);
        event.target.reset();
        setInputValue('teacherNameDisplay', '');
        updateTeacherSectionOptions();
        await loadAssignedTeachers();
        loadAssignedTeachersTable();
    } catch (err) {
        showAssignTeacherMessage(err.message || 'Teacher assignment failed.', false, true);
    }
}

function loadAssignedStudentsTable() {
    const tbody = document.getElementById('assignedStudentsTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!state.assignedStudents.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">No student assignments found</td></tr>';
        return;
    }

    state.assignedStudents.forEach((item) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(item.student_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(normalizeGradeLabel(item.grade_level))}</td>
            <td>${escapeHtml(item.section || '-')}</td>
            <td>${escapeHtml(formatDate(item.enrollment_date))}</td>
            <td><button class="btn btn-small btn-secondary" onclick="removeStudentAssignment(${Number(item.id)})">Remove</button></td>
        `;
        tbody.appendChild(row);
    });
}

function loadAssignedTeachersTable() {
    const tbody = document.getElementById('assignedTeachersTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!state.assignedTeachers.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#6b7280;">No teacher assignments found</td></tr>';
        return;
    }

    state.assignedTeachers.forEach((item) => {
        const row = document.createElement('tr');
        const isBlocked = Number(item.is_blocked || 0) === 1;
        const status = isBlocked ? 'blocked' : 'active';
        row.innerHTML = `
            <td>${escapeHtml(item.teacher_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(item.subject || item.subjects || '-')}</td>
            <td>${escapeHtml(item.grade_label || item.grade_levels || normalizeGradeLabel(item.grade_level))}</td>
            <td>${escapeHtml(item.section || item.sections || '-')}</td>
            <td>${escapeHtml(status)}</td>
            <td>
                <button class="btn btn-small btn-secondary" onclick="removeTeacherAssignment(${Number(item.class_id)})">Remove</button>
                <button class="btn btn-small ${isBlocked ? 'btn-primary' : 'btn-danger'}" onclick="toggleTeacherBlock('${escapeJs(item.teacher_username)}', ${Number(item.class_id)}, '${isBlocked ? 'unblock' : 'block'}')">${isBlocked ? 'Unblock' : 'Block'}</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function showAssignTeacherMessage(message, success = true, visible = true) {
    const box = document.getElementById('assignTeachersMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

async function removeStudentAssignment(assignmentId) {
    try {
        await apiPost(API.removeStudentAssignment, { assignment_id: Number(assignmentId) });
        await loadAssignedStudents();
        loadAssignedStudentsTable();
        showAssignBatchMessage('Student assignment removed.', true, true);
    } catch (err) {
        showAssignBatchMessage(err.message || 'Failed to remove student assignment.', false, true);
    }
}

async function removeTeacherAssignment(classId) {
    try {
        await apiPost(API.removeTeacherAssignment, { class_id: Number(classId) });
        await loadAssignedTeachers();
        loadAssignedTeachersTable();
        showAssignTeacherMessage('Teacher assignment removed.', true, true);
    } catch (err) {
        showAssignTeacherMessage(err.message || 'Failed to remove assignment.', false, true);
    }
}

async function toggleTeacherBlock(teacherUsername, classId, action) {
    if (!teacherUsername || !Number(classId)) return;
    try {
        await apiPost(API.blockTeacher, { teacher_username: teacherUsername, class_id: Number(classId), action });
        try {
            await Promise.all([loadTeachersDirectory(), loadAssignedTeachers(), loadDashboard()]);
        } catch (_) {
            // Ignore refresh failures here; action is already saved.
        }
        loadAssignedTeachersTable();
        renderTeacherRecordsTable();
        showAssignTeacherMessage(`Teacher ${teacherUsername} ${action === 'block' ? 'blocked' : 'unblocked'} for class ${classId}.`, true, true);
    } catch (err) {
        showAssignTeacherMessage(err.message || 'Failed to update teacher status.', false, true);
    }
}

function populateScheduleFormOptions() {}

function normalizeStreamValue(value) {
    const v = String(value || '').trim().toLowerCase();
    return v === 'natural' || v === 'social' ? v : '';
}

function buildScheduleGradeSectionOptions() {
    const gradeSelect = document.getElementById('scheduleGradeSelect');
    if (!gradeSelect) return;

    const current = String(gradeSelect.value || '');
    const grades = [...new Set(state.classes.map((c) => normalizeGradeValue(c.grade_level)).filter(Boolean))]
        .sort((a, b) => Number(a) - Number(b));

    gradeSelect.innerHTML = '<option value="">-- Select Grade --</option>';
    grades.forEach((g) => {
        const option = document.createElement('option');
        option.value = String(g);
        option.textContent = normalizeGradeLabel(g);
        gradeSelect.appendChild(option);
    });
    gradeSelect.value = grades.includes(current) ? current : '';
    onScheduleGradeOrStreamChange();
}

function onScheduleGradeOrStreamChange() {
    const grade = normalizeGradeValue(document.getElementById('scheduleGradeSelect')?.value || '');
    const streamSelect = document.getElementById('scheduleStreamSelect');
    const sectionSelect = document.getElementById('scheduleSectionSelect');
    if (!sectionSelect || !streamSelect) return;

    const gradeNum = Number(grade || 0);
    if (gradeNum >= 11) {
        streamSelect.disabled = false;
        const availableStreams = [...new Set(
            state.classes
                .filter((c) => normalizeGradeValue(c.grade_level) === grade)
                .map((c) => normalizeStreamValue(c.stream))
                .filter(Boolean)
        )];
        if (!normalizeStreamValue(streamSelect.value) && availableStreams.length === 1) {
            streamSelect.value = availableStreams[0];
        }
    } else {
        streamSelect.value = '';
        streamSelect.disabled = true;
    }

    const stream = normalizeStreamValue(streamSelect.value || '');
    const sections = [...new Set(
        state.classes
            .filter((c) => {
                const classGrade = normalizeGradeValue(c.grade_level);
                if (classGrade !== grade) return false;
                if (gradeNum >= 11) {
                    // If stream is not yet selected, show all sections for this grade.
                    if (!stream) return true;
                    return normalizeStreamValue(c.stream) === stream;
                }
                return true;
            })
            .map((c) => String(c.section || '').trim().toUpperCase())
            .filter(Boolean)
    )].sort();

    const currentSection = String(sectionSelect.value || '');
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    sections.forEach((s) => {
        const option = document.createElement('option');
        option.value = s;
        option.textContent = `Section ${s}`;
        sectionSelect.appendChild(option);
    });
    sectionSelect.value = sections.includes(currentSection) ? currentSection : '';
}

function showClassScheduleMessage(message, success = true, visible = true) {
    const box = document.getElementById('classScheduleMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function clearFixedScheduleTable() {
    state.selectedScheduleClassId = 0;
    state.fixedScheduleEntries = {};
    state.scheduleSubjects = [];
    setInputValue('scheduleGradeSelect', '');
    setInputValue('scheduleSectionSelect', '');
    setInputValue('scheduleStreamSelect', '');
    const streamSelect = document.getElementById('scheduleStreamSelect');
    if (streamSelect) streamSelect.disabled = true;
    onScheduleGradeOrStreamChange();
    const card = document.getElementById('scheduleTimetableCard');
    if (card) card.style.display = 'none';
    const tbody = document.getElementById('classSchedulesTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">Select grade, section, and stream then click "Load Timetable"</td></tr>';
    }
    showClassScheduleMessage('', true, false);
}

function fixedScheduleKey(day, start) {
    return `${day}|${String(start).slice(0, 5)}`;
}

function parseLocation(rawValue) {
    const raw = String(rawValue || '').trim();
    if (!raw) return { type: 'room', text: '' };
    const idx = raw.indexOf('|');
    if (idx > 0) {
        const t = raw.slice(0, idx).toLowerCase();
        const text = raw.slice(idx + 1);
        if (['room', 'lab', 'field'].includes(t)) {
            return { type: t, text };
        }
    }
    return { type: 'room', text: raw };
}

function encodeLocation(type, text) {
    const t = ['room', 'lab', 'field'].includes(String(type || '').toLowerCase()) ? String(type).toLowerCase() : 'room';
    return `${t}|${String(text || '').trim()}`;
}

function findSelectedScheduleClass() {
    const grade = normalizeGradeValue(document.getElementById('scheduleGradeSelect')?.value || '');
    const section = String(document.getElementById('scheduleSectionSelect')?.value || '').trim().toUpperCase();
    const stream = normalizeStreamValue(document.getElementById('scheduleStreamSelect')?.value || '');
    if (!grade || !section) return null;

    const gradeNum = Number(grade || 0);
    let selected = state.classes.find((c) => {
        if (normalizeGradeValue(c.grade_level) !== grade) return false;
        if (String(c.section || '').trim().toUpperCase() !== section) return false;
        if (gradeNum >= 11) {
            if (stream) {
                return normalizeStreamValue(c.stream) === stream;
            }
            return true;
        }
        return true;
    }) || null;

    // If multiple classes could match upper grade with empty stream, prefer a real stream class.
    if (!selected && gradeNum >= 11 && !stream) {
        selected = state.classes.find((c) =>
            normalizeGradeValue(c.grade_level) === grade &&
            String(c.section || '').trim().toUpperCase() === section
        ) || null;
    }
    return selected;
}

function findTeacherForClassSubject(classId, subjectName) {
    const s = String(subjectName || '').trim().toLowerCase();
    if (!classId || !s) return { username: '', display: 'TBS' };
    const match = state.assignedTeachers.find((a) =>
        Number(a.class_id) === Number(classId) &&
        String(a.subject || '').trim().toLowerCase() === s &&
        Number(a.is_blocked || 0) === 0
    );
    if (!match) return { username: '', display: 'TBS' };
    return {
        username: String(match.teacher_username || ''),
        display: String(match.full_name || match.teacher_username || 'TBS')
    };
}

function renderFixedScheduleTable() {
    const tbody = document.getElementById('classSchedulesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    SCHEDULE_SLOTS.forEach((slot) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><strong>${escapeHtml(slot.label)}</strong><br><small>${escapeHtml(slot.start)} - ${escapeHtml(slot.end)}</small></td>`;

        SCHEDULE_DAYS.forEach((day) => {
            const td = document.createElement('td');
            if (slot.break) {
                td.innerHTML = '<div style="text-align:center;color:#6b7280;font-weight:600;">BREAK</div>';
            } else {
                const key = fixedScheduleKey(day, slot.start);
                const entry = state.fixedScheduleEntries[key] || {
                    id: 0,
                    day,
                    start_time: slot.start,
                    end_time: slot.end,
                    subject_id: 0,
                    subject_name: '',
                    teacher_username: '',
                    teacher_display: 'TBS',
                    location_type: 'room',
                    location_text: ''
                };
                state.fixedScheduleEntries[key] = entry;
                td.innerHTML = renderFixedScheduleCell(day, slot);
            }
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
}

function renderFixedScheduleCell(day, slot) {
    const key = fixedScheduleKey(day, slot.start);
    const entry = state.fixedScheduleEntries[key];
    const selectedId = Number(entry.subject_id || 0);
    const availableSubjects = state.scheduleSubjects;
    const options = ['<option value="">-- Subject --</option>']
        .concat(availableSubjects.map((s) => `<option value="${Number(s.id)}" ${Number(s.id) === selectedId ? 'selected' : ''}>${escapeHtml(s.subject_name || '-')}</option>`))
        .join('');

    return `
        <div style="display:flex;flex-direction:column;gap:6px;min-width:180px;">
            <select class="form-control" onchange="onFixedScheduleSubjectChange('${escapeJs(day)}','${escapeJs(slot.start)}', this.value)">
                ${options}
            </select>
            <div style="font-size:12px;color:#374151;">Teacher: <strong id="teacherCell_${escapeHtml(key).replaceAll('|', '_').replaceAll(':', '')}">${escapeHtml(entry.teacher_display || 'TBS')}</strong></div>
            <div style="display:flex;gap:6px;">
                <select class="form-control" style="max-width:90px;" onchange="onFixedScheduleLocationTypeChange('${escapeJs(day)}','${escapeJs(slot.start)}', this.value)">
                    <option value="room" ${entry.location_type === 'room' ? 'selected' : ''}>Room</option>
                    <option value="lab" ${entry.location_type === 'lab' ? 'selected' : ''}>Lab</option>
                    <option value="field" ${entry.location_type === 'field' ? 'selected' : ''}>Field</option>
                </select>
                <input class="form-control" placeholder="Place" value="${escapeHtml(entry.location_text || '')}" oninput="onFixedScheduleLocationTextChange('${escapeJs(day)}','${escapeJs(slot.start)}', this.value)">
            </div>
        </div>
    `;
}

function onFixedScheduleSubjectChange(day, start, subjectIdRaw) {
    const key = fixedScheduleKey(day, start);
    const entry = state.fixedScheduleEntries[key];
    if (!entry) return;

    const subjectId = Number(subjectIdRaw || 0);
    entry.subject_id = subjectId;
    const subject = state.scheduleSubjects.find((s) => Number(s.id) === subjectId);
    entry.subject_name = subject ? String(subject.subject_name || '') : '';
    const teacher = findTeacherForClassSubject(state.selectedScheduleClassId, entry.subject_name);
    entry.teacher_username = teacher.username;
    entry.teacher_display = teacher.display;

    const teacherEl = document.getElementById(`teacherCell_${key.replaceAll('|', '_').replaceAll(':', '')}`);
    if (teacherEl) teacherEl.textContent = entry.teacher_display || 'TBS';
}

function onFixedScheduleLocationTypeChange(day, start, value) {
    const key = fixedScheduleKey(day, start);
    if (!state.fixedScheduleEntries[key]) return;
    state.fixedScheduleEntries[key].location_type = ['room', 'lab', 'field'].includes(String(value || '').toLowerCase()) ? String(value).toLowerCase() : 'room';
}

function onFixedScheduleLocationTextChange(day, start, value) {
    const key = fixedScheduleKey(day, start);
    if (!state.fixedScheduleEntries[key]) return;
    state.fixedScheduleEntries[key].location_text = String(value || '').trim();
}

async function loadFixedScheduleTable() {
    showClassScheduleMessage('', true, false);
    const selectedClass = findSelectedScheduleClass();
    if (!selectedClass) {
        showClassScheduleMessage('Select valid grade, section, and stream first.', false, true);
        return;
    }
    state.selectedScheduleClassId = Number(selectedClass.id || 0);
    try {
        const grade = normalizeGradeValue(selectedClass.grade_level || '');
        const stream = normalizeStreamValue(selectedClass.stream || '');
        const curriculumData = await apiGet(`${API.curriculumSubjects}?class_id=${encodeURIComponent(state.selectedScheduleClassId)}&grade=${encodeURIComponent(grade)}&stream=${encodeURIComponent(stream)}`);
        state.scheduleSubjects = curriculumData.subjects || [];
        if (!state.scheduleSubjects.length) {
            const card = document.getElementById('scheduleTimetableCard');
            if (card) card.style.display = 'none';
            showClassScheduleMessage(`No curriculum subjects found for Grade ${grade}${stream ? ` (${stream})` : ''}.`, false, true);
            return;
        }

        await loadClassSchedules();
        state.fixedScheduleEntries = {};
        state.classSchedules.forEach((item) => {
            const start = String(item.start_time || '').slice(0, 5);
            const day = String(item.day || '');
            const key = fixedScheduleKey(day, start);
            const location = parseLocation(item.room_number);
            const teacher = findTeacherForClassSubject(state.selectedScheduleClassId, item.subject_name || '');
            state.fixedScheduleEntries[key] = {
                id: Number(item.id || 0),
                day,
                start_time: start,
                end_time: String(item.end_time || '').slice(0, 5),
                subject_id: Number(item.subject_id || 0),
                subject_name: String(item.subject_name || ''),
                teacher_username: item.teacher_username || teacher.username || '',
                teacher_display: item.teacher_name && item.teacher_name !== '-' ? String(item.teacher_name) : teacher.display,
                location_type: location.type,
                location_text: location.text
            };
        });
        const card = document.getElementById('scheduleTimetableCard');
        if (card) card.style.display = 'block';
        renderFixedScheduleTable();
        showClassScheduleMessage(`Timetable loaded for ${selectedClass.display_name || (`Grade ${selectedClass.grade_level} - ${selectedClass.section}`)}.`, true, true);
    } catch (err) {
        showClassScheduleMessage(err.message || 'Failed to load timetable.', false, true);
    }
}

async function saveFixedScheduleTable() {
    if (!Number(state.selectedScheduleClassId || 0)) {
        showClassScheduleMessage('Load a timetable first.', false, true);
        return;
    }

    let createdOrUpdated = 0;
    let deleted = 0;
    const errors = [];

    for (const slot of SCHEDULE_SLOTS) {
        if (slot.break) continue;
        for (const day of SCHEDULE_DAYS) {
            const key = fixedScheduleKey(day, slot.start);
            const entry = state.fixedScheduleEntries[key];
            if (!entry) continue;

            const subjectId = Number(entry.subject_id || 0);
            if (!subjectId) {
                if (Number(entry.id || 0) > 0) {
                    try {
                        await apiPost(API.deleteClassSchedule, { schedule_id: Number(entry.id) });
                        deleted += 1;
                    } catch (err) {
                        errors.push(`${day} ${slot.start}: ${err.message || 'delete failed'}`);
                    }
                }
                continue;
            }

            const teacher = findTeacherForClassSubject(state.selectedScheduleClassId, entry.subject_name);
            const payload = {
                id: Number(entry.id || 0),
                class_id: Number(state.selectedScheduleClassId),
                subject_id: subjectId,
                teacher_username: teacher.username || '',
                day,
                start_time: slot.start,
                end_time: slot.end,
                room_number: encodeLocation(entry.location_type, entry.location_text)
            };

            try {
                await apiPost(API.saveClassSchedule, payload);
                createdOrUpdated += 1;
            } catch (err) {
                errors.push(`${day} ${slot.start}: ${err.message || 'save failed'}`);
            }
        }
    }

    await loadClassSchedules();
    await loadFixedScheduleTable();

    if (!errors.length) {
        showClassScheduleMessage(`Timetable saved. Updated/created: ${createdOrUpdated}, deleted: ${deleted}.`, true, true);
    } else {
        showClassScheduleMessage(`Saved with ${errors.length} error(s). Updated/created: ${createdOrUpdated}, deleted: ${deleted}.`, false, true);
    }
}

function switchCertTab(tab) {
    document.querySelectorAll('.cert-content').forEach((x) => x.classList.remove('active'));
    document.querySelectorAll('.cert-tab').forEach((x) => x.classList.remove('active'));

    if (tab === 'registration-slip') {
        document.getElementById('registration-slip-tab')?.classList.add('active');
        document.querySelectorAll('.cert-tab')[0]?.classList.add('active');
    } else if (tab === 'promotion-cert') {
        document.getElementById('promotion-cert-tab')?.classList.add('active');
        document.querySelectorAll('.cert-tab')[1]?.classList.add('active');
        loadPromotionCertificateCandidates();
    } else {
        document.getElementById('graduation-cert-tab')?.classList.add('active');
        document.querySelectorAll('.cert-tab')[2]?.classList.add('active');
    }
}

async function handleRegistrationSlip(event) {
    event.preventDefault();
    showCertificateMessage('registrationSlipMessage', '', true, false);
    const fd = new FormData(event.target);
    const issuedDate = event.target.querySelector('input[type="date"]')?.value || new Date().toISOString().slice(0, 10);
    const studentUsername = String(fd.get('studentId') || '').trim();

    try {
        const data = await apiPost(API.createCertificate, {
            student_username: studentUsername,
            type: 'Registration Slip',
            issued_date: issuedDate,
            remarks: 'Registration slip issued'
        });
        await loadCertificates();
        loadIssuedSlipsTable();
        showCertificateMessage('registrationSlipMessage', `Registration slip issued. Certificate No: ${data.certificate_number}`, true, true);
        printSlip(data.certificate_number);
    } catch (err) {
        showCertificateMessage('registrationSlipMessage', err.message || 'Failed to issue registration slip.', false, true);
    }
}

async function loadPromotionCertificateCandidates() {
    const grade = normalizeGradeValue(document.getElementById('promotionCertGradeFilter')?.value || '');
    const search = String(document.getElementById('promotionCertSearchInput')?.value || '').trim();
    const tbody = document.getElementById('promotionCertificateTableBody');
    if (!tbody) return;

    showCertificateMessage('promotionCertMessage', '', true, false);
    if (!grade && !search) {
        state.promotionCertCandidates = [];
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#6b7280;">Select grade or search by student name/username</td></tr>';
        return;
    }

    try {
        const url = `${API.promotionCertificateCandidates}?grade=${encodeURIComponent(grade)}&search=${encodeURIComponent(search)}`;
        const data = await apiGet(url);
        state.promotionCertCandidates = data.candidates || [];
        renderPromotionCertificateCandidatesTable();
        showCertificateMessage('promotionCertMessage', `Loaded ${state.promotionCertCandidates.length} student(s). Click Print to issue certificate card.`, true, true);
    } catch (err) {
        state.promotionCertCandidates = [];
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#6b7280;">Failed to load students</td></tr>';
        showCertificateMessage('promotionCertMessage', err.message || 'Failed to load promoted students list.', false, true);
    }
}

function renderPromotionCertificateCandidatesTable() {
    const tbody = document.getElementById('promotionCertificateTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!state.promotionCertCandidates.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#6b7280;">No promoted students found</td></tr>';
        return;
    }

    state.promotionCertCandidates.forEach((item) => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.addEventListener('click', () => {
            printPromotionCertificateByPromotionId(Number(item.promotion_id));
        });
        row.innerHTML = `
            <td>${escapeHtml(item.promotion_id)}</td>
            <td>${escapeHtml(item.student_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(normalizeGradeLabel(item.from_grade || '-'))}</td>
            <td>${escapeHtml(normalizeGradeLabel(item.to_grade || '-'))}</td>
            <td>${escapeHtml(formatDate(item.promoted_date))}</td>
            <td><button class="btn btn-small btn-primary" onclick="event.stopPropagation(); printPromotionCertificateByPromotionId(${Number(item.promotion_id)});">Print</button></td>
        `;
        tbody.appendChild(row);
    });
}

async function printPromotionCertificateByPromotionId(promotionId) {
    if (!promotionId) return;
    showCertificateMessage('promotionCertMessage', '', true, false);
    try {
        const data = await apiGet(`${API.promotionCertificateData}?promotion_id=${encodeURIComponent(promotionId)}`);
        const cert = data.certificate;
        openPromotionFinalCertificatePrintWindow(cert);
        showCertificateMessage('promotionCertMessage', `Printing certificate for ${cert.student_name}.`, true, true);
    } catch (err) {
        showCertificateMessage('promotionCertMessage', err.message || 'Failed to load promotion certificate data.', false, true);
    }
}

async function handleGraduationCertificate(event) {
    event.preventDefault();
    showCertificateMessage('graduationCertMessage', '', true, false);
    const fd = new FormData(event.target);
    const studentUsername = String(fd.get('studentId') || '').trim();
    const graduationDate = String(fd.get('graduationDate') || '').trim();
    const performance = String(fd.get('performance') || '').trim();
    const remarks = String(fd.get('remarks') || '').trim();

    try {
        const cert = await apiPost(API.createCertificate, {
            student_username: studentUsername,
            type: 'Graduation',
            issued_date: graduationDate,
            remarks: [performance, remarks].filter(Boolean).join(' | ')
        });
        await loadCertificates();
        loadGraduatedStudentsTable();
        showCertificateMessage('graduationCertMessage', `Graduation certificate issued. Certificate No: ${cert.certificate_number}`, true, true);
        printGraduationCert(cert.certificate_number);
    } catch (err) {
        showCertificateMessage('graduationCertMessage', err.message || 'Failed to issue graduation certificate.', false, true);
    }
}
function loadIssuedSlipsTable() {
    const tbody = document.getElementById('issuedSlipsTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    const items = state.certificates.filter((c) => c.type === 'Registration Slip');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No slips issued yet</td></tr>';
        return;
    }

    items.forEach((item) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(item.student_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(findStudentGrade(item.student_username))}</td>
            <td>${escapeHtml(formatDate(item.issued_date))}</td>
            <td><button class="btn btn-small btn-primary" onclick="printSlip('${escapeJs(item.certificate_number)}')">Print</button></td>
        `;
        tbody.appendChild(row);
    });
}

function loadIssuedPromotionCertsTable() {
    const tbody = document.getElementById('issuedPromotCertsTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    const items = state.certificates.filter((c) => c.type === 'Promotion');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">No promotion certificates issued yet</td></tr>';
        return;
    }

    items.forEach((item) => {
        const promo = [...state.promotions].find((p) => p.student_username === item.student_username && formatDate(p.promoted_date) === formatDate(item.issued_date));
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(item.student_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(normalizeGradeLabel(promo?.from_grade || '-'))}</td>
            <td>${escapeHtml(normalizeGradeLabel(promo?.to_grade || '-'))}</td>
            <td>${escapeHtml(formatDate(item.issued_date))}</td>
            <td><button class="btn btn-small btn-primary" onclick="printPromotionCert('${escapeJs(item.certificate_number)}')">Print</button></td>
        `;
        tbody.appendChild(row);
    });
}

function loadGraduatedStudentsTable() {
    const tbody = document.getElementById('graduatedStudentsTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    const items = state.certificates.filter((c) => c.type === 'Graduation');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6b7280;">No graduated students yet</td></tr>';
        return;
    }

    items.forEach((item) => {
        const performance = (item.remarks || '').split('|')[0] || 'N/A';
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(item.student_username)}</td>
            <td>${escapeHtml(item.full_name || '-')}</td>
            <td>${escapeHtml(formatDate(item.issued_date))}</td>
            <td>${escapeHtml(performance.trim())}</td>
            <td><span class="status-badge approved">Graduated</span></td>
            <td><button class="btn btn-small btn-primary" onclick="printGraduationCert('${escapeJs(item.certificate_number)}')">Print</button></td>
        `;
        tbody.appendChild(row);
    });
}

function printSlip(certificateNumber) {
    printRegistrationSlip(certificateNumber);
}

async function printRegistrationSlip(certificateNumber) {
    const cert = findCertificate(certificateNumber, 'Registration Slip');
    if (!cert) {
        showCertificateMessage('registrationSlipMessage', 'Certificate not found for printing.', false, true);
        return;
    }

    let details = null;
    try {
        const data = await apiGet(`${API.registrationSlipDetails}?username=${encodeURIComponent(cert.student_username)}`);
        details = data.details || null;
    } catch (err) {
        showCertificateMessage('registrationSlipMessage', err.message || 'Failed to load slip details.', false, true);
        return;
    }

    const grade = findStudentGrade(cert.student_username);
    const ok = openCertificatePrintWindow({
        title: 'Student Registration Slip',
        certificateType: 'Registration Slip',
        certificateNumber: cert.certificate_number,
        studentUsername: cert.student_username,
        studentPassword: details?.password || '-',
        studentName: details?.full_name || cert.full_name || findStudentName(cert.student_username),
        grade,
        issuedDate: cert.issued_date,
        metaRows: [
            ['Subjects', String(details?.subject_count ?? 0)],
            ['Academic Year', details?.academic_year || 'N/A'],
            ['School', getSchoolNameForPrint()]
        ]
    });
    if (!ok) {
        showCertificateMessage('registrationSlipMessage', 'Could not open print window. Allow popups and try again.', false, true);
    }
}

function printPromotionCert(certificateNumber) {
    const cert = findCertificate(certificateNumber, 'Promotion');
    if (!cert) {
        showCertificateMessage('promotionCertMessage', 'Certificate not found for printing.', false, true);
        return;
    }
    const promo = state.promotions.find((p) => p.student_username === cert.student_username && formatDate(p.promoted_date) === formatDate(cert.issued_date))
        || state.promotions.find((p) => p.student_username === cert.student_username)
        || null;
    const ok = openCertificatePrintWindow({
        title: 'Promotion Certificate',
        certificateType: 'Promotion',
        certificateNumber: cert.certificate_number,
        studentUsername: cert.student_username,
        studentName: cert.full_name || findStudentName(cert.student_username),
        grade: normalizeGradeLabel(promo?.to_grade || findStudentGrade(cert.student_username)),
        issuedDate: cert.issued_date,
        metaRows: [
            ['From Grade', normalizeGradeLabel(promo?.from_grade || '-')],
            ['To Grade', normalizeGradeLabel(promo?.to_grade || '-')],
            ['School', getSchoolNameForPrint()]
        ]
    });
    if (!ok) {
        showCertificateMessage('promotionCertMessage', 'Could not open print window. Allow popups and try again.', false, true);
    }
}

function openPromotionFinalCertificatePrintWindow(cert) {
    const win = window.open('', '_blank', 'width=980,height=760');
    if (!win) {
        showCertificateMessage('promotionCertMessage', 'Could not open print window. Allow popups and try again.', false, true);
        return;
    }

    const subjectRows = (cert.subject_results || []).map((s, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td>${escapeHtml(s.subject_name || '-')}</td>
            <td>${escapeHtml(s.term || '-')}</td>
            <td>${escapeHtml(s.marks ?? '-')}</td>
            <td>${escapeHtml(s.letter_grade || '-')}</td>
        </tr>
    `).join('');

    const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Promotion Certificate - ${escapeHtml(cert.student_username)}</title>
  <style>
    body { margin: 0; padding: 18px; background: #f8fafc; font-family: "Times New Roman", serif; color: #0f172a; }
    .page { max-width: 980px; margin: 0 auto; background: #fff; border: 8px double #0f172a; padding: 24px 28px; }
    .top { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 18px; }
    .school { font-size: 30px; font-weight: 700; letter-spacing: 1px; color: #1e3a8a; margin: 0; }
    .title { font-size: 26px; margin: 6px 0 0 0; text-transform: uppercase; letter-spacing: 2px; }
    .sub { font-size: 14px; color: #334155; margin-top: 4px; }
    .student { margin: 20px 0; text-align: center; }
    .student .name { font-size: 32px; font-weight: 700; margin: 8px 0; color: #0f172a; }
    .student .line { font-size: 16px; margin: 4px 0; }
    .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0; }
    .box { border: 1px solid #94a3b8; padding: 8px 10px; background: #f8fafc; }
    .box .k { font-size: 12px; color: #475569; text-transform: uppercase; }
    .box .v { font-size: 18px; font-weight: 700; margin-top: 3px; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    th, td { border: 1px solid #94a3b8; padding: 8px; font-size: 13px; }
    th { background: #e2e8f0; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
    .foot { margin-top: 18px; display: flex; justify-content: space-between; align-items: end; }
    .remarks { font-size: 13px; color: #334155; max-width: 70%; }
    .sig { text-align: center; min-width: 220px; }
    .sig .line { border-top: 1px solid #334155; margin-top: 28px; padding-top: 6px; font-size: 13px; }
    @media print { body { background: #fff; padding: 0; } .page { border: 6px double #0f172a; } }
  </style>
</head>
<body>
  <div class="page">
    <div class="top">
      <p class="school">${escapeHtml(cert.school_name || 'Bensa School')}</p>
      <p class="title">Final Promotion Certificate</p>
      <p class="sub">Academic Year: ${escapeHtml(cert.academic_year || 'N/A')}</p>
    </div>

    <div class="student">
      <div class="line">This is to certify that</div>
      <div class="name">${escapeHtml(cert.student_name || '-')}</div>
      <div class="line">Student ID: <strong>${escapeHtml(cert.student_username || '-')}</strong></div>
      <div class="line">has successfully completed <strong>${escapeHtml(normalizeGradeLabel(cert.from_grade || '-'))}</strong> and is promoted to <strong>${escapeHtml(normalizeGradeLabel(cert.to_grade || '-'))}</strong>.</div>
    </div>

    <div class="summary">
      <div class="box"><div class="k">Total Marks</div><div class="v">${escapeHtml(cert.total_marks ?? 0)}</div></div>
      <div class="box"><div class="k">Average</div><div class="v">${escapeHtml(cert.average_marks ?? 0)}</div></div>
      <div class="box"><div class="k">Overall Grade</div><div class="v">${escapeHtml(cert.overall_letter_grade || 'N/A')}</div></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Subject</th>
          <th>Term</th>
          <th>Marks</th>
          <th>Letter Grade</th>
        </tr>
      </thead>
      <tbody>
        ${subjectRows || '<tr><td colspan="5" style="text-align:center;">No subject results available</td></tr>'}
      </tbody>
    </table>

    <div class="foot">
      <div class="remarks"><strong>Remarks:</strong> ${escapeHtml(cert.remarks || 'Promoted based on final grade results.')}</div>
      <div class="sig">
        <div>${escapeHtml(formatDate(cert.promoted_date))}</div>
        <div class="line">Registrar Signature</div>
      </div>
    </div>
  </div>
  <script>window.onload=function(){window.print();};</script>
</body>
</html>`;

    win.document.open();
    win.document.write(html);
    win.document.close();
}

function printGraduationCert(certificateNumber) {
    const cert = findCertificate(certificateNumber, 'Graduation');
    if (!cert) {
        showCertificateMessage('graduationCertMessage', 'Certificate not found for printing.', false, true);
        return;
    }
    const performance = (cert.remarks || '').split('|')[0]?.trim() || 'N/A';
    const ok = openCertificatePrintWindow({
        title: 'Graduation Certificate',
        certificateType: 'Graduation',
        certificateNumber: cert.certificate_number,
        studentUsername: cert.student_username,
        studentName: cert.full_name || findStudentName(cert.student_username),
        grade: 'Grade 12',
        issuedDate: cert.issued_date,
        metaRows: [
            ['Performance', performance],
            ['School', getSchoolNameForPrint()]
        ]
    });
    if (!ok) {
        showCertificateMessage('graduationCertMessage', 'Could not open print window. Allow popups and try again.', false, true);
    }
}

function showCertificateMessage(elementId, message, success = true, visible = true) {
    const box = document.getElementById(elementId);
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function findCertificate(certificateNumber, type) {
    return state.certificates.find((c) => c.certificate_number === certificateNumber && (!type || c.type === type)) || null;
}

function findStudentName(studentUsername) {
    const student = state.students.find((s) => s.student_id_generated === studentUsername);
    return student?.full_name || '-';
}

function getSchoolNameForPrint() {
    return state.dashboard?.school_settings?.school_name || 'Bensa School';
}

function openCertificatePrintWindow(details) {
    const win = window.open('', '_blank', 'width=900,height=700');
    if (!win) return false;

    const metaRows = (details.metaRows || [])
        .map(([k, v]) => `<tr><td class="k">${escapeHtml(k)}</td><td>${escapeHtml(v ?? '-')}</td></tr>`)
        .join('');

    const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${escapeHtml(details.title)}</title>
<style>
body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
.wrap { max-width: 820px; margin: 0 auto; border: 2px solid #111827; padding: 24px; }
h1 { margin: 0 0 8px; font-size: 28px; text-align: center; }
h2 { margin: 0 0 24px; font-size: 18px; text-align: center; color: #374151; }
.lead { text-align: center; margin: 24px 0; font-size: 16px; }
.name { font-size: 26px; font-weight: 700; text-align: center; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
td { border: 1px solid #d1d5db; padding: 10px; font-size: 14px; }
td.k { width: 220px; font-weight: 700; background: #f3f4f6; }
.footer { margin-top: 28px; display: flex; justify-content: space-between; font-size: 13px; }
@media print { body { margin: 0; } .wrap { border: none; } }
</style>
</head>
<body>
  <div class="wrap">
    <h1>${escapeHtml(getSchoolNameForPrint())}</h1>
    <h2>${escapeHtml(details.title)}</h2>
    <p class="lead">This certifies that</p>
    <p class="name">${escapeHtml(details.studentName)}</p>
    <p class="lead">Username: <strong>${escapeHtml(details.studentUsername)}</strong></p>
    <p class="lead">Password: <strong>${escapeHtml(details.studentPassword || '-')}</strong></p>
    <table>
      <tr><td class="k">Certificate Type</td><td>${escapeHtml(details.certificateType)}</td></tr>
      <tr><td class="k">Certificate Number</td><td>${escapeHtml(details.certificateNumber)}</td></tr>
      <tr><td class="k">Grade</td><td>${escapeHtml(details.grade || '-')}</td></tr>
      <tr><td class="k">Issued Date</td><td>${escapeHtml(formatDate(details.issuedDate))}</td></tr>
      ${metaRows}
    </table>
    <div class="footer">
      <div>Prepared by: Registration Office</div>
      <div>Signature: ____________________</div>
    </div>
  </div>
  <script>window.onload=function(){window.print();};</script>
</body>
</html>`;
    win.document.open();
    win.document.write(html);
    win.document.close();
    return true;
}

function updatePromotionSectionOptions() {
    const grade = normalizeGradeValue(document.getElementById('promotionGradeFilter')?.value || '');
    const sectionSelect = document.getElementById('promotionSectionFilter');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    if (!grade) {
        sectionSelect.innerHTML = '<option value="">-- Select Grade First --</option>';
        return;
    }

    const sections = [...new Set(
        state.classes
            .filter((c) => normalizeGradeValue(c.grade_level) === grade)
            .map((c) => String(c.section || '').trim())
            .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b));

    if (!sections.length) {
        sectionSelect.innerHTML = '<option value="">-- No Sections Found --</option>';
        return;
    }

    sections.forEach((s) => {
        const option = document.createElement('option');
        option.value = s;
        option.textContent = s;
        sectionSelect.appendChild(option);
    });
}

async function evaluatePromotionEligibility() {
    const grade = normalizeGradeValue(document.getElementById('promotionGradeFilter')?.value || '');
    const section = String(document.getElementById('promotionSectionFilter')?.value || '').trim();
    if (!grade || !section) {
        showPromotionMessage('Select grade and section first.', false, true);
        return;
    }

    showPromotionMessage('', true, false);
    try {
        const data = await apiGet(`${API.evaluatePromotions}?grade=${encodeURIComponent(grade)}&section=${encodeURIComponent(section)}`);
        state.promotionEvaluation = {
            ready_students: data.ready_students || [],
            not_ready_students: data.not_ready_students || [],
            missing_grade_students: data.missing_grade_students || [],
            next_grade: data.next_grade || null
        };
        showPromotionResultTables(true);
        renderPromotionEvaluationTables();
        const msg = `Evaluation complete. Ready: ${state.promotionEvaluation.ready_students.length}, Not Ready: ${state.promotionEvaluation.not_ready_students.length}, Missing Grades: ${state.promotionEvaluation.missing_grade_students.length}.`;
        showPromotionMessage(msg, true, true);
    } catch (err) {
        resetPromotionEvaluationTables();
        showPromotionMessage(err.message || 'Failed to evaluate promotion.', false, true);
    }
}

async function promoteReadyStudents() {
    const grade = normalizeGradeValue(document.getElementById('promotionGradeFilter')?.value || '');
    const section = String(document.getElementById('promotionSectionFilter')?.value || '').trim();
    const targetStream = String(document.getElementById('promotionTargetStream')?.value || '').trim();
    if (!grade || !section) {
        showPromotionMessage('Select grade and section first.', false, true);
        return;
    }
    if (grade === '10' && !targetStream) {
        showPromotionMessage('For Grade 10 promotion, select target stream (Natural/Social).', false, true);
        return;
    }
    if (!state.promotionEvaluation.ready_students.length) {
        showPromotionMessage('No ready students to promote.', false, true);
        return;
    }

    const studentUsernames = state.promotionEvaluation.ready_students.map((s) => s.student_username);
    try {
        const data = await apiPost(API.processPromotions, {
            grade,
            section,
            target_stream: targetStream,
            student_usernames: studentUsernames
        });
        await Promise.all([
            loadPromotions(),
            loadStudentsDirectory(),
            loadAssignedStudents(),
            loadClasses(),
            loadDashboard()
        ]);
        loadStudentSelects();
        await evaluatePromotionEligibility();
        showPromotionMessage(`Promotion completed. ${data.promoted_count} students promoted to Grade ${data.to_grade}.`, true, true);
    } catch (err) {
        showPromotionMessage(err.message || 'Failed to process promotions.', false, true);
    }
}

function resetPromotionEvaluationTables() {
    state.promotionEvaluation = {
        ready_students: [],
        not_ready_students: [],
        missing_grade_students: [],
        next_grade: null
    };
    showPromotionResultTables(false);
}

function showPromotionResultTables(visible) {
    ['promotionReadyCard', 'promotionNotReadyCard', 'promotionMissingCard'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = visible ? 'block' : 'none';
        }
    });
}

function renderPromotionEvaluationTables() {
    const readyBody = document.getElementById('readyPromotionTable');
    const notReadyBody = document.getElementById('notReadyPromotionTable');
    const missingBody = document.getElementById('missingGradesTable');
    if (!readyBody || !notReadyBody || !missingBody) return;

    readyBody.innerHTML = '';
    notReadyBody.innerHTML = '';
    missingBody.innerHTML = '';

    const ready = state.promotionEvaluation.ready_students || [];
    const notReady = state.promotionEvaluation.not_ready_students || [];
    const missing = state.promotionEvaluation.missing_grade_students || [];
    const nextGrade = state.promotionEvaluation.next_grade || '-';

    if (!ready.length) {
        readyBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No ready students</td></tr>';
    } else {
        ready.forEach((s) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(s.student_username)}</td>
                <td>${escapeHtml(s.full_name || '-')}</td>
                <td>${escapeHtml(Number(s.cgpa || 0).toFixed(2))}</td>
                <td>${escapeHtml(s.f_count ?? 0)}</td>
                <td>${escapeHtml(normalizeGradeLabel(s.to_grade || nextGrade))}</td>
            `;
            readyBody.appendChild(row);
        });
    }

    if (!notReady.length) {
        notReadyBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No students in this list</td></tr>';
    } else {
        notReady.forEach((s) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(s.student_username)}</td>
                <td>${escapeHtml(s.full_name || '-')}</td>
                <td>${escapeHtml(Number(s.cgpa || 0).toFixed(2))}</td>
                <td>${escapeHtml(s.f_count ?? 0)}</td>
                <td>${escapeHtml(s.reason || '-')}</td>
            `;
            notReadyBody.appendChild(row);
        });
    }

    if (!missing.length) {
        missingBody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">No missing grades</td></tr>';
    } else {
        missing.forEach((s) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(s.student_username)}</td>
                <td>${escapeHtml(s.full_name || '-')}</td>
                <td>${escapeHtml(s.submitted_subjects ?? 0)}</td>
                <td>${escapeHtml(s.expected_subjects ?? 0)}</td>
            `;
            missingBody.appendChild(row);
        });
    }
}

function showPromotionMessage(message, success = true, visible = true) {
    const box = document.getElementById('promotionMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function loadReports() {
    const registrations = state.registrations;
    const total = registrations.length;
    const students = registrations.filter((r) => r.role === 'student').length;
    const teachers = registrations.filter((r) => r.role === 'teacher').length;
    const admins = registrations.filter((r) => r.role === 'admin').length;
    const approved = registrations.filter((r) => r.status === 'approved').length;

    setText('reportTotal', total);
    setText('reportStudents', students);
    setText('reportTeachers', teachers);
    setText('reportAdmins', admins);
    setText('reportApprovalRate', total ? `${Math.round((approved / total) * 100)}%` : '0%');

    const now = new Date();
    const thisMonth = registrations.filter((r) => {
        const d = new Date(r.submitted_at);
        return !isNaN(d.getTime()) && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
    }).length;
    setText('reportThisMonth', thisMonth);
}

function exportToCSV() {
    const rows = [['Username', 'Name', 'Role', 'Email', 'Submitted At', 'Status']];
    state.filteredRegistrations.forEach((r) => {
        rows.push([r.username, r.full_name || '', r.role_label, r.email || '', formatDate(r.submitted_at), r.status || '']);
    });

    const csv = rows.map((r) => r.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(',')).join('\n');
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = 'registrations.csv';
    link.click();
}

function exportToPDF() {
    alert('PDF export is not configured yet. CSV export is available now.');
}

function printReport() {
    window.print();
}

function openPasswordModal() {
    alert('Use the backend change password endpoint from your account settings page.');
}

function mapRoleLabel(role) {
    if (role === 'student') return 'Student';
    if (role === 'teacher') return 'Teacher';
    if (role === 'admin') return 'Admin';
    if (role === 'director') return 'Director';
    return role || '-';
}

function normalizeGradeValue(label) {
    const d = String(label || '').match(/\d+/);
    return d ? d[0] : label;
}

function normalizeGradeLabel(value) {
    const d = String(value || '').match(/\d+/);
    return d ? `Grade ${d[0]}` : String(value || '-');
}

function findStudentGrade(username) {
    const student = state.students.find((s) => s.student_id_generated === username);
    return student ? normalizeGradeLabel(student.grade_level) : '-';
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

function setInputValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
}

function formatDate(v) {
    if (!v) return '-';
    const d = new Date(v);
    return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString();
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

function showFormSuccessMessage(form, message, visible = true) {
    if (!form) return;
    const box = form.querySelector('.form-success-message');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = 'form-success-message show success';
}

function showAssignBatchMessage(message, success = true, visible = true) {
    const box = document.getElementById('assignStudentsBatchMessage');
    if (!box) return;
    if (!visible || !message) {
        box.className = 'form-success-message';
        box.textContent = '';
        return;
    }
    box.textContent = String(message);
    box.className = success ? 'form-success-message show success' : 'form-success-message show error';
}

function clearFormErrors(form) {
    if (!form) return;
    form.querySelectorAll('.form-control.error').forEach((el) => el.classList.remove('error'));
    form.querySelectorAll('.field-error').forEach((el) => el.remove());
}

function setFieldError(form, fieldName, message) {
    if (!form || !fieldName || !message) return;
    const field = form.querySelector(`[name="${fieldName}"]`);
    if (!field) return;
    field.classList.add('error');
    const err = document.createElement('div');
    err.className = 'field-error';
    err.textContent = message;
    field.insertAdjacentElement('afterend', err);
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function applyRegistrationFieldError(form, message, type) {
    const msg = String(message || '').toLowerCase();
    if (msg.includes('email already')) {
        setFieldError(form, 'email', 'This email is already registered.');
        return;
    }
    if (msg.includes('invalid email')) {
        setFieldError(form, 'email', 'Enter a valid email address.');
        return;
    }
    if (msg.includes('age')) {
        setFieldError(form, 'age', 'Age is outside allowed range.');
        return;
    }
    if (msg.includes('phone')) {
        setFieldError(form, type === 'student' ? 'parentPhone' : 'officePhone', 'Enter a valid phone number.');
        return;
    }
    if (msg.includes('required') || msg.includes('must be filled')) {
        const requiredNames = type === 'student'
            ? ['fname', 'lname', 'email', 'dateOfBirth', 'age', 'sex', 'gradeLevel', 'address', 'parentName', 'parentPhone']
            : ['fname', 'lname', 'email', 'dateOfBirth', 'age', 'sex', 'department', 'subject', 'address'];
        for (const name of requiredNames) {
            const field = form.querySelector(`[name="${name}"]`);
            if (field && !String(field.value || '').trim()) {
                setFieldError(form, name, 'This field is required.');
                break;
            }
        }
        return;
    }
    const firstInput = form.querySelector('.form-control');
    if (firstInput) {
        setFieldError(form, firstInput.getAttribute('name'), String(message || 'Invalid input'));
    }
}
