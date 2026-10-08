<?php $page_title = 'Version history'; $page_slug = 'versions'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Upload new version</h2>
        <form id="version-form" enctype="multipart/form-data">
            <label>File</label>
            <select name="file_id" id="version-file"></select>
            <label>New file content</label>
            <input type="file" name="file" required>
            <label>Comment</label>
            <input type="text" name="comment" placeholder="What changed?">
            <div style="margin-top:1rem"><button class="btn" type="submit">Create version</button></div>
        </form>
        <p id="version-result"></p>
    </div>
    <div class="card">
        <h2>Versioned files</h2>
        <table>
            <thead><tr><th>Name</th><th>Current</th><th>Total versions</th><th>Action</th></tr></thead>
            <tbody id="version-list"><tr><td colspan="4" class="meta">Loading…</td></tr></tbody>
        </table>
    </div>
</div>
<div class="card">
    <h2>Versions for selected file</h2>
    <table>
        <thead><tr><th>Version</th><th>Size</th><th>Uploaded by</th><th>Comment</th><th>Action</th></tr></thead>
        <tbody id="version-detail"><tr><td colspan="5" class="meta">Select a file above.</td></tr></tbody>
    </table>
</div>
<script>
window.CFS.versions = {
    init: function () {
        var self = this;
        var form = document.getElementById('version-form');
        var sel = document.getElementById('version-file');
        CFS.api('/api/file/version_history').then(function (d) {
            if (!d.ok || !d.records.length) {
                sel.innerHTML = '<option value="">No files available</option>';
                return;
            }
            sel.innerHTML = d.records.map(function (r) { return '<option value="' + r.id + '">' + CFS.esc(r.name) + '</option>'; }).join('');
            sel.addEventListener('change', function () { self.detail(); });
            self.detail();
            self.refresh();
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            CFS.api('/api/file/version_history', { method: 'POST', body: fd }).then(function (data) {
                var out = document.getElementById('version-result');
                out.className = data.ok ? 'alert alert-ok' : 'alert alert-error';
                out.textContent = data.ok ? data.message : ((data.errors && data.errors[0]) || 'Failed');
                form.reset();
                self.detail();
                self.refresh();
            });
        });
    },
    detail: function () {
        var fileId = document.getElementById('version-file').value;
        if (!fileId) { return; }
        var body = document.getElementById('version-detail');
        CFS.api('/api/file/version_history?file_id=' + fileId).then(function (data) {
            if (!data.ok || !data.versions.length) {
                body.innerHTML = '<tr><td colspan="5" class="meta">No versions found.</td></tr>';
                return;
            }
            var current = parseInt(data.file.current_version, 10);
            body.innerHTML = data.versions.map(function (v) {
                var isCurrent = parseInt(v.version_number, 10) === current;
                var action = isCurrent ? '<span class="badge badge-green">current</span>' : '<button class="btn btn-ghost btn-sm" data-restore="' + v.version_number + '">Restore</button>';
                return '<tr><td>v' + v.version_number + '</td><td class="meta">' + CFS.formatBytes(v.size_bytes) + '</td>' +
                    '<td class="meta">' + CFS.esc(v.uploaded_by_name || '-') + '</td>' +
                    '<td class="meta">' + CFS.esc(v.comment) + '</td><td>' + action + '</td></tr>';
            }).join('');
            body.querySelectorAll('[data-restore]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (!window.confirm('Restore version ' + b.getAttribute('data-restore') + '?')) { return; }
                    CFS.api('/api/file/version_history/' + fileId, { method: 'PATCH', body: { version: b.getAttribute('data-restore') } }).then(function (data) {
                        CFS.toast(data.ok ? data.message : (data.errors && data.errors[0]) || 'Failed', !data.ok);
                        if (data.ok) { CFS.versions.detail(); }
                    });
                });
            });
        });
    },
    refresh: function () {
        CFS.api('/api/file/version_history').then(function (data) {
            var body = document.getElementById('version-list');
            if (!data.ok || !data.records.length) {
                body.innerHTML = '<tr><td colspan="4" class="meta">No versioned files.</td></tr>';
                return;
            }
            body.innerHTML = data.records.map(function (r) {
                return '<tr><td>' + CFS.esc(r.name) + '</td><td>v' + r.current_version + '</td><td>' + r.version_count + '</td>' +
                    '<td><a class="btn btn-ghost btn-sm" href="/files/' + r.id + '/preview">Preview</a></td></tr>';
            }).join('');
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
