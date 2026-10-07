<h1>Users</h1>
<?php foreach ($users as $user): ?>
    <div class="card">
        <p><strong>#<?= htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8') ?></strong></p>
        <p><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php endforeach; ?>
