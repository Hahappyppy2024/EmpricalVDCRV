<section class="card">
    <h2>File detail</h2>
    <dl>
        <dt>Name</dt><dd><?= htmlspecialchars($file['original_name']) ?></dd>
        <dt>Size</dt><dd><?= (int)$file['size'] ?> bytes</dd>
        <dt>MIME</dt><dd><?= htmlspecialchars($file['mime_type']) ?></dd>
        <dt>Owner</dt><dd><?= htmlspecialchars((string)$file['owner_id']) ?></dd>
        <dt>Team</dt><dd><?= htmlspecialchars((string)$file['team_id']) ?></dd>
        <dt>Tags</dt><dd><?= htmlspecialchars(implode(', ', $file_tags)) ?></dd>
    </dl>
    <h3>Versions</h3>
    <ul>
    <?php foreach ($versions as $version): ?>
        <li>v<?= (int)$version['version_number'] ?> &mdash; <?= (int)$version['size'] ?> bytes &mdash; <?= htmlspecialchars((string)$version['change_summary']) ?></li>
    <?php endforeach; ?>
    </ul>
    <h3>Shares</h3>
    <ul>
    <?php foreach ($file_shares as $share): ?>
        <li><?= htmlspecialchars($share['scope']) ?> / <?= htmlspecialchars($share['permission']) ?> <?= $share['revoked_at'] ? '(revoked)' : '' ?></li>
    <?php endforeach; ?>
    </ul>
    <p><a href="/files/<?= (int)$file['id'] ?>/download">Download</a> &middot; <a href="/files/<?= (int)$file['id'] ?>/preview">Preview</a> &middot; <a href="/files/<?= (int)$file['id'] ?>/versions">Versions page</a></p>
</section>