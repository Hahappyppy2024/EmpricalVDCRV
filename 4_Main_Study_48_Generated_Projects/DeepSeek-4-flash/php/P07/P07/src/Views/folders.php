<?php $page_title = 'Folder management'; $page_slug = 'folders'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Create folder</h2>
        <form id="folder-create">
            <label>Folder name</label>
            <input type="text" name="name" required>
            <label>Parent folder</label>
            <select name="parent_id" id="folder-parent"><option value="0">Root</option></select>
            <div style="margin-top:1rem"><button class="btn" type="submit">Create</button></div>
        </form>
        <p id="folder-result"></p>
    </div>
    <div class="card">
        <h2>Your folders</h2>
        <table>
            <thead><tr><th>Name</th><th>Files</th><th>Subfolders</th><th>Actions</th></tr></thead>
            <tbody id="folder-table"><tr><td colspan="4" class="meta">Loading…</td></tr></tbody>
        </table>
    </div>
</div>
<script>
window.CFS.folders = {
    init: function () {
        var self = this;
        var create = document.getElementById('folder-create');
        create.addEventListener('submit', function (e) {
            e.preventDefault();
            CFS.api('/api/file/folder_management', { method: 'POST', body: {
                name: create.name.value,
                parent_id: create.parent_id.value
            }}).then(function (data) {
                var out = document.getElementById('folder-result');
                out.className = data.ok ? 'alert alert-ok' : 'alert alert-error';
                out.textContent = data.ok ? data.message : ((data.errors && data.errors[0]) || 'Failed');
                create.reset();
                self.refresh();
            });
        });
        self.refresh();
    },
    refresh: function () {
        CFS.api('/api/file/folder_management').then(function (data) {
            var body = document.getElementById('folder-table');
            var parent = document.getElementById('folder-parent');
            parent.innerHTML = '<option value="0">Root</option>';
            if (!data.ok || !data.folders.length) {
                body.innerHTML = '<tr><td colspan="4" class="meta">No folders yet.</td></tr>';
                return;
            }
            data.folders.forEach(function (f) {
                var opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = f.name;
                parent.appendChild(opt);
            });
            body.innerHTML = data.folders.map(function (f) {
                return '<tr><td>' + CFS.esc(f.name) + '</td><td>' + f.file_count + '</td><td>' + f.subfolder_count + '</td>' +
                    '<td><div class="row-actions">' +
                    '<button class="btn btn-ghost btn-sm" data-rename="' + f.id + '" data-name="' + CFS.esc(f.name) + '">Rename</button>' +
                    '<button class="btn btn-danger btn-sm" data-del="' + f.id + '" data-confirm="Delete this folder?">Delete</button>' +
                    '</div></td></tr>';
            }).join('');
            body.querySelectorAll('[data-del]').forEach(function (b) {
                b.addEventListener('click', function () {
                    CFS.api('/api/file/folder_management', { method: 'POST', body: { action: 'delete', folder_id: b.getAttribute('data-del') } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.folders.refresh(); }
                    });
                });
            });
            body.querySelectorAll('[data-rename]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var name = window.prompt('New folder name', b.getAttribute('data-name'));
                    if (!name) { return; }
                    CFS.api('/api/file/folder_management/' + b.getAttribute('data-rename'), { method: 'PATCH', body: { name: name } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.folders.refresh(); }
                    });
                });
            });
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
