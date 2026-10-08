<?php $page_title = 'File preview'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<section class="card">
    <h2><?= htmlspecialchars($file['name']) ?></h2>
    <p class="meta">
        Size: <?= htmlspecialchars($file['size_bytes']) ?> bytes ·
        Type: <?= htmlspecialchars($file['mime_type']) ?> ·
        Version: v<?= (int) $file['current_version'] ?> ·
        <a href="/files/<?= (int) $file['id'] ?>/download">Download</a> ·
        <a href="/versions">Version history</a>
    </p>
    <?php if ($file['description'] !== ''): ?><p><?= htmlspecialchars($file['description']) ?></p><?php endif; ?>
    <hr>
    <div class="preview"><?= $content ?></div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
