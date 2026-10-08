<?php $page_title = 'Upload file'; $page_slug = 'upload'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Upload a file</h2>
        <form id="upload-form" enctype="multipart/form-data">
            <label>File</label>
            <input type="file" name="file" required>
            <label>Display name (optional)</label>
            <input type="text" name="name">
            <label>Destination folder</label>
            <select name="folder_id">
                <option value="0">Root</option>
                <?php foreach ($folders as $folder): ?>
                    <option value="<?= (int) $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Description</label>
            <textarea name="description"></textarea>
            <label>Tags (comma separated)</label>
            <input type="text" name="tags" placeholder="report, finance">
            <div style="margin-top:1rem"><button class="btn" type="submit">Upload</button></div>
        </form>
    </div>
    <div class="card">
        <h2>Storage policy</h2>
        <p class="meta">Blocked file types:</p>
        <ul>
            <?php foreach ($blocked as $ext => $reason): ?>
                <li><span class="badge badge-red"><?= htmlspecialchars($ext) ?></span> <span class="meta"><?= htmlspecialchars($reason) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <p class="meta" id="upload-result"></p>
    </div>
</div>
<div class="card">
    <h2>Your files</h2>
    <table>
        <thead><tr><th>Name</th><th>Size</th><th>Tags</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="upload-list"><tr><td colspan="5" class="meta">Loading…</td></tr></tbody>
    </table>
</div>
<script>
window.CFS.upload = {
    init: function () {
        var form = document.getElementById('upload-form');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            CFS.api('/api/file/file_upload', { method: 'POST', body: fd }).then(function (data) {
                var out = document.getElementById('upload-result');
                if (data.ok) {
                    out.className = 'alert alert-ok';
                    out.textContent = data.message + ' (' + CFS.formatBytes(data.size_bytes) + ')';
                    form.reset();
                } else {
                    out.className = 'alert alert-error';
                    out.textContent = (data.errors && data.errors[0]) || data.error || 'Upload failed';
                }
                CFS.upload.refresh();
            });
        });
        CFS.upload.refresh();
    },
    refresh: function () {
        CFS.api('/api/file/file_upload').then(function (data) {
            var body = document.getElementById('upload-list');
            if (!data.ok || !data.files.length) {
                body.innerHTML = '<tr><td colspan="5" class="meta">No files uploaded yet.</td></tr>';
                return;
            }
            body.innerHTML = data.files.map(function (f) {
                return '<tr><td><a href="/files/' + f.id + '/preview">' + CFS.esc(f.name) + '</a></td>' +
                    '<td class="meta">' + CFS.formatBytes(f.size_bytes) + '</td>' +
                    '<td class="meta">' + CFS.esc(f.tags) + '</td>' +
                    '<td><span class="badge badge-green">' + CFS.esc(f.status) + '</span></td>' +
                    '<td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="/files/' + f.id + '/download">Download</a></div></td></tr>';
            }).join('');
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
