<?php /** @var array $files */ ?>
<header class="page-header">
    <h1>Backup manager</h1>
    <p class="muted">SYS-07 · upload, download, restore, delete backups.</p>
</header>

<section class="card form">
    <h3>Upload a backup</h3>
    <form method="post" action="/backups" enctype="multipart/form-data">
        <label>Description
            <input type="text" name="description" placeholder="Optional description">
        </label>
        <label>File
            <input type="file" name="file" required>
        </label>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</section>

<section class="card">
    <h3>Existing backups</h3>
    <table class="table">
        <thead><tr><th>File</th><th>Type</th><th>Size</th><th>Owner</th><th>Uploaded</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($file['original_name']) ?>
                        <div class="muted"><?= htmlspecialchars($file['description']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($file['mime_type']) ?></td>
                    <td><?= (int)$file['size_bytes'] ?> bytes</td>
                    <td>#<?= (int)$file['owner_id'] ?></td>
                    <td><?= htmlspecialchars((string)$file['created_at']) ?></td>
                    <td>
                        <a class="btn btn-xs" href="/backups/download/<?= (int)$file['id'] ?>">Download</a>
                        <form method="post" action="/backups/<?= (int)$file['id'] ?>/delete" class="inline">
                            <button class="btn btn-xs btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($files)): ?>
                <tr><td colspan="6" class="muted">No backups yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>