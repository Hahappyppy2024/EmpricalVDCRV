<?php $page_title = 'Admin console'; $page_slug = 'admin'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Storage policies</h2>
        <form id="policy-form">
            <label>Default quota (bytes)</label>
            <input type="number" name="default_quota_bytes">
            <label>Max upload (bytes)</label>
            <input type="number" name="max_upload_bytes">
            <label>Trash retention (days)</label>
            <input type="number" name="trash_retention_days">
            <label>Version retention (count)</label>
            <input type="number" name="version_retention_count">
            <div style="margin-top:1rem"><button class="btn" type="submit">Save policies</button></div>
        </form>
        <p id="policy-result"></p>
    </div>
    <div class="card">
        <h2>Blocked file types</h2>
        <form id="blocked-form">
            <label>Extension (without dot)</label>
            <input type="text" name="extension" placeholder="e.g. exe">
            <label>Reason</label>
            <input type="text" name="reason" placeholder="why blocked">
            <div style="margin-top:1rem"><button class="btn" type="submit">Add blocked type</button></div>
        </form>
        <ul id="blocked-list"><li class="meta">Loading…</li></ul>
    </div>
</div>
<div class="card">
    <h2>Users</h2>
    <table>
        <thead><tr><th>Username</th><th>Role</th><th>Quota</th><th>Used</th><th>Actions</th></tr></thead>
        <tbody id="user-table"><tr><td colspan="5" class="meta">Loading…</td></tr></tbody>
    </table>
</div>
<div class="card">
    <h2>System stats</h2>
    <div id="admin-stats" class="meta">Loading…</div>
</div>
<script>
window.CFS.admin = {
    init: function () {
        var self = this;
        var load = function () {
            CFS.api('/api/file/admin_console').then(function (data) {
                if (!data.ok) { return; }
                ['default_quota_bytes', 'max_upload_bytes', 'trash_retention_days', 'version_retention_count'].forEach(function (k) {
                    var el = document.querySelector('#policy-form [name="' + k + '"]');
                    if (el && data.settings[k] !== undefined) { el.value = data.settings[k]; }
                });
                var bl = document.getElementById('blocked-list');
                bl.innerHTML = Object.keys(data.blocked_file_types).map(function (ext) {
                    return '<li><span class="badge badge-red">' + CFS.esc(ext) + '</span> <span class="meta">' + CFS.esc(data.blocked_file_types[ext]) + '</span> ' +
                        '<button class="btn btn-ghost btn-sm" data-unblock="' + CFS.esc(ext) + '">remove</button></li>';
                }).join('');
                bl.querySelectorAll('[data-unblock]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        CFS.api('/api/file/admin_console/blocked/' + encodeURIComponent(b.getAttribute('data-unblock')), { method: 'DELETE' }).then(function (d) {
                            CFS.toast(d.ok ? d.message : 'Failed', !d.ok);
                            load();
                        });
                    });
                });
                var rows = data.users.map(function (u) {
                    var q = data.quota.filter(function (x) { return String(x.id) === String(u.id); })[0];
                    var used = q ? q.used_bytes : 0;
                    var quota = q ? q.quota_bytes : u.quota_bytes;
                    return '<tr><td>' + CFS.esc(u.username) + '</td>' +
                        '<td><span class="badge ' + (u.role === 'admin' ? 'badge-blue' : 'badge-green') + '">' + CFS.esc(u.role) + '</span></td>' +
                        '<td>' + CFS.formatBytes(quota) + '</td><td>' + CFS.formatBytes(used) + '</td>' +
                        '<td><div class="row-actions">' +
                        '<button class="btn btn-ghost btn-sm" data-quota="' + u.id + '" data-current="' + quota + '">Set quota</button>' +
                        '<button class="btn btn-ghost btn-sm" data-role="' + u.id + '" data-current="' + CFS.esc(u.role) + '">Toggle role</button>' +
                        '</div></td></tr>';
                }).join('');
                document.getElementById('user-table').innerHTML = rows || '<tr><td colspan="5" class="meta">No users.</td></tr>';
                document.getElementById('user-table').querySelectorAll('[data-quota]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var v = window.prompt('Quota in bytes', b.getAttribute('data-current'));
                        if (!v) { return; }
                        CFS.api('/api/file/storage_quota/' + b.getAttribute('data-quota'), { method: 'PATCH', body: { quota_bytes: v } }).then(function (d) {
                            CFS.toast(d.ok ? d.message : (d.errors && d.errors[0]) || 'Failed', !d.ok);
                            if (d.ok) { load(); }
                        });
                    });
                });
                document.getElementById('user-table').querySelectorAll('[data-role]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        if (!window.confirm('Toggle role for user #' + b.getAttribute('data-role') + '?')) { return; }
                        var next = b.getAttribute('data-current') === 'admin' ? 'user' : 'admin';
                        CFS.api('/api/file/admin_console/' + b.getAttribute('data-role'), { method: 'PATCH', body: { role: next } }).then(function (d) {
                            CFS.toast(d.ok ? d.message : (d.errors && d.errors[0]) || 'Failed', !d.ok);
                            if (d.ok) { load(); }
                        });
                    });
                });
                var s = data.stats;
                document.getElementById('admin-stats').textContent = [
                    'Users: ' + s.total_users, 'Files: ' + s.total_files,
                    'Audit events: ' + s.total_audit_events, 'Shares: ' + s.total_shares,
                    'Teams: ' + s.total_teams
                ].join(' · ');
            });
        };
        var policy = document.getElementById('policy-form');
        policy.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = {};
            ['default_quota_bytes', 'max_upload_bytes', 'trash_retention_days', 'version_retention_count'].forEach(function (k) {
                var el = policy.querySelector('[name="' + k + '"]');
                body[k] = el.value;
            });
            CFS.api('/api/file/admin_console', { method: 'POST', body: { action: 'update_policy', ...body } }).then(function (d) {
                var out = document.getElementById('policy-result');
                out.className = d.ok ? 'alert alert-ok' : 'alert alert-error';
                out.textContent = d.ok ? d.message : (d.errors && d.errors[0]) || 'Failed';
            });
        });
        var blocked = document.getElementById('blocked-form');
        blocked.addEventListener('submit', function (e) {
            e.preventDefault();
            CFS.api('/api/file/admin_console', { method: 'POST', body: { action: 'add_blocked', extension: blocked.extension.value, reason: blocked.reason.value } }).then(function (d) {
                CFS.toast(d.ok ? d.message : (d.errors && d.errors[0]) || 'Failed', !d.ok);
                if (d.ok) { blocked.reset(); load(); }
            });
        });
        load();
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
