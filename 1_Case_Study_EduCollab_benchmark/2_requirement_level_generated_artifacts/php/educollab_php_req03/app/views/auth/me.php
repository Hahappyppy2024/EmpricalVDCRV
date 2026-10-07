<h1>My Account</h1>
<?php if ($user): ?>
<div class="card">
    <p><strong>User ID:</strong> <?= htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Username:</strong> <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Display Name:</strong> <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Created At:</strong> <?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php else: ?>
<p>User not found.</p>
<?php endif; ?>
