<h1>Import / export</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="grid">
    <div class="card">
        <h2>Export</h2>
        <form id="export-form" class="stack">
            <select id="export-type">
                <option value="contacts">Contacts (CSV)</option>
                <option value="mailbox">Mailbox messages (CSV)</option>
                <?php if ($user['role'] === 'system_admin'): ?>
                <option value="audit">Audit log (CSV)</option>
                <?php endif; ?>
            </select>
            <button type="submit" class="btn">Create export</button>
        </form>
    </div>
    <div class="card">
        <h2>Import contacts</h2>
        <form id="import-form" class="stack">
            <input type="file" name="file" id="import-file" accept=".csv,text/csv" required>
            <p class="muted small">CSV columns: email,first_name,last_name,phone,organization (header row optional).</p>
            <button type="submit" class="btn">Import CSV</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Jobs</h2>
    <ul id="job-list" class="plain"></ul>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            P.api('GET', '/api/mail/import_export').then(function (r) {
                P.renderList('job-list', r.data.jobs.items || [], function (j) {
                    return '<li>' +
                        '<span class="badge ' + (j.status === 'done' ? 'ok' : j.status === 'failed' ? 'err' : 'warn') + '">' + P.esc(j.status) + '</span> ' +
                        '<strong>' + P.esc(j.kind) + '</strong> ' + P.esc(j.entity_type) +
                        (j.counts > 0 ? ' &middot; ' + j.counts + ' rows' : '') +
                        (j.file_name ? ' &middot; ' + P.esc(j.file_name) : '') +
                        ' <span class="muted">' + P.esc(j.created_at) + '</span>' +
                        '</li>';
                });
            });
        }

        P.el('export-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/import_export', { kind: 'export', entity_type: P.el('export-type').value }).then(function (res) {
                P.flash((res.data && res.data.message) || 'Export created', res.ok ? 'ok' : 'error');
                load();
            });
        });

        P.el('import-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData();
            fd.append('kind', 'import');
            fd.append('file', P.el('import-file').files[0]);
            P.api('POST', '/api/mail/import_export', fd).then(function (res) {
                P.flash((res.data && res.data.message) || 'Import complete', res.ok ? 'ok' : 'error');
                P.el('import-file').value = '';
                load();
            });
        });

        load();
    })();
</script>
