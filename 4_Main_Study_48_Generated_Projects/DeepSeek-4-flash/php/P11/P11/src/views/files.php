<?php
/**
 * File manager (HOST-04). Variables: $sites, $siteId, $path, $entries, $browseError
 */
?>
<div class="toolbar">
    <form method="get" action="/files" class="inline">
        <select name="site" onchange="this.form.submit()">
            <option value="">All sites</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $siteId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($path !== ''): ?>
            <input type="hidden" name="path" value="<?= e($path) ?>">
        <?php endif; ?>
    </form>
    <form method="get" action="/files" class="inline">
        <input type="hidden" name="site" value="<?= (int) $siteId ?>">
        <button class="btn btn-small" type="submit">Root</button>
    </form>
</div>

<p class="muted">Current path: <code>/<?= e($path) ?></code></p>

<?php if ($browseError !== null): ?>
    <div class="flash flash-error"><?= e($browseError) ?></div>
<?php else: ?>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Type</th><th>Size</th><th>Modified</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($path !== ''): ?>
            <tr>
                <td><a href="/files?site=<?= (int) $siteId ?>&path=<?= e(urlencode(dirname($path))) ?>">..</a></td>
                <td colspan="4"></td>
            </tr>
        <?php endif; ?>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <?php if ($entry['is_dir']): ?>
                    <td><a href="/files?site=<?= (int) $siteId ?>&path=<?= e(urlencode($entry['path'])) ?>"><?= e($entry['name']) ?>/</a></td>
                    <td>directory</td>
                    <td>—</td>
                <?php else: ?>
                    <td><?= e($entry['name']) ?></td>
                    <td><?= e($entry['mime_type']) ?></td>
                    <td><?= e(formatBytes((int) $entry['size'])) ?></td>
                <?php endif; ?>
                <td><?= e($entry['updated_at']) ?></td>
                <td>
                    <?php if (!$entry['is_dir']): ?>
                        <a class="btn btn-small" href="/files/download?site=<?= (int) $siteId ?>&path=<?= e(urlencode($entry['path'])) ?>">Download</a>
                    <?php endif; ?>
                    <form method="post" action="/files/rename" class="inline">
                        <input type="hidden" name="site_id" value="<?= (int) $siteId ?>">
                        <input type="hidden" name="path" value="<?= e($path) ?>">
                        <input type="hidden" name="old_path" value="<?= e($entry['path']) ?>">
                        <input type="text" name="new_name" value="<?= e($entry['name']) ?>" class="input-inline">
                        <button class="btn btn-small" type="submit">Rename</button>
                    </form>
                    <form method="post" action="/files/delete" class="inline" onsubmit="return confirm('Delete <?= e($entry['name']) ?>?');">
                        <input type="hidden" name="site_id" value="<?= (int) $siteId ?>">
                        <input type="hidden" name="path" value="<?= e($path) ?>">
                        <input type="hidden" name="target" value="<?= e($entry['path']) ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="two-col">
    <section>
        <h3>Upload files</h3>
        <form method="post" action="/files/upload" enctype="multipart/form-data" class="form">
            <input type="hidden" name="site_id" value="<?= (int) $siteId ?>">
            <input type="hidden" name="path" value="<?= e($path) ?>">
            <input type="file" name="file[]" multiple required>
            <button class="btn btn-primary" type="submit">Upload</button>
        </form>
    </section>
    <section>
        <h3>New directory</h3>
        <form method="post" action="/files/newdir" class="form">
            <input type="hidden" name="site_id" value="<?= (int) $siteId ?>">
            <input type="hidden" name="path" value="<?= e($path) ?>">
            <input type="text" name="dir_name" placeholder="assets" required>
            <button class="btn btn-primary" type="submit">Create</button>
        </form>
    </section>
</div>
