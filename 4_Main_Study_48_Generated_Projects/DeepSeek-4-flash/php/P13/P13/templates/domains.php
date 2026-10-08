<h1>Domain management</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="grid">
    <div class="card">
        <h2>Add domain</h2>
        <form id="domain-form" class="stack">
            <input type="text" id="d-name" placeholder="example.org" required>
            <input type="number" id="d-quota" value="1024" min="1">
            <input type="number" id="d-limit" value="25" min="1">
            <input type="text" id="d-aliases" placeholder="Aliases, comma separated (postmaster@example.org)">
            <button type="submit" class="btn">Create domain</button>
        </form>
    </div>
    <div class="card">
        <h2>Domains</h2>
        <ul id="domain-list" class="plain"></ul>
    </div>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            P.api('GET', '/api/mail/domain_management').then(function (r) {
                var items = (r.data.domains && r.data.domains.items) || [];
                P.renderList('domain-list', items, function (d) {
                    return '<li class="domain-item">' +
                        '<strong>' + P.esc(d.name) + '</strong> <span class="badge ' + (d.status === 'active' ? 'ok' : 'err') + '">' + P.esc(d.status) + '</span>' +
                        '<div class="muted">quota ' + d.quota_mb + ' MB &middot; ' + d.mailbox_count + '/' + d.mailbox_limit + ' mailboxes</div>' +
                        '<div class="muted">aliases: ' + P.esc((d.aliases || []).join(', ')) + '</div>' +
                        '<form class="stack row edit-domain">' +
                        '<input type="hidden" class="d-id" value="' + d.id + '">' +
                        '<select class="d-status"><option value="active"' + (d.status === 'active' ? ' selected' : '') + '>active</option>' +
                        '<option value="suspended"' + (d.status === 'suspended' ? ' selected' : '') + '>suspended</option></select>' +
                        '<input type="number" class="d-quota" value="' + d.quota_mb + '" min="1" title="Quota MB">' +
                        '<input type="number" class="d-limit" value="' + d.mailbox_limit + '" min="1" title="Mailbox limit">' +
                        '<button type="submit" class="btn btn-small">Save</button>' +
                        '</form>' +
                        '</li>';
                });
                document.querySelectorAll('.edit-domain').forEach(function (form) {
                    form.addEventListener('submit', function (ev) {
                        ev.preventDefault();
                        P.api('PATCH', '/api/mail/domain_management/' + form.querySelector('.d-id').value, {
                            status: form.querySelector('.d-status').value,
                            quota_mb: form.querySelector('.d-quota').value,
                            mailbox_limit: form.querySelector('.d-limit').value
                        }).then(function (res) {
                            P.flash((res.data && res.data.message) || 'Saved', res.ok ? 'ok' : 'error');
                            load();
                        });
                    });
                });
            });
        }

        P.el('domain-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/domain_management', {
                name: P.el('d-name').value,
                quota_mb: P.el('d-quota').value,
                mailbox_limit: P.el('d-limit').value,
                aliases: P.el('d-aliases').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)
            }).then(function (res) {
                P.flash((res.data && res.data.message) || 'Created', res.ok ? 'ok' : 'error');
                P.el('d-name').value = P.el('d-aliases').value = '';
                load();
            });
        });

        load();
    })();
</script>
