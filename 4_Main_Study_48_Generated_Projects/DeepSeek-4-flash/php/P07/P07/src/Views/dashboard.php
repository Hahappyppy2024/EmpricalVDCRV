<?php $page_title = 'Dashboard'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<section class="card">
    <h2>Welcome back, <?= htmlspecialchars($current_user['full_name']) ?></h2>
    <p class="meta">Role: <strong><?= htmlspecialchars($current_user['role']) ?></strong> · <?= htmlspecialchars($current_user['email']) ?></p>
</section>

<div class="grid">
    <div class="card">
        <h2>Your files (<?= count($files) ?>)</h2>
        <a class="btn btn-sm" href="/upload">Upload file</a>
        <table>
            <thead><tr><th>Name</th><th>Size</th><th>Tags</th><th></th></tr></thead>
            <tbody>
            <?php if (!$files): ?>
                <tr><td colspan="4" class="meta">No files yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><a href="/files/<?= (int) $file['id'] ?>/preview"><?= htmlspecialchars($file['name']) ?></a></td>
                    <td class="meta"><?= htmlspecialchars($file['size_bytes']) ?> B</td>
                    <td class="meta"><?= htmlspecialchars($file['tags']) ?></td>
                    <td><a class="btn btn-ghost btn-sm" href="/files/<?= (int) $file['id'] ?>/download">Download</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Folders (<?= count($folders) ?>)</h2>
        <a class="btn btn-sm" href="/folders">Manage folders</a>
        <ul>
            <?php foreach ($folders as $folder): ?>
                <li><?= htmlspecialchars($folder['name']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <h2>Recent activity</h2>
    <table>
        <thead><tr><th>Action</th><th>Target</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($recent_audit as $ev): ?>
            <tr>
                <td><?= htmlspecialchars($ev['action']) ?></td>
                <td class="meta"><?= htmlspecialchars($ev['target_type']) ?>#<?= htmlspecialchars($ev['target_id'] ?? '-') ?></td>
                <td class="meta"><?= htmlspecialchars($ev['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
