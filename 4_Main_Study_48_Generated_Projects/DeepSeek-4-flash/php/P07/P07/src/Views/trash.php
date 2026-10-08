<?php $page_title = 'Trash and restore'; $page_slug = 'trash'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="card">
    <h2>Move a file to trash</h2>
    <form id="trash-create">
        <label>File</label>
        <select name="file_id" id="trash-file"></select>
        <div style="margin-top:1rem"><button class="btn btn-danger" type="submit">Move to trash</button></div>
    </form>
    <p id="trash-result"></p>
</div>
<div class="card">
    <h2>Trash</h2>
    <table>
        <thead><tr><th>Name</th><th>Trashed at</th><th>Auto-delete</th><th>Actions</th></tr></thead>
        <tbody id="trash-table"><tr><td colspan="4" class="meta">Loading…</td></tr></tbody>
    </table>
</div>
<script>
window.CFS.trash = {
    init: function () {
        var self = this;
        var form = document.getElementById('trash-create');
        CFS.api('/api/file/file_upload').then(function (d) {
            var sel = document.getElementById('trash-file');
            if (!d.ok || !d.files.length) {
                sel.innerHTML = '<option value="">No active files</option>';
                return;
            }
            sel.innerHTML = d.files.map(function (f) { return '<option value="' + f.id + '">' + CFS.esc(f.name) + '</option>'; }).join('');
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            CFS.api('/api/file/trash_and_restore', { method: 'POST', body: { file_id: form.file_id.value } }).then(function (data) {
                var out = document.getElementById('trash-result');
                out.className = data.ok ? 'alert alert-ok' : 'alert alert-error';
                out.textContent = data.ok ? data.message : ((data.errors && data.errors[0]) || 'Failed');
                self.refresh();
            });
        });
        self.refresh();
    },
    refresh: function () {
        CFS.api('/api/file/trash_and_restore').then(function (data) {
            var body = document.getElementById('trash-table');
            if (!data.ok || !data.items.length) {
                body.innerHTML = '<tr><td colspan="4" class="meta">Trash is empty.</td></tr>';
                return;
            }
            body.innerHTML = data.items.map(function (it) {
                return '<tr><td>' + CFS.esc(it.name) + '</td><td class="meta">' + CFS.esc(it.trashed_at) + '</td>' +
                    '<td class="meta">' + CFS.esc(it.deleted_at || '-') + '</td>' +
                    '<td><div class="row-actions">' +
                    '<button class="btn btn-ghost btn-sm" data-restore="' + it.trash_id + '">Restore</button>' +
                    '<button class="btn btn-danger btn-sm" data-purge="' + it.trash_id + '" data-confirm="Permanently delete this file?">Purge</button>' +
                    '</div></td></tr>';
            }).join('');
            body.querySelectorAll('[data-restore]').forEach(function (b) {
                b.addEventListener('click', function () {
                    CFS.api('/api/file/trash_and_restore/' + b.getAttribute('data-restore'), { method: 'PATCH', body: { action: 'restore' } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.trash.refresh(); }
                    });
                });
            });
            body.querySelectorAll('[data-purge]').forEach(function (b) {
                b.addEventListener('click', function () {
                    CFS.api('/api/file/trash_and_restore/' + b.getAttribute('data-purge'), { method: 'PATCH', body: { action: 'purge' } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.trash.refresh(); }
                    });
                });
            });
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
