<h1>Admin audit logs</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="card">
    <form id="audit-filter" class="stack row">
        <input type="text" id="a-action" placeholder="action (e.g. auth.login)">
        <input type="text" id="a-entity" placeholder="entity type (e.g. User)">
        <input type="text" id="a-role" placeholder="role (e.g. system_admin)">
        <input type="text" id="a-q" placeholder="search actor / entity / details">
        <button type="submit" class="btn btn-small">Apply filter</button>
        <button type="button" class="btn btn-small btn-secondary" id="a-clear">Clear</button>
    </form>
    <p class="muted" id="audit-count"></p>
</div>

<div class="card">
    <table class="table">
        <thead>
        <tr><th>Time</th><th>Actor</th><th>Role</th><th>Action</th><th>Entity</th><th>ID</th><th>Details</th><th>IP</th></tr>
        </thead>
        <tbody id="audit-list"></tbody>
    </table>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            var params = [];
            if (P.el('a-action').value.trim()) { params.push('action=' + encodeURIComponent(P.el('a-action').value.trim())); }
            if (P.el('a-entity').value.trim()) { params.push('entity_type=' + encodeURIComponent(P.el('a-entity').value.trim())); }
            if (P.el('a-role').value.trim()) { params.push('role=' + encodeURIComponent(P.el('a-role').value.trim())); }
            if (P.el('a-q').value.trim()) { params.push('q=' + encodeURIComponent(P.el('a-q').value.trim())); }
            P.api('GET', '/api/mail/admin_audit_logs' + (params.length ? '?' + params.join('&') : '')).then(function (r) {
                var data = r.data;
                var items = data.events || [];
                P.el('audit-count').textContent = data.total + ' matching event(s).';
                var tbody = P.el('audit-list');
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="muted">No events match the filter.</td></tr>';
                    return;
                }
                tbody.innerHTML = items.map(function (ev) {
                    var details = '';
                    try {
                        details = P.esc(JSON.stringify(JSON.parse(ev.details) || {}));
                    } catch (err) {
                        details = P.esc(ev.details);
                    }
                    return '<tr>' +
                        '<td>' + P.esc(ev.created_at) + '</td>' +
                        '<td>' + P.esc(ev.actor_username || '-') + '</td>' +
                        '<td>' + P.esc(ev.actor_role) + '</td>' +
                        '<td>' + P.esc(ev.action) + '</td>' +
                        '<td>' + P.esc(ev.entity_type) + '</td>' +
                        '<td>' + P.esc(ev.entity_id || '-') + '</td>' +
                        '<td class="muted small">' + details + '</td>' +
                        '<td>' + P.esc(ev.ip_address) + '</td>' +
                        '</tr>';
                }).join('');
            });
        }

        P.el('audit-filter').addEventListener('submit', function (ev) {
            ev.preventDefault();
            load();
        });
        P.el('a-clear').addEventListener('click', function () {
            P.el('a-action').value = P.el('a-entity').value = P.el('a-role').value = P.el('a-q').value = '';
            load();
        });

        load();
    })();
</script>
