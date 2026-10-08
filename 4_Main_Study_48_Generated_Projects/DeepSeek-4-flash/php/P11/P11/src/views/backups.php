<?php
/**
 * Backup and restore (HOST-06). Variables: $backups, $sites, $section (list)
 */
?>
<div class="two-col">
    <section>
        <h2>Create backup</h2>
        <form method="post" action="/backups" class="form">
            <label>Backup name
                <input type="text" name="name" placeholder="weekly-full-backup" required>
            </label>
            <label>Source type
                <select name="source_type">
                    <option value="full">Full</option>
                    <option value="site">Site</option>
                    <option value="database">Database</option>
                </select>
            </label>
            <?php if ($sites !== []): ?>
                <label>Site (when source is site)
                    <select name="source_id">
                        <option value="">— none —</option>
                        <?php foreach ($sites as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Create backup</button>
        </form>
    </section>
    <section>
        <h2>Upload backup</h2>
        <form method="post" action="/backups/upload" enctype="multipart/form-data" class="form">
            <input type="file" name="file" accept=".backup,.zip,.tar,.gz,.sql" required>
            <button class="btn btn-primary" type="submit">Upload</button>
        </form>
    </section>
</div>

<h2>Backups</h2>
<table class="table">
    <thead>
    <tr><th>Name</th><th>Kind</th><th>Source</th><th>Size</th><th>Status</th><th>Created</th><th>Restored</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($backups as $b): ?>
        <tr>
            <td><?= e($b['name']) ?></td>
            <td><?= e($b['kind']) ?></td>
            <td><?= e($b['source_type']) ?> <?= $b['source_id'] ? '#' . (int) $b['source_id'] : '' ?></td>
            <td><?= e(formatBytes((int) $b['file_size'])) ?></td>
            <td><span class="badge <?= badge($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td><?= e($b['created_at']) ?></td>
            <td><?= e($b['restored_at'] ?? '—') ?></td>
            <td>
                <a class="btn btn-small" href="/backups/<?= (int) $b['id'] ?>/download">Download</a>
                <form method="post" action="/backups/<?= (int) $b['id'] ?>/restore" class="inline" onsubmit="return confirm('Restore this backup?');">
                    <button class="btn btn-small" type="submit">Restore</button>
                </form>
                <form method="post" action="/backups/<?= (int) $b['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this backup file?');">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
