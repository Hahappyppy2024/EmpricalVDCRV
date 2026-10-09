<section class="card">
    <h2>Storage quota</h2>
    <p>Personal: <?= (int)($quota['used_bytes'] ?? 0) ?> / <?= (int)($quota['limit_bytes'] ?? 0) ?> bytes</p>
    <h3>Teams</h3>
    <ul>
    <?php foreach ($teams as $team): ?>
        <li><?= htmlspecialchars($team['name']) ?> &mdash; <?= (int)$team['used_bytes'] ?> / <?= (int)$team['quota_limit'] ?> bytes</li>
    <?php endforeach; ?>
    </ul>
</section>