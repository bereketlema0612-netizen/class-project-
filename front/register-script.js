
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
    studentProfile: '../backend/registration/get_student_profile.php',
    updateStudentProfile: '../backend/registration/update_student_profile.php',
    deleteStudent: '../backend/registration/delete_student.php',
    teacherProfile: '../backend/registration/get_teacher_profile.php',
    updateTeacherProfile: '../backend/registration/update_teacher_profile.php'
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
    filteredStudents: [],
    selectedStudentProfile: null,
    filteredTeachers: [],
    selectedTeacherProfile: null,
    allRecordsTab: 'students',
    studentRecordsLoaded: false
};

document.addEventListener('DOMContentLoaded', async () => {
    state.user = getCurrentUser();
    if (!state.user || !['admin', 'director'].includes(state.user.role)) {
        window.location.href = 'login.html';
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
        loadAssignedTeachers()
    ]);

    applyRegistrationFilters();
    loadStudentSelects();
    loadTeacherSelects();
    populateStudentGradeFilter();
    renderStudentRecordsTable();
    renderTeacherRecordsTable();
    toggleStudentStreamField();
    toggleProfileStreamField();
    toggleAssignTeacherStreamField();

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

    document.getElementById('logoutBtn')?.addEventListener('click', handleAdminLogout);

    document.getElementById('searchInput')?.addEventListener('input', () => {
        applyRegistrationFilters();
    });
    document.getElementById('studentGradeFilter')?.addEventListener('change', () => {
        prepareStudentRecordsAwaitingLoad();
    });
    document.getElementById('studentSearchInput')?.addEventListener('input', prepareStudentRecordsAwaitingLoad);

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
    document.getElementById('studentGradeLevel')?.addEventListener('change', toggleStudentStreamField);
    document.getElementById('profileGrade')?.addEventListener('change', toggleProfileStreamField);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeStudentProfileModal();
            closeTeacherProfileModal();
        }
    });

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
        'all-registrations': 'All Registrations'
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
}

async function loadClasses() {
    const data = await apiGet(API.classes);
    state.classes = data.classes || [];
}

async function loadSubjects() {
    const data = await apiGet(API.subjects);
    state.subjects = data.subjects || [];
    populateTeacherSubjectOptions();
}

async function loadAssignedStudents() {
    const data = await apiGet(API.assignedStudents);
    state.assignedStudents = data.assigned_students || [];
}

async function loadAssignedTeachers() {
    const data = await apiGet(API.assignedTeachers);
    state.assignedTeachers = data.assigned_teachers || [];
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

function loadReports() {}

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

async function handleAdminLogout(event) {
    if (event?.preventDefault) event.preventDefault();
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (_) {}
    localStorage.removeItem('currentUser');
    window.location.href = 'login.html';
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

