const APP_BASE = '/bensa_school';
const FRONT_BASE = APP_BASE + '/front';

const API = {
    dashboard: APP_BASE + '/backend/teacher/get_teacher_dashboard.php',
    profile: APP_BASE + '/backend/teacher/get_teacher_profile.php',
    announcements: APP_BASE + '/backend/teacher/get_announcements.php',
    createAnnouncement: APP_BASE + '/backend/teacher/create_announcement.php',
    classStudents: APP_BASE + '/backend/teacher/get_class_students.php',
    classSubjects: APP_BASE + '/backend/teacher/get_class_subjects.php',
    enterGrades: APP_BASE + '/backend/teacher/enter_grades.php',
    logout: APP_BASE + '/backend/auth/logout.php'
};

var state = {
    user: null,
    classes: [],
    announcements: [],
    profile: null
};

function el(id) {
    return document.getElementById(id);
}

document.addEventListener('DOMContentLoaded', init);

async function init() {
    state.user = getCurrentUser();
    if (!state.user || state.user.role !== 'teacher') {
        window.location.href = FRONT_BASE + '/login.html';
        return;
    }

    bindNavigation();
    bindEvents();

    try {
        await loadTeacherData();
    } catch (err) {
        showMessage('teacherPageMessage', 'Failed to load teacher data: ' + err.message, true);
    }
}

function bindNavigation() {
    var items = document.querySelectorAll('.nav-item');
    items.forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            switchPage(item.getAttribute('data-page'));
        });
    });
}

function bindEvents() {
    el('logoutBtn').addEventListener('click', logout);
    el('openProfileBtn').addEventListener('click', function () { switchPage('profile'); });

    el('classSelectForGrading').addEventListener('change', onGradingClassChange);
    el('sectionSelectForGrading').addEventListener('change', onGradingSectionChange);
    el('loadStudentsBtn').addEventListener('click', loadStudentsForGrading);
    el('selectAllStudentsForGrade').addEventListener('change', toggleSelectAllStudents);
    el('submitGradesBtn').addEventListener('click', submitSelectedGrades);

    el('openAnnouncementModalBtn').addEventListener('click', openAnnouncementModal);
    el('closeAnnouncementModalBtn').addEventListener('click', closeAnnouncementModal);
    el('postAnnouncementBtn').addEventListener('click', saveAnnouncement);
    el('announcementTargetMode').addEventListener('change', onAnnouncementTargetModeChange);
    el('announcementFile').addEventListener('change', updateAnnouncementFilePreview);
    el('removeAnnouncementFileBtn').addEventListener('click', removeAnnouncementFile);
}

function switchPage(pageName) {
    var pages = document.querySelectorAll('.page');
    var nav = document.querySelectorAll('.nav-item');

    pages.forEach(function (p) { p.classList.remove('active'); });
    nav.forEach(function (n) { n.classList.remove('active'); });

    var page = el(pageName);
    if (page) {
        page.classList.add('active');
    }

    var activeNav = document.querySelector('.nav-item[data-page="' + pageName + '"]');
    if (activeNav) {
        activeNav.classList.add('active');
    }

    var titleMap = {
        dashboard: 'Dashboard',
        grading: 'Grading',
        announcements: 'Announcements',
        profile: 'Profile'
    };
    el('pageTitle').textContent = titleMap[pageName] || 'Dashboard';
}

async function loadTeacherData() {
    var dashboard = await getJSON(API.dashboard);
    var profile = await getJSON(API.profile);
    var announcements = await getJSON(API.announcements + '?page=1&limit=50');

    state.classes = dashboard.assigned_classes || [];
    state.profile = profile.teacher || null;
    state.announcements = announcements.announcements || [];

    renderDashboard(dashboard);
    renderProfile();
    renderAnnouncements();
    fillClassSelects();
}

function renderDashboard(dashboard) {
    var stats = dashboard.statistics || {};
    setText('totalClasses', stats.total_classes || 0);
    setText('totalStudents', stats.total_students || 0);
    setText('pendingGrading', 0);

    var box = el('gradingStatusContainer');
    box.innerHTML = '';

    if (state.classes.length === 0) {
        box.innerHTML = '<p>No assigned class yet.</p>';
        return;
    }

    state.classes.forEach(function (c) {
        var div = document.createElement('div');
        div.className = 'card';
        div.innerHTML = '<strong>' + escapeHtml(c.name || ('Class ' + c.class_id)) + '</strong> - Ready';
        box.appendChild(div);
    });
}

function renderProfile() {
    var p = state.profile || {};
    var name = '';
    if (p.fname || p.lname) {
        name = ((p.fname || '') + ' ' + (p.lname || '')).trim();
    } else {
        name = state.user.username || 'Teacher';
    }

    setText('profileName', name);
    setText('profileDept', 'Department: ' + (p.department || 'Teacher'));
    setText('profileEmpID', 'Employee ID: ' + (p.employee_id_generated || p.username || '-'));
    setText('profileEmail', p.email || '-');
    setText('profilePhone', p.office_phone || '-');
    setText('profileOffice', p.office_room || '-');
}

function renderAnnouncements() {
    var box = el('announcementsContainer');
    box.innerHTML = '';

    if (state.announcements.length === 0) {
        box.innerHTML = '<div class="card announcement-card"><p class="announcement-body">No announcements yet.</p></div>';
        return;
    }

    state.announcements.forEach(function (a) {
        var div = document.createElement('div');
        div.className = 'card announcement-card';

        var fileHtml = '';
        if (a.attachment_url) {
            var fileUrl = APP_BASE + '/' + String(a.attachment_url).replace(/^\/+/, '');
            var fileName = a.attachment_name || 'Download file';
            fileHtml = '<p class="announcement-body"><a href="' + escapeHtml(fileUrl) + '" target="_blank">Attachment: ' + escapeHtml(fileName) + '</a></p>';
        }

        var category = '';
        if (a.class_name) {
            category = a.class_name;
        } else if (a.class_id) {
            category = 'Class ' + a.class_id;
        } else {
            category = 'Teacher';
        }

        div.innerHTML =
            '<div class="announcement-header">' +
            '<h3>' + escapeHtml(a.title || 'Untitled') + '</h3>' +
            '<p class="announcement-date">' + escapeHtml(formatDate(a.created_at)) + '</p>' +
            '</div>' +
            '<p class="announcement-category">' + escapeHtml(String(category).toUpperCase()) + '</p>' +
            '<p class="announcement-body">' + escapeHtml(a.message || '') + '</p>' +
            fileHtml;

        box.appendChild(div);
    });
}

function fillClassSelects() {
    var single = el('announcementClassSelect');
    var multi = el('announcementClassMultiSelect');
    var gradeSelect = el('classSelectForGrading');

    single.innerHTML = '<option value="">-- Choose --</option>';
    multi.innerHTML = '';
    gradeSelect.innerHTML = '<option value="">-- Choose --</option>';

    var grades = [];

    state.classes.forEach(function (c) {
        var name = c.name || ('Class ' + c.class_id);
        var classId = String(c.class_id || '');

        var op1 = document.createElement('option');
        op1.value = classId;
        op1.textContent = name;
        single.appendChild(op1);

        var op2 = document.createElement('option');
        op2.value = classId;
        op2.textContent = name;
        multi.appendChild(op2);

        var parts = classNameParts(name, c.class_id);
        if (grades.indexOf(parts.grade) === -1) {
            grades.push(parts.grade);
        }
    });

    grades.forEach(function (g) {
        var op = document.createElement('option');
        op.value = g;
        op.textContent = g === 'General' ? 'General' : ('Grade ' + g);
        gradeSelect.appendChild(op);
    });
}

function classNameParts(name, classId) {
    var text = String(name || ('Class ' + classId));
    var match = text.match(/Grade\s*(\d+)\s*-\s*([A-Za-z0-9_-]+)/i);
    if (match) {
        return { grade: match[1], section: match[2] };
    }
    return { grade: 'General', section: String(classId || '') };
}

function onGradingClassChange() {
    var grade = el('classSelectForGrading').value;
    var section = el('sectionSelectForGrading');
    var subject = el('gradingSubjectSelect');

    section.innerHTML = '<option value="">-- Choose --</option>';
    subject.innerHTML = '<option value="">-- Choose --</option>';

    state.classes.forEach(function (c) {
        var parts = classNameParts(c.name, c.class_id);
        if (parts.grade === grade) {
            var op = document.createElement('option');
            op.value = String(c.class_id || '');
            op.textContent = parts.section || ('Class ' + c.class_id);
            section.appendChild(op);
        }
    });
}

async function onGradingSectionChange() {
    var classId = Number(el('sectionSelectForGrading').value || 0);
    var subject = el('gradingSubjectSelect');

    subject.innerHTML = '<option value="">Loading...</option>';

    if (classId <= 0) {
        subject.innerHTML = '<option value="">-- Choose --</option>';
        return;
    }

    try {
        var data = await getJSON(API.classSubjects + '?class_id=' + classId);
        var list = data.subjects || [];
        subject.innerHTML = '<option value="">-- Choose --</option>';
        list.forEach(function (s) {
            var op = document.createElement('option');
            op.value = s;
            op.textContent = s;
            subject.appendChild(op);
        });
    } catch (err) {
        subject.innerHTML = '<option value="">Failed to load</option>';
    }
}

async function loadStudentsForGrading() {
    var classId = Number(el('sectionSelectForGrading').value || 0);
    var subject = el('gradingSubjectSelect').value.trim();

    if (classId <= 0 || subject === '') {
        showMessage('gradingScoresMessage', 'Choose section and subject first.', true);
        return;
    }

    try {
        var data = await getJSON(API.classStudents + '?class_id=' + classId);
        var students = data.students || [];
        var body = el('gradingTableBody');
        body.innerHTML = '';

        var gradeText = el('classSelectForGrading').value || '';

        students.forEach(function (s) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-student', s.student_username || '');
            tr.innerHTML =
                '<td><input type="checkbox" class="student-check"></td>' +
                '<td>' + escapeHtml(s.student_username || '') + '</td>' +
                '<td>' + escapeHtml(s.full_name || '') + '</td>' +
                '<td>' + escapeHtml(gradeText) + '</td>' +
                '<td><input type="number" class="score-ass" min="0" max="10" value="0"></td>' +
                '<td><input type="number" class="score-mid" min="0" max="30" value="0"></td>' +
                '<td><input type="number" class="score-fin" min="0" max="60" value="0"></td>' +
                '<td class="score-total">0</td>' +
                '<td class="score-letter">F</td>';

            body.appendChild(tr);
        });

        var rows = body.querySelectorAll('tr');
        rows.forEach(function (row) {
            var recalc = function () {
                var sc = getScores(row);
                row.querySelector('.score-total').textContent = String(sc.total);
                row.querySelector('.score-letter').textContent = gradeLetter(sc.total);
            };
            row.querySelector('.score-ass').addEventListener('input', recalc);
            row.querySelector('.score-mid').addEventListener('input', recalc);
            row.querySelector('.score-fin').addEventListener('input', recalc);
            recalc();
        });

        el('gradingTableSection').style.display = 'block';
        showMessage('gradingScoresMessage', 'Loaded ' + students.length + ' students.', false);
    } catch (err) {
        showMessage('gradingScoresMessage', 'Failed to load students: ' + err.message, true);
    }
}

function toggleSelectAllStudents() {
    var checked = el('selectAllStudentsForGrade').checked;
    var list = document.querySelectorAll('#gradingTableBody .student-check');
    list.forEach(function (c) { c.checked = checked; });
}

async function submitSelectedGrades() {
    var classId = Number(el('sectionSelectForGrading').value || 0);
    var subject = el('gradingSubjectSelect').value.trim();
    var term = el('gradingTermSelect').value.trim();

    var rows = Array.from(document.querySelectorAll('#gradingTableBody tr')).filter(function (r) {
        var cb = r.querySelector('.student-check');
        return cb && cb.checked;
    });

    if (classId <= 0 || subject === '' || rows.length === 0) {
        showMessage('gradingBulkMessage', 'Select class, subject and students.', true);
        return;
    }

    try {
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var student = row.getAttribute('data-student') || '';
            var sc = getScores(row);

            await postJSON(API.enterGrades, {
                student_username: student,
                class_id: classId,
                term: term,
                subject: subject,
                assignment_marks: sc.assignment,
                mid_marks: sc.mid,
                final_marks: sc.final,
                marks: sc.total
            });
        }

        showMessage('gradingBulkMessage', 'Grades submitted successfully.', false);
    } catch (err) {
        showMessage('gradingBulkMessage', 'Failed to submit grades: ' + err.message, true);
    }
}

function getScores(row) {
    var ass = Number(row.querySelector('.score-ass').value || 0);
    var mid = Number(row.querySelector('.score-mid').value || 0);
    var fin = Number(row.querySelector('.score-fin').value || 0);

    ass = clamp(ass, 0, 10);
    mid = clamp(mid, 0, 30);
    fin = clamp(fin, 0, 60);

    row.querySelector('.score-ass').value = String(ass);
    row.querySelector('.score-mid').value = String(mid);
    row.querySelector('.score-fin').value = String(fin);

    return {
        assignment: ass,
        mid: mid,
        final: fin,
        total: ass + mid + fin
    };
}

function clamp(v, min, max) {
    if (!Number.isFinite(v)) return min;
    if (v < min) return min;
    if (v > max) return max;
    return v;
}

function gradeLetter(total) {
    if (total >= 90) return 'A';
    if (total >= 80) return 'B';
    if (total >= 70) return 'C';
    if (total >= 60) return 'D';
    return 'F';
}

function openAnnouncementModal() {
    el('announcementModal').style.display = 'flex';
    showMessage('announcementInlineMessage', '', false);
}

function closeAnnouncementModal() {
    el('announcementModal').style.display = 'none';
    showMessage('announcementInlineMessage', '', false);
}

function onAnnouncementTargetModeChange() {
    var mode = el('announcementTargetMode').value;
    el('announcementSingleClassGroup').style.display = mode === 'single' ? 'block' : 'none';
    el('announcementMultiClassGroup').style.display = mode === 'multiple' ? 'block' : 'none';
}

function updateAnnouncementFilePreview() {
    var input = el('announcementFile');
    var preview = el('announcementFilePreview');
    var fileName = el('announcementFileName');

    if (!input.files || !input.files[0]) {
        preview.style.display = 'none';
        fileName.textContent = '';
        return;
    }

    preview.style.display = 'block';
    fileName.textContent = input.files[0].name;
}

function removeAnnouncementFile() {
    el('announcementFile').value = '';
    updateAnnouncementFilePreview();
}

async function saveAnnouncement() {
    var title = el('announcementTitle').value.trim();
    var message = el('announcementContent').value.trim();
    var mode = el('announcementTargetMode').value;

    if (title === '' || message === '') {
        showMessage('announcementInlineMessage', 'Please enter title and message.', true);
        return;
    }

    var classIds = [];
    if (mode === 'single') {
        var single = Number(el('announcementClassSelect').value || 0);
        classIds = single > 0 ? [single] : [0];
    } else if (mode === 'multiple') {
        var selected = Array.from(el('announcementClassMultiSelect').selectedOptions);
        classIds = selected.map(function (o) { return Number(o.value); }).filter(function (v) { return v > 0; });
    } else {
        classIds = state.classes.map(function (c) { return Number(c.class_id || 0); }).filter(function (v) { return v > 0; });
    }

    if (classIds.length === 0) {
        classIds = [0];
    }

    try {
        for (var i = 0; i < classIds.length; i++) {
            var fd = new FormData();
            fd.append('title', title);
            fd.append('message', message);
            fd.append('class_id', String(classIds[i]));

            var fileInput = el('announcementFile');
            if (fileInput.files && fileInput.files[0]) {
                fd.append('announcement_file', fileInput.files[0]);
            }

            await postForm(API.createAnnouncement, fd);
        }

        el('announcementTitle').value = '';
        el('announcementContent').value = '';
        removeAnnouncementFile();
        closeAnnouncementModal();

        var data = await getJSON(API.announcements + '?page=1&limit=50');
        state.announcements = data.announcements || [];
        renderAnnouncements();

        showMessage('teacherPageMessage', 'Announcement posted.', false);
    } catch (err) {
        showMessage('announcementInlineMessage', 'Failed to post announcement: ' + err.message, true);
    }
}

async function getJSON(url) {
    var res = await fetch(url, { credentials: 'include' });
    var text = await res.text();
    var body = null;

    try {
        body = JSON.parse(text);
    } catch (e) {
        throw new Error('Invalid JSON from server');
    }

    if (!body || !body.success) {
        if (String(body && body.message ? body.message : '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
        }
        throw new Error(body && body.message ? body.message : 'Request failed');
    }

    return body.data || {};
}

async function postJSON(url, payload) {
    var res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    var text = await res.text();
    var body = null;

    try {
        body = JSON.parse(text);
    } catch (e) {
        throw new Error('Invalid JSON from server');
    }

    if (!body || !body.success) {
        if (String(body && body.message ? body.message : '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
        }
        throw new Error(body && body.message ? body.message : 'Request failed');
    }

    return body.data || {};
}

async function postForm(url, formData) {
    var res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        body: formData
    });

    var text = await res.text();
    var body = null;

    try {
        body = JSON.parse(text);
    } catch (e) {
        throw new Error('Invalid JSON from server');
    }

    if (!body || !body.success) {
        if (String(body && body.message ? body.message : '').toLowerCase() === 'unauthorized') {
            handleUnauthorized();
        }
        throw new Error(body && body.message ? body.message : 'Request failed');
    }

    return body.data || {};
}

async function logout() {
    try {
        await fetch(API.logout, { method: 'POST', credentials: 'include' });
    } catch (e) {
    }

    localStorage.removeItem('currentUser');
    sessionStorage.clear();
    window.location.replace(FRONT_BASE + '/login.html');
}

function handleUnauthorized() {
    localStorage.removeItem('currentUser');
    window.location.replace(FRONT_BASE + '/login.html');
}

function formatDate(value) {
    var s = String(value || '').trim();
    if (!s) return '';
    var d = new Date(s.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return s;
    return d.toLocaleDateString();
}

function getCurrentUser() {
    var raw = localStorage.getItem('currentUser');
    if (!raw) return null;

    try {
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function showMessage(id, text, isError) {
    var box = el(id);
    if (!box) return;

    if (!text) {
        box.style.display = 'none';
        box.textContent = '';
        return;
    }

    box.style.display = 'block';
    box.textContent = text;

    if (isError) {
        box.style.background = '#fee2e2';
        box.style.borderColor = '#fecaca';
        box.style.color = '#b91c1c';
    } else {
        box.style.background = '#dcfce7';
        box.style.borderColor = '#86efac';
        box.style.color = '#166534';
    }
}

function setText(id, value) {
    var node = el(id);
    if (node) {
        node.textContent = String(value == null ? '' : value);
    }
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
