<section class="card">
    <h2>Dashboard</h2>
    <p>Quota usage: <?= (int)($quota['used_bytes'] ?? 0) ?> / <?= (int)($quota['limit_bytes'] ?? 0) ?> bytes</p>
    <h3>Recent files</h3>
    <ul>
    <?php foreach ($recent as $file): ?>
        <li><a href="/files/<?= (int)$file['id'] ?>"><?= htmlspecialchars($file['original_name']) ?></a> &mdash; <?= (int)$file['size'] ?> bytes</li>
    <?php endforeach; ?>
    </ul>
    <h3>Teams</h3>
    <ul>
    <?php foreach ($teams as $team): ?>
        <li><?= htmlspecialchars($team['name']) ?> &mdash; <?= htmlspecialchars((string)$team['quota_limit']) ?> bytes</li>
    <?php endforeach; ?>
    </ul>
    <h3>Folders</h3>
    <ul>
    <?php foreach ($folders as $folder): ?>
        <li><?= htmlspecialchars($folder['name']) ?></li>
    <?php endforeach; ?>
    </ul>
</section>