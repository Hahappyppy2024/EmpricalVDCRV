<?php
/**
 * Site management (HOST-03). Sections: list | form | detail.
 * Variables: $section, $sites, $site, $domains
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/sites/new">Create site</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Domain</th><th>Document root</th><th>Status</th><th>Deployed</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($sites as $s): ?>
            <tr>
                <td><a href="/sites/<?= (int) $s['id'] ?>"><?= e($s['name']) ?></a></td>
                <td><?= e($s['domain_name']) ?></td>
                <td><?= e($s['document_root']) ?></td>
                <td><span class="badge <?= badge($s['status']) ?>"><?= e($s['status']) ?></span></td>
                <td><?= e($s['deployed_at'] ?? '—') ?></td>
                <td><a class="btn btn-small" href="/sites/<?= (int) $s['id'] ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <form method="post" action="/sites" class="form form-narrow">
        <label>Site name
            <input type="text" name="name" placeholder="my-site" required>
        </label>
        <label>Domain
            <select name="domain_id" required>
                <option value="">— select —</option>
                <?php foreach ($domains as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Document root
            <input type="text" name="document_root" value="/var/www/htdocs" required>
        </label>
        <label>Status
            <select name="status">
                <option value="deployed">Deployed</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Create site</button>
    </form>
<?php elseif ($section === 'detail'): ?>
    <div class="toolbar">
        <a class="btn btn-small" href="/sites">Back</a>
        <form method="post" action="/sites/<?= (int) $site['id'] ?>/deploy" class="inline">
            <button class="btn btn-small" type="submit">Deploy</button>
        </form>
    </div>
    <form method="post" action="/sites/<?= (int) $site['id'] ?>/update" class="form form-narrow">
        <label>Site name
            <input type="text" name="name" value="<?= e($site['name']) ?>" required>
        </label>
        <label>Domain
            <select name="domain_id" required>
                <?php foreach ($domains as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === (int) $site['domain_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Document root
            <input type="text" name="document_root" value="<?= e($site['document_root']) ?>" required>
        </label>
        <label>Status
            <select name="status">
                <option value="deployed" <?= $site['status'] === 'deployed' ? 'selected' : '' ?>>Deployed</option>
                <option value="pending" <?= $site['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="failed" <?= $site['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
    <p class="muted">Deployment status: <span class="badge <?= badge($site['status']) ?>"><?= e($site['status']) ?></span> · deployed at <?= e($site['deployed_at'] ?? '—') ?></p>
<?php endif; ?>
