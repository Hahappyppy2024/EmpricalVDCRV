<?php $page_title = 'Sharing links'; $page_slug = 'sharing'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Create share link</h2>
        <form id="share-create">
            <label>File</label>
            <select name="file_id" id="share-file"></select>
            <label>Scope</label>
            <select name="scope">
                <option value="public">Public</option>
                <option value="private">Private (password)</option>
            </select>
            <label>Permissions</label>
            <select name="permissions">
                <option value="view">View</option>
                <option value="download">View + download</option>
                <option value="edit">Edit</option>
            </select>
            <label>Expires at (optional)</label>
            <input type="date" name="expires_at">
            <label>Password (private shares)</label>
            <input type="password" name="password">
            <div style="margin-top:1rem"><button class="btn" type="submit">Create link</button></div>
        </form>
        <p id="share-result"></p>
    </div>
    <div class="card">
        <h2>Your share links</h2>
        <table>
            <thead><tr><th>File</th><th>Scope</th><th>Perm</th><th>Expires</th><th>Link</th><th>Action</th></tr></thead>
            <tbody id="share-table"><tr><td colspan="6" class="meta">Loading…</td></tr></tbody>
        </table>
    </div>
</div>
<script>
window.CFS.sharing = {
    init: function () {
        var self = this;
        var form = document.getElementById('share-create');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            var body = {
                file_id: form.file_id.value,
                scope: form.scope.value,
                permissions: form.permissions.value,
                expires_at: form.expires_at.value
            };
            if (form.password.value) { body.password = form.password.value; }
            CFS.api('/api/file/sharing_links', { method: 'POST', body: body }).then(function (data) {
                var out = document.getElementById('share-result');
                out.className = data.ok ? 'alert alert-ok' : 'alert alert-error';
                if (data.ok) { out.innerHTML = data.message + ' <a href="' + CFS.esc(data.url) + '">' + CFS.esc(data.url) + '</a>'; }
                else { out.textContent = (data.errors && data.errors[0]) || 'Failed'; }
                form.reset();
                self.refresh();
            });
        });
        CFS.api('/api/file/file_upload').then(function (d) {
            var sel = document.getElementById('share-file');
            if (!d.ok || !d.files.length) {
                sel.innerHTML = '<option value="">No files available</option>';
                return;
            }
            sel.innerHTML = d.files.map(function (f) { return '<option value="' + f.id + '">' + CFS.esc(f.name) + '</option>'; }).join('');
        });
        self.refresh();
    },
    refresh: function () {
        CFS.api('/api/file/sharing_links').then(function (data) {
            var body = document.getElementById('share-table');
            if (!data.ok || !data.shares.length) {
                body.innerHTML = '<tr><td colspan="6" class="meta">No share links yet.</td></tr>';
                return;
            }
            body.innerHTML = data.shares.map(function (s) {
                var state = s.revoked === 1 ? '<span class="badge badge-red">revoked</span>' : '<span class="badge ' + (s.scope === 'private' ? 'badge-blue' : 'badge-green') + '">' + CFS.esc(s.scope) + '</span>';
                var url = s.revoked === 1 ? '<span class="meta">—</span>' : '<a href="/s/' + CFS.esc(s.token) + '">' + CFS.esc(s.token.slice(0, 12)) + '…</a>';
                return '<tr><td>' + CFS.esc(s.file_name) + '</td><td>' + state + '</td><td class="meta">' + CFS.esc(s.permissions) + '</td>' +
                    '<td class="meta">' + CFS.esc(s.expires_at || 'never') + '</td><td>' + url + '</td>' +
                    '<td><button class="btn btn-danger btn-sm" data-revoke="' + s.id + '" data-confirm="Revoke this share link?">Revoke</button></td></tr>';
            }).join('');
            body.querySelectorAll('[data-revoke]').forEach(function (b) {
                b.addEventListener('click', function () {
                    CFS.api('/api/file/sharing_links/' + b.getAttribute('data-revoke'), { method: 'PATCH', body: { action: 'revoke' } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.sharing.refresh(); }
                    });
                });
            });
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
