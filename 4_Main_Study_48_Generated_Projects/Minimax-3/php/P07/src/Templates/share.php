<section class="card">
    <h2>Shared file</h2>
    <?php if (!$share): ?>
        <p>Share not found or has been revoked.</p>
    <?php else: ?>
        <p>File: <?= htmlspecialchars((string)$file['original_name']) ?></p>
        <p>Scope: <?= htmlspecialchars($share['scope']) ?></p>
        <p>Permission: <?= htmlspecialchars($share['permission']) ?></p>
        <p><a href="/s/<?= htmlspecialchars($token) ?>/preview">Preview</a>
            <?php if ($share['permission'] === 'download'): ?>
                | <a href="/s/<?= htmlspecialchars($token) ?>/download">Download</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>