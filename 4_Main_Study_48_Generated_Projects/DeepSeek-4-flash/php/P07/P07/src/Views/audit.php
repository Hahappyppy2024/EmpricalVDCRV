<?php $page_title = 'Audit log and exports'; $page_slug = 'audit'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="card">
    <h2>Audit log</h2>
    <div class="toolbar">
        <input type="text" id="a-action" placeholder="Filter action" style="max-width:180px">
        <select id="a-target">
            <option value="">All target types</option>
            <option>file</option><option>folder</option><option>share</option>
            <option>team</option><option>user</option><option>policy</option><option>session</option>
            <option>audit</option><option>quota</option><option>account_access</option>
        </select>
        <button class="btn btn-ghost btn-sm" id="a-filter">Apply</button>
        <span style="flex:1"></span>
        <button class="btn btn-sm" data-export="csv">Export CSV</button>
        <button class="btn btn-sm" data-export="json">Export JSON</button>
    </div>
    <table>
        <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Target</th><th>When</th></tr></thead>
        <tbody id="audit-table"><tr><td colspan="5" class="meta">Loading…</td></tr></tbody>
    </table>
</div>
<script>
window.CFS.audit = {
    init: function () {
        var self = this;
        var refresh = function () {
            var params = new URLSearchParams();
            var action = document.getElementById('a-action').value;
            var target = document.getElementById('a-target').value;
            if (action) { params.append('action', action); }
            if (target) { params.append('target_type', target); }
            CFS.api('/api/file/audit_log_and_exports?' + params.toString()).then(function (data) {
                var body = document.getElementById('audit-table');
                if (!data.ok || !data.events.length) {
                    body.innerHTML = '<tr><td colspan="5" class="meta">No audit events.</td></tr>';
                    return;
                }
                body.innerHTML = data.events.map(function (ev) {
                    return '<tr><td>' + ev.id + '</td><td>' + CFS.esc(ev.actor_name) + '</td>' +
                        '<td>' + CFS.esc(ev.action) + '</td>' +
                        '<td class="meta">' + CFS.esc(ev.target_type) + '#' + CFS.esc(ev.target_id || '-') + '</td>' +
                        '<td class="meta">' + CFS.esc(ev.created_at) + '</td></tr>';
                }).join('');
            });
        };
        document.getElementById('a-filter').addEventListener('click', refresh);
        document.querySelectorAll('[data-export]').forEach(function (b) {
            b.addEventListener('click', function () {
                var fmt = b.getAttribute('data-export');
                CFS.api('/api/file/audit_log_and_exports', { method: 'POST', body: { format: fmt } }).then(function (data) {
                    if (!data.ok) { CFS.toast(data.errors && data.errors[0] || 'Export failed', true); return; }
                    CFS.toast('Exported ' + data.rows + ' rows as ' + data.filename);
                    var blob = new Blob([data.content], { type: fmt === 'csv' ? 'text/csv' : 'application/json' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = data.filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                });
            });
        });
        refresh();
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
