<h1>Administration</h1>
<p class="muted">Manage users, roles, courses, enrollments and system settings. Every privileged action is written to the audit log (LMS-12).</p>

<nav class="tabs" id="admin-tabs">
  <a href="#" data-tab="users" class="active">Users</a>
  <a href="#" data-tab="courses">Courses</a>
  <a href="#" data-tab="enrollments">Enrollments</a>
  <a href="#" data-tab="settings">Settings</a>
  <a href="#" data-tab="audit">Audit log</a>
</nav>

<details class="panel">
  <summary>Create user</summary>
  <form id="user-form">
    <input type="hidden" name="action" value="user">
    <label>Username <input type="text" name="username" class="input" required></label>
    <label>Display name <input type="text" name="display_name" class="input" required></label>
    <label>Email <input type="email" name="email" class="input" required></label>
    <label>Password <input type="password" name="password" class="input" required></label>
    <label>Role
      <select name="role" class="input">
        <option>student</option><option>instructor</option><option>visitor</option><option>admin</option>
      </select>
    </label>
    <button type="submit" class="btn btn-primary">Create</button>
  </form>
</details>

<div id="admin-content"><div class="empty">Loading...</div></div>

<script>
(function () {
  var container = document.getElementById('admin-content');
  var tabs = document.getElementById('admin-tabs');
  var current = 'users';

  function load() {
    LMS.load(container, LMS.api('/api/lms/administrative_api?entity=' + current), function (data) {
      var html = '';
      if (current === 'users') {
        var users = data.users || [];
        if (!users.length) { container.innerHTML = '<div class="empty">No users.</div>'; return; }
        html = '<table class="table"><thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Change role</th></tr></thead><tbody>' +
          users.map(function (u) {
            var roleSelect = '<select class="input small role-select" data-id="' + u.id + '">' +
              ['student', 'instructor', 'admin', 'visitor'].map(function (r) { return '<option' + (u.role_name === r ? ' selected' : '') + '>' + r + '</option>'; }).join('') + '</select>';
            return '<tr><td>' + LMS.esc(u.username) + '</td><td>' + LMS.esc(u.email) + '</td><td>' + LMS.esc(u.role_name) + '</td><td>' + (u.active ? 'yes' : 'no') + '</td><td>' + roleSelect + '</td></tr>';
          }).join('') + '</tbody></table>';
        container.innerHTML = html;
        container.querySelectorAll('.role-select').forEach(function (sel) {
          sel.addEventListener('change', function () {
            LMS.api('/api/lms/administrative_api/' + sel.dataset.id, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ entity: 'user', role: sel.value }) }).then(function (r) {
              if (r.ok) load(); else alert(r.error.message);
            });
          });
        });
      } else if (current === 'courses') {
        var courses = data.courses || [];
        html = courses.length ? '<table class="table"><thead><tr><th>Title</th><th>Category</th><th>Semester</th><th>Visibility</th><th>Status</th></tr></thead><tbody>' +
          courses.map(function (c) { return '<tr><td>' + LMS.esc(c.title) + '</td><td>' + LMS.esc(c.category) + '</td><td>' + LMS.esc(c.semester) + '</td><td>' + LMS.esc(c.visibility) + '</td><td>' + LMS.esc(c.status) + '</td></tr>'; }).join('') + '</tbody></table>' : '<div class="empty">No courses.</div>';
      } else if (current === 'enrollments') {
        var enrollments = data.enrollments || [];
        html = enrollments.length ? '<table class="table"><thead><tr><th>Course</th><th>Student</th><th>Status</th></tr></thead><tbody>' +
          enrollments.map(function (e) { return '<tr><td>' + LMS.esc(e.course_title) + '</td><td>' + LMS.esc(e.display_name) + '</td><td>' + LMS.esc(e.status) + '</td></tr>'; }).join('') + '</tbody></table>' : '<div class="empty">No enrollments.</div>';
      } else if (current === 'settings') {
        var settings = data.settings || {};
        html = '<table class="table"><thead><tr><th>Key</th><th>Value</th><th></th></tr></thead><tbody>' +
          Object.keys(settings).map(function (key) {
            return '<tr><td>' + LMS.esc(key) + '</td><td><input class="input setting-value" data-key="' + LMS.esc(key) + '" value="' + LMS.esc(settings[key]) + '"></td><td><button class="btn setting-save" data-key="' + LMS.esc(key) + '">Save</button></td></tr>';
          }).join('') + '</tbody></table>';
        container.innerHTML = html;
        container.querySelectorAll('.setting-save').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var key = btn.dataset.key;
            var value = container.querySelector('.setting-value[data-key="' + key + '"]').value;
            LMS.api('/api/lms/administrative_api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'setting', key: key, value: value }) }).then(function (r) {
              if (r.ok) load(); else alert(r.error.message);
            });
          });
        });
      } else if (current === 'audit') {
        var events = data.audit_events || [];
        html = events.length ? '<table class="table"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th></tr></thead><tbody>' +
          events.map(function (e) { return '<tr><td>' + LMS.esc(e.created_at) + '</td><td>' + LMS.esc(e.username) + '</td><td>' + LMS.esc(e.action) + '</td><td>' + LMS.esc(e.entity_type) + '#' + e.entity_id + '</td></tr>'; }).join('') + '</tbody></table>' : '<div class="empty">No audit events.</div>';
      }
      container.innerHTML = html;
    });
  }

  tabs.addEventListener('click', function (e) {
    var link = e.target.closest('a');
    if (!link) return;
    e.preventDefault();
    tabs.querySelectorAll('a').forEach(function (a) { a.classList.toggle('active', a === link); });
    current = link.dataset.tab;
    load();
  });

  var uf = document.getElementById('user-form');
  uf.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/administrative_api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(uf))) }).then(function (r) {
      if (r.ok) { uf.reset(); load(); } else { alert(r.error.message); }
    });
  });

  load();
})();
</script>
